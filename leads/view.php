<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF validation failed');
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update') {
            $stmt = $pdo->prepare("UPDATE leads SET status = ?, notes = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['notes'], $id]);
            $_SESSION['flash_message'] = 'Lead updated.';
            $_SESSION['flash_type'] = 'success';
            header("Location: view.php?id=$id");
            exit;
        } elseif ($_POST['action'] === 'convert') {
            try {
                $pdo->beginTransaction();
                $lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
                $lead->execute([$id]);
                $l = $lead->fetch();

                // Create customer
                $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$l['name'], $l['email'], $l['phone']]);
                $new_customer_id = $pdo->lastInsertId();

                // Update lead
                $stmt = $pdo->prepare("UPDATE leads SET status = 'Converted', converted_customer_id = ? WHERE id = ?");
                $stmt->execute([$new_customer_id, $id]);

                $pdo->commit();
                $_SESSION['flash_message'] = 'Lead converted to customer successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: /Reper_hub/customers/view.php?id=$new_customer_id");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = 'Conversion failed: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        } elseif ($_POST['action'] === 'delete') {
            $pdo->prepare("DELETE FROM leads WHERE id = ?")->execute([$id]);
            $_SESSION['flash_message'] = 'Lead deleted.';
            $_SESSION['flash_type'] = 'success';
            header("Location: /Reper_hub/leads/index.php");
            exit;
        }
    }
}

$stmt = $pdo->prepare("SELECT l.*, u.name as assigned_to_name FROM leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE l.id = ?");
$stmt->execute([$id]);
$lead = $stmt->fetch();

if (!$lead) die("Lead not found.");

$statuses = ['New', 'Contacted', 'Interested', 'Converted', 'Lost'];

$pageTitle = 'View Lead: ' . $lead['name'];
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <div>
            <a href="/Reper_hub/leads/index.php" class="btn btn-outline-secondary me-2">Back</a>
            <?php if ($lead['status'] !== 'Converted'): ?>
                <form method="POST" class="d-inline" onsubmit="return confirm('Convert to Customer?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="convert">
                    <button type="submit" class="btn btn-success me-2"><i class="fas fa-user-plus"></i> Convert to Customer</button>
                </form>
            <?php endif; ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this lead?');">
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

    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Lead Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Email</p>
                            <h6><?= htmlspecialchars($lead['email'] ?: 'N/A') ?></h6>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Phone</p>
                            <h6><?= htmlspecialchars($lead['phone']) ?></h6>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Source</p>
                            <h6><?= htmlspecialchars($lead['source']) ?></h6>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Assigned To</p>
                            <h6><?= htmlspecialchars($lead['assigned_to_name'] ?? 'Unassigned') ?></h6>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Device Type</p>
                            <h6><?= htmlspecialchars($lead['device_type'] ?: 'N/A') ?></h6>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted mb-1">Estimated Budget</p>
                            <h6><?= $lead['estimated_budget'] ? '₹'.number_format($lead['estimated_budget'],2) : 'N/A' ?></h6>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted mb-1">Problem Description</p>
                        <p><?= nl2br(htmlspecialchars($lead['problem_description'] ?: 'N/A')) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Update Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" value="update">
                        
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" <?= $lead['status'] === 'Converted' ? 'disabled' : '' ?>>
                                <?php foreach($statuses as $st): ?>
                                    <option value="<?= $st ?>" <?= $lead['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="4"><?= htmlspecialchars($lead['notes']) ?></textarea>
                        </div>
                        
                        <?php if ($lead['status'] !== 'Converted'): ?>
                            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                        <?php else: ?>
                            <div class="alert alert-success">This lead has been converted to customer #<?= $lead['converted_customer_id'] ?></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

  </div>
</div>
<?php include '../includes/footer.php'; ?>
