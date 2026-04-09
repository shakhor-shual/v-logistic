<?php
// /scanner/ajax_save_device.php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/qr_helper.php';

header('Content-Type: application/json');

$nrPracownika = isset($_POST['nr_pracownika']) ? $_POST['nr_pracownika'] : '';
$kod = isset($_POST['kod']) ? $_POST['kod'] : '';

if (empty($nrPracownika)) {
    echo json_encode(['success' => false, 'message' => 'Brak numeru pracownika']);
    exit;
}

if (empty($kod)) {
    echo json_encode(['success' => false, 'message' => 'Brak kodu urządzenia']);
    exit;
}

try {
    // Проверяем, существует ли уже устройство для этого сотрудника
    $existing = fetchOne(
        "SELECT id FROM ListaSkanerow WHERE NR_PRACOWNIKA = ?",
        [$nrPracownika]
    );
    
    if ($existing) {
        // Обновляем существующее устройство (перевыпуск)
        // Проверяем уникальность кода (кроме текущего устройства)
        $codeExists = fetchOne(
            "SELECT id FROM ListaSkanerow WHERE KOD_REJ_URZ = ? AND NR_PRACOWNIKA != ?",
            [$kod, $nrPracownika]
        );
        
        if ($codeExists) {
            // Код уже существует, генерируем новый
            $newCode = generateUniqueDeviceCode();
            executeQuery(
                "UPDATE ListaSkanerow 
                 SET KOD_REJ_URZ = ?, TOKEN_DOSTEP_URZ = NULL, CZAS_UTW = NOW() 
                 WHERE NR_PRACOWNIKA = ?",
                [$newCode, $nrPracownika]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Urządzenie zostało ponownie wydane z nowym kodem',
                'data' => [
                    'exists' => true,
                    'status' => 'pending'
                ],
                'new_code' => $newCode
            ]);
        } else {
            // Обновляем с текущим кодом
            executeQuery(
                "UPDATE ListaSkanerow 
                 SET KOD_REJ_URZ = ?, TOKEN_DOSTEP_URZ = NULL, CZAS_UTW = NOW() 
                 WHERE NR_PRACOWNIKA = ?",
                [$kod, $nrPracownika]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Urządzenie zostało ponownie wydane',
                'data' => [
                    'exists' => true,
                    'status' => 'pending'
                ]
            ]);
        }
    } else {
        // Создаем новое устройство
        // Проверяем уникальность кода
        $codeExists = fetchOne(
            "SELECT id FROM ListaSkanerow WHERE KOD_REJ_URZ = ?",
            [$kod]
        );
        
        if ($codeExists) {
            // Код уже существует, генерируем новый
            $newCode = generateUniqueDeviceCode();
            executeQuery(
                "INSERT INTO ListaSkanerow (KOD_REJ_URZ, NR_PRACOWNIKA, TOKEN_DOSTEP_URZ, CZAS_UTW) 
                 VALUES (?, ?, NULL, NOW())",
                [$newCode, $nrPracownika]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Urządzenie zostało zarejestrowane z nowym kodem',
                'data' => [
                    'exists' => true,
                    'status' => 'pending'
                ],
                'new_code' => $newCode
            ]);
        } else {
            executeQuery(
                "INSERT INTO ListaSkanerow (KOD_REJ_URZ, NR_PRACOWNIKA, TOKEN_DOSTEP_URZ, CZAS_UTW) 
                 VALUES (?, ?, NULL, NOW())",
                [$kod, $nrPracownika]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Urządzenie zostało zarejestrowane',
                'data' => [
                    'exists' => true,
                    'status' => 'pending'
                ]
            ]);
        }
    }
    
} catch (Exception $e) {
    error_log("AJAX Save Device Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd podczas zapisywania: ' . $e->getMessage()
    ]);
}
