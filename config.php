<?php
$host = 'localhost';
$dbname = 'motor_parts_inventory';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ✅ Activity Log Helper Function
function log_activity($pdo, $action, $product_id = null, $product_name = null, $details = null) {
    // Ensure we know who is performing the action
    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'unknown';

    // Insert into activity_log table
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, username, action, product_id, product_name, details)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $username, $action, $product_id, $product_name, $details]);
}
?>
