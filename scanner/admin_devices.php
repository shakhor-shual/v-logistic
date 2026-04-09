<?php
// /scanner/admin_devices.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/qr_helper.php';

// Генерируем временный код при загрузке страницы
function generateTempCode() {
    return 'REG-' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 4);
}

$tempCode = generateTempCode();
$tempQR = generateQRCodeBase64($tempCode);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administracja skanerami - Zarządzanie urządzeniami</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .content {
            padding: 30px;
        }

        /* Поиск сотрудника */
        .search-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }

        .search-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .search-type {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .search-type label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #555;
        }

        .search-type input[type="radio"] {
            cursor: pointer;
        }

        .search-input-wrapper {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Подсказки автодополнения */
        .autocomplete-items {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: none;
        }

        .autocomplete-item {
            padding: 12px 15px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f0f0f0;
        }

        .autocomplete-item:hover {
            background: #f0f0ff;
        }

        .autocomplete-item strong {
            color: #667eea;
        }

        /* Информация о сотруднике */
        .employee-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid #e0e0e0;
            display: none;
        }

        .employee-info.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .employee-info h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .employee-details {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px 20px;
        }

        .employee-label {
            font-weight: 600;
            color: #555;
        }

        .employee-value {
            color: #333;
        }

        /* Устройство */
        .device-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #e0e0e0;
            text-align: center;
        }

        .qr-container {
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 15px;
            display: inline-block;
            transition: all 0.3s;
        }

        .qr-container img {
            max-width: 250px;
            height: auto;
            display: block;
        }

        /* Цветовые рамки статусов */
        .status-none {
            border: 3px solid #9e9e9e;
            box-shadow: 0 0 0 3px rgba(158, 158, 158, 0.1);
        }

        .status-pending {
            border: 3px solid #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
        }

        .status-active {
            border: 3px solid #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .status-disabled {
            border: 3px solid #f44336;
            box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
        }

        .device-code {
            font-size: 24px;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            color: #333;
            margin: 15px 0;
            letter-spacing: 1px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
        }

        .status-badge span {
            font-size: 18px;
        }

        .badge-none {
            background: #f5f5f5;
            color: #757575;
        }

        .badge-pending {
            background: #fff3e0;
            color: #f57c00;
        }

        .badge-active {
            background: #e8f5e9;
            color: #388e3c;
        }

        .badge-disabled {
            background: #ffebee;
            color: #d32f2f;
        }

        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        button {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .message {
            margin-top: 20px;
            padding: 12px;
            border-radius: 10px;
            display: none;
            animation: slideIn 0.3s ease;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .message.show {
            display: block;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.6s linear infinite;
            margin-left: 10px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .copy-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            font-size: 14px;
            margin-left: 10px;
        }

        .copy-btn:hover {
            background: #218838;
        }

        @media (max-width: 768px) {
            .content {
                padding: 20px;
            }
            
            .buttons {
                flex-direction: column;
            }
            
            button {
                width: 100%;
            }
            
            .device-code {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Zarządzanie urządzeniami skanującymi</h1>
            <p>Rejestracja i przydzielanie skanerów dla pracowników</p>
        </div>
        
        <div class="content">
            <!-- Поиск сотрудника -->
            <div class="search-section">
                <div class="search-title">🔍 Wybierz pracownika</div>
                <div class="search-type">
                    <label>
                        <input type="radio" name="search_type" value="nr" checked> 
                        📋 Numer pracownika
                    </label>
                    <label>
                        <input type="radio" name="search_type" value="nazwisko"> 
                        👤 Nazwisko
                    </label>
                </div>
                <div class="search-input-wrapper">
                    <input type="text" id="searchInput" class="search-input" 
                           placeholder="Wprowadź numer lub nazwisko..." autocomplete="off">
                    <div id="autocompleteList" class="autocomplete-items"></div>
                </div>
            </div>
            
            <!-- Информация о сотруднике -->
            <div id="employeeInfo" class="employee-info">
                <h3>📋 Informacje o pracowniku</h3>
                <div class="employee-details">
                    <div class="employee-label">Numer:</div>
                    <div class="employee-value" id="empNr">-</div>
                    
                    <div class="employee-label">Imię i nazwisko:</div>
                    <div class="employee-value" id="empName">-</div>
                    
                    <div class="employee-label">Komórka organizacyjna:</div>
                    <div class="employee-value" id="empDepartment">-</div>
                </div>
            </div>
            
            <!-- Устройство -->
            <div class="device-section">
                <div class="qr-container" id="qrContainer">
                    <img id="qrImage" src="<?php echo $tempQR; ?>" alt="QR Code">
                </div>
                <div class="device-code" id="deviceCode"><?php echo $tempCode; ?></div>
                <button class="copy-btn" onclick="copyDeviceCode()">📋 Kopiuj kod</button>
                <div id="statusBadge" class="status-badge badge-none">
                    <span>⚪</span> Nie zarejestrowano
                </div>
                <div class="buttons">
                    <button id="newCodeBtn" class="btn-secondary" onclick="generateNewCode()">
                        🔄 Nowy kod
                    </button>
                    <button id="saveBtn" class="btn-primary" onclick="saveDevice()" disabled>
                        💾 Zapisz
                    </button>
                </div>
                <div id="message" class="message"></div>
            </div>
        </div>
    </div>
    
    <script>
        let currentEmployee = null;
        let currentDeviceCode = '<?php echo $tempCode; ?>';
        let currentStatus = 'none';
        let existingDevice = null;
        
        // Поиск с автодополнением
        const searchInput = document.getElementById('searchInput');
        const autocompleteList = document.getElementById('autocompleteList');
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                autocompleteList.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });
        
        function performSearch(query) {
            const searchType = document.querySelector('input[name="search_type"]:checked').value;
            
            fetch('ajax_search_employees.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=${searchType}&query=${encodeURIComponent(query)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    showAutocomplete(data.data);
                } else {
                    autocompleteList.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                autocompleteList.style.display = 'none';
            });
        }
        
        function showAutocomplete(employees) {
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
            currentEmployee = employee;
            searchInput.value = `${employee.NR_PRACOWNIKA} - ${employee.IMIE} ${employee.NAZWISKO}`;
            autocompleteList.style.display = 'none';
            
            // Показать информацию о сотруднике
            document.getElementById('empNr').textContent = employee.NR_PRACOWNIKA;
            document.getElementById('empName').textContent = `${employee.IMIE} ${employee.NAZWISKO}`;
            document.getElementById('empDepartment').textContent = `${employee.KOMORKA_ORG} - ${employee.NAZWA_KOMORKI_ORG}`;
            document.getElementById('employeeInfo').classList.add('active');
            
            // Проверить наличие устройства
            checkEmployeeDevice(employee.NR_PRACOWNIKA);
        }
        
        function checkEmployeeDevice(nrPracownika) {
            fetch('ajax_check_device.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `nr_pracownika=${encodeURIComponent(nrPracownika)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    existingDevice = data.data;
                    if (existingDevice.exists) {
                        // У сотрудника есть устройство
                        currentDeviceCode = existingDevice.kod;
                        updateQRCode(currentDeviceCode);
                        updateDeviceStatus(existingDevice.status);
                        document.getElementById('saveBtn').textContent = '🔄 Wygeneruj ponownie';
                        document.getElementById('saveBtn').disabled = false;
                        showMessage('info', 'Pracownik ma już urządzenie. Możesz je wymienić.');
                    } else {
                        // Нет устройства
                        updateDeviceStatus('none');
                        document.getElementById('saveBtn').textContent = '💾 Zapisz';
                        document.getElementById('saveBtn').disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd podczas sprawdzania urządzenia');
            });
        }
        
        function generateNewCode() {
            fetch('ajax_generate_code.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentDeviceCode = data.code;
                    updateQRCode(currentDeviceCode);
                    if (!existingDevice || !existingDevice.exists) {
                        updateDeviceStatus('none');
                        document.getElementById('saveBtn').textContent = '💾 Zapisz';
                    }
                    showMessage('success', 'Wygenerowano nowy kod tymczasowy');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd podczas generowania kodu');
            });
        }
        
        function saveDevice() {
            if (!currentEmployee) {
                showMessage('error', 'Najpierw wybierz pracownika');
                return;
            }
            
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
                if (data.success) {
                    existingDevice = data.data;
                    updateDeviceStatus('pending');
                    document.getElementById('saveBtn').textContent = '🔄 Wygeneruj ponownie';
                    showMessage('success', data.message);
                } else {
                    showMessage('error', data.message || 'Błąd podczas zapisywania');
                    if (data.new_code) {
                        currentDeviceCode = data.new_code;
                        updateQRCode(currentDeviceCode);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', 'Błąd połączenia z serwerem');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '💾 Zapisz';
            });
        }
        
        function updateQRCode(code) {
            fetch('ajax_generate_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `code=${encodeURIComponent(code)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('qrImage').src = data.qr;
                    document.getElementById('deviceCode').textContent = code;
                    currentDeviceCode = code;
                }
            });
        }
        
        function updateDeviceStatus(status) {
            currentStatus = status;
            const qrContainer = document.getElementById('qrContainer');
            const statusBadge = document.getElementById('statusBadge');
            
            // Удаляем все классы статусов
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
        
        // Скрыть автодополнение при клике вне
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !autocompleteList.contains(e.target)) {
                autocompleteList.style.display = 'none';
            }
        });
    </script>
</body>
</html>
