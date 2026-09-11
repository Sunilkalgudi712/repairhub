<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add Inventory Item';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = "Invalid CSRF token.";
        $_SESSION['flash_type'] = "danger";
    } else {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $min_stock_level = (int)($_POST['min_stock_level'] ?? 5);
        $cost_price = (float)($_POST['cost_price'] ?? 0);
        $selling_price = (float)($_POST['selling_price'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');
        $location = trim($_POST['location'] ?? '');

        if (empty($name)) {
            $_SESSION['flash_message'] = "Item name is required.";
            $_SESSION['flash_type'] = "danger";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO inventory (name, sku, category, description, quantity, min_stock_level, cost_price, selling_price, supplier, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $sku, $category, $description, $quantity, $min_stock_level, $cost_price, $selling_price, $supplier, $location]);
                
                $itemId = $pdo->lastInsertId();

                // Log inventory history
                $createDesc = "Item created by " . ($_SESSION['user_name'] ?? 'User') . " with initial stock of {$quantity} units. Category: " . ($category ?: 'Uncategorized') . ", Cost: " . CURRENCY_SYMBOL . number_format($cost_price, 2) . ", Price: " . CURRENCY_SYMBOL . number_format($selling_price, 2) . ".";
                logInventoryHistory($pdo, $itemId, $name, 'created', 'all', null, $quantity, $createDesc);

                $_SESSION['flash_message'] = "Item added successfully.";
                $_SESSION['flash_type'] = "success";
                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
                $_SESSION['flash_type'] = "danger";
            }
        }
    }
}

// Fetch categories for datalist
$categories = [];
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM inventory WHERE category != '' AND category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Ignore error
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plus-circle me-2"></i> Add Inventory Item</h2>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Inventory</a>
    </div>

    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form method="POST" action="create.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="col-md-6">
                        <label for="sku" class="form-label">SKU</label>
                        <input type="text" class="form-control" id="sku" name="sku">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="category" class="form-label">Category</label>
                        <input class="form-control" list="categoryOptions" id="category" name="category" placeholder="Type to search...">
                        <datalist id="categoryOptions">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label for="supplier" class="form-label">Supplier</label>
                        <input type="text" class="form-control" id="supplier" name="supplier">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="quantity" class="form-label">Initial Quantity</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" value="0" min="0">
                    </div>
                    <div class="col-md-3">
                        <label for="min_stock_level" class="form-label">Min Stock Level</label>
                        <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="5" min="0">
                    </div>
                    <div class="col-md-3">
                        <label for="cost_price" class="form-label">Cost Price (₹)</label>
                        <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="0.00" min="0">
                    </div>
                    <div class="col-md-3">
                        <label for="selling_price" class="form-label">Selling Price (₹)</label>
                        <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" value="0.00" min="0">
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="location" class="form-label">Location in Store (e.g. A1, B2)</label>
                        <input type="text" class="form-control" id="location" name="location">
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="reset" class="btn btn-outline-secondary me-2">Clear</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Item</button>
                </div>
            </form>
        </div>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
