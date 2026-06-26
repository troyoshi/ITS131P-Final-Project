<?php
// api/auth.php — Login / Logout
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

match($action) {
    'login'  => handleLogin(),
    'logout' => handleLogout(),
    'me'     => handleMe(),
    default  => jsonError('Unknown action', 404),
};

// ---- Login ----
function handleLogin(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST required', 405);

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $username = trim($body['username'] ?? '');
    $password = $body['password'] ?? '';
    $role     = $body['role']     ?? '';   // optional filter from front-end

    if (!$username || !$password) jsonError('Username and password are required.');

    $legacyDemoMap = [
        'volunteer' => ['username' => 'francis.go', 'password' => 'Password123!'],
        'admin'     => ['username' => 'juan.admin', 'password' => 'Password123!'],
    ];

    if (isset($legacyDemoMap[strtolower($username)])) {
        $legacy = $legacyDemoMap[strtolower($username)];
        if ($password === 'volunteer123' || $password === $legacy['password']) {
            $username = $legacy['username'];
            $password = $legacy['password'];
        }
    }

    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT u.user_id, u.username, u.first_name, u.last_name, u.password, u.is_active,
                r.role_name
         FROM users u
         JOIN roles r ON u.role_id = r.role_id
         WHERE (u.username = ? OR u.email = ?)
         LIMIT 1'
    );
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonError('Invalid username or password.', 401);
    }
    if (!$user['is_active']) {
        jsonError('Your account has been deactivated. Contact the administrator.', 403);
    }

    // Update last_login
    $db->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
       ->execute([$user['user_id']]);

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['username']  = $user['username'] ?? $username;
    $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['role']      = $user['role_name'];

    jsonSuccess([
        'user' => [
            'id'       => $user['user_id'],
            'name'     => $_SESSION['full_name'],
            'role'     => $user['role_name'],
            'redirect' => 'dashboard.html',
        ]
    ], 'Login successful.');
}

// ---- Logout ----
function handleLogout(): void {
    session_unset();
    session_destroy();
    jsonSuccess([], 'Logged out.');
}

// ---- Current user ----
function handleMe(): void {
    if (empty($_SESSION['user_id'])) jsonError('Not authenticated.', 401);
    jsonSuccess(['user' => currentUser()]);
}
