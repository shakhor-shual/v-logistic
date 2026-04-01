<?php
// scanner/api/device_reset.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$device_code = trim($_POST['device_code'] ?? '');

if (empty($device_code)) {
    errorResponse('Device code is required', 400);
}

try {
    // Проверяем существует ли устройство
    $existing = fetchOne("SELECT id FROM devices WHERE device_code = ?", [$device_code]);
    
    if (!$existing) {
        errorResponse('Device not found', 404);
    }
    
    // Сбрасываем устройство
    executeQuery(
        "UPDATE devices SET device_token = NULL, last_active = NULL WHERE device_code = ?",
        [$device_code]
    );
    
    successResponse([
        'message' => 'Device reset, ready for activation',
        'device_code' => $device_code
    ]);
    
} catch (Exception $e) {
    error_log("Error resetting device: " . $e->getMessage());
    errorResponse('Failed to reset device: ' . $e->getMessage(), 500);
}