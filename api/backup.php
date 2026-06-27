<?php
// api/backup.php — Backup and restore operations

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
requireLogin();

// Only administrators can perform backups
if (currentUser()['role'] !== 'Administrator') {
    jsonError('Permission denied. Admin access required.', 403);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    match(true) {
        $action === 'export_sql'     => exportFullSQL(),
        $action === 'export_csv'     => exportTableCSV(),
        $action === 'import_csv'     => importTableCSV(),
        $action === 'restore_sql'    => restoreFromSQL(),
        $action === 'history'        => getBackupHistory(),
        $action === 'download'       => downloadBackupFile(),
        $action === 'delete'         => deleteBackupFile(),
        default => jsonError('Unknown action', 404),
    };
} catch (Exception $e) {
    jsonError('Error: ' . $e->getMessage(), 500);
}

// ============================================================
// EXPORT FULL DATABASE AS SQL
// ============================================================
function exportFullSQL(): void {
    $db = getDB();
    $backupDir = __DIR__ . '/../backups';
    
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = 'cdrc_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    $tables = [
        'roles',
        'users',
        'evacuation_centers',
        'special_needs_types',
        'beneficiaries',
        'item_categories',
        'relief_items',
        'inventory_transactions',
        'distribution_records',
        'distribution_items'
    ];

    $sql = "-- CDRC Relief Tracker Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- User: " . currentUser()['username'] . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        // Drop table
        $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";

        // Create table
        $stmt = $db->query("SHOW CREATE TABLE `$table`");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql .= $result['Create Table'] . ";\n\n";

        // Insert data
        $stmt = $db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $sql .= "INSERT INTO `$table` (" . implode(', ', array_map(fn($c) => "`$c`", $columns)) . ") VALUES\n";

            $values = [];
            foreach ($rows as $row) {
                $rowValues = array_map(function($val) {
                    if ($val === null) return 'NULL';
                    return "'" . str_replace("'", "''", $val) . "'";
                }, $row);
                $values[] = "(" . implode(', ', $rowValues) . ")";
            }

            $sql .= implode(",\n", $values) . ";\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    file_put_contents($filepath, $sql);
    
    // Save backup record
    recordBackup($filename, 'sql', filesize($filepath));

    // Return file for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filepath);
    exit;
}

// ============================================================
// EXPORT TABLE AS CSV
// ============================================================
function exportTableCSV(): void {
    $table = trim($_GET['table'] ?? '');
    $headers = (bool)($_GET['headers'] ?? true);

    if (!$table || !preg_match('/^[a-z_]+$/', $table)) {
        jsonError('Invalid table name');
        return;
    }

    $db = getDB();
    
    try {
        $stmt = $db->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        jsonError('Table not found: ' . $e->getMessage(), 404);
        return;
    }

    if (empty($rows)) {
        jsonError('No data in table');
        return;
    }

    $csv = '';

    // Add headers
    if ($headers) {
        $csv .= implode(',', array_keys($rows[0])) . "\n";
    }

    // Add data
    foreach ($rows as $row) {
        $values = array_map(function($val) {
            if ($val === null) return '';
            if (strpos($val, ',') !== false || strpos($val, '"') !== false) {
                return '"' . str_replace('"', '""', $val) . '"';
            }
            return $val;
        }, $row);

        $csv .= implode(',', $values) . "\n";
    }

    recordBackup($table . '_' . date('Y-m-d_H-i-s') . '.csv', 'csv', strlen($csv));

    jsonSuccess(['data' => $csv]);
}

// ============================================================
// IMPORT TABLE FROM CSV
// ============================================================
function importTableCSV(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $table = trim($body['table'] ?? '');
    $csv = $body['csv'] ?? '';
    $hasHeaders = (bool)($body['has_headers'] ?? true);

    if (!$table || !preg_match('/^[a-z_]+$/', $table)) {
        jsonError('Invalid table name');
        return;
    }

    if (empty($csv)) {
        jsonError('No CSV data provided');
        return;
    }

    $lines = array_filter(explode("\n", trim($csv)));
    if (empty($lines)) {
        jsonError('CSV is empty');
        return;
    }

    $db = getDB();

    // Parse CSV
    $startLine = $hasHeaders ? 1 : 0;
    $headers = null;

    if ($hasHeaders) {
        $headers = str_getcsv($lines[0]);
    } else {
        // Get headers from table
        try {
            $stmt = $db->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = array_map(fn($col) => $col['Field'], $columns);
        } catch (Exception $e) {
            jsonError('Table not found: ' . $e->getMessage(), 404);
            return;
        }
    }

    $inserted = 0;
    $errors = [];
    
    for ($i = $startLine; $i < count($lines); $i++) {
        $values = str_getcsv($lines[$i]);
        
        if (empty(array_filter($values))) continue;

        $placeholders = array_fill(0, count($headers), '?');
        $query = "INSERT INTO `$table` (" . implode(',', array_map(fn($h) => "`$h`", $headers)) . ") VALUES (" . implode(',', $placeholders) . ")";
        
        try {
            $db->prepare($query)->execute($values);
            $inserted++;
        } catch (Exception $e) {
            $errors[] = "Line " . ($i + 1) . ": " . $e->getMessage();
            error_log("CSV import error on line " . ($i + 1) . ": " . $e->getMessage());
        }
    }

    recordBackup($table . '_import_' . date('Y-m-d_H-i-s') . '.csv', 'csv', strlen($csv));

    if (empty($errors)) {
        jsonSuccess([], "Successfully imported $inserted rows into $table");
    } else {
        jsonSuccess([], "Imported $inserted rows. " . count($errors) . " errors: " . implode('; ', array_slice($errors, 0, 3)));
    }
}

// ============================================================
// RESTORE FROM SQL
// ============================================================
function restoreFromSQL(): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $sql = trim($body['sql'] ?? '');

    if (empty($sql)) {
        jsonError('No SQL provided');
        return;
    }

    // Check for confirmation parameter
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
    if (!$confirm) {
        jsonError('Confirmation required. Call with ?confirm=1', 400);
        return;
    }

    $db = getDB();

    // Remove comments and extra whitespace
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove SQL comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove block comments

    // Split SQL by semicolon
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s)
    );

    if (empty($statements)) {
        jsonError('No valid SQL statements found');
        return;
    }

    $executed = 0;
    $errors = [];

    // Disable foreign key checks during restore
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($statements as $statement) {
            try {
                $db->exec($statement);
                $executed++;
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                error_log("Restore error: " . $e->getMessage());
            }
        }

        // Re-enable foreign key checks
        $db->exec('SET FOREIGN_KEY_CHECKS = 1');

    } catch (Exception $e) {
        error_log("Critical restore error: " . $e->getMessage());
        jsonError('Critical error during restore: ' . $e->getMessage(), 500);
        return;
    }

    if (!empty($errors) && count($errors) > count($statements) / 2) {
        jsonError('Restore failed with too many errors: ' . implode('; ', array_slice($errors, 0, 3)), 500);
        return;
    }

    recordBackup('restore_' . date('Y-m-d_H-i-s') . '.sql', 'sql', strlen($sql));

    $message = "Database restored successfully. Executed $executed statements.";
    if (!empty($errors)) {
        $message .= " (" . count($errors) . " non-critical errors)";
    }

    jsonSuccess([], $message);
}

// ============================================================
// BACKUP HISTORY
// ============================================================
function getBackupHistory(): void {
    $backupDir = __DIR__ . '/../backups';

    if (!is_dir($backupDir)) {
        jsonSuccess(['data' => []]);
        return;
    }

    $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
    $backups = [];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $filepath = $backupDir . '/' . $file;
        if (!is_file($filepath)) continue;

        $backups[] = [
            'filename' => $file,
            'type' => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            'size' => filesize($filepath),
            'created_at' => date('M d, Y H:i', filemtime($filepath)),
            'created_by' => currentUser()['username']
        ];
    }

    jsonSuccess(['data' => array_slice($backups, 0, 50)]);
}

// ============================================================
// DOWNLOAD BACKUP
// ============================================================
function downloadBackupFile(): void {
    $file = basename($_GET['file'] ?? '');

    if (empty($file)) {
        jsonError('File not specified');
        return;
    }

    $filepath = __DIR__ . '/../backups/' . $file;

    if (!file_exists($filepath) || !is_file($filepath)) {
        jsonError('File not found', 404);
        return;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// ============================================================
// DELETE BACKUP
// ============================================================
function deleteBackupFile(): void {
    $file = basename($_GET['file'] ?? '');

    if (empty($file)) {
        jsonError('File not specified');
        return;
    }

    $filepath = __DIR__ . '/../backups/' . $file;

    if (!file_exists($filepath)) {
        jsonError('File not found', 404);
        return;
    }

    if (!unlink($filepath)) {
        jsonError('Failed to delete file');
        return;
    }

    jsonSuccess([], 'Backup deleted successfully');
}

// ============================================================
// HELPER: RECORD BACKUP
// ============================================================
function recordBackup(string $filename, string $type, int $size): void {
    // Optional: Store backup metadata in database
    // This helps track backup history
    try {
        error_log("Backup recorded: $filename ($type, " . formatBytes($size) . ")");
    } catch (Exception $e) {
        error_log("Failed to record backup: " . $e->getMessage());
    }
}

function formatBytes($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i) * 100) / 100 . ' ' . $sizes[$i];
}

?>
