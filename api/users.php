<?php
// api/users.php — User management & profile
// Plaintext passwords (no hashing)

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
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
        $action === 'user_activity'   => getUserActivity(),
        default => jsonError('Unknown action', 404),
    };
} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}

// ============================================================
// USER MANAGEMENT FUNCTIONS
// ============================================================

function listUsers(): void {
    if (currentUser()['role'] !== 'Administrator') {
        jsonError('Permission denied.', 403);
        return;
    }
    
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
    if ($role) { 
        $where[] = 'r.role_name = ?'; 
        $params[] = $role; 
    }

    $stmt = $db->prepare(
        "SELECT u.user_id,
                CONCAT(u.first_name,' ',u.last_name) AS full_name,
                u.first_name, u.last_name, u.username, u.email,
                u.contact_no, u.department, u.is_active,
                r.role_name,
                DATE_FORMAT(u.created_at,'%b %d, %Y') AS created_at,
                DATE_FORMAT(u.last_login,'%b %d, %Y %H:%i') AS last_login
         FROM users u 
         JOIN roles r ON u.role_id=r.role_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY r.role_id, u.last_name"
    );
    $stmt->execute($params);
    jsonSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getUser(?int $id): void {
    if (!$id) {
        jsonError('ID required.');
        return;
    }
    
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT u.user_id, u.first_name, u.last_name, u.middle_init,
                u.username, u.email, u.contact_no, u.department, u.is_active,
                r.role_id, r.role_name,
                DATE_FORMAT(u.created_at,'%b %d, %Y') AS created_at,
                DATE_FORMAT(u.last_login,'%b %d, %Y %H:%i') AS last_login
         FROM users u 
         JOIN roles r ON u.role_id=r.role_id
         WHERE u.user_id=?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonError('User not found.', 404);
        return;
    }
    jsonSuccess(['data' => $row]);
}

function createUser(): void {
    if (currentUser()['role'] !== 'Administrator') {
        jsonError('Permission denied.', 403);
        return;
    }
    
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['first_name','last_name','username','email','password','role_id'];
    
    foreach ($required as $f) {
        if (empty($body[$f])) {
            jsonError("Field '$f' is required.");
            return;
        }
    }
    
    if (strlen($body['password']) < 4) {
        jsonError('Password must be at least 4 characters.');
        return;
    }

    $db = getDB();
    
    // Check uniqueness
    $dup = $db->prepare('SELECT user_id FROM users WHERE username=? OR email=? LIMIT 1');
    $dup->execute([$body['username'], $body['email']]);
    if ($dup->fetch()) {
        jsonError('Username or email already exists.');
        return;
    }

    // Store plaintext password
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
        trim($body['password']),
        trim($body['contact_no']  ?? '') ?: null,
        trim($body['department']  ?? '') ?: null,
    ]);
    
    $newId = $db->lastInsertId();
    jsonSuccess(['id' => $newId], 'User created successfully.');
}

function updateUser(?int $id): void {
    if (currentUser()['role'] !== 'Administrator') {
        jsonError('Permission denied.', 403);
        return;
    }
    
    if (!$id) {
        jsonError('ID required.');
        return;
    }
    
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
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // Get and trim passwords
    $current = trim($body['current_password'] ?? '');
    $new = trim($body['new_password'] ?? '');
    $uid = currentUser()['id'];

    // Validation - check if empty after trim
    if (empty($current)) {
        jsonError('Current password is required.');
        return;
    }
    
    if (empty($new)) {
        jsonError('New password is required.');
        return;
    }
    
    if (strlen($new) < 4) {
        jsonError('New password must be at least 4 characters.');
        return;
    }

    if ($current === $new) {
        jsonError('New password must be different from current password.', 400);
        return;
    }

    try {
        $db = getDB();
        
        // Get user's current password from database
        $stmt = $db->prepare('SELECT user_id, password FROM users WHERE user_id = ?');
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            jsonError('User account not found.', 404);
            return;
        }
        
        // Trim stored password and compare
        $storedPassword = trim($row['password']);
        
        // Direct string comparison (plaintext)
        if ($current !== $storedPassword) {
            jsonError('Current password is incorrect.', 401);
            return;
        }
        
        // Update to new password
        $updateStmt = $db->prepare('UPDATE users SET password = ? WHERE user_id = ?');
        $success = $updateStmt->execute([$new, $uid]);
        
        if (!$success) {
            jsonError('Failed to update password.', 500);
            return;
        }
        
        jsonSuccess([], 'Password updated successfully.');
        
    } catch (PDOException $e) {
        error_log('Password update error: ' . $e->getMessage());
        jsonError('Database error: ' . $e->getMessage(), 500);
    } catch (Exception $e) {
        error_log('Unexpected error in updatePassword: ' . $e->getMessage());
        jsonError('Unexpected error: ' . $e->getMessage(), 500);
    }
}

function toggleActive(?int $id): void {
    if (currentUser()['role'] !== 'Administrator') {
        jsonError('Permission denied.', 403);
        return;
    }
    
    if (!$id) {
        jsonError('ID required.');
        return;
    }
    
    $db   = getDB();
    $stmt = $db->prepare('SELECT is_active FROM users WHERE user_id=?');
    $stmt->execute([$id]);
    $current = (int)$stmt->fetchColumn();
    $newStatus = !$current;
    
    $db->prepare('UPDATE users SET is_active=? WHERE user_id=?')
       ->execute([$newStatus, $id]);
    
    jsonSuccess(['is_active' => $newStatus], $current ? 'User deactivated.' : 'User activated.');
}

// ============================================================
// PROFILE FUNCTIONS
// ============================================================

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
                (SELECT COUNT(*) FROM inventory_transactions WHERE user_id=u.user_id) AS inv_count,
                (SELECT COUNT(*) FROM beneficiaries WHERE registered_by=u.user_id) AS bene_count
         FROM users u 
         JOIN roles r ON u.role_id=r.role_id
         WHERE u.user_id=?"
    );
    
    $stmt->execute([$uid]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        jsonError('Profile not found.', 404);
        return;
    }
    
    jsonSuccess(['data' => $data]);
}

function updateProfile(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid  = currentUser()['id'];
    
    // Validate required fields
    if (empty($body['first_name']) || empty($body['last_name'])) {
        jsonError('First name and last name are required.');
        return;
    }
    
    if (empty($body['email'])) {
        jsonError('Email is required.');
        return;
    }
    
    $db = getDB();
    $stmt = $db->prepare(
        "UPDATE users SET first_name=?, last_name=?, middle_init=?, email=?, contact_no=?, department=?
         WHERE user_id=?"
    );
    
    $success = $stmt->execute([
        trim($body['first_name']),
        trim($body['last_name']),
        trim($body['middle_init'] ?? '') ?: null,
        trim($body['email']),
        trim($body['contact_no'] ?? '') ?: null,
        trim($body['department'] ?? '') ?: null,
        $uid,
    ]);
    
    if (!$success) {
        jsonError('Failed to update profile.', 500);
        return;
    }
    
    jsonSuccess([], 'Profile updated successfully.');
}

// ============================================================
// ROLES FUNCTION
// ============================================================

function getRoles(): void {
    $rows = getDB()->query('SELECT * FROM roles ORDER BY role_id')->fetchAll(PDO::FETCH_ASSOC);
    jsonSuccess(['data' => $rows]);
}

// ============================================================
// ACTIVITY LOG FUNCTION
// ============================================================

function getUserActivity(): void {
    try {
        $uid = currentUser()['id'];
        $db  = getDB();
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        
        // Fetch all activities from multiple tables
        $stmt = $db->prepare(
            "SELECT 'distribution' AS action,
                    CONCAT('Distributed relief items to ', b.first_name, ' ', b.last_name) AS description,
                    DATE_FORMAT(dr.distribution_date,'%b %d, %Y') AS timestamp
             FROM distribution_records dr
             JOIN beneficiaries b ON dr.beneficiary_id = b.beneficiary_id
             WHERE dr.distributed_by = ?
             
             UNION ALL
             
             SELECT 'inventory' AS action,
                    CONCAT(it.transaction_type, ' - ', i.item_name, ' (', it.quantity, ' ', i.unit, ')') AS description,
                    DATE_FORMAT(it.transaction_date,'%b %d, %Y') AS timestamp
             FROM inventory_transactions it
             JOIN relief_items i ON it.item_id = i.item_id
             WHERE it.user_id = ?
             
             UNION ALL
             
             SELECT 'beneficiary' AS action,
                    CONCAT('Registered beneficiary: ', b.first_name, ' ', b.last_name) AS description,
                    DATE_FORMAT(b.registered_at,'%b %d, %Y %H:%i') AS timestamp
             FROM beneficiaries b
             WHERE b.registered_by = ?
             
             ORDER BY timestamp DESC
             LIMIT ?"
        );
        
        $stmt->execute([$uid, $uid, $uid, $limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonSuccess(['data' => $activities]);
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
        jsonError('Error loading activity: ' . $e->getMessage(), 500);
    }
}

?>
