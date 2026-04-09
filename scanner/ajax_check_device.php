<?php
// /scanner/ajax_check_device.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$nrPracownika = isset($_POST['nr_pracownika']) ? $_POST['nr_pracownika'] : '';

if (empty($nrPracownika)) {
    echo json_encode(['success' => false, 'message' => 'Brak numeru pracownika']);
    exit;
}

try {
    // Ищем устройство для сотрудника
    $device = fetchOne(
        "SELECT KOD_REJ_URZ, TOKEN_DOSTEP_URZ 
         FROM ListaSkanerow 
         WHERE NR_PRACOWNIKA = ?",
        [$nrPracownika]
    );
    
    if ($device) {
        // Определяем статус по TOKEN_DOSTEP_URZ
        if ($device['TOKEN_DOSTEP_URZ'] === 'disabled') {
            $status = 'disabled';
        } elseif ($device['TOKEN_DOSTEP_URZ'] !== null) {
            $status = 'active';
        } else {
            $status = 'pending';
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'exists' => true,
                'kod' => $device['KOD_REJ_URZ'],
                'token' => $device['TOKEN_DOSTEP_URZ'],
                'status' => $status
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'exists' => false
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("AJAX Check Device Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd sprawdzania urządzenia'
    ]);
}
