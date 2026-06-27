<?php
// api/inventory.php — Inventory Management CRUD
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match(true) {
    $action === 'list'           => listInventory(),
    $action === 'categories'     => listCategories(),
    $action === 'stock_in'       => recordStockIn(),
    $action === 'stock_out'      => recordStockOut(),
    $action === 'edit_stock'     => editStockAmount(),
    $action === 'history'        => getItemHistory(),
    default => jsonError('Unknown action', 404),
};

// ============================================================
// List Inventory with Filters
// ============================================================
function listInventory(): void {
    $db     = getDB();
    $search = trim($_GET['search']    ?? '');
    $catId  = (int)($_GET['category_id'] ?? 0);

    $where  = ['1=1'];
    $params = [];

    if ($search) {
        $where[] = "i.item_name LIKE ?";
        $params[] = "%$search%";
    }

    if ($catId) {
        $where[] = "i.category_id = ?";
        $params[] = $catId;
    }

    $whereSQL = implode(' AND ', $where);

    $stmt = $db->prepare(
        "SELECT 
            i.item_id,
            i.item_name,
            i.unit,
            i.current_stock,
            i.reorder_level,
            i.description,
            c.category_id,
            c.category_name,
            CASE 
                WHEN i.current_stock <= i.reorder_level THEN 'Low Stock'
                ELSE 'OK'
            END AS stock_status
         FROM relief_items i
         JOIN item_categories c ON i.category_id = c.category_id
         WHERE $whereSQL
         ORDER BY c.category_name, i.item_name"
    );

    $stmt->execute($params);
    jsonSuccess(['data' => $stmt->fetchAll()]);
}

// ============================================================
// List Categories
// ============================================================
function listCategories(): void {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT category_id, category_name, description
         FROM item_categories
         ORDER BY category_name"
    );
    $stmt->execute();
    jsonSuccess(['data' => $stmt->fetchAll()]);
}

// ============================================================
// Record Stock In (Receive/Donation)
// ============================================================
function recordStockIn(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $itemId = (int)($body['item_id'] ?? 0);
    $qty    = (int)($body['quantity'] ?? 0);
    $date   = $body['transaction_date'] ?? date('Y-m-d');
    $note   = trim($body['reference_note'] ?? '');

    if (!$itemId || $qty <= 0) {
        jsonError('Invalid item_id or quantity.');
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        // Verify item exists
        $check = $db->prepare('SELECT item_id FROM relief_items WHERE item_id=?');
        $check->execute([$itemId]);
        if (!$check->fetch()) {
            $db->rollBack();
            jsonError('Item not found.', 404);
        }

        // Update stock
        $db->prepare('UPDATE relief_items SET current_stock=current_stock+? WHERE item_id=?')
           ->execute([$qty, $itemId]);

        // Log transaction
        $db->prepare(
            "INSERT INTO inventory_transactions 
             (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
             VALUES (?,?,'IN',?,?,?)"
        )->execute([$itemId, currentUser()['id'], $qty, $note ?: null, $date]);

        $db->commit();
        jsonSuccess(
            ['item_id' => $itemId, 'quantity_added' => $qty],
            'Stock received and recorded successfully.'
        );
    } catch (\Throwable $e) {
        $db->rollBack();
        jsonError('Failed to record stock in: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// Record Stock Out (Distribution to Beneficiary)
// ============================================================
function recordStockOut(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $itemId      = (int)($body['item_id'] ?? 0);
    $qty         = (int)($body['quantity'] ?? 0);
    $date        = $body['transaction_date'] ?? date('Y-m-d');
    $note        = trim($body['reference_note'] ?? '');
    $benefId     = (int)($body['beneficiary_id'] ?? 0);
    $centerId    = (int)($body['center_id'] ?? 0);

    if (!$itemId || $qty <= 0) {
        jsonError('Invalid item_id or quantity.');
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        // Check current stock
        $stock = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id=?');
        $stock->execute([$itemId]);
        $current = (int)$stock->fetchColumn();

        if ($current === false) {
            $db->rollBack();
            jsonError('Item not found.', 404);
        }

        if ($current < $qty) {
            $db->rollBack();
            jsonError("Insufficient stock. Available: $current", 400);
        }

        // Update stock
        $db->prepare('UPDATE relief_items SET current_stock=current_stock-? WHERE item_id=?')
           ->execute([$qty, $itemId]);

        // Log transaction
        $db->prepare(
            "INSERT INTO inventory_transactions 
             (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
             VALUES (?,?,'OUT',?,?,?)"
        )->execute([$itemId, currentUser()['id'], $qty, $note ?: null, $date]);

        // If beneficiary is selected, create distribution record
        if ($benefId && $centerId) {
            // Check if beneficiary exists
            $benefCheck = $db->prepare('SELECT beneficiary_id FROM beneficiaries WHERE beneficiary_id=?');
            $benefCheck->execute([$benefId]);
            if (!$benefCheck->fetch()) {
                $db->rollBack();
                jsonError('Beneficiary not found.', 404);
            }

            // Create distribution record
            $db->prepare(
                "INSERT INTO distribution_records (beneficiary_id, center_id, distributed_by, distribution_date, remarks)
                 VALUES (?,?,?,?,?)"
            )->execute([$benefId, $centerId, currentUser()['id'], $date, $note ?: null]);

            $distId = $db->lastInsertId();

            // Add distribution item
            $db->prepare(
                "INSERT INTO distribution_items (distribution_id, item_id, quantity_given)
                 VALUES (?,?,?)"
            )->execute([$distId, $itemId, $qty]);

            // Update beneficiary status if not already served
            $db->prepare(
                "UPDATE beneficiaries SET status='Served' WHERE beneficiary_id=? AND status='Pending'"
            )->execute([$benefId]);
        }

        $db->commit();
        jsonSuccess(
            ['item_id' => $itemId, 'quantity_removed' => $qty, 'beneficiary_id' => $benefId],
            'Stock distributed and recorded successfully.'
        );
    } catch (\Throwable $e) {
        $db->rollBack();
        jsonError('Failed to record stock out: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// Edit Stock Amount (Direct Correction)
// ============================================================
function editStockAmount(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $itemId   = (int)($body['item_id'] ?? 0);
    $newStock = (int)($body['new_stock'] ?? 0);
    $reason   = trim($body['reason'] ?? '');

    if (!$itemId || $newStock < 0) {
        jsonError('Invalid item_id or stock amount.');
    }
    if (!$reason) {
        jsonError('Reason is required.');
    }

    $db = getDB();
    $db->beginTransaction();
    try {
        // Get current stock
        $current = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id=?');
        $current->execute([$itemId]);
        $currentStock = (int)$current->fetchColumn();

        if ($currentStock === false) {
            $db->rollBack();
            jsonError('Item not found.', 404);
        }

        $difference = $newStock - $currentStock;

        // Update relief_items
        $db->prepare('UPDATE relief_items SET current_stock=? WHERE item_id=?')
           ->execute([$newStock, $itemId]);

        // Log as transaction (IN if positive, OUT if negative)
        if ($difference != 0) {
            $type = $difference > 0 ? 'IN' : 'OUT';
            $quantity = abs($difference);

            $db->prepare(
                "INSERT INTO inventory_transactions 
                 (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
                 VALUES (?,?,'$type',?,'Stock correction: $reason',?)"
            )->execute([$itemId, currentUser()['id'], $quantity, date('Y-m-d')]);
        }

        // Also log in stock_corrections table if it exists
        try {
            $db->prepare(
                "INSERT INTO stock_corrections (item_id, old_stock, new_stock, reason, corrected_by)
                 VALUES (?,?,?,?,?)"
            )->execute([$itemId, $currentStock, $newStock, $reason, currentUser()['id']]);
        } catch (\Throwable $e) {
            // Table might not exist yet, continue anyway
        }

        $db->commit();
        jsonSuccess(
            ['item_id' => $itemId, 'old_stock' => $currentStock, 'new_stock' => $newStock],
            'Stock amount updated successfully.'
        );
    } catch (\Throwable $e) {
        $db->rollBack();
        jsonError('Failed to update stock: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// Get Item Transaction History
// ============================================================
function getItemHistory(): void {
    $itemId = (int)($_GET['item_id'] ?? 0);
    $limit  = (int)($_GET['limit'] ?? 50);

    if (!$itemId) {
        jsonError('item_id is required.');
    }

    $db = getDB();

    $stmt = $db->prepare(
        "SELECT 
            it.transaction_id,
            DATE_FORMAT(it.transaction_date, '%b %d, %Y') AS transaction_date,
            it.transaction_type,
            it.quantity,
            it.reference_note,
            CONCAT(u.first_name, ' ', u.last_name) AS user_name,
            u.user_id
         FROM inventory_transactions it
         JOIN users u ON it.user_id = u.user_id
         WHERE it.item_id = ?
         ORDER BY it.transaction_date DESC, it.transaction_id DESC
         LIMIT ?"
    );
    $stmt->execute([$itemId, $limit]);

    jsonSuccess(['data' => $stmt->fetchAll()]);
}
?>
