<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Restrict access: only admin can edit
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$product_id = $_GET['id'] ?? null;
$product = null;
$variants = [];
$success = '';
$error = '';

if ($product_id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Fetch all variants that share the same name & category
        $vstmt = $pdo->prepare("SELECT * FROM products WHERE name = ? AND category = ? ORDER BY brand, size");
        $vstmt->execute([$product['name'], $product['category']]);
        $variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!$product) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category = $_POST['category'] ?? '';

    $ids       = $_POST['variant_id'] ?? [];
    $brands    = $_POST['variant_brand'] ?? [];
    $sizes     = $_POST['variant_size'] ?? [];
    $quantities= $_POST['variant_quantity'] ?? [];
    $prices    = $_POST['variant_price'] ?? [];

    if ($name === '' || $category === '') {
        $error = 'Product name and category are required.';
    } else {
        try {
            $pdo->beginTransaction();

            $updatedDetails = [];

            foreach ($brands as $index => $brand) {
                $brand = trim($brand);
                $size  = trim($sizes[$index] ?? '');
                $qty   = isset($quantities[$index]) ? (int)$quantities[$index] : 0;
                $price = isset($prices[$index]) ? (float)$prices[$index] : 0;
                $id    = isset($ids[$index]) && $ids[$index] !== '' ? (int)$ids[$index] : null;

                // Skip completely empty rows
                if ($brand === '' && $size === '' && $qty === 0 && $price == 0.0 && $id === null) {
                    continue;
                }

                if ($brand === '' || $size === '' || $qty < 0 || $price < 0) {
                    throw new Exception('Please fill all variant fields correctly. Quantity and price must be non‑negative.');
                }

                if ($id !== null) {
                    // Update existing variant
                    $oldStmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
                    $oldStmt->execute([$id]);
                    $old = $oldStmt->fetch(PDO::FETCH_ASSOC);

                    if ($old) {
                        $upd = $pdo->prepare("
                            UPDATE products
                            SET name = ?, category = ?, brand = ?, size = ?, price = ?, quantity = ?
                            WHERE id = ?
                        ");
                        $upd->execute([$name, $category, $brand, $size, $price, $qty, $id]);

                        $updatedDetails[] = "Updated ID {$id}: "
                            . "({$old['brand']} {$old['size']} - qty {$old['quantity']} @ ₱{$old['price']})"
                            . " → ({$brand} {$size} - qty {$qty} @ ₱{$price})";
                    }
                } else {
                    // Insert new variant
                    $ins = $pdo->prepare("
                        INSERT INTO products (name, brand, size, category, quantity, price)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $ins->execute([$name, $brand, $size, $category, $qty, $price]);
                    $newId = $pdo->lastInsertId();

                    $updatedDetails[] = "Added ID {$newId}: {$brand} {$size} - qty {$qty} @ ₱{$price}";
                }
            }

            if (empty($updatedDetails)) {
                throw new Exception('No valid changes were submitted.');
            }

            if (function_exists('log_activity')) {
                $details = 'Updated product "' . $name . '" in category "' . $category . '". Changes: ' . implode(' || ', $updatedDetails);
                // We log against the primary product id that was initially opened
                log_activity($pdo, 'Updated Product', $product_id, $name, $details);
            }

            $pdo->commit();
            $success = '✅ Product variants updated successfully!';

            // Reload variants with latest data
            $vstmt = $pdo->prepare("SELECT * FROM products WHERE name = ? AND category = ? ORDER BY brand, size");
            $vstmt->execute([$name, $category]);
            $variants = $vstmt->fetchAll(PDO::FETCH_ASSOC);
            $product['name'] = $name;
            $product['category'] = $category;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '❌ Failed to update product. ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Motor Parts Inventory</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; }

        .header {
            background-color: #2c3e50; /* match dashboard navigation */
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .nav { display: flex; gap: 2rem; }
        .nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .nav a:hover { background-color: rgba(255, 255, 255, 0.1); }

        .container { max-width: 700px; margin: 2.5rem auto; padding: 0 1.5rem; }
        .form-container {
            background: white;
            padding: 2.25rem 2rem;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(15,23,42,0.12);
        }
        .form-container h2 { margin-bottom: 2rem; color: #2c3e50; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #374151; font-weight: 500; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 1px rgba(44,62,80,0.15);
        }
        .btn {
            background-color: #22c55e;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            transition: background-color 0.2s, transform 0.1s;
        }
        .btn:hover { background-color: #16a34a; transform: translateY(-1px); }
        .btn-secondary { background-color: #6b7280; margin-left: 1rem; }
        .btn-secondary:hover { background-color: #4b5563; }
        .success { background-color: #d1fae5; color: #059669; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .error { background-color: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
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
        <div class="form-container">
            <h2>Edit Product &amp; Variants</h2>
            
            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <?php
                        $categories = ['Engine Parts', 'Brake System', 'Suspension', 'Electrical', 'Wheels', 'Tires', 'Accessories', 'Body Parts', 'Other'];
                        foreach ($categories as $cat) {
                            $selected = ($product['category'] === $cat) ? 'selected' : '';
                            echo "<option value='$cat' $selected>$cat</option>";
                        }
                        ?>
                    </select>
                </div>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; color:#1e3a8a;">Product Variants (Brand &amp; Size)</h3>

                <div id="variants-wrapper">
                    <?php $rowIndex = 0; foreach ($variants as $v): $rowIndex++; ?>
                        <div class="form-group variant-row">
                            <input type="hidden" name="variant_id[]" value="<?= htmlspecialchars($v['id']) ?>">
                            <label>Variant <?= $rowIndex ?></label>
                            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start;">
                                <input type="text" name="variant_brand[]" value="<?= htmlspecialchars($v['brand']) ?>" placeholder="Brand" style="flex:1; min-width:120px;" required>
                                <input type="text" name="variant_size[]" value="<?= htmlspecialchars($v['size']) ?>" placeholder="Size / Variant" style="flex:1; min-width:120px;" required>
                                <input type="number" name="variant_quantity[]" value="<?= htmlspecialchars($v['quantity']) ?>" min="0" placeholder="Qty" style="width:90px;" required>
                                <input type="number" name="variant_price[]" value="<?= htmlspecialchars($v['price']) ?>" min="0" step="0.01" placeholder="Price (₱)" style="width:120px;" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn" style="background-color:#16a34a; margin-bottom:1rem;" onclick="addVariantRow()">
                    + Add Another Variant
                </button>

                <button type="submit" class="btn">Save Changes</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script>
    let variantCount = <?= count($variants) ?>;

    function addVariantRow() {
        variantCount++;
        const wrapper = document.getElementById('variants-wrapper');
        const div = document.createElement('div');
        div.className = 'form-group variant-row';
        div.innerHTML = `
            <input type="hidden" name="variant_id[]" value="">
            <label>Variant ${variantCount}</label>
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:flex-start;">
                <input type="text" name="variant_brand[]" placeholder="Brand" style="flex:1; min-width:120px;">
                <input type="text" name="variant_size[]" placeholder="Size / Variant" style="flex:1; min-width:120px;">
                <input type="number" name="variant_quantity[]" min="0" placeholder="Qty" style="width:90px;">
                <input type="number" name="variant_price[]" min="0" step="0.01" placeholder="Price (₱)" style="width:120px;">
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
