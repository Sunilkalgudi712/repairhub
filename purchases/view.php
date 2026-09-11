<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF validation failed');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'receive') {
        try {
            $pdo->beginTransaction();
            // Update purchase
            $stmt = $pdo->prepare("UPDATE purchases SET status = 'Received', received_date = CURDATE() WHERE id = ?");
            $stmt->execute([$id]);

            // Update inventory
            $itemsStmt = $pdo->prepare("SELECT pi.inventory_id, pi.quantity, i.name as item_name 
                                        FROM purchase_items pi 
                                        LEFT JOIN inventory i ON pi.inventory_id = i.id 
                                        WHERE pi.purchase_id = ? AND pi.inventory_id IS NOT NULL");
            $itemsStmt->execute([$id]);
            $items = $itemsStmt->fetchAll();

            $invUpdate = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
            foreach ($items as $item) {
                $invUpdate->execute([$item['quantity'], $item['inventory_id']]);

                $pDesc = "Received {$item['quantity']} units via Purchase Order #{$id}.";
                logInventoryHistory($pdo, $item['inventory_id'], $item['item_name'], 'quantity_change', 'quantity', null, null, $pDesc);
            }
            
            $pdo->commit();
            $_SESSION['flash_message'] = 'Purchase marked as received and inventory updated.';
            $_SESSION['flash_type'] = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = 'Error updating purchase: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }
        header("Location: view.php?id=$id");
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$id]);
        $_SESSION['flash_message'] = 'Purchase deleted.';
        $_SESSION['flash_type'] = 'success';
        header("Location: " . APP_URL . "/purchases/index.php");
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
$stmt->execute([$id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    die("Purchase not found.");
}

$itemsStmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'View Purchase: ' . $purchase['purchase_number'];
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <div>
            <a href="<?= APP_URL ?>/purchases/index.php" class="btn btn-outline-secondary me-2">Back</a>
            <?php if ($purchase['status'] !== 'Received' && $purchase['status'] !== 'Cancelled'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Mark as received and update inventory?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="receive">
                    <button type="submit" class="btn btn-success me-2"><i class="fas fa-check"></i> Mark as Received</button>
                </form>
            <?php endif; ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this purchase entirely?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Supplier:</strong> <?= htmlspecialchars($purchase['supplier_name']) ?></p>
                    <p><strong>Contact:</strong> <?= htmlspecialchars($purchase['supplier_contact'] ?? 'N/A') ?></p>
                    <p><strong>Status:</strong> <span class="badge bg-secondary"><?= htmlspecialchars($purchase['status']) ?></span></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p><strong>Date:</strong> <?= date('d M Y', strtotime($purchase['created_at'])) ?></p>
                    <p><strong>Expected:</strong> <?= $purchase['expected_date'] ? date('d M Y', strtotime($purchase['expected_date'])) : 'N/A' ?></p>
                    <p><strong>Received:</strong> <?= $purchase['received_date'] ? date('d M Y', strtotime($purchase['received_date'])) : 'N/A' ?></p>
                </div>
                <?php if ($purchase['notes']): ?>
                <div class="col-12 mt-3">
                    <p><strong>Notes:</strong><br><?= nl2br(htmlspecialchars($purchase['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Name</th>
                        <th>Linked Inventory ID</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= $item['inventory_id'] ?: '-' ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td>₹<?= number_format($item['unit_price'], 2) ?></td>
                        <td>₹<?= number_format($item['total'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Subtotal:</td>
                        <td>₹<?= number_format($purchase['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Tax:</td>
                        <td>₹<?= number_format($purchase['tax_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end fw-bold">Grand Total:</td>
                        <td>₹<?= number_format($purchase['total_amount'], 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
