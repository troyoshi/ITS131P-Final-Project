<?php
// api/dashboard.php — KPIs, charts, activity feed
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$action = $_GET['action'] ?? 'kpis';

match($action) {
    'kpis'          => getKPIs(),
    'weekly_chart'  => getWeeklyChart(),
    'activity'      => getActivity(),
    'centers'       => getCenters(),
    default         => jsonError('Unknown action', 404),
};

function getKPIs(): void {
    $db = getDB();

    $bene   = $db->query("SELECT COUNT(*) AS total, SUM(status='Served') AS served, SUM(status='Pending') AS pending FROM beneficiaries")->fetch();
    $dist   = $db->query("SELECT COUNT(*) AS total FROM distribution_records")->fetch();
    $centers= $db->query("SELECT COUNT(*) AS active FROM evacuation_centers WHERE status='Active'")->fetch();
    $invTx  = $db->query("SELECT SUM(quantity) AS out_today FROM inventory_transactions WHERE transaction_type='OUT' AND transaction_date=CURDATE()")->fetch();

    jsonSuccess(['data' => [
        'total_beneficiaries' => (int)$bene['total'],
        'served'              => (int)$bene['served'],
        'pending'             => (int)$bene['pending'],
        'total_distributions' => (int)$dist['total'],
        'active_centers'      => (int)$centers['active'],
        'distributed_today'   => (int)($invTx['out_today'] ?? 0),
    ]]);
}

function getWeeklyChart(): void {
    $db   = getDB();
    $rows = $db->query(
        "SELECT DATE_FORMAT(distribution_date,'%a') AS day_label,
                distribution_date,
                COUNT(*) AS count
         FROM distribution_records
         WHERE distribution_date >= CURDATE() - INTERVAL 6 DAY
         GROUP BY distribution_date
         ORDER BY distribution_date ASC"
    )->fetchAll();
    jsonSuccess(['data' => $rows]);
}

function getActivity(): void {
    $db   = getDB();
    // Combine recent distributions + inventory transactions into one feed
    $feed = $db->query(
        "(SELECT 'distribution' AS type,
                 CONCAT('Distribution completed for ',CONCAT(b.first_name,' ',b.last_name)) AS text,
                 e.center_name AS detail,
                 dr.created_at AS ts
          FROM distribution_records dr
          JOIN beneficiaries       b ON dr.beneficiary_id=b.beneficiary_id
          JOIN evacuation_centers  e ON dr.center_id=e.center_id
          ORDER BY dr.created_at DESC LIMIT 5)
         UNION ALL
         (SELECT 'inventory' AS type,
                 CONCAT(transaction_type,' — ',i.item_name,' x',t.quantity) AS text,
                 t.reference_note AS detail,
                 t.created_at AS ts
          FROM inventory_transactions t
          JOIN relief_items i ON t.item_id=i.item_id
          ORDER BY t.created_at DESC LIMIT 5)
         ORDER BY ts DESC LIMIT 8"
    )->fetchAll();
    jsonSuccess(['data' => $feed]);
}

function getCenters(): void {
    $rows = getDB()->query(
        "SELECT center_id, center_name, barangay, status, max_capacity, current_occupancy,
                ROUND(current_occupancy/max_capacity*100,1) AS occupancy_pct
         FROM evacuation_centers ORDER BY center_name"
    )->fetchAll();
    jsonSuccess(['data' => $rows]);
}
