<?php
// ============================================================
//  CDRC Relief Tracker — Database Configuration
//  File: includes/config.php
//  Adjust DB_HOST, DB_NAME, DB_USER, DB_PASS for your server
// ============================================================

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3307');
define('DB_NAME', 'cdrc_relief_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');          // default XAMPP password is empty
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'CDRC Relief Tracker');
$siteUrl = getenv('CDRC_SITE_URL');
define('SITE_URL', $siteUrl !== false && $siteUrl !== '' ? $siteUrl : 'http://localhost/ITS131P-Final-Project');

// ---- PDO Connection (singleton) ----
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ---- Session helper ----
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
            http_response_code(401);
            die(json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']));
        }
        header('Location: ' . SITE_URL . '/pages/login.html');
        exit;
    }
}

function currentUser(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return [
        'id'       => $_SESSION['user_id']   ?? null,
        'username' => $_SESSION['username']  ?? '',
        'name'     => $_SESSION['full_name'] ?? '',
        'role'     => $_SESSION['role']      ?? '',
    ];
}

// ---- JSON helpers ----
function jsonSuccess(array $data = [], string $message = 'Success'): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}
function jsonError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}
