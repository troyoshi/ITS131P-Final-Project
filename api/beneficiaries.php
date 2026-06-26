<?php
// api/beneficiaries.php — CRUD for beneficiaries
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match(true) {
    $method === 'GET'  && $action === 'list'    => listBeneficiaries(),
    $method === 'GET'  && $action === 'get'     => getBeneficiary($id),
    $method === 'GET'  && $action === 'stats'   => getStats(),
    $method === 'POST' && $action === 'create'  => createBeneficiary(),
    $method === 'POST' && $action === 'update'  => updateBeneficiary($id),
    $method === 'POST' && $action === 'delete'  => deleteBeneficiary($id),
    default => jsonError('Unknown action', 404),
};

// ---- LIST (with search + filter + pagination) ----
function listBeneficiaries(): void {
    $db      = getDB();
    $search  = trim($_GET['search']  ?? '');
    $status  = trim($_GET['status']  ?? '');
    $center  = trim($_GET['center']  ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = max(5, min(100, (int)($_GET['limit'] ?? 10)));
    $offset  = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = "(b.first_name LIKE ? OR b.last_name LIKE ? OR b.beneficiary_code LIKE ? OR b.barangay LIKE ?)";
        $like = "%$search%";
        array_push($params, $like, $like, $like, $like);
    }
    if ($status) { $where[] = 'b.status = ?';      $params[] = $status; }
    if ($center) { $where[] = 'e.center_name LIKE ?'; $params[] = "%$center%"; }

    $whereSQL = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM beneficiaries b JOIN evacuation_centers e ON b.center_id=e.center_id WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT b.beneficiary_id, b.beneficiary_code,
                CONCAT(b.first_name,' ',b.last_name) AS full_name,
                b.first_name, b.last_name, b.household_size,
                b.address, b.barangay, b.city, b.contact_no,
                b.status, b.notes,
                DATE_FORMAT(b.registered_at,'%b %d, %Y') AS registered_at,
                e.center_name, e.center_id,
                sn.need_label AS special_need, sn.need_id,
                CONCAT(u.first_name,' ',u.last_name) AS registered_by
         FROM beneficiaries b
         JOIN evacuation_centers  e  ON b.center_id    = e.center_id
         JOIN special_needs_types sn ON b.need_id      = sn.need_id
         JOIN users               u  ON b.registered_by = u.user_id
         WHERE $whereSQL
         ORDER BY b.registered_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    jsonSuccess([
        'data'        => $rows,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

// ---- GET SINGLE ----
function getBeneficiary(?int $id): void {
    if (!$id) jsonError('ID required.');
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT b.*, e.center_name, sn.need_label,
                CONCAT(u.first_name,' ',u.last_name) AS registered_by_name
         FROM beneficiaries b
         JOIN evacuation_centers  e  ON b.center_id    = e.center_id
         JOIN special_needs_types sn ON b.need_id      = sn.need_id
         JOIN users               u  ON b.registered_by = u.user_id
         WHERE b.beneficiary_id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Beneficiary not found.', 404);
    jsonSuccess(['data' => $row]);
}

// ---- STATS ----
function getStats(): void {
    $db = getDB();
    $stats = $db->query(
        "SELECT
            COUNT(*) AS total,
            SUM(status='Served')   AS served,
            SUM(status='Pending')  AS pending,
            SUM(status='Priority') AS priority,
            SUM(need_id != 1)      AS special_needs
         FROM beneficiaries"
    )->fetch();
    jsonSuccess(['data' => $stats]);
}

// ---- CREATE ----
function createBeneficiary(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['first_name','last_name','household_size','address','barangay','center_id'];
    foreach ($required as $f) {
        if (empty($body[$f])) jsonError("Field '$f' is required.");
    }

    $db = getDB();

    // Auto-generate code
    $max  = $db->query("SELECT MAX(beneficiary_id) FROM beneficiaries")->fetchColumn();
    $code = 'BEN-' . str_pad(($max + 1), 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare(
        "INSERT INTO beneficiaries
            (center_id, need_id, registered_by, beneficiary_code,
             first_name, last_name, middle_name, household_size,
             address, barangay, city, contact_no, status, notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
        (int)$body['center_id'],
        (int)($body['need_id']       ?? 1),
        currentUser()['id'],
        $code,
        trim($body['first_name']),
        trim($body['last_name']),
        trim($body['middle_name']    ?? '') ?: null,
        (int)$body['household_size'],
        trim($body['address']),
        trim($body['barangay']),
        trim($body['city']           ?? 'Quezon City'),
        trim($body['contact_no']     ?? '') ?: null,
        $body['status']              ?? 'Pending',
        trim($body['notes']          ?? '') ?: null,
    ]);

    jsonSuccess(['id' => $db->lastInsertId(), 'code' => $code], 'Beneficiary registered successfully.');
}

// ---- UPDATE ----
function updateBeneficiary(?int $id): void {
    if (!$id) jsonError('ID required.');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $db   = getDB();
    $stmt = $db->prepare(
        "UPDATE beneficiaries SET
            center_id=?, need_id=?, first_name=?, last_name=?, middle_name=?,
            household_size=?, address=?, barangay=?, city=?, contact_no=?,
            status=?, notes=?
         WHERE beneficiary_id=?"
    );
    $stmt->execute([
        (int)($body['center_id']     ?? 1),
        (int)($body['need_id']       ?? 1),
        trim($body['first_name']     ?? ''),
        trim($body['last_name']      ?? ''),
        trim($body['middle_name']    ?? '') ?: null,
        (int)($body['household_size']?? 1),
        trim($body['address']        ?? ''),
        trim($body['barangay']       ?? ''),
        trim($body['city']           ?? 'Quezon City'),
        trim($body['contact_no']     ?? '') ?: null,
        $body['status']              ?? 'Pending',
        trim($body['notes']          ?? '') ?: null,
        $id,
    ]);
    jsonSuccess([], 'Beneficiary updated.');
}

// ---- DELETE ----
function deleteBeneficiary(?int $id): void {
    if (!$id) jsonError('ID required.');
    // Only admins can delete
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    $db = getDB();
    $db->prepare('DELETE FROM beneficiaries WHERE beneficiary_id = ?')->execute([$id]);
    jsonSuccess([], 'Beneficiary deleted.');
}
