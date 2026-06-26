<?php
// api/centers.php — Evacuation center CRUD
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match(true) {
    $action === 'list'   => listCenters(),
    $action === 'get'    => getCenter($id),
    $action === 'create' => createCenter(),
    $action === 'update' => updateCenter($id),
    $action === 'delete' => deleteCenter($id),
    default => jsonError('Unknown action', 404),
};

function listCenters(): void {
    $rows = getDB()->query(
        "SELECT e.*,
                ROUND(e.current_occupancy / e.max_capacity * 100, 1) AS occupancy_pct,
                (SELECT COUNT(*) FROM beneficiaries b WHERE b.center_id=e.center_id)            AS total_beneficiaries,
                (SELECT COUNT(*) FROM distribution_records dr WHERE dr.center_id=e.center_id)   AS total_distributions
         FROM evacuation_centers e
         ORDER BY e.status, e.center_name"
    )->fetchAll();
    jsonSuccess(['data' => $rows]);
}

function getCenter(?int $id): void {
    if (!$id) jsonError('ID required.');
    $stmt = getDB()->prepare(
        "SELECT e.*,
                ROUND(e.current_occupancy/e.max_capacity*100,1) AS occupancy_pct,
                (SELECT COUNT(*) FROM beneficiaries WHERE center_id=e.center_id) AS beneficiary_count
         FROM evacuation_centers e WHERE e.center_id=?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Center not found.', 404);
    jsonSuccess(['data' => $row]);
}

function createCenter(): void {
    if (currentUser()['role'] === 'Volunteer') jsonError('Permission denied.', 403);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($body['center_name']) || empty($body['barangay'])) jsonError('center_name and barangay required.');
    $db = getDB();
    $db->prepare(
        "INSERT INTO evacuation_centers
            (center_name, barangay, city, province, max_capacity, current_occupancy, status, contact_person, contact_no)
         VALUES (?,?,?,?,?,?,?,?,?)"
    )->execute([
        trim($body['center_name']),
        trim($body['barangay']),
        trim($body['city']             ?? 'Quezon City'),
        trim($body['province']         ?? 'Metro Manila'),
        (int)($body['max_capacity']    ?? 0),
        (int)($body['current_occupancy'] ?? 0),
        $body['status']                ?? 'Active',
        trim($body['contact_person']   ?? '') ?: null,
        trim($body['contact_no']       ?? '') ?: null,
    ]);
    jsonSuccess(['id' => $db->lastInsertId()], 'Center created.');
}

function updateCenter(?int $id): void {
    if (!$id) jsonError('ID required.');
    if (currentUser()['role'] === 'Volunteer') jsonError('Permission denied.', 403);
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    getDB()->prepare(
        "UPDATE evacuation_centers SET
            center_name=?, barangay=?, city=?, province=?,
            max_capacity=?, current_occupancy=?, status=?,
            contact_person=?, contact_no=?
         WHERE center_id=?"
    )->execute([
        trim($body['center_name']),
        trim($body['barangay']),
        trim($body['city']               ?? 'Quezon City'),
        trim($body['province']           ?? 'Metro Manila'),
        (int)($body['max_capacity']      ?? 0),
        (int)($body['current_occupancy'] ?? 0),
        $body['status']                  ?? 'Active',
        trim($body['contact_person']     ?? '') ?: null,
        trim($body['contact_no']         ?? '') ?: null,
        $id,
    ]);
    jsonSuccess([], 'Center updated.');
}

function deleteCenter(?int $id): void {
    if (!$id) jsonError('ID required.');
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    $check = getDB()->prepare('SELECT COUNT(*) FROM beneficiaries WHERE center_id=?');
    $check->execute([$id]);
    if ((int)$check->fetchColumn() > 0) jsonError('Cannot delete — beneficiaries are assigned to this center.');
    getDB()->prepare('DELETE FROM evacuation_centers WHERE center_id=?')->execute([$id]);
    jsonSuccess([], 'Center deleted.');
}
