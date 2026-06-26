<?php
// api/inventory.php — Relief inventory & transactions
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;

match(true) {
    $action === 'list'         => listInventory(),
    $action === 'get'          => getItem($id),
    $action === 'categories'   => getCategories(),
    $action === 'transactions' => getTransactions($id),
    $action === 'stock_in'     => stockIn(),
    $action === 'stock_out'    => stockOut(),
    $action === 'create'       => createItem(),
    $action === 'update'       => updateItem($id),
    $action === 'low_stock'    => getLowStock(),
    default => jsonError('Unknown action', 404),
};

function listInventory(): void {
    $db     = getDB();
    $search = trim($_GET['search'] ?? '');
    $cat    = (int)($_GET['category_id'] ?? 0);

    $where  = ['1=1'];
    $params = [];
    if ($search) { $where[] = 'i.item_name LIKE ?'; $params[] = "%$search%"; }
    if ($cat)    { $where[] = 'i.category_id = ?';  $params[] = $cat; }

    $stmt = $db->prepare(
        "SELECT i.item_id, i.item_name, i.unit, i.current_stock, i.reorder_level, i.description,
                c.category_name,
                CASE WHEN i.current_stock <= i.reorder_level THEN 'Low Stock' ELSE 'OK' END AS stock_status
         FROM relief_items i
         JOIN item_categories c ON i.category_id = c.category_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY c.category_name, i.item_name"
    );
    $stmt->execute($params);
    jsonSuccess(['data' => $stmt->fetchAll()]);
}

function getItem(?int $id): void {
    if (!$id) jsonError('ID required.');
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT i.*, c.category_name FROM relief_items i
         JOIN item_categories c ON i.category_id = c.category_id
         WHERE i.item_id = ?"
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) jsonError('Item not found.', 404);
    jsonSuccess(['data' => $row]);
}

function getCategories(): void {
    $rows = getDB()->query('SELECT * FROM item_categories ORDER BY category_name')->fetchAll();
    jsonSuccess(['data' => $rows]);
}

function getTransactions(?int $itemId): void {
    $db     = getDB();
    $where  = $itemId ? 'WHERE t.item_id = ?' : '';
    $params = $itemId ? [$itemId] : [];
    $stmt   = $db->prepare(
        "SELECT t.*, i.item_name, i.unit,
                CONCAT(u.first_name,' ',u.last_name) AS staff_name,
                DATE_FORMAT(t.transaction_date,'%b %d, %Y') AS tx_date
         FROM inventory_transactions t
         JOIN relief_items i ON t.item_id = i.item_id
         JOIN users u        ON t.user_id = u.user_id
         $where
         ORDER BY t.created_at DESC
         LIMIT 100"
    );
    $stmt->execute($params);
    jsonSuccess(['data' => $stmt->fetchAll()]);
}

function stockIn(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    _recordTransaction('IN', $body);
}

function stockOut(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    _recordTransaction('OUT', $body);
}

function _recordTransaction(string $type, array $body): void {
    $itemId = (int)($body['item_id']  ?? 0);
    $qty    = (int)($body['quantity'] ?? 0);
    $date   = $body['transaction_date'] ?? date('Y-m-d');
    $note   = trim($body['reference_note'] ?? '');

    if (!$itemId || $qty <= 0) jsonError('item_id and positive quantity required.');

    $db = getDB();

    // Check stock for OUT
    if ($type === 'OUT') {
        $stock = (int)$db->prepare('SELECT current_stock FROM relief_items WHERE item_id=?')
                         ->execute([$itemId]) && false;
        $stock = $db->prepare('SELECT current_stock FROM relief_items WHERE item_id=?');
        $stock->execute([$itemId]);
        $current = (int)($stock->fetchColumn());
        if ($current < $qty) jsonError("Insufficient stock. Available: $current.");
    }

    $db->beginTransaction();
    try {
        $db->prepare(
            "INSERT INTO inventory_transactions (item_id, user_id, transaction_type, quantity, reference_note, transaction_date)
             VALUES (?,?,?,?,?,?)"
        )->execute([$itemId, currentUser()['id'], $type, $qty, $note ?: null, $date]);

        $op = $type === 'IN' ? '+' : '-';
        $db->prepare("UPDATE relief_items SET current_stock = current_stock $op ? WHERE item_id = ?")
           ->execute([$qty, $itemId]);

        $db->commit();
        jsonSuccess([], $type === 'IN' ? 'Stock added.' : 'Stock deducted.');
    } catch (\Throwable $e) {
        $db->rollBack();
        jsonError('Transaction failed: ' . $e->getMessage(), 500);
    }
}

function createItem(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($body['item_name']) || empty($body['unit']) || empty($body['category_id']))
        jsonError('item_name, unit, and category_id are required.');
    $db = getDB();
    $db->prepare(
        "INSERT INTO relief_items (category_id, item_name, unit, current_stock, reorder_level, description)
         VALUES (?,?,?,?,?,?)"
    )->execute([
        (int)$body['category_id'],
        trim($body['item_name']),
        trim($body['unit']),
        (int)($body['current_stock']  ?? 0),
        (int)($body['reorder_level']  ?? 50),
        trim($body['description']     ?? '') ?: null,
    ]);
    jsonSuccess(['id' => $db->lastInsertId()], 'Item created.');
}

function updateItem(?int $id): void {
    if (!$id) jsonError('ID required.');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    getDB()->prepare(
        "UPDATE relief_items SET category_id=?, item_name=?, unit=?, reorder_level=?, description=?
         WHERE item_id=?"
    )->execute([
        (int)($body['category_id']   ?? 1),
        trim($body['item_name']      ?? ''),
        trim($body['unit']           ?? ''),
        (int)($body['reorder_level'] ?? 50),
        trim($body['description']    ?? '') ?: null,
        $id,
    ]);
    jsonSuccess([], 'Item updated.');
}

function getLowStock(): void {
    $rows = getDB()->query(
        "SELECT i.item_id, i.item_name, i.unit, i.current_stock, i.reorder_level, c.category_name
         FROM relief_items i JOIN item_categories c ON i.category_id=c.category_id
         WHERE i.current_stock <= i.reorder_level
         ORDER BY i.current_stock ASC"
    )->fetchAll();
    jsonSuccess(['data' => $rows]);
}
