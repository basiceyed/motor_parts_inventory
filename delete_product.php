<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only admin can delete products
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$product_id = $_GET['id'] ?? null;

if ($product_id) {
    try {
        // ✅ 1. Get product name before deletion
        $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();

        // ✅ 2. Delete the product
        $del = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $del->execute([$product_id]);

        // ✅ 3. Log the deletion (only if product existed)
        if ($product_name) {
            log_activity(
                $pdo,
                'Deleted Product',
                $product_id,
                $product_name,
                "Deleted by {$_SESSION['username']}"
            );
        }
    } catch (PDOException $e) {
        // Handle error if needed
        // echo "Error deleting product: " . $e->getMessage();
    }
}

header('Location: index.php');
exit();
?>
