<?php
require_once 'config.php';

/* -----------------------------
   AUTH CHECK
----------------------------- */
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

/* -----------------------------
   STAFF-ONLY SELLING
----------------------------- */
if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    header('Location: index.php');
    exit();
}


/* -----------------------------
   FETCH PRODUCT & VARIANTS
----------------------------- */
$product_id = $_GET['id'] ?? null;
$product = null;
$error = '';
$variants = [];

if ($product_id) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}

if (!$product) {
    header('Location: index.php');
    exit();
}

// Fetch all variants with same name + category for brand/size selection
$variant_stmt = $pdo->prepare("SELECT * FROM products WHERE name = ? AND category = ? ORDER BY brand, size");
$variant_stmt->execute([$product['name'], $product['category']]);
$variants = $variant_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build arrays of brands and sizes for current selection
$brands = [];
foreach ($variants as $v) {
    if (!in_array($v['brand'], $brands, true)) {
        $brands[] = $v['brand'];
    }
}

/* -----------------------------
   HANDLE SALE
----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_brand = $_POST['brand'] ?? $product['brand'];
    $selected_size = $_POST['size'] ?? $product['size'];

    // Find the exact variant being sold
    $variant_lookup = $pdo->prepare("
        SELECT * FROM products
        WHERE name = ? AND category = ? AND brand = ? AND size = ?
        LIMIT 1
    ");
    $variant_lookup->execute([
        $product['name'],
        $product['category'],
        $selected_brand,
        $selected_size
    ]);
    $selectedProduct = $variant_lookup->fetch(PDO::FETCH_ASSOC);

    if (!$selectedProduct) {
        $error = 'Selected brand/size combination is invalid.';
    } else {
        // Use the selected variant as the active product for stock/sale
        $product = $selectedProduct;
        $product_id = $product['id'];

        $quantity_to_sell = (int)$_POST['quantity'];

        if ($quantity_to_sell <= 0) {
            $error = 'Quantity must be greater than 0';
        } elseif ($quantity_to_sell > $product['quantity']) {
            $error = 'Not enough stock available. Available: ' . $product['quantity'];
        } else {
            try {
                $pdo->beginTransaction();

                // Update stock for selected variant
                $stmt = $pdo->prepare(
                    "UPDATE products SET quantity = quantity - ? WHERE id = ?"
                );
                $stmt->execute([$quantity_to_sell, $product_id]);

                // Record sale
                $total_price = $quantity_to_sell * $product['price'];
                $stmt = $pdo->prepare(
                    "INSERT INTO sales (product_id, quantity_sold, total_price, sold_by)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $product_id,
                    $quantity_to_sell,
                    $total_price,
                    $_SESSION['username']
                ]);

                $saleId = $pdo->lastInsertId();

                // Optional: log activity for audit trail
                if (function_exists('log_activity')) {
                    $details = "Sold {$quantity_to_sell} x {$product['name']} ({$product['brand']} - {$product['size']}) "
                             . "at ₱{$product['price']} each. Total: ₱{$total_price}";
                    log_activity($pdo, 'Product Sold', $product_id, $product['name'], $details);
                }

                $pdo->commit();

                // Redirect back to dashboard and trigger receipt popup
                header("Location: index.php?receipt=" . $saleId);
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Sale failed. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sell Product - Motor Parts Inventory</title>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }

.header {
    background: #2c3e50; /* same dark blue-gray as dashboard */
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.nav a {
    color: white;
    text-decoration: none;
    padding: .5rem 1rem;
    border-radius: 4px;
    transition: background-color 0.3s ease;
}

.nav a:hover { background: rgba(255,255,255,.1); }

.container {
    max-width: 650px;
    margin: 2.5rem auto;
    padding: 0 1.5rem;
}

.form-container {
    background: white;
    padding: 2.25rem 2rem;
    border-radius: 10px;
    box-shadow: 0 8px 20px rgba(15,23,42,0.12);
}

.form-container h2 {
    margin-bottom: 1.5rem;
    color: #2c3e50;
}

.product-info {
    background: #f8fafc;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    border: 1px solid #e5e7eb;
}

.info-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: .5rem;
    font-size: 0.95rem;
}

.info-label { color: #6b7280; font-weight: 600; }

.form-group { margin-bottom: 1.25rem; }

.form-group input,
.form-group select {
    width: 100%;
    padding: .75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.95rem;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #2c3e50;
    box-shadow: 0 0 0 1px rgba(44,62,80,0.15);
}

.btn {
    background: #22c55e;
    color: white;
    padding: .75rem 2rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 500;
    transition: background-color 0.2s ease, transform 0.1s ease;
}

.btn:hover { background: #16a34a; transform: translateY(-1px); }

.btn-secondary {
    background: #6b7280;
    margin-left: 1rem;
}

.btn-secondary:hover { background: #4b5563; }

.btn-disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.error {
    background: #fee2e2;
    color: #dc2626;
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}
</style>
</head>

<body>

<div class="header">
    <h1>Motor Parts Inventory</h1>
    <nav class="nav">
        <a href="index.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div class="container">
<div class="form-container">

<h2>Sell Product</h2>

<div class="product-info">
    <div class="info-row">
        <span class="info-label">Name:</span>
        <span><?= htmlspecialchars($product['name']) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Category:</span>
        <span><?= htmlspecialchars($product['category']) ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Available:</span>
        <span id="availableDisplay"><?= $product['quantity'] ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Price:</span>
        <span id="priceDisplay">₱<?= number_format($product['price'], 2) ?></span>
    </div>
</div>

<?php if ($error): ?>
    <div class="error"><?= $error ?></div>
<?php endif; ?>

<form method="POST">

    <div class="form-group">
        <label>Brand</label>
        <select name="brand" id="brandSelect" required>
            <?php foreach ($brands as $brand): ?>
                <option value="<?= htmlspecialchars($brand) ?>"
                    <?= (($_POST['brand'] ?? $product['brand']) === $brand) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($brand) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Size / Variant</label>
        <select name="size" id="sizeSelect" required>
            <?php
            $currentBrand = $_POST['brand'] ?? $product['brand'];
            $currentSize = $_POST['size'] ?? $product['size'];
            foreach ($variants as $v):
                if ($v['brand'] !== $currentBrand) {
                    continue;
                }
            ?>
                <option value="<?= htmlspecialchars($v['size']) ?>"
                    <?= ($currentSize === $v['size']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['size']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Quantity to Sell</label>
        <input type="number"
               name="quantity"
               min="1"
               max="<?= $product['quantity'] ?>"
               <?= $product['quantity'] == 0 ? 'disabled' : '' ?>
               required>
    </div>

    <?php if ($product['quantity'] > 0): ?>
        <button type="submit" class="btn">Complete Sale</button>
    <?php else: ?>
        <button type="button" class="btn btn-disabled">Out of Stock</button>
    <?php endif; ?>

    <a href="index.php" class="btn btn-secondary">Cancel</a>

</form>

</div>
</div>

<script>
// Build a JS map of variants to update fields dynamically
const variants = <?php
    $jsVariants = [];
    foreach ($variants as $v) {
        $key = $v['brand'] . '||' . $v['size'];
        $jsVariants[$key] = [
            'quantity' => (int)$v['quantity'],
            'price' => (float)$v['price']
        ];
    }
    echo json_encode($jsVariants);
?>;

const brandSelect = document.getElementById('brandSelect');
const sizeSelect = document.getElementById('sizeSelect');
const availableDisplay = document.getElementById('availableDisplay');
const priceDisplay = document.getElementById('priceDisplay');
const quantityInput = document.querySelector('input[name="quantity"]');

function updateSizes() {
    const selectedBrand = brandSelect.value;
    const currentSize = sizeSelect.value;
    const sizes = [];

    // Collect sizes for selected brand
    for (const key in variants) {
        const [brand, size] = key.split('||');
        if (brand === selectedBrand && !sizes.includes(size)) {
            sizes.push(size);
        }
    }

    // Rebuild size options
    sizeSelect.innerHTML = '';
    sizes.forEach(size => {
        const opt = document.createElement('option');
        opt.value = size;
        opt.textContent = size;
        if (size === currentSize) {
            opt.selected = true;
        }
        sizeSelect.appendChild(opt);
    });

    updateVariantInfo();
}

function updateVariantInfo() {
    const key = brandSelect.value + '||' + sizeSelect.value;
    const variant = variants[key];
    if (!variant) return;

    availableDisplay.textContent = variant.quantity;
    priceDisplay.textContent = '₱' + Number(variant.price).toFixed(2);

    if (quantityInput) {
        quantityInput.max = variant.quantity;
        quantityInput.disabled = variant.quantity === 0;
        if (quantityInput.value === '' || Number(quantityInput.value) < 1) {
            quantityInput.value = variant.quantity > 0 ? 1 : 0;
        }
    }
}

if (brandSelect && sizeSelect) {
    brandSelect.addEventListener('change', updateSizes);
    sizeSelect.addEventListener('change', updateVariantInfo);

    // Initialize on load
    updateVariantInfo();
}
</script>

</body>
</html>
