// app.js - Fixed version with token validation

const debugConsole = document.getElementById('debug-console');
const toggleDebug = document.getElementById('toggle-debug');

const protocolStatus = document.getElementById('protocol-status');
const activationPanel = document.getElementById('activation-panel');
const mainPanel = document.getElementById('main-panel');
const activateBtn = document.getElementById('activate-btn');
const startBtn = document.getElementById('start-btn');
const readerDiv = document.getElementById('reader');
const resultBox = document.getElementById('result-box');
const resultSpan = document.getElementById('scanned-result');
const scanCountSpan = document.getElementById('scan-count');

let html5QrCode = null;
let appState = {
    authenticated: false,
    deviceId: null,
    token: null,
    isScanning: false,
    scanCount: 0
};

// Helper function to get translated text
function t(key, params = {}) {
    return i18n.t(key, params);
}

// Update UI with current language
function updateUILanguage() {
    i18n.updateDOM();
    
    if (appState.scanCount > 0) {
        scanCountSpan.innerText = t('result.scan_count', { count: appState.scanCount });
    } else {
        scanCountSpan.innerText = '';
    }
}

// Logging with i18n support
function log(msg, isError = false) {
    if (!toggleDebug.checked) return;
    const time = new Date().toLocaleTimeString();
    const color = isError ? '#ff4d4f' : '#00ff9c';
    debugConsole.innerHTML += `<div style="color:${color}">[${time}] ${msg}</div>`;
    debugConsole.scrollTop = debugConsole.scrollHeight;
    console.log(msg);
}

// Check HTTPS
if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
    protocolStatus.innerText = t('connection_insecure');
    protocolStatus.style.color = "red";
    log("Error: HTTPS required", true);
} else {
    protocolStatus.innerText = t('connection_secure');
    protocolStatus.style.color = "green";
}

/**
 * Verify token with server
 */
async function verifyToken(deviceId, token) {
    log("Verifying token with server...");
    
    const formData = new FormData();
    formData.append('device_id', deviceId);
    formData.append('token', token);
    
    try {
        const response = await fetch('scanner/api/device_verify.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok && result.valid === true) {
            log("✅ Token is valid");
            return true;
        } else {
            log("❌ Token is invalid: " + (result.message || "Unknown error"), true);
            return false;
        }
    } catch (err) {
        log("❌ Token verification failed: " + err.message, true);
        return false;
    }
}

/**
 * Clear authentication and show activation UI
 */
function clearAuthAndShowActivation() {
    log("Clearing authentication...");
    
    // Clear local state
    appState.authenticated = false;
    appState.deviceId = null;
    appState.token = null;
    appState.scanCount = 0;
    
    // Clear localStorage
    localStorage.removeItem('scan_auth');
    
    // Stop scanner if running
    if (html5QrCode && html5QrCode.isScanning) {
        stopScanner();
    }
    
    // Show activation UI
    showActivationUI();
}

// Save to database
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
            const result = await response.json();
            log("✅ Saved: " + result.message);
        } else if (response.status === 401) {
            log("❌ Session expired or invalid", true);
            clearAuthAndShowActivation();
            alert(t('errors.session_expired'));
        } else {
            const errorText = await response.text();
            log("❌ Save error: " + response.status, true);
        }
    } catch (err) {
        log("❌ Network error: " + err.message, true);
    }
}

// Activate device
// Activate device with debug
async function activateDevice(qrCode) {
    log("🔐 Activating device...");
    log("📤 Sending QR code: " + qrCode);
    
    const formData = new FormData();
    formData.append('code', qrCode);
    
    // Log FormData contents (for debugging)
    for (let pair of formData.entries()) {
        log(`   FormData: ${pair[0]} = ${pair[1]}`);
    }
    
    try {
         const response = await fetch('scanner/api/device_activate.php', {
            method: 'POST',
            body: formData
        });
        
        log(`📡 Response status: ${response.status}`);
        
        // Get raw response first
        const textResponse = await response.text();
        log(`📝 Raw response: ${textResponse}`);
        
        // Parse JSON
        let result;
        try {
            result = JSON.parse(textResponse);
        } catch (e) {
            log(`❌ Failed to parse JSON: ${e.message}`, true);
            throw new Error(`Server returned invalid response: ${textResponse.substring(0, 100)}`);
        }
        
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
            
            log("✅ Activation successful! Device ID: " + result.device_id);
            showMainScannerUI();
            alert(t('messages.activation_success'));
        } else {
            throw new Error(result.message || "Activation error ZERO");
        }
    } catch (err) {
        log("❌ Activation error ALPHA: " + err.message, true);
        alert(t('errors.activation_error', { error: err.message }));
        throw err;
    }
}

// Show activation UI
function showActivationUI() {
    log("📱 Activation mode");
    appState.authenticated = false;
    activationPanel.style.display = 'flex';
    mainPanel.style.display = 'none';
    resultBox.style.display = 'none';
    readerDiv.style.display = 'none';
}

// Show main scanner UI
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
        scanCountSpan.innerText = t('result.scan_count', { count: appState.scanCount });
    } else {
        scanCountSpan.innerText = '';
    }
}

// Start scanning
async function startScanning(onSuccess, button, context) {
    log(`🚀 Starting scanner (${context})`);
    
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
        log("Scanner instance created");
    }
    
    try {
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
                    await onSuccess(decodedText);
                    
                    if (context === "scanning") {
                        appState.scanCount++;
                        updateScanCount();
                    }
                    
                    if (context === "scanning") {
                        resultSpan.innerText = decodedText;
                        resultBox.style.display = 'block';
                        setTimeout(() => {
                            if (resultBox.style.display === 'block') {
                                resultBox.style.display = 'none';
                            }
                        }, 3000);
                    }
                    
                } catch (err) {
                    log(`❌ Processing error: ${err.message}`, true);
                    alert(t('errors.scan_error', { error: err.message }));
                }
            },
            () => {
                // Empty errorCallback - prevents log spam
            }
        );
        
        log(`🎥 Camera active (${context})`);
        
        if (context === "activation") {
            setTimeout(() => {
                if (readerDiv.style.display === 'block') {
                    log("⏳ Waiting for activation QR code...");
                }
            }, 1000);
        }
        
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

// Event listeners
activateBtn.addEventListener('click', async () => {
    await startScanning(
        async (code) => {
            await activateDevice(code);
            await stopScanner();
            showMainScannerUI();
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
    
    startBtn.innerText = t('scanner_panel.scanning_button');
    
    await startScanning(
        async (code) => {
            await saveToDatabase(code, appState.deviceId, appState.token);
        },
        startBtn,
        "scanning"
    );
    
    setTimeout(() => {
        if (startBtn.style.display === 'none') {
            startBtn.style.display = 'block';
            startBtn.innerText = t('scanner_panel.next_button');
        }
    }, 100);
});

// Debug toggle
toggleDebug.addEventListener('change', () => {
    debugConsole.style.display = toggleDebug.checked ? 'block' : 'none';
});

// Language selector
document.querySelectorAll('.lang-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const lang = btn.getAttribute('data-lang');
        await i18n.setLanguage(lang);
        updateUILanguage();
        
        document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        updateScanCount();
    });
});

/**
 * Initialize app with token validation
 */
async function initializeApp() {
    log("Initializing application...");
    
    // Check saved session
    const savedAuth = localStorage.getItem('scan_auth');
    
    if (savedAuth) {
        try {
            const { deviceId, token } = JSON.parse(savedAuth);
            
            log("Found saved session for device: " + deviceId);
            
            // Verify token with server
            const isValid = await verifyToken(deviceId, token);
            
            if (isValid) {
                // Token is valid, restore session
                appState.deviceId = deviceId;
                appState.token = token;
                appState.authenticated = true;
                appState.scanCount = 0;
                
                log("✅ Session restored and verified: " + deviceId);
                showMainScannerUI();
            } else {
                // Token is invalid, clear and show activation
                log("❌ Saved session is invalid", true);
                clearAuthAndShowActivation();
            }
        } catch (err) {
            log("❌ Session read error: " + err, true);
            clearAuthAndShowActivation();
        }
    } else {
        log("No saved session found");
        showActivationUI();
    }
}

// Initialize i18n and app
window.onload = async () => {
    // Initialize i18n first
    await i18n.init();
    updateUILanguage();
    
    // Set active language button
    const currentLang = i18n.getCurrentLanguage();
    document.querySelectorAll('.lang-btn').forEach(btn => {
        if (btn.getAttribute('data-lang') === currentLang) {
            btn.classList.add('active');
        }
    });
    
    // Check library
    if (typeof Html5Qrcode !== 'undefined') {
        log("✅ Library loaded successfully");
    } else {
        log("❌ Failed to load library!", true);
        protocolStatus.innerText = "Scanner load error";
        protocolStatus.style.color = "red";
        return;
    }
    
    // Initialize app with token validation
    await initializeApp();
};
