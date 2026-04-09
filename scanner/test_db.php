<?php
// /scanner/test_db.php
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test połączenia z bazą danych</h1>";

// Проверяем таблицу Pracownicy
try {
    $pracownicy = fetchAll("SELECT NR_PRACOWNIKA, IMIE, NAZWISKO, STATUS FROM Pracownicy LIMIT 5");
    
    echo "<h2>✅ Połączenie z bazą działa!</h2>";
    echo "<h3>Przykładowi pracownicy:</h3>";
    echo "<ul>";
    foreach ($pracownicy as $p) {
        echo "<li>{$p['NR_PRACOWNIKA']} - {$p['IMIE']} {$p['NAZWISKO']} (STATUS: {$p['STATUS']})</li>";
    }
    echo "</ul>";
    
    // Проверяем статусы
    $statusy = fetchAll("SELECT DISTINCT STATUS FROM Pracownicy");
    echo "<h3>Dostępne statusy w tabeli:</h3>";
    echo "<ul>";
    foreach ($statusy as $s) {
        echo "<li>'{$s['STATUS']}'</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Błąd połączenia: " . $e->getMessage() . "</h2>";
}
?>
