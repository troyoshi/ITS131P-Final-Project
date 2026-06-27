<?php
// api/beneficiaries.php

// ========== START SESSION FIRST ==========
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// ========== ERROR HANDLING ==========
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

// ========== REQUIRE CONFIG ==========
require_once __DIR__ . '/../includes/config.php';

// ========== AUTH CHECK ==========
requireLogin();

// ========== ROUTE DISPATCHER ==========
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

match (true) {
    $method === 'GET'  && $action === 'list'                    => listBeneficiaries(),
    $method === 'GET'  && $action === 'get'                     => getBeneficiary($id),
    $method === 'GET'  && $action === 'stats'                   => getStats(),
    $method === 'GET'  && $action === 'special_needs'           => getSpecialNeeds(),
    $method === 'GET'  && $action === 'by_center'               => getBeneficiariesByCenter(),
    $method === 'GET'  && $action === 'distribution_history'    => getBeneficiaryDistributionHistory($id),
    $method === 'POST' && $action === 'create'                  => createBeneficiary(),
    $method === 'POST' && $action === 'update'                  => updateBeneficiary($id),
    $method === 'POST' && $action === 'delete'                  => deleteBeneficiary($id),
    default => jsonError('Unknown action.', 404)
};


/* =========================================================
   SPECIAL NEEDS
========================================================= */

function getSpecialNeeds(): void
{
    $rows = getDB()
        ->query("SELECT * FROM special_needs_types ORDER BY need_id")
        ->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess(['data' => $rows]);
}


/* =========================================================
   LIST BENEFICIARIES
========================================================= */

function listBeneficiaries(): void
{
    $db = getDB();

    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $center = trim($_GET['center'] ?? '');

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(5, min(100, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $where = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = "(
            b.first_name LIKE ?
            OR b.last_name LIKE ?
            OR b.beneficiary_code LIKE ?
            OR b.barangay LIKE ?
        )";

        $like = "%{$search}%";
        array_push($params, $like, $like, $like, $like);
    }

    if ($status) {
        $where[] = "b.status = ?";
        $params[] = $status;
    }

    if ($center) {
        $where[] = "e.center_name LIKE ?";
        $params[] = "%{$center}%";
    }

    $whereSQL = implode(' AND ', $where);

    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM beneficiaries b
        LEFT JOIN evacuation_centers e
            ON b.center_id = e.center_id
        WHERE {$whereSQL}
    ");

    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT
            b.beneficiary_id,
            b.beneficiary_code,
            CONCAT(b.first_name,' ',b.last_name) AS full_name,
            b.first_name,
            b.last_name,
            b.household_size,
            b.address,
            b.barangay,
            b.city,
            b.contact_no,
            b.status,
            b.notes,
            DATE_FORMAT(b.registered_at,'%b %d, %Y') AS registered_at,
            e.center_id,
            e.center_name,
            sn.need_id,
            sn.need_label AS special_need,
            CONCAT(u.first_name,' ',u.last_name) AS registered_by
        FROM beneficiaries b
        LEFT JOIN evacuation_centers e
            ON b.center_id = e.center_id
        LEFT JOIN special_needs_types sn
            ON b.need_id = sn.need_id
        LEFT JOIN users u
            ON b.registered_by = u.user_id
        WHERE {$whereSQL}
        ORDER BY b.registered_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ");

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess([
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => max(1, ceil($total / $limit))
    ]);
}


/* =========================================================
   GET SINGLE BENEFICIARY
========================================================= */

function getBeneficiary(?int $id): void
{
    if (!$id) {
        jsonError('ID required.');
    }

    $db = getDB();

    $stmt = $db->prepare("
        SELECT
            b.*,
            e.center_name,
            sn.need_id,
            sn.need_label,
            CONCAT(u.first_name,' ',u.last_name) AS registered_by_name
        FROM beneficiaries b
        LEFT JOIN evacuation_centers e
            ON b.center_id = e.center_id
        LEFT JOIN special_needs_types sn
            ON b.need_id = sn.need_id
        LEFT JOIN users u
            ON b.registered_by = u.user_id
        WHERE b.beneficiary_id = ?
    ");

    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        jsonError('Beneficiary not found.', 404);
    }

    jsonSuccess(['data' => $row]);
}


/* =========================================================
   GET BENEFICIARIES BY CENTER
   NEW FUNCTION - Used in inventory distribution
========================================================= */

function getBeneficiariesByCenter(): void
{
    $centerId = (int)($_GET['center_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 1000);

    if (!$centerId) {
        jsonError('center_id is required.');
    }

    $db = getDB();

    $stmt = $db->prepare("
        SELECT
            b.beneficiary_id,
            b.beneficiary_code,
            CONCAT(b.first_name,' ',b.last_name) AS full_name,
            b.household_size,
            b.status,
            e.center_name
        FROM beneficiaries b
        JOIN evacuation_centers e ON b.center_id = e.center_id
        WHERE b.center_id = ?
        ORDER BY b.last_name, b.first_name
        LIMIT ?
    ");

    $stmt->execute([$centerId, $limit]);

    jsonSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}


/* =========================================================
   GET BENEFICIARY DISTRIBUTION HISTORY
   NEW FUNCTION - Shows all items received by beneficiary
========================================================= */

function getBeneficiaryDistributionHistory(?int $beneficiaryId): void
{
    if (!$beneficiaryId) {
        $beneficiaryId = (int)($_GET['beneficiary_id'] ?? 0);
    }

    if (!$beneficiaryId) {
        jsonError('beneficiary_id is required.');
    }

    $limit = (int)($_GET['limit'] ?? 100);

    $db = getDB();

    $stmt = $db->prepare("
        SELECT
            dr.distribution_id,
            DATE_FORMAT(dr.distribution_date, '%b %d, %Y') AS distribution_date,
            di.quantity_given,
            i.item_name,
            i.unit,
            CONCAT(u.first_name, ' ', u.last_name) AS staff_name,
            e.center_name,
            dr.remarks,
            ic.category_name
        FROM distribution_records dr
        JOIN distribution_items di ON dr.distribution_id = di.distribution_id
        JOIN relief_items i ON di.item_id = i.item_id
        JOIN item_categories ic ON i.category_id = ic.category_id
        JOIN users u ON dr.distributed_by = u.user_id
        JOIN evacuation_centers e ON dr.center_id = e.center_id
        WHERE dr.beneficiary_id = ?
        ORDER BY dr.distribution_date DESC
        LIMIT ?
    ");

    $stmt->execute([$beneficiaryId, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        jsonSuccess(['data' => []], 'No distribution history found.');
    }

    jsonSuccess(['data' => $rows]);
}


/* =========================================================
   STATS
========================================================= */

function getStats(): void
{
    $db = getDB();

    $stats = $db->query("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'Served'), 0) AS served,
            COALESCE(SUM(status = 'Pending'), 0) AS pending,
            COALESCE(SUM(status = 'Priority'), 0) AS priority
        FROM beneficiaries
    ")->fetch(PDO::FETCH_ASSOC);

    jsonSuccess(['data' => $stats]);
}


/* =========================================================
   CREATE
========================================================= */

function createBeneficiary(): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $required = ['first_name', 'last_name', 'household_size', 'address', 'barangay', 'center_id'];

    foreach ($required as $field) {
        if (empty($body[$field])) {
            jsonError("Field '{$field}' is required.");
        }
    }

    $db = getDB();

    $max = $db->query("SELECT COALESCE(MAX(beneficiary_id), 0) FROM beneficiaries")->fetchColumn();
    $code = 'BEN-' . str_pad(($max + 1), 4, '0', STR_PAD_LEFT);

    $stmt = $db->prepare("
        INSERT INTO beneficiaries (
            center_id, need_id, registered_by, beneficiary_code,
            first_name, last_name, middle_name, household_size,
            address, barangay, city, contact_no, status, notes
        )
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $user = currentUser();

    $result = $stmt->execute([
        (int)$body['center_id'],
        (int)($body['need_id'] ?? 1),
        $user['id'],
        $code,
        trim($body['first_name']),
        trim($body['last_name']),
        !empty($body['middle_name']) ? trim($body['middle_name']) : null,
        (int)$body['household_size'],
        trim($body['address']),
        trim($body['barangay']),
        trim($body['city'] ?? 'Quezon City'),
        !empty($body['contact_no']) ? trim($body['contact_no']) : null,
        $body['status'] ?? 'Pending',
        !empty($body['notes']) ? trim($body['notes']) : null
    ]);

    if ($result) {
        jsonSuccess([
            'id' => $db->lastInsertId(),
            'code' => $code
        ], 'Beneficiary registered successfully.');
    } else {
        jsonError('Failed to create beneficiary.');
    }
}


/* =========================================================
   UPDATE
========================================================= */

function updateBeneficiary(?int $id): void
{
    if (!$id) {
        jsonError('ID required.');
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $db = getDB();

    $existingStmt = $db->prepare("SELECT * FROM beneficiaries WHERE beneficiary_id = ?");
    $existingStmt->execute([$id]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        jsonError('Beneficiary not found.', 404);
    }

    $stmt = $db->prepare("
        UPDATE beneficiaries SET
            center_id = ?,
            need_id = ?,
            first_name = ?,
            last_name = ?,
            middle_name = ?,
            household_size = ?,
            address = ?,
            barangay = ?,
            city = ?,
            contact_no = ?,
            status = ?,
            notes = ?
        WHERE beneficiary_id = ?
    ");

    $stmt->execute([
        $body['center_id'] ?? $existing['center_id'],
        $body['need_id'] ?? $existing['need_id'],
        $body['first_name'] ?? $existing['first_name'],
        $body['last_name'] ?? $existing['last_name'],
        $body['middle_name'] ?? $existing['middle_name'],
        $body['household_size'] ?? $existing['household_size'],
        $body['address'] ?? $existing['address'],
        $body['barangay'] ?? $existing['barangay'],
        $body['city'] ?? $existing['city'],
        $body['contact_no'] ?? $existing['contact_no'],
        $body['status'] ?? $existing['status'],
        $body['notes'] ?? $existing['notes'],
        $id
    ]);

    jsonSuccess([], 'Beneficiary updated.');
}


/* =========================================================
   DELETE
========================================================= */

function deleteBeneficiary(?int $id): void
{
    if (!$id) {
        jsonError('ID required.');
    }

    $user = currentUser();

    if ($user['role'] !== 'Administrator') {
        jsonError('Permission denied.', 403);
    }

    $db = getDB();
    $db->prepare("DELETE FROM beneficiaries WHERE beneficiary_id = ?")->execute([$id]);

    jsonSuccess([], 'Beneficiary deleted.');
}

?>
