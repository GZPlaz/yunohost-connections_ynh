<?php
header('Content-Type: application/json');

// ==========================================
// 🔴 UPDATE THESE CREDENTIALS FOR YUNOHOST
// ==========================================
$host = '127.0.0.1';
$db   = 'my_webapp';        // Usually my_webapp by default
$user = 'my_webapp';        // Usually my_webapp by default
$pass = 'i3MrjWZDqDqo9p7TVHMGy7szXafHeh'; // Replace with your actual DB password!

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die(json_encode(["error" => "Database connection failed. Check your password in api.php!"]));
}

// This automatically sets up your database schema on the first run!
$pdo->exec("CREATE TABLE IF NOT EXISTS puzzles (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    groups_data JSON NOT NULL,
    status VARCHAR(20) NOT NULL,
    played_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$action = $_GET['action'] ?? '';

if ($action === 'getCurrent') {
    $stmt = $pdo->query("SELECT * FROM puzzles WHERE status = 'current' LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode([
            "title" => $row['title'],
            "groups" => json_decode($row['groups_data'])
        ]);
    } else {
        echo json_encode(null);
    }
    exit;
}

if ($action === 'getAll') {
    $stmt = $pdo->query("SELECT * FROM puzzles ORDER BY created_at ASC");
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = ['current' => null, 'queue' => [], 'history' => []];
    foreach ($all as $row) {
        $puzzle = [
            'id' => $row['id'],
            'title' => $row['title'],
            'groups' => json_decode($row['groups_data'], true),
            'playedAt' => $row['played_at']
        ];
        if ($row['status'] === 'current') {
            $data['current'] = $puzzle;
        } elseif ($row['status'] === 'queue') {
            $data['queue'][] = $puzzle;
        } elseif ($row['status'] === 'history') {
            $data['history'][] = $puzzle;
        }
    }
    // Reverse history to show newest completions first
    $data['history'] = array_reverse($data['history']);
    
    echo json_encode($data);
    exit;
}

if ($action === 'save') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? uniqid();
    $title = $input['title'];
    $groups = json_encode($input['groups']);
    $status = $input['status']; // 'queue' or 'current'
    
    $stmt = $pdo->prepare("INSERT INTO puzzles (id, title, groups_data, status) VALUES (?, ?, ?, ?) 
                           ON CONFLICT (id) DO UPDATE SET 
                           title = EXCLUDED.title, 
                           groups_data = EXCLUDED.groups_data, 
                           status = EXCLUDED.status");
    $stmt->execute([$id, $title, $groups, $status]);
    echo json_encode(["success" => true, "id" => $id]);
    exit;
}

if ($action === 'delete') {
    $id = $_GET['id'] ?? '';
    $stmt = $pdo->prepare("DELETE FROM puzzles WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["success" => true]);
    exit;
}

if ($action === 'advance') {
    // 1. Move the current puzzle to history
    $pdo->exec("UPDATE puzzles SET status = 'history', played_at = CURRENT_TIMESTAMP WHERE status = 'current'");
    
    // 2. Find the oldest puzzle in the queue and make it current
    $stmt = $pdo->query("SELECT id FROM puzzles WHERE status = 'queue' ORDER BY created_at ASC LIMIT 1");
    $next = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($next) {
        $stmt = $pdo->prepare("UPDATE puzzles SET status = 'current' WHERE id = ?");
        $stmt->execute([$next['id']]);
        echo json_encode(["success" => true, "message" => "Advanced queue to next puzzle."]);
    } else {
        echo json_encode(["success" => false, "message" => "Queue empty. No active puzzle set."]);
    }
    exit;
}

if ($action === 'clearHistory') {
    $pdo->exec("DELETE FROM puzzles WHERE status = 'history'");
    echo json_encode(["success" => true]);
    exit;
}

echo json_encode(["error" => "Invalid action."]);
