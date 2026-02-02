const SCRIPT = document.currentScript;
const ENDPOINT = SCRIPT.dataset.endpoint;
const AUTH_SECRET = SCRIPT.dataset.secret;

let countdownInterval = null;
let pausedUntil = null;

async function apiRequest(action, extra = {}) {
    const response = await fetch(ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Auth-Token': AUTH_SECRET
        },
        body: JSON.stringify({ action, ...extra })
    });

    return response.json();
}

async function loadStatus() {
    const data = await apiRequest('status');

    const statusEl = document.getElementById('status');
    const timerEl = document.getElementById('timer');
    const pauseControl = document.getElementById('pause-control');
    const pauseButton = document.getElementById('pauseButton');
    const resumeControl = document.getElementById('resume-control');
    const resumeButton = document.getElementById('resumeButton');

    if (!data.paused) {
        statusEl.textContent = '🟢 Serviço Ativo';
        statusEl.className = 'status active';
        timerEl.textContent = '';
        pauseControl.classList.remove('hidden');
        pauseButton.disabled = false;
        resumeControl.classList.add('hidden');
        clearInterval(countdownInterval);
    } else {
        pausedUntil = Number(data.until) * 1000;
        statusEl.textContent = '⚫ Serviço Pausado';
        statusEl.className = 'status paused';
        resumeControl.classList.remove('hidden');
        resumeButton.disabled = false;
        pauseControl.classList.add('hidden');
        startCountdown();
    }
}

function startCountdown() {
    const timerEl = document.getElementById('timer');

    clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        const now = Date.now();
        const diff = Math.max(0, Math.floor((pausedUntil - now) / 1000));

        if (diff <= 0) {
            clearInterval(countdownInterval);
            loadStatus();
            return;
        }

        timerEl.textContent = `Retomando em ${diff} segundos`;
    }, 1000);
}

async function pause() {
    document.getElementById('pauseButton').disabled = true;
    const pauseSeconds = parseInt(document.getElementById('pauseSeconds').textContent) || 30;
    await apiRequest('pause', { seconds: pauseSeconds });
    loadStatus();
}

async function resume() {
    document.getElementById('resumeButton').disabled = true;
    await apiRequest('resume');
    loadStatus();
}

// Inicialização
loadStatus();