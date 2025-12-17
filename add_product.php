<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ONLY ADMIN CAN ACCESS
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';

    $brands = $_POST['brand'] ?? [];
    $sizes = $_POST['size'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];

    if ($name === '' || $category === '') {
        $error = 'Product name and category are required.';
    } elseif (empty($brands)) {
        $error = 'Please add at least one brand/size variant.';
    } else {
        try {
            $pdo->beginTransaction();

            $addedCount = 0;
            $detailsParts = [];

            foreach ($brands as $index => $brand) {
                $brand = trim($brand);
                $size = trim($sizes[$index] ?? '');
                $qty = isset($quantities[$index]) ? (int)$quantities[$index] : 0;
                $price = isset($prices[$index]) ? (float)$prices[$index] : 0;

                // Skip completely empty rows
                if ($brand === '' && $size === '' && $qty === 0 && $price == 0.0) {
                    continue;
                }

                if ($brand === '' || $size === '' || $qty < 0 || $price < 0) {
                    throw new Exception('Please fill all variant fields correctly. Quantity and price must be non‑negative.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO products (name, brand, size, category, quantity, price)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([$name, $brand, $size, $category, $qty, $price]);

                $product_id = $pdo->lastInsertId();
                $addedCount++;

                $detailsParts[] = "ID: $product_id | Brand: $brand | Size: $size | Qty: $qty | Price: ₱$price";
            }

            if ($addedCount === 0) {
                throw new Exception('No valid variants were provided.');
            }

            // Log the action (one summary entry)
            if (function_exists('log_activity')) {
                $details = "Added product \"$name\" in category \"$category\" with variants: " . implode(' || ', $detailsParts);
                log_activity($pdo, 'Added Product', null, $name, $details);
            }

            $pdo->commit();
            $success = "Product and $addedCount variant(s) added successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to add product. ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Motor Parts Inventory</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .nav { display: flex; gap: 2rem; }
        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
        }
        .nav a:hover { background-color: rgba(255,255,255,0.1); }
        .container { max-width: 600px; margin: 2rem auto; padding: 0 2rem; }
        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-container h2 { margin-bottom: 2rem; color: #1e3a8a; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            background-color: #1e3a8a;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-secondary { background-color: #6b7280; margin-left: 1rem; }
        .success { background: #d1fae5; color: #059669; padding: 1rem; margin-bottom: 1rem; }
        .error { background: #fee2e2; color: #dc2626; padding: 1rem; margin-bottom: 1rem; }
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
    <div class="form-container">
        <h2>Add New Product</h2>

        <?php if ($success): ?><div class="success"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="">Select Category</option>
                    <option>Engine Parts</option>
                    <option>Brake System</option>
                    <option>Suspension</option>
                    <option>Electrical</option>
                    <option>Wheels</option>
                    <option>Tires</option>
                    <option>Accessories</option>
                    <option>Body Parts</option>
                    <option>Other</option>
                </select>
            </div>

            <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color:#1e3a8a;">Product Variants (Brand &amp; Size)</h3>

            <div id="variants-wrapper">
                <div class="form-group variant-row">
                    <label>Variant 1</label>
                    <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                        <input type="text" name="brand[]" placeholder="Brand (e.g. NGK, Yamaha)" style="flex:1; min-width:120px;" required>
                        <input type="text" name="size[]" placeholder="Size / Variant (e.g. Small, 10mm)" style="flex:1; min-width:120px;" required>
                        <input type="number" name="quantity[]" min="0" placeholder="Qty" style="width:90px;" required>
                        <input type="number" name="price[]" step="0.01" min="0" placeholder="Price (₱)" style="width:120px;" required>
                    </div>
                </div>
            </div>

            <button type="button" class="btn" style="background-color:#16a34a; margin-bottom:1rem;" onclick="addVariantRow()">
                + Add Another Variant
            </button>

            <button class="btn">Save Product</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>

<script>
let variantCount = 1;

function addVariantRow() {
    variantCount++;
    const wrapper = document.getElementById('variants-wrapper');
    const div = document.createElement('div');
    div.className = 'form-group variant-row';
    div.innerHTML = `
        <label>Variant ${variantCount}</label>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start;">
            <input type="text" name="brand[]" placeholder="Brand" style="flex:1; min-width:120px;">
            <input type="text" name="size[]" placeholder="Size / Variant" style="flex:1; min-width:120px;">
            <input type="number" name="quantity[]" min="0" placeholder="Qty" style="width:90px;">
            <input type="number" name="price[]" step="0.01" min="0" placeholder="Price (₱)" style="width:120px;">
            <button type="button" onclick="removeVariantRow(this)" style="background:#ef4444;color:#fff;border:none;border-radius:4px;padding:0.4rem 0.6rem;cursor:pointer;">×</button>
        </div>
    `;
    wrapper.appendChild(div);
}

function removeVariantRow(btn) {
    const row = btn.closest('.variant-row');
    if (row) {
        row.remove();
    }
}
</script>
</body>
</html>
