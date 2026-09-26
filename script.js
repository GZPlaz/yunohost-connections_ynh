let puzzleData = [];
let selectedWords = [];
let solvedGroups = [];
let mistakesLeft = 4;
let remainingWords = [];
let toastTimeout = null;

async function initGame() {
  const res = await fetch('puzzles.json');
  const data = await res.json();
  document.getElementById('game-title').innerText = data.title;
  puzzleData = data.groups;

  remainingWords = puzzleData.flatMap(g => g.words.map(w => ({ word: w, category: g.category, level: g.level })));
  shuffleArray(remainingWords);
  renderGrid();
  updateLives();
}

function renderGrid() {
  const grid = document.getElementById('grid');
  grid.innerHTML = '';
  remainingWords.forEach(item => {
    const card = document.createElement('div');
    card.className = 'card' + (selectedWords.includes(item.word) ? ' selected' : '');
    card.innerText = item.word;
    card.onclick = () => toggleSelect(item.word);
    grid.appendChild(card);
  });
  document.getElementById('submit-btn').disabled = selectedWords.length !== 4;
}

function toggleSelect(word) {
  if (selectedWords.includes(word)) {
    selectedWords = selectedWords.filter(w => w !== word);
  } else if (selectedWords.length < 4) {
    selectedWords.push(word);
  }
  renderGrid();
}

function deselectAll() {
  selectedWords = [];
  renderGrid();
}

function shuffleGrid() {
  shuffleArray(remainingWords);
  renderGrid();
}

function submitGuess() {
  if (selectedWords.length !== 4) return;

  const matchedGroup = puzzleData.find(g => 
    g.words.every(w => selectedWords.includes(w))
  );

  if (matchedGroup) {
    solvedGroups.push(matchedGroup);
    remainingWords = remainingWords.filter(item => !selectedWords.includes(item.word));
    selectedWords = [];
    renderSolved();
    renderGrid();

    if (remainingWords.length === 0) {
      showToast("Great job! You won! 🎉", 4000);
    }
  } else {
    // 1. Shake wrong answer cards
    const selectedElements = document.querySelectorAll('.card.selected');
    selectedElements.forEach(el => el.classList.add('shake'));

    // Remove shake class after animation completes
    setTimeout(() => {
      selectedElements.forEach(el => el.classList.remove('shake'));
    }, 400);

    // 2. Check if the guess is "One Away!"
    const unsolvedGroups = puzzleData.filter(g => !solvedGroups.includes(g));
    const isOneAway = unsolvedGroups.some(g => {
      const matchCount = g.words.filter(w => selectedWords.includes(w)).length;
      return matchCount === 3;
    });

    if (isOneAway) {
      showToast("One away!");
    }

    // 3. Decrement lives
    mistakesLeft--;
    updateLives();

    if (mistakesLeft === 0) {
      setTimeout(() => showToast("Next time! Game Over.", 4000), 450);
    }
  }
}

/* Custom Notification Banner */
function showToast(message, duration = 2000) {
  const toast = document.getElementById('toast');
  toast.innerText = message;
  toast.classList.add('show');

  if (toastTimeout) clearTimeout(toastTimeout);

  toastTimeout = setTimeout(() => {
    toast.classList.remove('show');
  }, duration);
}

function renderSolved() {
  const container = document.getElementById('solved-container');
  container.innerHTML = '';
  solvedGroups.forEach(g => {
    const div = document.createElement('div');
    div.className = `solved-group ${g.level}`;
    div.innerHTML = `<div>${g.category}</div><small>${g.words.join(', ')}</small>`;
    container.appendChild(div);
  });
}

function updateLives() {
  document.getElementById('lives').innerText = '•'.repeat(mistakesLeft);
}

function shuffleArray(arr) {
  arr.sort(() => Math.random() - 0.5);
}

initGame();