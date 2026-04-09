<?php
// /scanner/ajax_generate_qr.php
require_once __DIR__ . '/includes/qr_helper.php';

header('Content-Type: application/json');

$code = isset($_POST['code']) ? $_POST['code'] : '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Brak kodu']);
    exit;
}

try {
    $qrBase64 = generateQRCodeBase64($code);
    
    echo json_encode([
        'success' => true,
        'qr' => $qrBase64
    ]);
} catch (Exception $e) {
    error_log("AJAX Generate QR Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd generowania QR kodu'
    ]);
}
