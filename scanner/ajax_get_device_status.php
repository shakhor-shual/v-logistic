<?php
// /scanner/ajax_get_device_status.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

$nrPracownika = isset($_POST['nr_pracownika']) ? $_POST['nr_pracownika'] : '';
$kodUrzadzenia = isset($_POST['kod']) ? $_POST['kod'] : '';

if (empty($nrPracownika) && empty($kodUrzadzenia)) {
    echo json_encode(['success' => false, 'message' => 'Brak danych']);
    exit;
}

try {
    // Ищем устройство по номеру сотрудника или коду
    if ($nrPracownika) {
        $device = fetchOne(
            "SELECT KOD_REJ_URZ, TOKEN_DOSTEP_URZ, CZAS_UTW, OST_AKTYWNOSC 
             FROM ListaSkanerow 
             WHERE NR_PRACOWNIKA = ?",
            [$nrPracownika]
        );
    } else {
        $device = fetchOne(
            "SELECT KOD_REJ_URZ, TOKEN_DOSTEP_URZ, CZAS_UTW, OST_AKTYWNOSC, NR_PRACOWNIKA
             FROM ListaSkanerow 
             WHERE KOD_REJ_URZ = ?",
            [$kodUrzadzenia]
        );
    }
    
    if ($device) {
        // Определяем статус
        if ($device['TOKEN_DOSTEP_URZ'] === 'disabled') {
            $status = 'disabled';
            $statusText = 'Zablokowany';
            $statusIcon = '🔴';
            $statusClass = 'status-disabled';
        } elseif ($device['TOKEN_DOSTEP_URZ'] !== null && strlen($device['TOKEN_DOSTEP_URZ']) === 36) {
            $status = 'active';
            $statusText = 'Aktywny';
            $statusIcon = '🟢';
            $statusClass = 'status-active';
        } else {
            $status = 'pending';
            $statusText = 'Oczekuje na aktywację';
            $statusIcon = '🟡';
            $statusClass = 'status-pending';
        }
        
        // Дополнительная информация
        $response = [
            'success' => true,
            'exists' => true,
            'status' => $status,
            'statusText' => $statusText,
            'statusIcon' => $statusIcon,
            'statusClass' => $statusClass,
            'kod' => $device['KOD_REJ_URZ'],
            'token' => $device['TOKEN_DOSTEP_URZ'],
            'czas_utw' => $device['CZAS_UTW'],
            'ost_aktywnosc' => $device['OST_AKTYWNOSC']
        ];
        
        // Если есть сотрудник, добавим его данные
        if ($device['NR_PRACOWNIKA']) {
            $pracownik = fetchOne(
                "SELECT IMIE, NAZWISKO FROM Pracownicy WHERE NR_PRACOWNIKA = ?",
                [$device['NR_PRACOWNIKA']]
            );
            if ($pracownik) {
                $response['pracownik'] = $pracownik['IMIE'] . ' ' . $pracownik['NAZWISKO'];
            }
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'status' => 'none',
            'statusText' => 'Nie zarejestrowano',
            'statusIcon' => '⚪',
            'statusClass' => 'status-none'
        ]);
    }
    
} catch (Exception $e) {
    error_log("AJAX Get Status Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd sprawdzania statusu'
    ]);
}