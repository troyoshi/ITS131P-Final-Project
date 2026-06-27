<?php
// api/distributions.php — Distribution records CRUD
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$beneficiaryId = isset($_GET['beneficiary_id']) ? (int)$_GET['beneficiary_id'] : null;

match(true) {
    $action === 'list'                   => listDistributions(),
    $action === 'get'                    => getDistribution($id),
    $action === 'beneficiary_history'    => getBeneficiaryDonationHistory($beneficiaryId),
    $action === 'create'                 => createDistribution(),
    $action === 'delete'                 => deleteDistribution($id),
    default => jsonError('Unknown action', 404),
};

function listDistributions(): void {
    $db     = getDB();
    $search = trim($_GET['search']    ?? '');
    $center = (int)($_GET['center_id'] ?? 0);
    $from   = $_GET['date_from']      ?? '';
    $to     = $_GET['date_to']        ?? '';
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = max(5, min(100, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = "(b.first_name LIKE ? OR b.last_name LIKE ? OR b.beneficiary_code LIKE ?)";
        $like    = "%$search%";
        array_push($params, $like, $like, $like);
    }
    if ($center) { $where[] = 'dr.center_id = ?';            $params[] = $center; }
    if ($from)   { $where[] = 'dr.distribution_date >= ?';   $params[] = $from;   }
    if ($to)     { $where[] = 'dr.distribution_date <= ?';   $params[] = $to;     }

    $whereSQL = implode(' AND ', $where);

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM distribution_records dr
         JOIN beneficiaries b ON dr.beneficiary_id=b.beneficiary_id
         WHERE $whereSQL"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT dr.distribution_id,
                DATE_FORMAT(dr.distribution_date,'%b %d, %Y') AS dist_date,
                dr.distribution_date,
                b.beneficiary_code,
                CONCAT(b.first_name,' ',b.last_name) AS beneficiary_name,
                b.household_size,
                e.center_name,
                CONCAT(u.first_name,' ',u.last_name) AS staff_name,
                dr.remarks,
                GROUP_CONCAT(CONCAT(i.item_name,' x',di.quantity_given)
                    ORDER BY i.item_name SEPARATOR '; ') AS items_given
         FROM distribution_records dr
         JOIN beneficiaries      b  ON dr.beneficiary_id=b.beneficiary_id
         JOIN evacuation_centers e  ON dr.center_id=e.center_id
         JOIN users              u  ON dr.distributed_by=u.user_id
         LEFT JOIN distribution_items di ON di.distribution_id=dr.distribution_id
         LEFT JOIN relief_items  i  ON di.item_id=i.item_id
         WHERE $whereSQL
         GROUP BY dr.distribution_id
         ORDER BY dr.distribution_date DESC, dr.distribution_id DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);

    jsonSuccess([
        'data'        => $stmt->fetchAll(),
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => (int)ceil($total / $limit),
    ]);
}

function getDistribution(?int $id): void {
    if (!$id) jsonError('ID required.');
    $db   = getDB();

    $header = $db->prepare(
        "SELECT dr.*,
                DATE_FORMAT(dr.distribution_date,'%b %d, %Y') AS dist_date,
                b.beneficiary_code,
                CONCAT(b.first_name,' ',b.last_name) AS beneficiary_name,
                b.household_size,
                e.center_name,
                CONCAT(u.first_name,' ',u.last_name) AS staff_name
         FROM distribution_records dr
         JOIN beneficiaries      b ON dr.beneficiary_id=b.beneficiary_id
         JOIN evacuation_centers e ON dr.center_id=e.center_id
         JOIN users              u ON dr.distributed_by=u.user_id
         WHERE dr.distribution_id=?"
    );
    $header->execute([$id]);
    $row = $header->fetch();
    if (!$row) jsonError('Distribution not found.', 404);

    $items = $db->prepare(
        "SELECT di.quantity_given, i.item_name, i.unit
         FROM distribution_items di JOIN relief_items i ON di.item_id=i.item_id
         WHERE di.distribution_id=?"
    );
    $items->execute([$id]);
    $row['items'] = $items->fetchAll();

    jsonSuccess(['data' => $row]);
}

/* =========================================================
   GET BENEFICIARY DONATION HISTORY — NEW FUNCTION
========================================================= */

function getBeneficiaryDonationHistory(?int $beneficiaryId): void
{
    if (!$beneficiaryId) {
        jsonError('Beneficiary ID required.');
    }

    $db = getDB();

    $stmt = $db->prepare(
        "SELECT 
            DATE_FORMAT(dr.distribution_date,'%b %d, %Y') AS distribution_date,
            di.quantity_given,
            i.item_name,
            i.unit,
            CONCAT(u.first_name,' ',u.last_name) AS staff_name,
            e.center_name
        FROM distribution_records dr
        JOIN distribution_items di ON dr.distribution_id = di.distribution_id
        JOIN relief_items i ON di.item_id = i.item_id
        JOIN users u ON dr.distributed_by = u.user_id
        JOIN evacuation_centers e ON dr.center_id = e.center_id
        WHERE dr.beneficiary_id = ?
        ORDER BY dr.distribution_date DESC
        LIMIT 50"
    );

    $stmt->execute([$beneficiaryId]);
    $rows = $stmt->fetchAll();

    jsonSuccess(['data' => $rows]);
}

function createDistribution(): void {
    $body          = json_decode(file_get_contents('php://input'), true) ?? [];
    $beneficiaryId = (int)($body['beneficiary_id']   ?? 0);
    $centerId      = (int)($body['center_id']         ?? 0);
    $date          = $body['distribution_date']        ?? date('Y-m-d');
    $remarks       = trim($body['remarks']             ?? '');
    $items         = $body['items']                    ?? [];   // [{item_id, quantity}]

    if (!$beneficiaryId || !$centerId) jsonError('beneficiary_id and center_id are required.');
    if (empty($items))                 jsonError('At least one item must be included.');

    $db = getDB();
    $db->beginTransaction();
    try {
        // Insert distribution header
        $db->prepare(
            "INSERT INTO distribution_records (beneficiary_id, center_id, distributed_by, distribution_date, remarks)
             VALUES (?,?,?,?,?)"
        )->execute([$beneficiaryId, $centerId, currentUser()['id'], $date, $remarks ?: null]);
        $distId = $db->lastInsertId();

        foreach ($items as $item) {
            $itemId = (int)($item['item_id']  ?? 0);
            $qty    = (int)($item['quantity'] ?? 0);
            if (!$itemId || $qty <= 0) continue;

            // Check stock
            $stock = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id=?');
            $stock->execute([$itemId]);
            $current = (int)$stock->fetchColumn();
            if ($current < $qty) {
                $db->rollBack();
                $name = $db->prepare('SELECT item_name FROM relief_items WHERE item_id=?');
                $name->execute([$itemId]);
                jsonError("Insufficient stock for '{$name->fetchColumn()}'. Available: $current.");
            }

            // Insert line item
            $db->prepare(
                "INSERT INTO distribution_items (distribution_id, item_id, quantity_given) VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE quantity_given=?"
            )->execute([$distId, $itemId, $qty, $qty]);

            // Deduct from inventory
            $db->prepare("UPDATE relief_items SET current_stock=current_stock-? WHERE item_id=?")
               ->execute([$qty, $itemId]);

            // Log transaction
            $db->prepare(
                "INSERT INTO inventory_transactions (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
                 VALUES (?,?,'OUT',?,?,?)"
            )->execute([$itemId, currentUser()['id'], $qty, "Distribution ID: $distId", $date]);
        }

        // Mark beneficiary as Served
        $db->prepare("UPDATE beneficiaries SET status='Served' WHERE beneficiary_id=?")
           ->execute([$beneficiaryId]);

        $db->commit();
        jsonSuccess(['distribution_id' => $distId], 'Distribution recorded successfully.');
    } catch (\Throwable $e) {
        $db->rollBack();
        jsonError('Failed to save distribution: ' . $e->getMessage(), 500);
    }
}

function deleteDistribution(?int $id): void {
    if (!$id) jsonError('ID required.');
    if (currentUser()['role'] !== 'Administrator') jsonError('Permission denied.', 403);
    getDB()->prepare('DELETE FROM distribution_records WHERE distribution_id=?')->execute([$id]);
    jsonSuccess([], 'Distribution record deleted.');
}
