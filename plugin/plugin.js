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

    const container = document.getElementById('plugin-container');
    const statusEl = document.getElementById('status');
    const timerEl = document.querySelector('.timer span');
    const pauseButton = document.getElementById('pause-button');
    const resumeButton = document.getElementById('resume-button');

    if (!data.paused) {
        statusEl.textContent = '🟢 Serviço Ativo';
        container.classList.remove('paused');
        timerEl.textContent = '??';
        pauseButton.disabled = false;
        clearInterval(countdownInterval);
    } else {
        pausedUntil = Number(data.until) * 1000;
        statusEl.textContent = '⚫ Serviço Pausado';
        container.classList.add('paused');
        resumeButton.disabled = false;
        startCountdown();
    }
}

function startCountdown() {
    const timerEl = document.querySelector('.timer span');

    clearInterval(countdownInterval);
    countdownInterval = setInterval(() => {
        const now = Date.now();
        const diff = Math.max(0, Math.floor((pausedUntil - now) / 1000));

        if (diff <= 0) {
            clearInterval(countdownInterval);
            loadStatus();
            return;
        }

        timerEl.textContent = diff;
    }, 1000);
}

async function pause() {
    document.getElementById('pause-button').disabled = true;
    const pauseSeconds = parseInt(document.getElementById('pause-seconds').textContent) || 30;
    await apiRequest('pause', { seconds: pauseSeconds });
    loadStatus();
}

async function resume() {
    document.getElementById('resume-button').disabled = true;
    await apiRequest('resume');
    loadStatus();
}

// Inicialização
loadStatus();