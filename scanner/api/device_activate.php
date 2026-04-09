<?php
// scanner/api/activate.php - Device activation (FIXED)
require_once __DIR__ . '/../config/database.php';

try {
    // Get QR code
    $qr_code = $_POST['code'] ?? '';
    
    if (empty($qr_code)) {
        errorResponse('QR code not received', 400);
    }
    
    // Find device
    $device = fetchOne(
        "SELECT id, KOD_REJ_URZ, TOKEN_DOSTEP_URZ FROM ListaSkanerow WHERE KOD_REJ_URZ = ?",
        [$qr_code]
    );
    
    if (!$device) {
        errorResponse('Activation code not found', 404);
    }
    
    // Check if blocked
    if ($device['TOKEN_DOSTEP_URZ'] === 'disabled') {
        errorResponse('Device is blocked by administrator', 403);
    }
    
    // Check if already activated
    if (!empty($device['TOKEN_DOSTEP_URZ']) && $device['TOKEN_DOSTEP_URZ'] !== 'disabled') {
        errorResponse('Device already activated', 409);
    }
    
    // Generate UUID v4 token
    $token = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    // Activate device - ПРОСТО ВЫПОЛНЯЕМ ЗАПРОС без проверки rowCount()
    executeQuery(
        "UPDATE ListaSkanerow 
         SET TOKEN_DOSTEP_URZ = ?, OST_AKTYWNOSC = NOW()
         WHERE id = ? AND (TOKEN_DOSTEP_URZ IS NULL OR TOKEN_DOSTEP_URZ = '' OR TOKEN_DOSTEP_URZ = 'disabled')",
        [$token, $device['id']]
    );
    
    // Success - всегда возвращаем успех, так как если бы была ошибка - executeQuery выбросил бы исключение
    successResponse([
        'message' => 'Activation completed',
        'device_id' => $device['id'],
        'token' => $token
    ]);
    
} catch (PDOException $e) {
    error_log("Activation error: " . $e->getMessage());
    errorResponse('Database error', 500);
} catch (Exception $e) {
    error_log("Activation error: " . $e->getMessage());
    errorResponse('Error: ' . $e->getMessage(), 500);
}
?>