<?php
// /scanner/api/scan_save.php

require_once __DIR__ . '/../config/database.php';

// Устанавливаем заголовок JSON
header('Content-Type: application/json');

// Получаем данные из POST запроса
$device_id = isset($_POST['device_id']) ? trim($_POST['device_id']) : '';
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$code = isset($_POST['code']) ? trim($_POST['code']) : '';

// Валидация входных данных
if (empty($code) || empty($device_id) || empty($token)) {
    errorResponse('Missing required data: code, device_id, and token are required', 400);
}

try {
    // Проверка существования устройства и токена
    $device = fetchOne("
        SELECT id, device_code, is_active, expires_at, blocked_at 
        FROM devices 
        WHERE id = ? AND device_token = ?
    ", [$device_id, $token]);
    
    // Проверяем существует ли устройство
    if (!$device) {
        errorResponse('Invalid device or token', 403);
    }
    
    // Проверяем не заблокировано ли устройство
    if ($device['blocked_at'] !== null) {
        errorResponse('Device is blocked. Contact administrator.', 403);
    }
    
    // Проверяем активно ли устройство
    if ($device['is_active'] != 1) {
        errorResponse('Device is not active', 403);
    }
    
    // Проверка срока действия токена
    if (!empty($device['expires_at'])) {
        $expiresAt = new DateTime($device['expires_at']);
        $now = new DateTime();
        
        if ($now > $expiresAt) {
            // Деактивируем устройство при истечении срока
            executeQuery("UPDATE devices SET is_active = 0 WHERE id = ?", [$device_id]);
            errorResponse('Token has expired. Device deactivated.', 401);
        }
    }
    
    // Сохраняем скан в базу данных
    $result = executeQuery(
        "INSERT INTO scans (code, device_id, synced, timestamp, created_at, ip_address, user_agent) 
         VALUES (?, ?, 0, NOW(), NOW(), ?, ?)",
        [
            $code, 
            $device_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );
    
    // Обновляем last_active для устройства
    executeQuery(
        "UPDATE devices SET last_active = NOW(), scan_count = COALESCE(scan_count, 0) + 1 WHERE id = ?",
        [$device_id]
    );
    
    // Успешный ответ
    successResponse([
        'message' => 'Scan saved successfully',
        'scan_id' => $result ? $result->lastInsertId() : null,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Логируем ошибку с деталями
    error_log("Error saving scan [Device: {$device_id}, Code: {$code}]: " . $e->getMessage());
    
    // Отправляем ошибку
    errorResponse('Failed to save scan: ' . $e->getMessage(), 500);
}
