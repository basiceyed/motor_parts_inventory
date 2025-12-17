<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$sale = null;

if ($sale_id) {
    // Get sale details with product information
    $stmt = $pdo->prepare("
        SELECT 
            s.*, 
            p.name as product_name, 
            p.brand as product_brand,
            p.size as product_size,
            p.price as unit_price 
        FROM sales s 
        JOIN products p ON s.product_id = p.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();
}

if (!$sale) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Motor Parts Inventory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #1e3a8a;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .receipt-header h1 {
            color: #1e3a8a;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .receipt-header p {
            color: #666;
            font-size: 14px;
        }
        
        .receipt-info {
            margin-bottom: 20px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: bold;
            color: #333;
        }
        
        .info-value {
            color: #666;
        }
        
        .receipt-items {
            margin-bottom: 20px;
        }
        
        .items-header {
            background-color: #1e3a8a;
            color: white;
            padding: 10px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .item-name {
            flex: 2;
            font-weight: bold;
        }
        
        .item-qty {
            flex: 1;
            text-align: center;
        }
        
        .item-price {
            flex: 1;
            text-align: right;
        }
        
        .receipt-total {
            border-top: 2px solid #1e3a8a;
            padding-top: 15px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .total-label {
            font-weight: bold;
        }
        
        .total-amount {
            font-weight: bold;
            color: #1e3a8a;
            font-size: 18px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 12px;
        }
        
        .print-buttons {
            text-align: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        
        .btn-print {
            background-color: #1e3a8a;
            color: white;
        }
        
        .btn-back {
            background-color: #6b7280;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
                margin: 0;
                max-width: none;
            }
            
            .print-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <h1>MOTOR PARTS INVENTORY</h1>
            <p>Sales Receipt</p>
        </div>
        
        <div class="receipt-info">
            <div class="info-row">
                <span class="info-label">Receipt #:</span>
                <span class="info-value"><?php echo str_pad($sale['id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Product:</span>
                <span class="info-value">
                    <?php echo htmlspecialchars($sale['product_name']); ?>
                    <?php if (!empty($sale['product_brand']) || !empty($sale['product_size'])): ?>
                        (<?php echo htmlspecialchars(trim($sale['product_brand'] . ' ' . $sale['product_size'])); ?>)
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value"><?php echo date('M j, Y', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Time:</span>
                <span class="info-value"><?php echo date('H:i:s', strtotime($sale['sale_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Sold By:</span>
                <span class="info-value"><?php echo htmlspecialchars($sale['sold_by']); ?></span>
            </div>
        </div>
        
        <div class="receipt-items">
            <div class="items-header">ITEM DETAILS</div>
            <div class="item-row">
                <span class="item-name"><?php echo htmlspecialchars($sale['product_name']); ?></span>
                <span class="item-qty"><?php echo $sale['quantity_sold']; ?></span>
                <span class="item-price">₱<?php echo number_format($sale['unit_price'], 2); ?></span>
            </div>
        </div>
        
        <div class="receipt-total">
            <div class="total-row">
                <span class="total-label">Subtotal:</span>
                <span class="total-amount">₱<?php echo number_format($sale['quantity_sold'] * $sale['unit_price'], 2); ?></span>
            </div>
            <div class="total-row">
                <span class="total-label">Total Amount:</span>
                <span class="total-amount">₱<?php echo number_format($sale['total_price'], 2); ?></span>
            </div>
        </div>
        
        <div class="receipt-footer">
            <p>Thank you for your business!</p>
            <p>Motor Parts Inventory System</p>
        </div>
    </div>
    
    <div class="print-buttons">
        <button onclick="window.print()" class="btn btn-print">Print Receipt</button>
        <a href="index.php" class="btn btn-back">Back to Dashboard</a>
    </div>
    
    <script>
        // Auto print when page loads (optional)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>