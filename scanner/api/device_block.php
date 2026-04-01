<?php
// scanner/api/device_block.php
require_once __DIR__ . '/../config/database.php';

// Устанавливаем заголовок JSON
header('Content-Type: application/json');

// Получаем device_code из POST запроса
$device_code = $_POST['device_code'] ?? '';

// Проверяем, что device_code не пустой
if (empty($device_code)) {
    errorResponse('Device code is required', 400);
}

try {
    // Проверяем существует ли устройство
    $existing = fetchOne("SELECT id FROM devices WHERE device_code = ?", [$device_code]);
    
    if (!$existing) {
        errorResponse('Device not found', 404);
    }
    
    // Блокируем устройство
    executeQuery(
        "UPDATE devices SET device_token = 'disabled', last_active = NULL, blocked_at = NOW() WHERE device_code = ?",
        [$device_code]
    );
    
    // Успешный ответ
    successResponse([
        'message' => 'Device blocked successfully',
        'device_code' => $device_code
    ]);
    
} catch (Exception $e) {
    // Логируем ошибку
    error_log("Error blocking device: " . $e->getMessage());
    
    // Отправляем ошибку
    errorResponse('Failed to block device: ' . $e->getMessage(), 500);
}