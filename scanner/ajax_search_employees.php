<?php
// /scanner/ajax_search_employees.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Получаем параметры
$type = isset($_POST['type']) ? $_POST['type'] : 'nr';
$query = isset($_POST['query']) ? trim($_POST['query']) : '';

// Логируем запрос для отладки
error_log("AJAX Search - Type: $type, Query: $query");

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Za krótkie zapytanie', 'data' => []]);
    exit;
}

try {
    // Сначала проверим соединение и получим всех сотрудников для теста
    $testCount = fetchOne("SELECT COUNT(*) as cnt FROM Pracownicy");
    error_log("Total employees in DB: " . ($testCount ? $testCount['cnt'] : 0));
    
    // Более гибкий поиск - убираем фильтр по статусу если нет активных
    if ($type === 'nr') {
        $sql = "SELECT NR_PRACOWNIKA, IMIE, NAZWISKO, KOMORKA_ORG, NAZWA_KOMORKI_ORG, STATUS
                FROM Pracownicy 
                WHERE NR_PRACOWNIKA LIKE ? 
                LIMIT 10";
        $searchParam = $query . '%';
    } else {
        $sql = "SELECT NR_PRACOWNIKA, IMIE, NAZWISKO, KOMORKA_ORG, NAZWA_KOMORKI_ORG, STATUS
                FROM Pracownicy 
                WHERE NAZWISKO LIKE ? 
                LIMIT 10";
        $searchParam = '%' . $query . '%';
    }
    
    $employees = fetchAll($sql, [$searchParam]);
    
    error_log("Found employees: " . count($employees));
    
    // Фильтруем активных сотрудников (если есть поле STATUS)
    $activeEmployees = array_filter($employees, function($emp) {
        // Если статус NULL или пустой - считаем активным
        if (empty($emp['STATUS'])) return true;
        // Проверяем различные варианты статуса "активный"
        $activeStatuses = ['AKTYWNY', 'Aktywny', 'aktywny', 'ACTIVE', 'Active', 'active'];
        return in_array($emp['STATUS'], $activeStatuses);
    });
    
    // Если нет активных, показываем всех
    if (empty($activeEmployees)) {
        $activeEmployees = $employees;
    }
    
    echo json_encode([
        'success' => true,
        'data' => array_values($activeEmployees),
        'debug' => [
            'total_found' => count($employees),
            'active_filtered' => count($activeEmployees),
            'status_values' => array_unique(array_column($employees, 'STATUS'))
        ]
    ]);
    
} catch (Exception $e) {
    error_log("AJAX Search Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Błąd wyszukiwania: ' . $e->getMessage(),
        'data' => []
    ]);
}