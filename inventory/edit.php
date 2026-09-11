<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = $_GET['id'] ?? null;
if (!$id) {
    $_SESSION['flash_message'] = "Invalid item ID.";
    $_SESSION['flash_type'] = "danger";
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $_SESSION['flash_message'] = "Item not found.";
        $_SESSION['flash_type'] = "danger";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = "Invalid CSRF token.";
        $_SESSION['flash_type'] = "danger";
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            try {
                $delStmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $delStmt->execute([$id]);

                // Log activity
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'Item Deleted', ?, NOW())");
                $logStmt->execute([$_SESSION['user_id'], "Deleted inventory item: {$item['name']}"]);

                $_SESSION['flash_message'] = "Item deleted successfully.";
                $_SESSION['flash_type'] = "success";
                header("Location: index.php");
                exit;
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
                $_SESSION['flash_type'] = "danger";
            }
        } else {
            $name = trim($_POST['name'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
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
                    $updStmt = $pdo->prepare("UPDATE inventory SET name = ?, sku = ?, category = ?, description = ?, min_stock_level = ?, cost_price = ?, selling_price = ?, supplier = ?, location = ? WHERE id = ?");
                    $updStmt->execute([$name, $sku, $category, $description, $min_stock_level, $cost_price, $selling_price, $supplier, $location, $id]);

                    // Log activity
                    $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'Item Updated', ?, NOW())");
                    $logStmt->execute([$_SESSION['user_id'], "Updated inventory item: $name (ID: $id)"]);

                    $_SESSION['flash_message'] = "Item updated successfully.";
                    $_SESSION['flash_type'] = "success";
                    header("Location: edit.php?id=$id");
                    exit;
                } catch (PDOException $e) {
                    $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
                    $_SESSION['flash_type'] = "danger";
                }
            }
        }
    }
}

// Fetch categories for datalist
$categories = [];
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM inventory WHERE category != '' AND category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Fetch adjustments
$adjustments = [];
try {
    $adjStmt = $pdo->prepare("SELECT sa.*, u.name as user_name FROM stock_adjustments sa LEFT JOIN users u ON sa.user_id = u.id WHERE sa.inventory_id = ? ORDER BY sa.created_at DESC LIMIT 50");
    $adjStmt->execute([$id]);
    $adjustments = $adjStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If table doesn't exist, ignore
}

$pageTitle = 'Edit Inventory Item';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-edit me-2"></i> Edit Item: <?= htmlspecialchars($item['name']) ?></h2>
        <div>
            <a href="adjust.php?id=<?= $id ?>" class="btn btn-secondary me-2"><i class="fas fa-boxes me-1"></i> Adjust Stock</a>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back</a>
        </div>
    </div>

    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <form method="POST" action="edit.php?id=<?= $id ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="sku" class="form-label">SKU</label>
                                <input type="text" class="form-control" id="sku" name="sku" value="<?= htmlspecialchars($item['sku'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <input class="form-control" list="categoryOptions" id="category" name="category" value="<?= htmlspecialchars($item['category'] ?? '') ?>">
                                <datalist id="categoryOptions">
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label for="supplier" class="form-label">Supplier</label>
                                <input type="text" class="form-control" id="supplier" name="supplier" value="<?= htmlspecialchars($item['supplier'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Current Quantity</label>
                                <input type="text" class="form-control bg-light" value="<?= number_format($item['quantity']) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="min_stock_level" class="form-label">Min Stock Level</label>
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="<?= $item['min_stock_level'] ?>" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="cost_price" class="form-label">Cost Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="<?= $item['cost_price'] ?>" min="0">
                            </div>
                            <div class="col-md-3">
                                <label for="selling_price" class="form-label">Selling Price (₹)</label>
                                <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" value="<?= $item['selling_price'] ?>" min="0">
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="location" class="form-label">Location in Store</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($item['location'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="fas fa-trash me-1"></i> Delete Item</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h5 class="card-title mb-0">Stock Adjustments History</h5>
                </div>
                <div class="card-body">
                    <?php if (count($adjustments) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($adjustments as $adj): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong>
                                            <?php if($adj['type'] == 'Add'): ?>
                                                <span class="text-success"><i class="fas fa-arrow-up"></i> Added</span>
                                            <?php elseif($adj['type'] == 'Remove'): ?>
                                                <span class="text-danger"><i class="fas fa-arrow-down"></i> Removed</span>
                                            <?php else: ?>
                                                <span class="text-primary"><i class="fas fa-equals"></i> Set</span>
                                            <?php endif; ?>
                                        </strong>
                                        <span class="badge bg-secondary"><?= $adj['quantity'] ?></span>
                                    </div>
                                    <p class="mb-1 small text-muted"><?= htmlspecialchars($adj['reason'] ?? '') ?></p>
                                    <div class="d-flex justify-content-between text-muted" style="font-size: 0.8rem;">
                                        <span><?= htmlspecialchars($adj['user_name'] ?? 'System') ?></span>
                                        <span><?= date('M d, Y H:i', strtotime($adj['created_at'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No stock adjustments recorded.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <strong><?= htmlspecialchars($item['name']) ?></strong>? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="edit.php?id=<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
