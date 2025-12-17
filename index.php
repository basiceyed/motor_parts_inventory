<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch role
$role = $_SESSION['role'] ?? 'staff';

// Dashboard Stats
$total_products = $pdo->query("SELECT COUNT(*) as total FROM products")->fetch()['total'];
$low_stock = $pdo->query("SELECT COUNT(*) as low FROM products WHERE quantity <= 5")->fetch()['low'];

// Products List
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

// Recent Sales
$stmt = $pdo->query("
    SELECT s.*, p.name AS product_name 
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    ORDER BY s.sale_date DESC 
    LIMIT 5
");
$recent_sales = $stmt->fetchAll();

// Top 10 Selling Products
$top_products_stmt = $pdo->query("
    SELECT p.name, SUM(s.quantity_sold) AS total_sold
    FROM sales s
    JOIN products p ON s.product_id = p.id
    GROUP BY s.product_id
    ORDER BY total_sold DESC
    LIMIT 10
");
$top_products = $top_products_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motor Parts Inventory</title>
    <style>
        :root {
            --primary: #1abc9c;
            --dark: #2c3e50;
            --accent: #22c55e;
            --danger: #ef4444;
            --info: #3b82f6;
            --bg: #f4f6f8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: var(--bg);
            color: #333;
        }

        header {
            background: var(--dark);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-left: 1.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            transition: 0.3s;
        }

        nav a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .container {
            max-width: 1300px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .welcome {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 1.5rem;
            text-align: center;
            transition: 0.3s;
        }

        .stat-card:hover { transform: translateY(-3px); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label { color: #666; }

        .search-box {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .search-input {
            width: 100%;
            padding: 0.8rem;
            font-size: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .product-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: 0.3s;
        }

        .product-card:hover { transform: translateY(-4px); }

        .product-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .product-info {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1rem;
        }

        .btn {
            text-decoration: none;
            border: none;
            border-radius: 6px;
            padding: 0.5rem 0.9rem;
            font-size: 0.9rem;
            cursor: pointer;
            margin-right: 0.4rem;
            display: inline-block;
            transition: 0.3s;
        }

        .btn:hover { opacity: 0.9; }
        .btn-edit { background: var(--info); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-sell { background: var(--accent); color: white; }

        .recent-sales, .top-products {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .recent-sales h3, .top-products h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }

        @media (max-width: 700px) {
            nav { display: flex; flex-wrap: wrap; }
            nav a { margin-bottom: 0.5rem; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Motor Parts Inventory</h1>
        <nav>
            <a href="index.php">Dashboard</a>
            <?php if ($role === 'admin'): ?>
                <a href="add_product.php">Add Product</a>
                <a href="sales_history.php">Sales History</a>
                <a href="stockouts.php">Stockouts</a>
                <a href="activity_log.php">Activity Log</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="container">
        <div class="welcome">
            👋 Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
        </div>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $low_stock; ?></div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>

        <div class="search-box">
            <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search products...">
        </div>

        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <div class="product-info">
                        <p>Category: <strong><?php echo htmlspecialchars($product['category']); ?></strong></p>
                        <p>Quantity: <strong><?php echo $product['quantity']; ?></strong></p>
                        <p>Price: ₱<?php echo number_format($product['price'], 2); ?></p>
                    </div>
                    <div class="actions">
                        <?php if ($role === 'admin'): ?>
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit">Edit</a>
                            <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this?')">Delete</a>
                        <?php endif; ?>
                        <a href="sell_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sell">Sell</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="recent-sales">
            <h3>📊 Recent Sales</h3>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total (₱)</th>
                        <th>Sold By</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sales as $sale): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                            <td><?php echo $sale['quantity_sold']; ?></td>
                            <td><?php echo number_format($sale['total_price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($sale['sold_by']); ?></td>
                            <td><?php echo date('M j, Y H:i', strtotime($sale['sale_date'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="top-products">
            <h3>🏆 Top 10 Best-Selling Products</h3>
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Product Name</th>
                        <th>Total Sold</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $rank = 1;
                    foreach ($top_products as $prod): ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($prod['name']); ?></td>
                            <td><?php echo $prod['total_sold']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('searchInput').addEventListener('input', function() {
            const term = this.value.toLowerCase();
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                card.style.display = card.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    </script>

    <?php if (isset($_GET['receipt'])): ?>
    <script>
        // Open receipt in a popup window after a completed sale
        window.addEventListener('load', function () {
            var receiptId = <?php echo json_encode($_GET['receipt']); ?>;
            if (!receiptId) return;
            window.open(
                'receipt.php?id=' + receiptId,
                'ReceiptWindow',
                'width=500,height=700,menubar=no,toolbar=no,location=no,status=no'
            );
        });
    </script>
    <?php endif; ?>
</body>
</html>
