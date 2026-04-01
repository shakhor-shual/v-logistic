<?php

// /scanner/api/device_add.php
require_once __DIR__ . '/../config/database.php';

// Подключаем библиотеку для генерации QR-кодов
require_once __DIR__ . '/phpqrcode/qrlib.php';

// Получаем параметр n из GET-запроса
$n = isset($_GET['n']) ? (int)$_GET['n'] : 1;

// Создаем директорию для QR-кодов, если её нет
$qrDir = __DIR__ . '/qrcodes';
if (!file_exists($qrDir)) {
    mkdir($qrDir, 0777, true);
}

// Начинаем вывод HTML
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Генерация устройств с QR-кодами</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .device-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: inline-block;
            width: 300px;
            margin: 10px;
            text-align: center;
            vertical-align: top;
        }
        .device-code {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
            font-family: monospace;
        }
        .qr-container {
            margin: 10px 0;
            padding: 10px;
            background: white;
            display: inline-block;
        }
        .qr-container img {
            max-width: 200px;
            height: auto;
        }
        .success {
            color: green;
            font-size: 12px;
            margin-top: 5px;
        }
        .error {
            color: red;
            font-size: 12px;
            margin-top: 5px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .print-btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background: #007bff;
            color: white;
            text-align: center;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-btn:hover {
            background: #0056b3;
        }
        @media print {
            .print-btn {
                display: none;
            }
            body {
                background: white;
            }
            .device-card {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <h1>QR-code for device</h1>
    <button class="print-btn" onclick="window.print()">🖨️ Print QR-codes</button>
    <div style="text-align: center;">

<?php
// Генерируем указанное количество устройств
for ($i = 0; $i < $n; $i++) {
    // Генерируем уникальный код устройства
    $code = 'DEV-' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 4) . '-' . substr(strtoupper(bin2hex(random_bytes(4))), 0, 4);
    
    // Данные для QR-кода (можно изменить на URL)
    // $qrData = 'https://' . $_SERVER['HTTP_HOST'] . '/scanner/device.php?code=' . urlencode($code);
    $qrData = $code;
    
    // Имя файла для QR-кода
    $qrFilename = $qrDir . '/' . $code . '.png';
    
    // Генерируем QR-код
    QRcode::png($qrData, $qrFilename, QR_ECLEVEL_L, 10, 2);
    
    // Вставляем в базу данных используя функции из database.php
    try {
        // Проверяем существует ли устройство
        $existing = fetchOne("SELECT id FROM devices WHERE device_code = ?", [$code]);
        
        if (!$existing) {
            // Вставляем новое устройство
            executeQuery("INSERT INTO devices (device_code, created_at) VALUES (?, NOW())", [$code]);
            $status = '<div class="success">✓ Successfully added to the System</div>';
        } else {
            $status = '<div class="success" style="color: orange;">⚠ Already exists in the System</div>';
        }
    } catch (Exception $e) {
        error_log("Error adding device: " . $e->getMessage());
        $status = '<div class="error">✗ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    
    // Выводим карточку устройства с QR-кодом
    echo '
    <div class="device-card">
        <div class="qr-container">
            <img src="qrcodes/' . $code . '.png" alt="QR Code for ' . htmlspecialchars($code) . '">
        </div>
        <div class="device-code">' . htmlspecialchars($code) . '</div>
        ' . $status . '
    </div>';
}
?>

    </div>
    <script>
        // Автоматическая печать (опционально, раскомментировать если нужно)
        // setTimeout(() => { window.print(); }, 1000);
    </script>
</body>
</html>