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
        $type = $_POST['type'] ?? '';
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!in_array($type, ['Add', 'Remove', 'Set']) || $quantity < 0) {
            $_SESSION['flash_message'] = "Invalid adjustment parameters.";
            $_SESSION['flash_type'] = "danger";
        } else {
            $currentQty = (int)$item['quantity'];
            $newQty = $currentQty;

            if ($type === 'Add') {
                $newQty = $currentQty + $quantity;
            } elseif ($type === 'Remove') {
                $newQty = max(0, $currentQty - $quantity);
            } elseif ($type === 'Set') {
                $newQty = $quantity;
            }

            try {
                $pdo->beginTransaction();

                // Update inventory
                $updStmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?");
                $updStmt->execute([$newQty, $id]);

                // Insert adjustment log
                // Make sure table exists, create if not
                $pdo->exec("CREATE TABLE IF NOT EXISTS stock_adjustments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    inventory_id INT NOT NULL,
                    user_id INT NOT NULL,
                    type VARCHAR(50) NOT NULL,
                    quantity INT NOT NULL,
                    reason TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");

                $adjStmt = $pdo->prepare("INSERT INTO stock_adjustments (inventory_id, user_id, type, quantity, reason) VALUES (?, ?, ?, ?, ?)");
                $adjStmt->execute([$id, $_SESSION['user_id'], $type, $quantity, $reason]);

                // Log activity
                $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, 'Stock Adjusted', ?, NOW())");
                $logStmt->execute([$_SESSION['user_id'], "Adjusted stock for {$item['name']}: $type $quantity. New total: $newQty"]);

                $pdo->commit();

                $_SESSION['flash_message'] = "Stock adjusted successfully.";
                $_SESSION['flash_type'] = "success";
                header("Location: edit.php?id=$id");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
                $_SESSION['flash_type'] = "danger";
            }
        }
    }
}

$pageTitle = 'Adjust Stock';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-boxes me-2"></i> Adjust Stock</h2>
        <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to Item</a>
    </div>

    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3"><?= htmlspecialchars($item['name']) ?></h4>
                    <p class="mb-4 fs-5">Current Quantity: <strong class="text-primary"><?= number_format($item['quantity']) ?></strong></p>

                    <form method="POST" action="adjust.php?id=<?= $id ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Adjustment Type</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="type" id="typeAdd" value="Add" checked>
                                    <label class="form-check-label text-success fw-bold" for="typeAdd">
                                        <i class="fas fa-plus"></i> Add Stock
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="type" id="typeRemove" value="Remove">
                                    <label class="form-check-label text-danger fw-bold" for="typeRemove">
                                        <i class="fas fa-minus"></i> Remove Stock
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="type" id="typeSet" value="Set">
                                    <label class="form-check-label text-primary fw-bold" for="typeSet">
                                        <i class="fas fa-equals"></i> Set Total
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" required min="0">
                        </div>

                        <div class="mb-4">
                            <label for="reason" class="form-label">Reason / Notes</label>
                            <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="e.g. New shipment arrived, found damaged item..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-check-circle me-1"></i> Apply Adjustment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
