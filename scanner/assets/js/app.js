// app.js - Ventura Logistics Scanner
// Основан на рабочей версии, добавлены новые функции

const debugConsole = document.getElementById('debug-console');
const activationPanel = document.getElementById('activation-panel');
const mainPanel = document.getElementById('main-panel');
const activateBtn = document.getElementById('activate-btn');
const startBtn = document.getElementById('start-btn');
const readerDiv = document.getElementById('reader');
const resultBox = document.getElementById('result-box');
const resultSpan = document.getElementById('scanned-result');
const scanCountSpan = document.getElementById('scan-count');
const saveBtn = document.getElementById('save-btn');
const cancelBtn = document.getElementById('cancel-btn');
const actionButtons = document.getElementById('action-buttons');

let html5QrCode = null;
let pendingCode = null;
let appState = {
    authenticated: false,
    deviceId: null,
    token: null,
    isScanning: false,
    scanCount: 0,
    awaitingDecision: false
};

// Helper functions
function t(key, params = {}) {
    return i18n.t(key, params);
}

// Debug logging
let debugEnabled = false;
function log(msg, isError = false) {
    if (!debugEnabled) return;
    const time = new Date().toLocaleTimeString();
    const color = isError ? '#ff6b6b' : '#86efac';
    debugConsole.innerHTML += `<div style="color:${color};">[${time}] ${msg}</div>`;
    debugConsole.scrollTop = debugConsole.scrollHeight;
    console.log(msg);
}

// Enable debug mode (long press on header)
let debugTriggerTimer = null;
const headerElement = document.querySelector('.card-header');
if (headerElement) {
    headerElement.addEventListener('touchstart', () => {
        debugTriggerTimer = setTimeout(() => {
            debugEnabled = !debugEnabled;
            const debugSection = document.querySelector('.debug-section');
            if (debugSection) debugSection.style.display = debugEnabled ? 'block' : 'none';
            if (debugEnabled) log("Debug mode enabled");
        }, 3000);
    });
    headerElement.addEventListener('touchend', () => {
        if (debugTriggerTimer) clearTimeout(debugTriggerTimer);
    });
}

// API Functions
async function verifyToken(deviceId, token) {
    log("Verifying token...");
    const formData = new FormData();
    formData.append('device_id', deviceId);
    formData.append('token', token);
    
    try {
        const response = await fetch('scanner/api/device_verify.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        return response.ok && result.valid === true;
    } catch (err) {
        log("Verify error: " + err.message, true);
        return false;
    }
}

async function saveToDatabase(code, deviceId, token) {
    log("Saving code: " + code);
    const formData = new FormData();
    formData.append('code', code);
    formData.append('device_id', deviceId);
    formData.append('token', token);
    
    try {
        const response = await fetch('scanner/api/scan_save.php', {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            log("✅ Saved successfully");
            return true;
        } else if (response.status === 401) {
            log("Session expired", true);
            clearAuthAndShowActivation();
            alert(t('errors.session_expired'));
            return false;
        }
        return false;
    } catch (err) {
        log("Save error: " + err.message, true);
        return false;
    }
}

async function activateDevice(qrCode) {
    log("🔐 Activating device with code: " + qrCode);
    const formData = new FormData();
    formData.append('code', qrCode);
    
    try {
        const response = await fetch('scanner/api/device_activate.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok && result.status === 'success') {
            appState.authenticated = true;
            appState.deviceId = result.device_id;
            appState.token = result.token;
            appState.scanCount = 0;
            
            localStorage.setItem('scan_auth', JSON.stringify({
                deviceId: result.device_id,
                token: result.token,
                timestamp: Date.now()
            }));
            
            log("✅ Activation successful!");
            showMainScannerUI();
            alert(t('messages.activation_success'));
            return true;
        } else {
            throw new Error(result.message || "Activation failed");
        }
    } catch (err) {
        log("❌ Activation error: " + err.message, true);
        alert(t('errors.activation_error', { error: err.message }));
        return false;
    }
}

// UI Functions
function clearAuthAndShowActivation() {
    appState.authenticated = false;
    appState.deviceId = null;
    appState.token = null;
    appState.scanCount = 0;
    appState.awaitingDecision = false;
    pendingCode = null;
    
    localStorage.removeItem('scan_auth');
    
    if (html5QrCode && html5QrCode.isScanning) {
        stopScanner();
    }
    
    showActivationUI();
}

function showActivationUI() {
    log("📱 Activation mode");
    activationPanel.style.display = 'block';
    mainPanel.style.display = 'none';
    resultBox.style.display = 'none';
    readerDiv.style.display = 'none';
}

function showMainScannerUI() {
    log("📷 Scanning mode");
    activationPanel.style.display = 'none';
    mainPanel.style.display = 'block';
    resultBox.style.display = 'none';
    readerDiv.style.display = 'none';
    updateScanCount();
}

function updateScanCount() {
    if (appState.scanCount > 0) {
        scanCountSpan.innerText = t('result_scan_count', { count: appState.scanCount });
    } else {
        scanCountSpan.innerText = '';
    }
}

function showScannedCode(code) {
    pendingCode = code;
    appState.awaitingDecision = true;
    
    resultSpan.innerText = code;
    resultBox.style.display = 'block';
    actionButtons.style.display = 'flex';
    startBtn.style.display = 'none';
    
    // Останавливаем сканер
    if (html5QrCode && html5QrCode.isScanning) {
        stopScanner();
    }
}

// Start scanning - КОПИРУЕМ ЛОГИКУ ИЗ РАБОЧЕЙ ВЕРСИИ
async function startScanning(onSuccess, button, context) {
    log(`🚀 Starting scanner (${context})`);
    
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
        log("Scanner instance created");
    }
    
    try {
        // КЛЮЧЕВОЙ МОМЕНТ: скрываем кнопку и показываем reader
        button.style.display = 'none';
        readerDiv.style.display = 'block';
        resultBox.style.display = 'none';
        
        log("📷 Requesting camera access...");
        
        await html5QrCode.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 150 }
            },
            async (decodedText) => {
                log(`${context === "activation" ? "Activation QR" : "Code"} scanned: ${decodedText}`);
                
                try {
                    // Для активации - сразу обрабатываем и останавливаем
                    if (context === "activation") {
                        await onSuccess(decodedText);
                        await stopScanner();
                        showMainScannerUI();
                        return;
                    }
                    
                    // Для сканирования - показываем UI выбора
                    if (context === "scanning") {
                        await stopScanner();
                        showScannedCode(decodedText);
                        // Сохраняем колбэк для сохранения
                        window._pendingSaveCallback = async () => {
                            await onSuccess(decodedText);
                            appState.scanCount++;
                            updateScanCount();
                        };
                    }
                    
                } catch (err) {
                    log(`❌ Processing error: ${err.message}`, true);
                    alert(t('errors.scan_error', { error: err.message }));
                    // Возвращаем кнопку при ошибке
                    button.style.display = 'block';
                    readerDiv.style.display = 'none';
                }
            },
            () => {
                // Empty errorCallback - prevents log spam
            }
        );
        
        log(`🎥 Camera active (${context})`);
        
    } catch (err) {
        log(`❌ Camera error: ${err.message}`, true);
        button.style.display = 'block';
        readerDiv.style.display = 'none';
        
        if (err.name === 'NotAllowedError') {
            alert(t('errors.no_camera_access'));
        } else {
            alert(t('errors.camera_error', { error: err.message }));
        }
    }
}

// Stop scanner
async function stopScanner() {
    if (html5QrCode && html5QrCode.isScanning) {
        try {
            await html5QrCode.stop();
            log("⏹️ Camera stopped");
        } catch (err) {
            // Silent fail
        }
    }
    readerDiv.style.display = 'none';
}

// Save handler
async function handleSave() {
    if (!pendingCode) return;
    
    saveBtn.disabled = true;
    saveBtn.innerText = t('saving_btn');
    
    const success = await saveToDatabase(pendingCode, appState.deviceId, appState.token);
    
    if (success) {
        appState.scanCount++;
        updateScanCount();
        log("✅ Code saved");
        
        pendingCode = null;
        appState.awaitingDecision = false;
        resultBox.style.display = 'none';
        actionButtons.style.display = 'none';
        
        startBtn.style.display = 'block';
        startBtn.innerText = t('scan_next_btn');
    } else {
        alert(t('errors.save_error'));
    }
    
    saveBtn.disabled = false;
    saveBtn.innerText = t('save_btn');
}

function handleCancel() {
    log("❌ Code discarded");
    pendingCode = null;
    appState.awaitingDecision = false;
    resultBox.style.display = 'none';
    actionButtons.style.display = 'none';
    
    startBtn.style.display = 'block';
    startBtn.innerText = t('scan_next_btn');
}

// Event listeners
activateBtn.addEventListener('click', async () => {
    log("=== ACTIVATE BUTTON CLICKED ===");
    await startScanning(
        async (code) => {
            await activateDevice(code);
        },
        activateBtn,
        "activation"
    );
});

startBtn.addEventListener('click', async () => {
    if (!appState.authenticated) {
        showActivationUI();
        return;
    }
    
    if (appState.awaitingDecision) {
        log("Waiting for decision on current code");
        return;
    }
    
    startBtn.innerText = t('scanning_btn');
    
    await startScanning(
        async (code) => {
            // Этот колбэк будет вызван после нажатия Save
            // Сохраняем в глобальной переменной
            window._pendingSaveCallback = async () => {
                await saveToDatabase(code, appState.deviceId, appState.token);
            };
        },
        startBtn,
        "scanning"
    );
    
    // Восстанавливаем текст кнопки если сканер не запустился
    setTimeout(() => {
        if (startBtn.style.display !== 'none') {
            startBtn.innerText = t('scan_start_btn');
        }
    }, 100);
});

saveBtn.addEventListener('click', handleSave);
cancelBtn.addEventListener('click', handleCancel);

// Language selector
const langToggleBtn = document.getElementById('lang-toggle-btn');
const langPanel = document.getElementById('lang-panel');

if (langToggleBtn && langPanel) {
    langToggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        langPanel.classList.toggle('show');
    });
    
    document.addEventListener('click', (e) => {
        if (!langToggleBtn.contains(e.target) && !langPanel.contains(e.target)) {
            langPanel.classList.remove('show');
        }
    });
}

document.querySelectorAll('.lang-option').forEach(btn => {
    btn.addEventListener('click', async () => {
        const lang = btn.getAttribute('data-lang');
        await i18n.setLanguage(lang);
        i18n.updateDOM();
        
        document.querySelectorAll('.lang-option').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        updateScanCount();
        if (startBtn && startBtn.style.display !== 'none') {
            startBtn.innerText = t('scan_start_btn');
        }
        langPanel.classList.remove('show');
    });
});

function updateUILanguage() {
    i18n.updateDOM();
    if (appState.scanCount > 0) {
        scanCountSpan.innerText = t('result_scan_count', { count: appState.scanCount });
    }
    if (startBtn && startBtn.style.display !== 'none') {
        startBtn.innerText = t('scan_start_btn');
    }
}

// Initialize app
async function initializeApp() {
    log("Initializing Ventura Scan...");
    
    const savedAuth = localStorage.getItem('scan_auth');
    
    if (savedAuth) {
        try {
            const { deviceId, token } = JSON.parse(savedAuth);
            log("Found saved session for device: " + deviceId);
            
            const isValid = await verifyToken(deviceId, token);
            
            if (isValid) {
                appState.deviceId = deviceId;
                appState.token = token;
                appState.authenticated = true;
                appState.scanCount = 0;
                log("✅ Session restored");
                showMainScannerUI();
            } else {
                log("❌ Invalid session", true);
                clearAuthAndShowActivation();
            }
        } catch (err) {
            log("Session error: " + err, true);
            clearAuthAndShowActivation();
        }
    } else {
        log("No saved session");
        showActivationUI();
    }
}

// Start app
window.onload = async () => {
    await i18n.init();
    updateUILanguage();
    
    const currentLang = i18n.getCurrentLanguage();
    document.querySelectorAll('.lang-option').forEach(btn => {
        if (btn.getAttribute('data-lang') === currentLang) {
            btn.classList.add('active');
        }
    });
    
    if (typeof Html5Qrcode !== 'undefined') {
        log("✅ QR library loaded");
    } else {
        log("❌ Library load failed!", true);
    }
    
    await initializeApp();
};