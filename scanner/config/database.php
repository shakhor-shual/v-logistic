<?php
// config/database.php - Database configuration and connection

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load database credentials from JSON file
$credentialsFile = __DIR__ . '/credentials_db.json';

if (!file_exists($credentialsFile)) {
    throw new Exception('Database credentials file not found: ' . $credentialsFile);
}

$credentials = json_decode(file_get_contents($credentialsFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception('Invalid JSON in credentials file: ' . json_last_error_msg());
}

// Define database credentials from loaded file
define('DB_HOST', $credentials['host'] ?? '');
define('DB_NAME', $credentials['database'] ?? '');
define('DB_USER', $credentials['username'] ?? '');
define('DB_PASS', $credentials['password'] ?? '');
define('DB_CHARSET', $credentials['charset'] ?? 'utf8mb4');

// Validate required credentials
if (empty(DB_HOST) || empty(DB_NAME) || empty(DB_USER)) {
    throw new Exception('Missing required database credentials');
}

/**
 * Get database connection
 * @return PDO
 * @throws PDOException
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            // Log error and rethrow
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed", 0, $e);
        }
    }
    
    return $pdo;
}

/**
 * Execute query with parameters
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function executeQuery($sql, $params = []) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get single row
 * @param string $sql
 * @param array $params
 * @return array|false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Get all rows
 * @param string $sql
 * @param array $params
 * @return array
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Send JSON response
 * @param array $data
 * @param int $statusCode
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send error response
 * @param string $message
 * @param int $statusCode
 */
function errorResponse($message, $statusCode = 400) {
    jsonResponse([
        'status' => 'error',
        'message' => $message
    ], $statusCode);
}

/**
 * Send success response
 * @param array $data
 */
function successResponse($data = []) {
    jsonResponse(array_merge(['status' => 'success'], $data));
}
