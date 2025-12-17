<?php
require_once 'config.php';

// ✅ Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Pagination setup
$limit = 50;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// ✅ Fetch logs safely
$stmt = $pdo->prepare("SELECT id, username, action, product_name, details, log_time 
                       FROM activity_log 
                       ORDER BY log_time DESC 
                       LIMIT $limit OFFSET $offset");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Count total logs for pagination
$total_stmt = $pdo->query("SELECT COUNT(*) FROM activity_log");
$total_logs = $total_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Helper to make long "details" strings easier to read/analyze
function format_log_details(string $action, ?string $details): string {
    if (!$details) {
        return '';
    }

    $text = trim($details);

    // Break multiple variants / changes into separate lines
    // our logs typically use " || " to separate variant descriptions
    $text = str_replace(' || ', "\n", $text);

    // For some older entries we used pipes or arrows to chain info
    $text = str_replace(' | ', "\n", $text);
    $text = str_replace(' → ', " → ", $text); // keep arrow but ensure spacing

    // For product-related actions, remove overly verbose prefixes if present
    if (in_array($action, ['Added Product', 'Updated Product', 'Deleted Product'], true)) {
        $text = str_replace(['Added product', 'Updated product'], ['', ''], $text);
        $text = trim($text);
    }

    return $text;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motor Parts Inventory - Activity Log</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }

        .header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .nav {
            display: flex;
            gap: 2rem;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        .nav a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .log-header h2 {
            color: #1e3a8a;
        }

        .btn-export {
            background-color: #16a34a;
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .btn-export:hover {
            background-color: #15803d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 0.8rem;
            text-align: left;
        }

        th {
            background-color: #1e3a8a;
            color: white;
        }

        tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .pagination {
            text-align: center;
            margin-top: 1.5rem;
        }

        .pagination a {
            display: inline-block;
            margin: 0 5px;
            padding: 0.5rem 1rem;
            color: #1e3a8a;
            text-decoration: none;
            border: 1px solid #1e3a8a;
            border-radius: 4px;
        }

        .pagination a.active {
            background-color: #1e3a8a;
            color: white;
        }

        .pagination a:hover {
            background-color: #1e40af;
            color: white;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Motor Parts Inventory</h1>
    <nav class="nav">
        <a href="index.php">Dashboard</a>
        <a href="add_product.php">Add Product</a>
        <a href="sales_history.php">Sales History</a>
        <a href="stockouts.php">Stockouts</a>
        <a href="activity_log.php">Activity Log</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="container">
    <div class="log-header">
        <h2>Activity Log</h2>
        <a href="activity_log_export.php" class="btn-export">Export CSV</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Action</th>
                <th>Product</th>
                <th>Details</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo htmlspecialchars($log['username']); ?></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td><?php echo htmlspecialchars($log['product_name']); ?></td>
                        <td>
                            <?php
                                $formatted = format_log_details($log['action'], $log['details']);
                                echo nl2br(htmlspecialchars($formatted));
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($log['log_time']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" style="text-align:center;">No activity found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
