<?php
// scanner/api/activate.php - Device activation
require_once __DIR__ . '/../config/database.php';

try {
    // Get QR code
    $qr_code = $_POST['code'] ?? '';
    
    if (empty($qr_code)) {
        errorResponse('QR code not received', 400);
    }
    
    // Find device
    $device = fetchOne(
        "SELECT id, device_code, device_token FROM devices WHERE device_code = ?",
        [$qr_code]
    );
    
    if (!$device) {
        errorResponse('Activation code not found', 404);
    }
    
    // Check if blocked
    if ($device['device_token'] === 'disabled') {
        errorResponse('Device is blocked by administrator', 403);
    }
    
    // Check if already activated
    if (!empty($device['device_token']) && $device['device_token'] !== 'disabled') {
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
    
    // Activate device
    $stmt = executeQuery(
        "UPDATE devices 
         SET device_token = ?, last_active = NOW()
         WHERE id = ? AND (device_token IS NULL OR device_token = '' OR device_token = 'disabled')",
        [$token, $device['id']]
    );
    
    if ($stmt->rowCount() === 0) {
        errorResponse('Failed to activate device', 500);
    }
    
    // Success
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