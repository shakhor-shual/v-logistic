<?php
// api/verify_token.php - Verify if token is still valid
require_once __DIR__ . '/../config/database.php';

try {
    $device_id = $_POST['device_id'] ?? '';
    $token = $_POST['token'] ?? '';
    
    if (empty($device_id) || empty($token)) {
        jsonResponse([
            'status' => 'error',
            'message' => 'Missing device_id or token'
        ], 400);
    }
    
    // Check if device exists with this token and is active
    $device = fetchOne(
        "SELECT id, device_token, last_active FROM devices 
         WHERE id = ? AND device_token = ? AND device_token != 'disabled'",
        [$device_id, $token]
    );
    
    if ($device) {
        // Update last_active timestamp
        executeQuery(
            "UPDATE devices SET last_active = NOW() WHERE id = ?",
            [$device_id]
        );
        
        jsonResponse([
            'status' => 'success',
            'message' => 'Token is valid',
            'valid' => true
        ]);
    } else {
        jsonResponse([
            'status' => 'error',
            'message' => 'Token is invalid or device is blocked',
            'valid' => false
        ], 401);
    }
    
} catch (PDOException $e) {
    error_log("Verify token error: " . $e->getMessage());
    jsonResponse([
        'status' => 'error',
        'message' => 'Database error',
        'valid' => false
    ], 500);
} catch (Exception $e) {
    jsonResponse([
        'status' => 'error',
        'message' => $e->getMessage(),
        'valid' => false
    ], 500);
}
