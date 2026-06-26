<?php
// api/reports.php — Report generation
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$action   = $_GET['action']    ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$centerId = (int)($_GET['center_id'] ?? 0);

match($action) {
    'distribution' => reportDistribution($dateFrom, $dateTo, $centerId),
    'beneficiary'  => reportBeneficiary($centerId),
    'inventory'    => reportInventory(),
    'summary'      => reportSummary($dateFrom, $dateTo),
    'chart_daily'  => chartDailyDistributions($dateFrom, $dateTo),
    'chart_center' => chartByCenter($dateFrom, $dateTo),
    default        => jsonError('Unknown action', 404),
};

function reportDistribution(string $from, string $to, int $centerId): void {
    $db     = getDB();
    $where  = 'dr.distribution_date BETWEEN ? AND ?';
    $params = [$from, $to];
    if ($centerId) { $where .= ' AND dr.center_id=?'; $params[] = $centerId; }

    $rows = $db->prepare(
        "SELECT dr.distribution_id,
                DATE_FORMAT(dr.distribution_date,'%b %d, %Y') AS dist_date,
                b.beneficiary_code,
                CONCAT(b.first_name,' ',b.last_name) AS beneficiary_name,
                b.household_size,
                e.center_name,
                CONCAT(u.first_name,' ',u.last_name) AS staff_name,
                dr.remarks,
                GROUP_CONCAT(CONCAT(i.item_name,' x',di.quantity_given) ORDER BY i.item_name SEPARATOR ', ') AS items_given
         FROM distribution_records dr
         JOIN beneficiaries       b  ON dr.beneficiary_id=b.beneficiary_id
         JOIN evacuation_centers  e  ON dr.center_id=e.center_id
         JOIN users               u  ON dr.distributed_by=u.user_id
         LEFT JOIN distribution_items di ON di.distribution_id=dr.distribution_id
         LEFT JOIN relief_items   i  ON di.item_id=i.item_id
         WHERE $where
         GROUP BY dr.distribution_id
         ORDER BY dr.distribution_date DESC, dr.distribution_id DESC"
    );
    $rows->execute($params);

    // Totals
    $total = $db->prepare(
        "SELECT COUNT(DISTINCT dr.distribution_id) AS distributions,
                COUNT(DISTINCT dr.beneficiary_id)  AS beneficiaries_served,
                SUM(b.household_size)               AS individuals_served
         FROM distribution_records dr
         JOIN beneficiaries b ON dr.beneficiary_id=b.beneficiary_id
         WHERE $where"
    );
    $total->execute($params);

    jsonSuccess(['data' => $rows->fetchAll(), 'totals' => $total->fetch()]);
}

function reportBeneficiary(int $centerId): void {
    $db     = getDB();
    $where  = $centerId ? 'AND b.center_id=?' : '';
    $params = $centerId ? [$centerId] : [];

    $rows = $db->prepare(
        "SELECT b.beneficiary_code,
                CONCAT(b.first_name,' ',b.last_name) AS name,
                b.household_size, b.barangay,
                sn.need_label AS special_need,
                e.center_name, b.status,
                DATE_FORMAT(b.registered_at,'%b %d, %Y') AS registered,
                (SELECT COUNT(*) FROM distribution_records dr WHERE dr.beneficiary_id=b.beneficiary_id) AS dist_count
         FROM beneficiaries b
         JOIN evacuation_centers  e  ON b.center_id=e.center_id
         JOIN special_needs_types sn ON b.need_id=sn.need_id
         WHERE 1=1 $where
         ORDER BY b.status, b.last_name"
    );
    $rows->execute($params);

    $summary = $db->prepare(
        "SELECT
            COUNT(*)                AS total,
            SUM(status='Served')   AS served,
            SUM(status='Pending')  AS pending,
            SUM(status='Priority') AS priority,
            SUM(need_id != 1)      AS special_needs,
            SUM(household_size)    AS total_individuals
         FROM beneficiaries b WHERE 1=1 $where"
    );
    $summary->execute($params);

    jsonSuccess(['data' => $rows->fetchAll(), 'summary' => $summary->fetch()]);
}

function reportInventory(): void {
    $db = getDB();

    $items = $db->query(
        "SELECT i.item_name, c.category_name, i.unit,
                i.current_stock, i.reorder_level,
                COALESCE(SUM(CASE WHEN t.transaction_type='IN'  THEN t.quantity END),0) AS total_in,
                COALESCE(SUM(CASE WHEN t.transaction_type='OUT' THEN t.quantity END),0) AS total_out,
                CASE WHEN i.current_stock <= i.reorder_level THEN 'Low Stock' ELSE 'OK' END AS stock_status
         FROM relief_items i
         JOIN item_categories c ON i.category_id=c.category_id
         LEFT JOIN inventory_transactions t ON t.item_id=i.item_id
         GROUP BY i.item_id
         ORDER BY c.category_name, i.item_name"
    )->fetchAll();

    jsonSuccess(['data' => $items]);
}

function reportSummary(string $from, string $to): void {
    $db = getDB();

    $s = $db->prepare(
        "SELECT
            (SELECT COUNT(*) FROM beneficiaries) AS total_beneficiaries,
            (SELECT COUNT(*) FROM beneficiaries WHERE status='Served') AS served,
            (SELECT COUNT(*) FROM distribution_records WHERE distribution_date BETWEEN ? AND ?) AS distributions,
            (SELECT COALESCE(SUM(quantity),0) FROM inventory_transactions WHERE transaction_type='OUT' AND transaction_date BETWEEN ? AND ?) AS items_distributed,
            (SELECT COUNT(*) FROM evacuation_centers WHERE status='Active') AS active_centers,
            (SELECT COUNT(*) FROM relief_items WHERE current_stock <= reorder_level) AS low_stock_items"
    );
    $s->execute([$from, $to, $from, $to]);
    jsonSuccess(['data' => $s->fetch()]);
}

function chartDailyDistributions(string $from, string $to): void {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(distribution_date,'%b %d') AS label,
                COUNT(*) AS count
         FROM distribution_records
         WHERE distribution_date BETWEEN ? AND ?
         GROUP BY distribution_date
         ORDER BY distribution_date"
    );
    $stmt->execute([$from, $to]);
    jsonSuccess(['data' => $stmt->fetchAll()]);
}

function chartByCenter(string $from, string $to): void {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT e.center_name AS label, COUNT(*) AS count
         FROM distribution_records dr
         JOIN evacuation_centers e ON dr.center_id=e.center_id
         WHERE dr.distribution_date BETWEEN ? AND ?
         GROUP BY e.center_id
         ORDER BY count DESC"
    );
    $stmt->execute([$from, $to]);
    jsonSuccess(['data' => $stmt->fetchAll()]);
}
