<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Only admin can view sales history
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'sale_date';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "p.name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(s.sale_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(s.sale_date) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Validate sort parameters
$allowed_sorts = ['sale_date', 'product_name', 'total_price', 'sold_by'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sorts)) $sort_by = 'sale_date';
if (!in_array($sort_order, $allowed_orders)) $sort_order = 'DESC';

// Get sales history with filters
$sql = "
    SELECT s.*, p.name as product_name 
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    $where_clause
    ORDER BY $sort_by $sort_order
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sales = $stmt->fetchAll();

// Calculate statistics
$stats_sql = "SELECT 
    COUNT(*) as total_transactions,
    SUM(total_price) as total_revenue,
    SUM(quantity_sold) as total_products_sold
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    $where_clause";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute($params);
$stats = $stmt->fetch();

// Get most sold product
$most_sold_sql = "
    SELECT p.name, SUM(s.quantity_sold) as total_sold
    FROM sales s 
    JOIN products p ON s.product_id = p.id 
    $where_clause
    GROUP BY p.id, p.name 
    ORDER BY total_sold DESC 
    LIMIT 1";
$stmt = $pdo->prepare($most_sold_sql);
$stmt->execute($params);
$most_sold = $stmt->fetch();

// Get average daily sales (last 30 days)
$avg_daily_sql = "
    SELECT AVG(daily_total) as avg_daily_sales
    FROM (
        SELECT DATE(sale_date) as sale_date, SUM(total_price) as daily_total
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(sale_date)
    ) as daily_sales";
$stmt = $pdo->query($avg_daily_sql);
$avg_daily = $stmt->fetch()['avg_daily_sales'] ?? 0;

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$total_records = count($sales);
$total_pages = ceil($total_records / $limit);
$sales_paginated = array_slice($sales, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History - Motor Parts Inventory</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 6rem 2rem 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #1e3a8a;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-title i {
            font-size: 1.8rem;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .stat-card:hover {
            transform: scale(1.02);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .stat-icon.sales { color: #3b82f6; }
        .stat-icon.revenue { color: #10b981; }
        .stat-icon.products { color: #f59e0b; }
        .stat-icon.average { color: #8b5cf6; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .filters {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .filters h3 {
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #1e3a8a;
        }
        
        .filter-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: #1e3a8a;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1e40af;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #4b5563;
        }
        
        .btn-success {
            background-color: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #059669;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .table-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h3 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .table-actions {
            display: flex;
            gap: 1rem;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1.25rem 1rem;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }
        
        th {
            background-color: #f8fafc;
            color: #374151;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        
        tbody tr:hover {
            background-color: #f3f4f6;
        }
        
        .sortable {
            cursor: pointer;
            user-select: none;
            transition: color 0.3s;
        }
        
        .sortable:hover {
            color: #1e3a8a;
        }
        
        .sort-icon {
            margin-left: 0.5rem;
            opacity: 0.5;
        }
        
        .no-data {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            padding: 2rem;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .pagination button {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .pagination button:hover:not(:disabled) {
            background-color: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .current {
            background-color: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
        }
        
        .export-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 6rem 1rem 2rem;
            }
            
            .stats {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .table-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .nav {
                gap: 1rem;
            }
            
            .nav a {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Motor Parts Inventory</h1>
        <nav class="nav">
            <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php"><i class="fas fa-plus"></i> Add Product</a>
            <a href="sales_history.php"><i class="fas fa-chart-line"></i> Sales History</a>
            <a href="stockouts.php"><i class="fas fa-exclamation-triangle"></i> Stockouts</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </div>
    
    <div class="container">
        <h1 class="page-title">
            <i class="fas fa-chart-line"></i>
            Sales History & Analytics
        </h1>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-icon sales">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_transactions']); ?></div>
                <div class="stat-label">Total Sales Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-number">₱<?php echo number_format($stats['total_revenue'], 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon products">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_products_sold']); ?></div>
                <div class="stat-label">Total Products Sold</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon average">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-number">₱<?php echo number_format($avg_daily, 2); ?></div>
                <div class="stat-label">Average Daily Sales</div>
            </div>
        </div>
        
        <?php if ($most_sold): ?>
        <div class="filters">
            <h3><i class="fas fa-trophy"></i> Top Selling Product</h3>
            <p style="font-size: 1.1rem; color: #374151;">
                <strong><?php echo htmlspecialchars($most_sold['name']); ?></strong> - 
                <?php echo number_format($most_sold['total_sold']); ?> units sold
            </p>
        </div>
        <?php endif; ?>
        
        <div class="filters">
            <h3><i class="fas fa-filter"></i> Filters & Search</h3>
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">Search Product</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter product name...">
                    </div>
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="sort_by">Sort By</label>
                        <select id="sort_by" name="sort_by">
                            <option value="sale_date" <?php echo $sort_by == 'sale_date' ? 'selected' : ''; ?>>Date</option>
                            <option value="product_name" <?php echo $sort_by == 'product_name' ? 'selected' : ''; ?>>Product</option>
                            <option value="total_price" <?php echo $sort_by == 'total_price' ? 'selected' : ''; ?>>Total Price</option>
                            <option value="sold_by" <?php echo $sort_by == 'sold_by' ? 'selected' : ''; ?>>Sold By</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="sort_order">Order</label>
                        <select id="sort_order" name="sort_order">
                            <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="sales_history.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
        
        <div class="table-container">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Sales Records</h3>
                <div class="table-actions">
                    <div class="export-buttons">
                        <button onclick="exportToCSV()" class="btn btn-success btn-small">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <button onclick="window.print()" class="btn btn-secondary btn-small">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($sales_paginated)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3>No sales records found</h3>
                    <p>Try adjusting your filters or add some products to see sales here.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th class="sortable" onclick="sortTable('id')">
                                    Sale ID
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('product_name')">
                                    Product
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('quantity_sold')">
                                    Quantity
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('total_price')">
                                    Unit Price
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('total_price')">
                                    Total Price
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('sold_by')">
                                    Sold By
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th class="sortable" onclick="sortTable('sale_date')">
                                    Date & Time
                                    <i class="fas fa-sort sort-icon"></i>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales_paginated as $sale): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-box" style="color: #6b7280;"></i>
                                        <?php echo htmlspecialchars($sale['product_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo number_format($sale['quantity_sold']); ?></td>
                                <td>₱<?php echo number_format($sale['total_price'] / $sale['quantity_sold'], 2); ?></td>
                                <td><strong>₱<?php echo number_format($sale['total_price'], 2); ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-user" style="color: #6b7280;"></i>
                                        <?php echo htmlspecialchars($sale['sold_by']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem;">
                                        <div><?php echo date('M j, Y', strtotime($sale['sale_date'])); ?></div>
                                        <div style="color: #6b7280;"><?php echo date('H:i:s', strtotime($sale['sale_date'])); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <a href="receipt.php?id=<?php echo $sale['id']; ?>" class="btn btn-primary btn-small">
                                        <i class="fas fa-receipt"></i> Receipt
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <button onclick="changePage(1)" <?php echo $page == 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button onclick="changePage(<?php echo $page - 1; ?>)" <?php echo $page == 1 ? 'disabled' : ''; ?>>
                        <i class="fas fa-angle-left"></i>
                    </button>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <button onclick="changePage(<?php echo $i; ?>)" class="<?php echo $i == $page ? 'current' : ''; ?>">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    
                    <button onclick="changePage(<?php echo $page + 1; ?>)" <?php echo $page == $total_pages ? 'disabled' : ''; ?>>
                        <i class="fas fa-angle-right"></i>
                    </button>
                    <button onclick="changePage(<?php echo $total_pages; ?>)" <?php echo $page == $total_pages ? 'disabled' : ''; ?>>
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                    
                    <span style="margin-left: 1rem; color: #6b7280;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        (<?php echo number_format($total_records); ?> total records)
                    </span>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function sortTable(column) {
            const url = new URL(window.location);
            const currentSort = url.searchParams.get('sort_by');
            const currentOrder = url.searchParams.get('sort_order');
            
            if (currentSort === column) {
                url.searchParams.set('sort_order', currentOrder === 'ASC' ? 'DESC' : 'ASC');
            } else {
                url.searchParams.set('sort_by', column);
                url.searchParams.set('sort_order', 'DESC');
            }
            
            window.location.href = url.toString();
        }
        
        function changePage(page) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }
        
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    // Remove HTML tags and get text content
                    const text = cell.innerText || cell.textContent || '';
                    // Escape quotes and wrap in quotes if contains comma
                    return text.includes(',') ? `"${text.replace(/"/g, '""')}"` : text;
                }).join(',');
            }).join('\n');
            
            // Add BOM for proper UTF-8 encoding
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8;' });
            
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `sales_history_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
        }
        
        // Auto-submit form when date filters change
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value) {
                        document.querySelector('.filter-form').submit();
                    }
                });
            });
        });
    </script>
</body>
</html>