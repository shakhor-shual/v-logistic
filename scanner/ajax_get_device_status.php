<?php
// /scanner/ajax_get_device_status.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$nrPracownika = isset($_POST['nr_pracownika']) ? trim($_POST['nr_pracownika']) : '';
$kodUrzadzenia = isset($_POST['kod']) ? trim($_POST['kod']) : '';

if (empty($nrPracownika) && empty($kodUrzadzenia)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Brak danych',
        'debug' => ['nr' => $nrPracownika, 'kod' => $kodUrzadzenia]
    ]);
    exit;
}

try {
    // Ищем устройство
    if (!empty($nrPracownika)) {
        $device = fetchOne(
            "SELECT KOD_REJ_URZ, TOKEN_DOSTEP_URZ, CZAS_UTW, OST_AKTYWNOSC, NR_PRACOWNIKA 
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
    
    // Логируем результат для отладки
    error_log("Device status check - NR: $nrPracownika, Found: " . ($device ? 'YES' : 'NO'));
    if ($device) {
        error_log("Token value: " . ($device['TOKEN_DOSTEP_URZ'] ?? 'NULL'));
    }
    
    if ($device) {
        // Определяем статус
        $token = $device['TOKEN_DOSTEP_URZ'];
        
        if ($token === 'disabled') {
            $status = 'disabled';
            $statusText = 'Zablokowany';
            $statusIcon = '🔴';
            $statusClass = 'status-disabled';
        } elseif ($token !== null && strlen($token) == 36) {
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
        
        $response = [
            'success' => true,
            'exists' => true,
            'status' => $status,
            'statusText' => $statusText,
            'statusIcon' => $statusIcon,
            'statusClass' => $statusClass,
            'kod' => $device['KOD_REJ_URZ'],
            'token' => $token,
            'czas_utw' => $device['CZAS_UTW'],
            'ost_aktywnosc' => $device['OST_AKTYWNOSC'],
            'debug_token' => $token // Для отладки
        ];
        
        // Добавляем информацию о сотруднике
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
        'message' => 'Błąd sprawdzania statusu: ' . $e->getMessage()
    ]);
}