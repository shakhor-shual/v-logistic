<?php
// /scanner/ajax_generate_code.php
require_once __DIR__ . '/includes/qr_helper.php';

header('Content-Type: application/json');

try {
    $code = generateUniqueDeviceCode();
    
    echo json_encode([
        'success' => true,
        'code' => $code
    ]);
} catch (Exception $e) {
    error_log("AJAX Generate Code Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd generowania kodu'
    ]);
}
