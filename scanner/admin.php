<?php
// /scanner/admin_devices.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/qr_helper.php';

// Генерируем временный код при загрузке страницы
$tempCode = generateUniqueDeviceCode();
$tempQR = generateQRCodeBase64($tempCode);

// Определяем стиль (можно передать параметром в URL: ?style=corporate)
$style = isset($_GET['style']) && $_GET['style'] === 'corporate' ? 'corporate' : 'corporate'; // по умолчанию корпоративный
$cssFile = $style === 'corporate' ? 'admin-style-corporate.css' : 'admin-style.css';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administracja skanerami - Zarządzanie urządzeniami</title>
    <link rel="stylesheet" href="assets/css/<?php echo $cssFile; ?>">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Zarządzanie urządzeniami skanującymi</h1>
            <p>Rejestracja i przydzielanie skanerów dla pracowników</p>
        </div>
        
        <div class="content">
            <!-- Поиск сотрудника -->
            <div class="search-section">
                <div class="search-title">Wyszukaj pracownika</div>
                <div class="search-type">
                    <label>
                        <input type="radio" name="search_type" value="nr" checked> 
                        Numer pracownika
                    </label>
                    <label>
                        <input type="radio" name="search_type" value="nazwisko"> 
                        Nazwisko
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
                <h3>Dane pracownika</h3>
                <div class="employee-details">
                    <div class="employee-label">Numer:</div>
                    <div class="employee-value" id="empNr">—</div>
                    
                    <div class="employee-label">Imię i nazwisko:</div>
                    <div class="employee-value" id="empName">—</div>
                    
                    <div class="employee-label">Komórka organizacyjna:</div>
                    <div class="employee-value" id="empDepartment">—</div>
                </div>
            </div>
            
            <!-- Устройство -->
            <div class="device-section">
                <div class="qr-container" id="qrContainer">
                    <img id="qrImage" src="<?php echo $tempQR; ?>" alt="QR Code">
                </div>
                <div class="device-code" id="deviceCode"><?php echo $tempCode; ?></div>
                <button class="copy-btn" onclick="copyDeviceCode()">Kopiuj kod</button>
                <div id="statusBadge" class="status-badge badge-none">
                    <span>⚪</span> Nie zarejestrowano
                </div>
                <div class="buttons">
                    <button id="newCodeBtn" class="btn-secondary" onclick="generateNewCode()">
                        Nowy kod
                    </button>
                    <button id="saveBtn" class="btn-primary" onclick="saveDevice()" disabled>
                        Zapisz
                    </button>
                </div>
                <div id="message" class="message"></div>
            </div>
        </div>
    </div>
    
    <script>
        // Передаем начальные значения из PHP в JavaScript
        const initialDeviceCode = '<?php echo $tempCode; ?>';
    </script>
    <script src="assets/js/admin-devices.js"></script>
    <script>
        // Инициализация после загрузки JS
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof currentDeviceCode !== 'undefined') {
                currentDeviceCode = initialDeviceCode;
            }
        });
    </script>
</body>
</html>