<?php
require_once 'config.php';

// ✅ Allow only logged-in admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

try {
    // ✅ Fetch all logs
    $stmt = $pdo->query("
        SELECT 
            id, 
            user_id, 
            username, 
            action, 
            product_id, 
            product_name, 
            details, 
            log_time 
        FROM activity_log 
        ORDER BY log_time DESC
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=activity_log_' . date('Ymd_His') . '.csv');

    // ✅ Output to browser
    $out = fopen('php://output', 'w');

    if (!empty($logs)) {
        // Write header row (column names)
        fputcsv($out, array_keys($logs[0]));
        // Write each log entry
        foreach ($logs as $row) {
            fputcsv($out, $row);
        }
    } else {
        // No logs found
        fputcsv($out, ['No activity log records found.']);
    }

    fclose($out);
    exit();

} catch (PDOException $e) {
    die("Error exporting logs: " . $e->getMessage());
}
?>
