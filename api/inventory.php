<?php
// api/inventory.php — Inventory management

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    match(true) {
        $action === 'list'           => listInventory(),
        $action === 'get'            => getItem($id),
        $action === 'categories'     => getCategories(),
        $action === 'low_stock'      => getLowStock(),
        $action === 'history'        => getItemHistory($id),
        $action === 'stock_in'       => stockIn(),
        $action === 'stock_out'      => stockOut(),
        $action === 'edit_stock'     => editStock(),
        default => jsonError('Unknown action', 404),
    };
} catch (Exception $e) {
    jsonError('Server error: ' . $e->getMessage(), 500);
}

// ============================================================
// LIST INVENTORY
// ============================================================
function listInventory(): void {
    $db = getDB();
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category_id'] ?? '');
    
    $where = ['1=1'];
    $params = [];
    
    if ($search) {
        $where[] = "i.item_name LIKE ?";
        $params[] = "%$search%";
    }
    
    if ($category) {
        $where[] = "i.category_id = ?";
        $params[] = (int)$category;
    }
    
    $stmt = $db->prepare(
        "SELECT i.item_id, i.item_name, i.unit, i.current_stock, i.reorder_level,
                i.description, c.category_id, c.category_name,
                CASE WHEN i.current_stock <= i.reorder_level THEN 'Low Stock' ELSE 'OK' END AS stock_status
         FROM relief_items i
         JOIN item_categories c ON i.category_id = c.category_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.category_name, i.item_name"
    );
    
    $stmt->execute($params);
    jsonSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ============================================================
// GET SINGLE ITEM
// ============================================================
function getItem(?int $id): void {
    if (!$id) {
        jsonError('ID required.');
        return;
    }
    
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT i.*, c.category_name
         FROM relief_items i
         JOIN item_categories c ON i.category_id = c.category_id
         WHERE i.item_id = ?"
    );
    
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        jsonError('Item not found.', 404);
        return;
    }
    
    jsonSuccess(['data' => $row]);
}

// ============================================================
// GET CATEGORIES
// ============================================================
function getCategories(): void {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM item_categories ORDER BY category_name");
    jsonSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ============================================================
// GET LOW STOCK ITEMS
// ============================================================
function getLowStock(): void {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT i.item_id, i.item_name, i.unit, i.current_stock, i.reorder_level,
                c.category_name,
                CASE WHEN i.current_stock <= i.reorder_level THEN 'Low Stock' ELSE 'OK' END AS stock_status
         FROM relief_items i
         JOIN item_categories c ON i.category_id = c.category_id
         WHERE i.current_stock <= i.reorder_level
         ORDER BY i.current_stock ASC"
    );
    
    $stmt->execute();
    jsonSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ============================================================
// GET ITEM HISTORY
// ============================================================
function getItemHistory(?int $id): void {
    if (!$id) {
        jsonError('Item ID required.');
        return;
    }
    
    $db = getDB();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    
    $stmt = $db->prepare(
        "SELECT it.transaction_id, it.transaction_type, it.quantity, it.transaction_date,
                it.reference_note, i.unit,
                u.first_name, u.last_name
         FROM inventory_transactions it
         JOIN relief_items i ON it.item_id = i.item_id
         JOIN users u ON it.user_id = u.user_id
         WHERE it.item_id = ?
         ORDER BY it.transaction_date DESC, it.transaction_id DESC
         LIMIT ?"
    );
    
    $stmt->execute([$id, $limit]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = array_map(function($row) {
        return [
            'transaction_id' => $row['transaction_id'],
            'transaction_type' => $row['transaction_type'],
            'quantity' => $row['quantity'],
            'unit' => $row['unit'],
            'transaction_date' => $row['transaction_date'],
            'reference_note' => $row['reference_note'],
            'user_name' => $row['first_name'] . ' ' . $row['last_name']
        ];
    }, $rows);
    
    jsonSuccess(['data' => $data]);
}

// ============================================================
// STOCK IN
// ============================================================
function stockIn(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $item_id = (int)($body['item_id'] ?? 0);
    $quantity = (int)($body['quantity'] ?? 0);
    $date = $body['transaction_date'] ?? date('Y-m-d');
    $note = trim($body['reference_note'] ?? $body['reference_note'] ?? '');
    
    if (!$item_id || !$quantity || $quantity <= 0) {
        jsonError('Invalid item or quantity.');
        return;
    }
    
    $db = getDB();
    $uid = currentUser()['id'];
    
    // Get current stock
    $stmt = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id = ?');
    $stmt->execute([$item_id]);
    $current = (int)$stmt->fetchColumn();
    
    if ($current === null) {
        jsonError('Item not found.', 404);
        return;
    }
    
    // Update stock
    $newStock = $current + $quantity;
    $db->prepare('UPDATE relief_items SET current_stock = ? WHERE item_id = ?')
       ->execute([$newStock, $item_id]);
    
    // Record transaction
    $db->prepare(
        "INSERT INTO inventory_transactions (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
         VALUES (?, ?, 'IN', ?, ?, ?)"
    )->execute([$item_id, $uid, $quantity, $note, $date]);
    
    jsonSuccess([], 'Stock in recorded successfully.');
}

// ============================================================
// STOCK OUT
// ============================================================
function stockOut(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $item_id = (int)($body['item_id'] ?? 0);
    $quantity = (int)($body['quantity'] ?? 0);
    $date = $body['transaction_date'] ?? date('Y-m-d');
    $note = trim($body['reference_note'] ?? '');
    $beneficiary_id = (int)($body['beneficiary_id'] ?? 0);
    $center_id = (int)($body['center_id'] ?? 0);
    
    if (!$item_id || !$quantity || $quantity <= 0) {
        jsonError('Invalid item or quantity.');
        return;
    }
    
    $db = getDB();
    $uid = currentUser()['id'];
    
    // Get current stock
    $stmt = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id = ?');
    $stmt->execute([$item_id]);
    $current = (int)$stmt->fetchColumn();
    
    if ($current === null) {
        jsonError('Item not found.', 404);
        return;
    }
    
    if ($quantity > $current) {
        jsonError('Insufficient stock. Available: ' . $current);
        return;
    }
    
    // Update stock
    $newStock = $current - $quantity;
    $db->prepare('UPDATE relief_items SET current_stock = ? WHERE item_id = ?')
       ->execute([$newStock, $item_id]);
    
    // Record transaction
    $db->prepare(
        "INSERT INTO inventory_transactions (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
         VALUES (?, ?, 'OUT', ?, ?, ?)"
    )->execute([$item_id, $uid, $quantity, $note, $date]);
    
    // If distribution info provided, create distribution record
    if ($beneficiary_id && $center_id) {
        $db->prepare(
            "INSERT INTO distribution_records (beneficiary_id, center_id, distributed_by, distribution_date, remarks)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$beneficiary_id, $center_id, $uid, $date, $note]);
        
        $dist_id = $db->lastInsertId();
        
        $db->prepare(
            "INSERT INTO distribution_items (distribution_id, item_id, quantity_given)
             VALUES (?, ?, ?)"
        )->execute([$dist_id, $item_id, $quantity]);
    }
    
    jsonSuccess([], 'Stock out recorded successfully.');
}

// ============================================================
// EDIT STOCK
// ============================================================
function editStock(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $item_id = (int)($body['item_id'] ?? 0);
    $new_stock = (int)($body['new_stock'] ?? 0);
    $reason = trim($body['reason'] ?? '');
    
    if (!$item_id || $new_stock < 0) {
        jsonError('Invalid item or stock amount.');
        return;
    }
    
    if (!$reason) {
        jsonError('Reason is required.');
        return;
    }
    
    $db = getDB();
    
    // Get current stock
    $stmt = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id = ?');
    $stmt->execute([$item_id]);
    $current = (int)$stmt->fetchColumn();
    
    if ($current === null) {
        jsonError('Item not found.', 404);
        return;
    }
    
    // Calculate difference
    $difference = $new_stock - $current;
    
    // Update stock
    $db->prepare('UPDATE relief_items SET current_stock = ? WHERE item_id = ?')
       ->execute([$new_stock, $item_id]);
    
    // Record as transaction
    $uid = currentUser()['id'];
    $type = $difference > 0 ? 'IN' : 'OUT';
    $qty = abs($difference);
    $note = "Stock adjustment: $reason";
    
    $db->prepare(
        "INSERT INTO inventory_transactions (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$item_id, $uid, $type, $qty, $note, date('Y-m-d')]);
    
    jsonSuccess([], 'Stock updated successfully.');
}

?>
