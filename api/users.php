<?php
// api/users.php — User management & profile
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match(true) {
    $action === 'list'            => listUsers(),
    $action === 'get'             => getUser($id),
    $action === 'create'          => createUser(),
    $action === 'update'          => updateUser($id),
    $action === 'update_password' => updatePassword(),
    $action === 'toggle_active'   => toggleActive($id),
    $action === 'profile'         => getProfile(),
    $action === 'update_profile'  => updateProfile(),
    $action === 'roles'           => getRoles(),
    default => jsonError('Unknown action', 404),
};

function listUsers(): void {
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    $db     = getDB();
    $search = trim($_GET['search'] ?? '');
    $role   = trim($_GET['role']   ?? '');
    $where  = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $like    = "%$search%";
        array_push($params, $like, $like, $like, $like);
    }
    if ($role) { $where[] = 'r.role_name = ?'; $params[] = $role; }

    $stmt = $db->prepare(
        "SELECT u.user_id,
                CONCAT(u.first_name,' ',u.last_name) AS full_name,
                u.first_name, u.last_name, u.username, u.email,
                u.contact_no, u.department, u.is_active,
                r.role_name,
                DATE_FORMAT(u.created_at,'%b %d, %Y') AS created_at,
                DATE_FORMAT(u.last_login,'%b %d, %Y %H:%i') AS last_login
         FROM users u JOIN roles r ON u.role_id=r.role_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY r.role_id, u.last_name"
    );
    $stmt->execute($params);
    jsonSuccess(['data' => $stmt->fetchAll()]);
}

function getUser(?int $id): void {
    if (!$id) jsonError('ID required.');
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.middle_init,
                u.username, u.email, u.contact_no, u.department, u.is_active,
                r.role_id, r.role_name,
                DATE_FORMAT(u.created_at,'%b %d, %Y') AS created_at,
                DATE_FORMAT(u.last_login,'%b %d, %Y %H:%i') AS last_login
         FROM users u JOIN roles r ON u.role_id=r.role_id
         WHERE u.user_id=?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('User not found.', 404);
    jsonSuccess(['data' => $row]);
}

function createUser(): void {
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['first_name','last_name','username','email','password','role_id'];
    foreach ($required as $f) {
        if (empty($body[$f])) jsonError("Field '$f' is required.");
    }
    if (strlen($body['password']) < 8) jsonError('Password must be at least 8 characters.');

    $db = getDB();
    // Check uniqueness
    $dup = $db->prepare('SELECT user_id FROM users WHERE username=? OR email=? LIMIT 1');
    $dup->execute([$body['username'], $body['email']]);
    if ($dup->fetch()) jsonError('Username or email already exists.');

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $db->prepare(
        "INSERT INTO users (role_id, first_name, last_name, middle_init, username, email, password, contact_no, department)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([
        (int)$body['role_id'],
        trim($body['first_name']),
        trim($body['last_name']),
        trim($body['middle_init'] ?? '') ?: null,
        trim($body['username']),
        trim($body['email']),
        $hash,
        trim($body['contact_no']  ?? '') ?: null,
        trim($body['department']  ?? '') ?: null,
    ]);
    jsonSuccess(['id' => $db->lastInsertId()], 'User created successfully.');
}

function updateUser(?int $id): void {
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    if (!$id) jsonError('ID required.');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    getDB()->prepare(
        "UPDATE users SET role_id=?, first_name=?, last_name=?, middle_init=?,
                email=?, contact_no=?, department=?
         WHERE user_id=?"
    )->execute([
        (int)($body['role_id']    ?? 2),
        trim($body['first_name']  ?? ''),
        trim($body['last_name']   ?? ''),
        trim($body['middle_init'] ?? '') ?: null,
        trim($body['email']       ?? ''),
        trim($body['contact_no']  ?? '') ?: null,
        trim($body['department']  ?? '') ?: null,
        $id,
    ]);
    jsonSuccess([], 'User updated.');
}

function updatePassword(): void {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $current = $body['current_password'] ?? '';
    $new     = $body['new_password']     ?? '';
    $uid     = currentUser()['id'];

    if (!$current || !$new) jsonError('Both current and new password are required.');
    if (strlen($new) < 8)   jsonError('New password must be at least 8 characters.');

    $db   = getDB();
    $stmt = $db->prepare('SELECT password FROM users WHERE user_id=?');
    $stmt->execute([$uid]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) jsonError('Current password is incorrect.', 401);

    $db->prepare('UPDATE users SET password=? WHERE user_id=?')
       ->execute([password_hash($new, PASSWORD_BCRYPT), $uid]);
    jsonSuccess([], 'Password updated successfully.');
}

function toggleActive(?int $id): void {
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    if (!$id) jsonError('ID required.');
    $db   = getDB();
    $stmt = $db->prepare('SELECT is_active FROM users WHERE user_id=?');
    $stmt->execute([$id]);
    $current = (int)$stmt->fetchColumn();
    $db->prepare('UPDATE users SET is_active=? WHERE user_id=?')->execute([!$current, $id]);
    jsonSuccess(['is_active' => !$current], $current ? 'User deactivated.' : 'User activated.');
}

function getProfile(): void {
    $uid  = currentUser()['id'];
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.middle_init,
                u.username, u.email, u.contact_no, u.department,
                r.role_name,
                DATE_FORMAT(u.created_at,'%B %Y') AS member_since,
                DATE_FORMAT(u.last_login,'%b %d, %Y %H:%i') AS last_login,
                (SELECT COUNT(*) FROM distribution_records WHERE distributed_by=u.user_id) AS dist_count,
                (SELECT COUNT(*) FROM beneficiaries WHERE registered_by=u.user_id)         AS bene_count,
                (SELECT COUNT(*) FROM inventory_transactions WHERE user_id=u.user_id)       AS tx_count
         FROM users u JOIN roles r ON u.role_id=r.role_id
         WHERE u.user_id=?"
    );
    $stmt->execute([$uid]);
    jsonSuccess(['data' => $stmt->fetch()]);
}

function updateProfile(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid  = currentUser()['id'];
    getDB()->prepare(
        "UPDATE users SET first_name=?, last_name=?, middle_init=?, email=?, contact_no=?, department=?
         WHERE user_id=?"
    )->execute([
        trim($body['first_name']  ?? ''),
        trim($body['last_name']   ?? ''),
        trim($body['middle_init'] ?? '') ?: null,
        trim($body['email']       ?? ''),
        trim($body['contact_no']  ?? '') ?: null,
        trim($body['department']  ?? '') ?: null,
        $uid,
    ]);
    jsonSuccess([], 'Profile updated.');
}

function getRoles(): void {
    $rows = getDB()->query('SELECT * FROM roles ORDER BY role_id')->fetchAll();
    jsonSuccess(['data' => $rows]);
}
