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
                $delDesc = "Item deleted by " . ($_SESSION['user_name'] ?? 'User') . ". Final stock was {$item['quantity']} units.";
                logInventoryHistory($pdo, $id, $item['name'], 'deleted', 'all', $item['quantity'], null, $delDesc);

                $delStmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
                $delStmt->execute([$id]);

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

                    // Track changes in inventory_history
                    if ($item['category'] !== $category) {
                        $desc = "Category changed from '" . ($item['category'] ?: 'None') . "' to '" . ($category ?: 'None') . "'";
                        logInventoryHistory($pdo, $id, $name, 'category_change', 'category', $item['category'], $category, $desc);
                    }

                    if ((float)$item['cost_price'] != (float)$cost_price) {
                        $desc = "Cost price changed from " . CURRENCY_SYMBOL . number_format($item['cost_price'], 2) . " to " . CURRENCY_SYMBOL . number_format($cost_price, 2);
                        logInventoryHistory($pdo, $id, $name, 'price_change', 'cost_price', $item['cost_price'], $cost_price, $desc);
                    }

                    if ((float)$item['selling_price'] != (float)$selling_price) {
                        $desc = "Selling price changed from " . CURRENCY_SYMBOL . number_format($item['selling_price'], 2) . " to " . CURRENCY_SYMBOL . number_format($selling_price, 2);
                        logInventoryHistory($pdo, $id, $name, 'price_change', 'selling_price', $item['selling_price'], $selling_price, $desc);
                    }

                    if ($item['name'] !== $name) {
                        $desc = "Item name changed from '{$item['name']}' to '{$name}'";
                        logInventoryHistory($pdo, $id, $name, 'updated', 'name', $item['name'], $name, $desc);
                    }

                    if (($item['sku'] ?? '') !== $sku) {
                        $desc = "SKU changed from '" . ($item['sku'] ?: 'None') . "' to '" . ($sku ?: 'None') . "'";
                        logInventoryHistory($pdo, $id, $name, 'updated', 'sku', $item['sku'], $sku, $desc);
                    }

                    if ((int)$item['min_stock_level'] != (int)$min_stock_level) {
                        $desc = "Min stock level changed from {$item['min_stock_level']} to {$min_stock_level}";
                        logInventoryHistory($pdo, $id, $name, 'updated', 'min_stock_level', $item['min_stock_level'], $min_stock_level, $desc);
                    }

                    if (($item['supplier'] ?? '') !== $supplier) {
                        $desc = "Supplier changed from '" . ($item['supplier'] ?: 'None') . "' to '" . ($supplier ?: 'None') . "'";
                        logInventoryHistory($pdo, $id, $name, 'updated', 'supplier', $item['supplier'], $supplier, $desc);
                    }

                    if (($item['location'] ?? '') !== $location) {
                        $desc = "Location changed from '" . ($item['location'] ?: 'None') . "' to '" . ($location ?: 'None') . "'";
                        logInventoryHistory($pdo, $id, $name, 'updated', 'location', $item['location'], $location, $desc);
                    }

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

// Fetch full item history
$itemHistory = [];
try {
    $histStmt = $pdo->prepare("SELECT * FROM inventory_history WHERE inventory_id = ? ORDER BY created_at DESC LIMIT 50");
    $histStmt->execute([$id]);
    $itemHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);

    // If empty, auto-seed initial record from existing item
    if (empty($itemHistory) && !empty($item)) {
        $initDesc = "Initial item record. Starting stock: {$item['quantity']} units. Category: " . ($item['category'] ?: 'Uncategorized') . ", Cost: " . CURRENCY_SYMBOL . number_format($item['cost_price'], 2) . ", Price: " . CURRENCY_SYMBOL . number_format($item['selling_price'], 2);
        logInventoryHistory($pdo, $item['id'], $item['name'], 'created', 'all', null, $item['quantity'], $initDesc, 1, 'Admin');

        // Re-fetch
        $histStmt->execute([$id]);
        $itemHistory = $histStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // If table doesn't exist yet, ignore
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
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="fas fa-history text-primary me-2"></i> Audit & Stock History</h5>
                    <a href="history.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary" title="View Full Log">
                        <i class="fas fa-external-link-alt"></i> All
                    </a>
                </div>
                <div class="card-body p-3" style="max-height: 520px; overflow-y: auto;">
                    <?php if (count($itemHistory) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($itemHistory as $hist): ?>
                                <li class="list-group-item px-0 py-2 border-bottom">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <?php if ($hist['action_type'] === 'created'): ?>
                                            <span class="badge bg-success"><i class="fas fa-plus-circle me-1"></i> Added</span>
                                        <?php elseif ($hist['action_type'] === 'quantity_change'): ?>
                                            <span class="badge bg-primary"><i class="fas fa-boxes me-1"></i> Stock Change</span>
                                        <?php elseif ($hist['action_type'] === 'price_change'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-tags me-1"></i> Price</span>
                                        <?php elseif ($hist['action_type'] === 'category_change'): ?>
                                            <span class="badge bg-info text-white"><i class="fas fa-folder me-1"></i> Category</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-edit me-1"></i> Updated</span>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted" style="font-size: 0.75rem;">
                                            <i class="far fa-clock me-1"></i><?= date('d M, H:i', strtotime($hist['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1 small text-dark"><?= htmlspecialchars($hist['change_description']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center text-muted" style="font-size: 0.75rem;">
                                        <span><i class="fas fa-user-circle me-1 text-primary"></i><strong><?= htmlspecialchars($hist['user_name'] ?? 'System') ?></strong></span>
                                        <span class="text-muted"><?= date('Y', strtotime($hist['created_at'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-history fa-2x mb-2 text-muted opacity-50"></i>
                            <p class="mb-0 small">No history entries recorded yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center py-2">
                    <a href="history.php?id=<?= $id ?>" class="small text-decoration-none fw-semibold">
                        <i class="fas fa-list me-1"></i> Open Complete Audit Trail
                    </a>
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
