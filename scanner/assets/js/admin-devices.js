// /scanner/assets/js/admin-devices.js

// Глобальные переменные
let currentEmployee = null;
let currentDeviceCode = '';
let currentStatus = 'none';
let existingDevice = null;
let statusCheckInterval = null;
let currentIdentifier = null;
let isMonitoringActive = false;
let waitingAnimationInterval = null;

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin devices initialized');
    
    // Запрос разрешения на уведомления
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission();
    }
    
    // Добавляем кнопку ручного обновления
    addManualRefreshButton();
    
    // Настройка обработчиков
    setupEventListeners();
    
    // Получаем начальный код из PHP
    if (typeof initialDeviceCode !== 'undefined') {
        currentDeviceCode = initialDeviceCode;
        console.log('Initial device code:', currentDeviceCode);
    }
});

function setupEventListeners() {
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            document.getElementById('autocompleteList').style.display = 'none';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            performSearch(query);
        }, 300);
    });
    
    // Скрыть автодополнение при клике вне
    document.addEventListener('click', function(e) {
        const searchInput = document.getElementById('searchInput');
        const autocompleteList = document.getElementById('autocompleteList');
        if (!searchInput.contains(e.target) && !autocompleteList.contains(e.target)) {
            autocompleteList.style.display = 'none';
        }
    });
}

// Функция поиска
function performSearch(query) {
    const searchType = document.querySelector('input[name="search_type"]:checked').value;
    
    console.log('Searching:', query, 'Type:', searchType);
    
    fetch('ajax_search_employees.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `type=${searchType}&query=${encodeURIComponent(query)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Search response:', data);
        if (data.success && data.data && data.data.length > 0) {
            showAutocomplete(data.data);
        } else {
            document.getElementById('autocompleteList').style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Search error:', error);
    });
}

function showAutocomplete(employees) {
    const autocompleteList = document.getElementById('autocompleteList');
    autocompleteList.innerHTML = '';
    
    employees.forEach(emp => {
        const item = document.createElement('div');
        item.className = 'autocomplete-item';
        item.innerHTML = `<strong>${emp.NR_PRACOWNIKA}</strong> - ${emp.IMIE} ${emp.NAZWISKO} (${emp.KOMORKA_ORG})`;
        item.onclick = () => selectEmployee(emp);
        autocompleteList.appendChild(item);
    });
    
    autocompleteList.style.display = 'block';
}

function selectEmployee(employee) {
    console.log('Selected employee:', employee);
    currentEmployee = employee;
    const searchInput = document.getElementById('searchInput');
    searchInput.value = `${employee.NR_PRACOWNIKA} - ${employee.IMIE} ${employee.NAZWISKO}`;
    document.getElementById('autocompleteList').style.display = 'none';
    
    // Показать информацию о сотруднике
    document.getElementById('empNr').textContent = employee.NR_PRACOWNIKA;
    document.getElementById('empName').textContent = `${employee.IMIE} ${employee.NAZWISKO}`;
    document.getElementById('empDepartment').textContent = `${employee.KOMORKA_ORG} - ${employee.NAZWA_KOMORKI_ORG}`;
    document.getElementById('employeeInfo').classList.add('active');
    
    // Проверить наличие устройства
    checkEmployeeDevice(employee.NR_PRACOWNIKA);
    
    // Останавливаем мониторинг при выборе другого сотрудника
    stopStatusMonitoring();
    stopWaitingAnimation();
}

function checkEmployeeDevice(nrPracownika) {
    console.log('Checking device for employee:', nrPracownika);
    
    fetch('ajax_check_device.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `nr_pracownika=${encodeURIComponent(nrPracownika)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Device check response:', data);
        
        if (data.success) {
            existingDevice = data.data;
            if (existingDevice.exists) {
                currentDeviceCode = existingDevice.kod;
                updateQRCode(currentDeviceCode);
                updateDeviceStatus(existingDevice.status);
                document.getElementById('saveBtn').textContent = '🔄 Wygeneruj ponownie';
                document.getElementById('saveBtn').disabled = false;
                showMessage('info', 'Pracownik ma już urządzenie. Możesz je wymienić.');
                stopStatusMonitoring();
            } else {
                updateDeviceStatus('none');
                document.getElementById('saveBtn').textContent = '💾 Zapisz';
                document.getElementById('saveBtn').disabled = false;
                stopStatusMonitoring();
            }
        }
    })
    .catch(error => {
        console.error('Check device error:', error);
        showMessage('error', 'Błąd podczas sprawdzania urządzenia');
    });
}

function generateNewCode() {
    console.log('Generating new code...');
    stopStatusMonitoring();
    stopWaitingAnimation();
    
    fetch('ajax_generate_code.php')
    .then(response => response.json())
    .then(data => {
        console.log('New code response:', data);
        if (data.success) {
            currentDeviceCode = data.code;
            updateQRCode(currentDeviceCode);
            if (!existingDevice || !existingDevice.exists) {
                updateDeviceStatus('none');
                document.getElementById('saveBtn').textContent = '💾 Zapisz';
            }
            showMessage('success', 'Wygenerowano nowy kod tymczasowy');
            currentStatus = 'none';
        }
    })
    .catch(error => {
        console.error('Generate code error:', error);
        showMessage('error', 'Błąd podczas generowania kodu');
    });
}

function saveDevice() {
    if (!currentEmployee) {
        showMessage('error', 'Najpierw wybierz pracownika');
        return;
    }
    
    console.log('Saving device for employee:', currentEmployee.NR_PRACOWNIKA, 'Code:', currentDeviceCode);
    
    const saveBtn = document.getElementById('saveBtn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = 'Zapisywanie... <div class="loading"></div>';
    
    fetch('ajax_save_device.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `nr_pracownika=${encodeURIComponent(currentEmployee.NR_PRACOWNIKA)}&kod=${encodeURIComponent(currentDeviceCode)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Save response:', data);
        
        if (data.success) {
            existingDevice = data.data;
            
            if (data.new_code) {
                currentDeviceCode = data.new_code;
                updateQRCode(currentDeviceCode);
            }
            
            updateDeviceStatus('pending');
            document.getElementById('saveBtn').textContent = '🔄 Wygeneruj ponownie';
            showMessage('success', data.message);
            
            // Запускаем мониторинг после успешного сохранения
            startMonitoringAfterSave(currentEmployee.NR_PRACOWNIKA);
        } else {
            showMessage('error', data.message || 'Błąd podczas zapisywania');
            if (data.new_code) {
                currentDeviceCode = data.new_code;
                updateQRCode(currentDeviceCode);
            }
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        showMessage('error', 'Błąd połączenia z serwerem');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '💾 Zapisz';
    });
}

function updateQRCode(code) {
    console.log('Updating QR code for:', code);
    
    fetch('ajax_generate_qr.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `code=${encodeURIComponent(code)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('QR generation response:', data);
        if (data.success) {
            document.getElementById('qrImage').src = data.qr;
            document.getElementById('deviceCode').textContent = code;
            currentDeviceCode = code;
        }
    })
    .catch(error => {
        console.error('QR generation error:', error);
    });
}

function updateDeviceStatus(status) {
    console.log('Updating device status to:', status);
    currentStatus = status;
    const qrContainer = document.getElementById('qrContainer');
    const statusBadge = document.getElementById('statusBadge');
    
    qrContainer.classList.remove('status-none', 'status-pending', 'status-active', 'status-disabled');
    statusBadge.classList.remove('badge-none', 'badge-pending', 'badge-active', 'badge-disabled');
    
    switch(status) {
        case 'none':
            qrContainer.classList.add('status-none');
            statusBadge.innerHTML = '<span>⚪</span> Nie zarejestrowano';
            statusBadge.classList.add('badge-none');
            break;
        case 'pending':
            qrContainer.classList.add('status-pending');
            statusBadge.innerHTML = '<span>🟡</span> Oczekuje na aktywację';
            statusBadge.classList.add('badge-pending');
            break;
        case 'active':
            qrContainer.classList.add('status-active');
            statusBadge.innerHTML = '<span>🟢</span> Aktywowany';
            statusBadge.classList.add('badge-active');
            break;
        case 'disabled':
            qrContainer.classList.add('status-disabled');
            statusBadge.innerHTML = '<span>🔴</span> Zablokowany';
            statusBadge.classList.add('badge-disabled');
            break;
    }
}

function copyDeviceCode() {
    navigator.clipboard.writeText(currentDeviceCode).then(() => {
        showMessage('success', 'Kod skopiowany do schowka');
        setTimeout(() => {
            const msg = document.getElementById('message');
            msg.classList.remove('show');
        }, 2000);
    });
}

function showMessage(type, text) {
    const messageDiv = document.getElementById('message');
    messageDiv.className = `message ${type} show`;
    messageDiv.textContent = text;
    
    setTimeout(() => {
        messageDiv.classList.remove('show');
    }, 3000);
}

// Функции мониторинга статуса
function startMonitoringAfterSave(nrPracownika) {
    console.log('Starting monitoring for employee:', nrPracownika);
    stopStatusMonitoring();
    
    currentIdentifier = nrPracownika;
    isMonitoringActive = true;
    
    // Немедленно проверяем статус
    checkDeviceStatus();
    
    // Затем проверяем каждую секунду
    statusCheckInterval = setInterval(() => {
        checkDeviceStatus();
    }, 1000);
    
    console.log(`🔍 Monitorowanie aktywacji rozpoczęte dla pracownika: ${nrPracownika} (co 1 sekundę)`);
    showWaitingIndicator(true);
    startWaitingAnimation();
}

function stopStatusMonitoring() {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
        statusCheckInterval = null;
        console.log('⏹️ Monitorowanie statusu zatrzymane');
    }
    isMonitoringActive = false;
    currentIdentifier = null;
    showWaitingIndicator(false);
}

function checkDeviceStatus() {
    if (!currentIdentifier || !isMonitoringActive) {
        console.log('Monitoring not active or no identifier');
        return;
    }
    
    console.log('Checking device status for:', currentIdentifier);
    
    fetch('ajax_get_device_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `nr_pracownika=${encodeURIComponent(currentIdentifier)}`
    })
    .then(response => response.json())
    .then(data => {
        console.log('Status check response:', data);
        
        if (data.success) {
            const oldStatus = currentStatus;
            
            // Обновляем UI в любом случае
            updateDeviceStatusUI(data);
            
            // Проверяем изменение статуса
            if (oldStatus !== data.status) {
                console.log(`Status changed from ${oldStatus} to ${data.status}`);
                handleStatusChange(oldStatus, data.status);
            }
            
            // Если устройство активировано, замедляем проверку
            if (data.status === 'active' && statusCheckInterval) {
                clearInterval(statusCheckInterval);
                statusCheckInterval = setInterval(() => {
                    checkDeviceStatus();
                }, 30000);
                console.log('✅ Urządzenie aktywne - zmniejszono częstotliwość sprawdzania do 30s');
                showWaitingIndicator(false);
                stopWaitingAnimation();
            }
            
            currentStatus = data.status;
        } else {
            console.error('Status check failed:', data.message);
        }
    })
    .catch(error => {
        console.error('Status check error:', error);
    });
}

function updateDeviceStatusUI(data) {
    const qrContainer = document.getElementById('qrContainer');
    const statusBadge = document.getElementById('statusBadge');
    
    qrContainer.classList.remove('status-none', 'status-pending', 'status-active', 'status-disabled');
    statusBadge.classList.remove('badge-none', 'badge-pending', 'badge-active', 'badge-disabled');
    
    qrContainer.classList.add(data.statusClass);
    statusBadge.innerHTML = `<span>${data.statusIcon}</span> ${data.statusText}`;
    
    switch(data.status) {
        case 'none':
            statusBadge.classList.add('badge-none');
            break;
        case 'pending':
            statusBadge.classList.add('badge-pending');
            break;
        case 'active':
            statusBadge.classList.add('badge-active');
            break;
        case 'disabled':
            statusBadge.classList.add('badge-disabled');
            break;
    }
    
    // Если статус активный, обновляем информацию
    if (data.status === 'active' && data.ost_aktywnosc) {
        const lastActive = new Date(data.ost_aktywnosc).toLocaleString('pl-PL');
        addStatusDetail(`✅ Aktywowano: ${lastActive}`);
    }
}

function handleStatusChange(oldStatus, newStatus) {
    console.log(`Status change handler: ${oldStatus} -> ${newStatus}`);
    
    let message = '';
    let messageType = 'info';
    
    switch(newStatus) {
        case 'pending':
            if (oldStatus === 'none') {
                message = '🟡 Urządzenie zostało zarejestrowane! Oczekuje na aktywację przez pracownika.';
                messageType = 'info';
            }
            break;
        case 'active':
            message = '🟢✅ AKTYWACJA UDANA! Pracownik aktywował urządzenie.';
            messageType = 'success';
            playActivationEffects();
            break;
        case 'disabled':
            message = '🔴⛔ URZĄDZENIE ZABLOKOWANE! Dostęp został wyłączony.';
            messageType = 'error';
            break;
    }
    
    if (message) {
        showMessage(messageType, message);
        if (newStatus === 'active') {
            showToastNotification('Aktywacja urządzenia!', message, 'success');
        }
    }
}

let statusDetailElement = null;

function addStatusDetail(text) {
    if (!statusDetailElement) {
        const deviceSection = document.querySelector('.device-section');
        statusDetailElement = document.createElement('div');
        statusDetailElement.className = 'status-detail';
        statusDetailElement.style.cssText = 'margin-top: 10px; font-size: 12px; color: #666;';
        deviceSection.appendChild(statusDetailElement);
    }
    statusDetailElement.innerHTML = text;
}

function playActivationEffects() {
    console.log('Playing activation effects');
    const qrContainer = document.getElementById('qrContainer');
    
    let flashes = 0;
    const flashInterval = setInterval(() => {
        if (flashes >= 8) {
            clearInterval(flashInterval);
            qrContainer.style.transition = '';
            qrContainer.style.transform = '';
            qrContainer.style.boxShadow = '';
        } else {
            qrContainer.style.transition = 'all 0.15s';
            if (flashes % 2 === 0) {
                qrContainer.style.transform = 'scale(1.05)';
                qrContainer.style.boxShadow = '0 0 25px rgba(76, 175, 80, 0.8)';
            } else {
                qrContainer.style.transform = 'scale(1)';
                qrContainer.style.boxShadow = '0 0 10px rgba(76, 175, 80, 0.3)';
            }
            flashes++;
        }
    }, 150);
    
    playNotificationSound();
    showToastNotification('✅ Urządzenie aktywowane!', 'Pracownik pomyślnie aktywował skaner.', 'success');
}

function playNotificationSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 880;
        gainNode.gain.value = 0.3;
        
        oscillator.start();
        gainNode.gain.exponentialRampToValueAtTime(0.00001, audioContext.currentTime + 0.5);
        oscillator.stop(audioContext.currentTime + 0.5);
        
        if (audioContext.state === 'suspended') {
            audioContext.resume();
        }
    } catch(e) {
        console.log('Audio not supported:', e);
    }
}

function startWaitingAnimation() {
    if (waitingAnimationInterval) return;
    
    console.log('Starting waiting animation');
    const qrContainer = document.getElementById('qrContainer');
    let pulse = 0;
    
    waitingAnimationInterval = setInterval(() => {
        if (!isMonitoringActive || currentStatus !== 'pending') {
            stopWaitingAnimation();
            return;
        }
        
        pulse = (pulse + 1) % 60;
        if (pulse < 30) {
            qrContainer.style.boxShadow = '0 0 15px rgba(255, 193, 7, 0.5)';
        } else {
            qrContainer.style.boxShadow = '0 0 25px rgba(255, 193, 7, 0.8)';
        }
    }, 500);
}

function stopWaitingAnimation() {
    if (waitingAnimationInterval) {
        clearInterval(waitingAnimationInterval);
        waitingAnimationInterval = null;
        const qrContainer = document.getElementById('qrContainer');
        qrContainer.style.boxShadow = '';
        console.log('Stopped waiting animation');
    }
}

function showWaitingIndicator(show) {
    const statusBadge = document.getElementById('statusBadge');
    if (!statusBadge) return;
    
    if (show && currentStatus === 'pending') {
        if (!document.querySelector('.live-indicator')) {
            const liveIndicator = document.createElement('div');
            liveIndicator.className = 'live-indicator';
            liveIndicator.innerHTML = '<span class="live-dot"></span> Oczekiwanie na aktywację...';
            statusBadge.parentNode.appendChild(liveIndicator);
        }
    } else {
        const indicator = document.querySelector('.live-indicator');
        if (indicator) indicator.remove();
    }
}

function showToastNotification(title, message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <div class="toast-header">
            <strong>${title}</strong>
            <button class="toast-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
        <div class="toast-body">${message}</div>
    `;
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        min-width: 300px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        z-index: 10000;
        animation: slideInRight 0.3s ease;
        overflow: hidden;
    `;
    
    if (type === 'success') {
        toast.style.borderLeft = '4px solid #4caf50';
    } else if (type === 'error') {
        toast.style.borderLeft = '4px solid #f44336';
    } else {
        toast.style.borderLeft = '4px solid #ffc107';
    }
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast && toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
}

function addManualRefreshButton() {
    const buttonsDiv = document.querySelector('.buttons');
    
    // Проверяем, нет ли уже такой кнопки
    if (document.querySelector('.refresh-status-btn')) return;
    
    const refreshBtn = document.createElement('button');
    refreshBtn.className = 'btn-secondary refresh-status-btn';
    refreshBtn.innerHTML = '🔄 Sprawdź status';
    refreshBtn.onclick = () => {
        if (currentIdentifier && isMonitoringActive) {
            console.log('Manual status check requested');
            checkDeviceStatus();
            showMessage('info', 'Sprawdzanie statusu...');
        } else if (currentEmployee && currentEmployee.NR_PRACOWNIKA) {
            // Если мониторинг не активен, но сотрудник выбран и есть устройство
            console.log('Manual check for employee:', currentEmployee.NR_PRACOWNIKA);
            fetch('ajax_get_device_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `nr_pracownika=${encodeURIComponent(currentEmployee.NR_PRACOWNIKA)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDeviceStatusUI(data);
                    currentStatus = data.status;
                    showMessage('success', `Status: ${data.statusText}`);
                }
            });
        } else {
            showMessage('info', 'Najpierw wybierz pracownika i zapisz urządzenie');
        }
    };
    buttonsDiv.appendChild(refreshBtn);
}

// Очистка при закрытии страницы
window.addEventListener('beforeunload', function() {
    if (statusCheckInterval) {
        clearInterval(statusCheckInterval);
    }
    if (waitingAnimationInterval) {
        clearInterval(waitingAnimationInterval);
    }
});
