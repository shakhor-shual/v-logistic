<?php
// /scanner/includes/qr_helper.php
require_once __DIR__ . '/../api/phpqrcode/qrlib.php';

/**
 * Генерирует QR-код и возвращает его в формате base64 для вставки в img src
 * 
 * @param string $data Данные для QR-кода
 * @return string Base64 encoded PNG image
 */
function generateQRCodeBase64($data) {
    // Включаем буферизацию вывода
    ob_start();
    
    // Генерируем QR-код прямо в буфер
    QRcode::png($data, null, QR_ECLEVEL_L, 10, 2);
    
    // Получаем содержимое буфера
    $imageData = ob_get_clean();
    
    // Кодируем в base64
    $base64 = base64_encode($imageData);
    
    // Возвращаем data:URI
    return 'data:image/png;base64,' . $base64;
}

/**
 * Генерирует случайный код устройства
 * 
 * @return string Код в формате REG-XXXX
 */
function generateDeviceCode() {
    return 'REG-' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 4);
}

/**
 * Проверяет уникальность кода в БД
 * 
 * @param string $code Код для проверки
 * @return bool True если код уникален
 */
function isCodeUnique($code) {
    $existing = fetchOne("SELECT id FROM ListaSkanerow WHERE KOD_REJ_URZ = ?", [$code]);
    return !$existing;
}

/**
 * Генерирует уникальный код устройства
 * 
 * @return string Уникальный код
 */
function generateUniqueDeviceCode() {
    do {
        $code = generateDeviceCode();
    } while (!isCodeUnique($code));
    
    return $code;
}
