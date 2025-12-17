<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only admin can view stockouts dashboard
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get low stock items (quantity <= 5)
$stmt = $pdo->query("SELECT * FROM products WHERE quantity <= 5 ORDER BY quantity ASC");
$low_stock_products = $stmt->fetchAll();

// Get out of stock items (quantity = 0)
$stmt = $pdo->query("SELECT * FROM products WHERE quantity = 0 ORDER BY name ASC");
$out_of_stock_products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stockouts - Motor Parts Inventory</title>
    <style>
        :root {
            --primary: #2c3e50;
            --accent: #3b82f6;
            --danger: #ef4444;
            --light-bg: #f5f6fa;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-gray: #64748b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background-color: var(--light-bg);
            color: var(--text-dark);
        }

        /* Header */
        .header {
            background-color: var(--primary);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .nav {
            display: flex;
            gap: 1.5rem;
        }

        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.3s;
        }

        .nav a:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Stat Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 1.5rem;
            text-align: center;
            transition: 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--danger);
        }

        .stat-label {
            font-size: 1.1rem;
            color: var(--text-gray);
        }

        /* Table Section */
        .table-container {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .table-header {
            background-color: var(--danger);
            color: white;
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem 1.2rem;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }

        th {
            background-color: var(--danger);
            color: white;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f9fafb;
        }

        .stock-critical {
            color: var(--danger);
            font-weight: 700;
        }

        .stock-zero {
            background: #fee2e2;
            color: #b91c1c;
            font-weight: 700;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            transition: 0.3s;
        }

        .btn-restock {
            background-color: var(--accent);
            color: white;
        }

        .btn-restock:hover {
            background-color: #2563eb;
        }

        .no-data {
            text-align: center;
            padding: 2.5rem;
            color: var(--text-gray);
        }

        .no-data h3 {
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        @media (max-width: 768px) {
            th, td { padding: 0.8rem; }
            .header h1 { font-size: 1.3rem; }
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
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($out_of_stock_products); ?></div>
                <div class="stat-label">Out of Stock Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($low_stock_products); ?></div>
                <div class="stat-label">Low Stock Items (≤ 5)</div>
            </div>
        </div>

        <?php if (!empty($out_of_stock_products)): ?>
        <div class="table-container">
            <div class="table-header">🚫 Out of Stock Items (0 quantity)</div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Price (₱)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($out_of_stock_products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                        <td class="stock-zero"><?php echo $product['quantity']; ?></td>
                        <td><?php echo number_format($product['price'], 2); ?></td>
                        <td><a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-restock">Restock</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($low_stock_products)): ?>
        <div class="table-container">
            <div class="table-header">⚠️ Low Stock Items (≤ 5 quantity)</div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Price (₱)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                        <td class="stock-critical"><?php echo $product['quantity']; ?></td>
                        <td><?php echo number_format($product['price'], 2); ?></td>
                        <td><a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-restock">Restock</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($low_stock_products) && empty($out_of_stock_products)): ?>
        <div class="table-container">
            <div class="no-data">
                <h3>✅ All Stocks are Healthy!</h3>
                <p>No products are low or out of stock. Keep it up!</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
