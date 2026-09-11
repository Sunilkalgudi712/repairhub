<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        try {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['flash_message'] = "Customer deleted successfully.";
            $_SESSION['flash_type'] = "success";
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $error = "Could not delete customer. They may have associated records.";
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch customer
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: index.php');
    exit;
}

// Fetch stats
$statsSql = "SELECT 
    (SELECT COUNT(*) FROM repair_tickets WHERE customer_id = :id1) as total_tickets,
    (SELECT COUNT(*) FROM invoices WHERE customer_id = :id2) as total_invoices,
    (SELECT COALESCE(SUM(total_amount), 0) FROM invoices WHERE customer_id = :id3 AND status != 'Cancelled') as total_spent,
    (SELECT MAX(created_at) FROM repair_tickets WHERE customer_id = :id4) as last_visit";
$stmt = $pdo->prepare($statsSql);
$stmt->execute(['id1'=>$id, 'id2'=>$id, 'id3'=>$id, 'id4'=>$id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Tickets
$stmt = $pdo->prepare("SELECT * FROM repair_tickets WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Invoices
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$id]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);



$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$pageTitle = 'Customer Profile';
include '../includes/header.php';
include '../includes/sidebar.php';

// Generate Avatar Initials
$nameParts = explode(' ', trim($customer['name']));
$initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customer Profile</h2>
        <div>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="edit.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Edit</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="fas fa-trash"></i> Delete</button>
        </div>
    </div>

    <!-- Customer Info Header -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex align-items-center">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-4" style="width: 80px; height: 80px; font-size: 32px;">
                <?= htmlspecialchars($initials) ?>
            </div>
            <div>
                <h3 class="mb-1"><?= htmlspecialchars($customer['name']) ?></h3>
                <div class="text-muted">
                    <span class="me-3"><i class="fas fa-phone"></i> <?= htmlspecialchars($customer['phone']) ?></span>
                    <?php if ($customer['email']): ?>
                    <span class="me-3"><i class="fas fa-envelope"></i> <?= htmlspecialchars($customer['email']) ?></span>
                    <?php endif; ?>
                    <?php if ($customer['city']): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($customer['city']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Tickets</h6>
                    <h3><?= $stats['total_tickets'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Invoices</h6>
                    <h3><?= $stats['total_invoices'] ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Spent</h6>
                    <h3>₹<?= number_format($stats['total_spent'], 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Last Visit</h6>
                    <h5><?= $stats['last_visit'] ? date('d M Y', strtotime($stats['last_visit'])) : 'Never' ?></h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Contact Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Alt Phone:</strong> <?= htmlspecialchars($customer['alt_phone'] ?: 'N/A') ?></p>
                    <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($customer['address'] ?: 'N/A')) ?></p>
                    <p><strong>City:</strong> <?= htmlspecialchars($customer['city'] ?: 'N/A') ?></p>
                    <p><strong>State:</strong> <?= htmlspecialchars($customer['state'] ?: 'N/A') ?></p>
                    <p><strong>Pincode:</strong> <?= htmlspecialchars($customer['pincode'] ?: 'N/A') ?></p>
                    <p><strong>GST Number:</strong> <?= htmlspecialchars($customer['gst_number'] ?: 'N/A') ?></p>
                    <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($customer['notes'] ?: 'N/A')) ?></p>
                    <p><strong>Joined:</strong> <?= date('d M Y, h:i A', strtotime($customer['created_at'])) ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Repair History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tickets) > 0): ?>
                                    <?php foreach ($tickets as $t): ?>
                                    <tr>
                                        <td><a href="../tickets/view.php?id=<?= $t['id'] ?>" class="text-decoration-none">#<?= str_pad($t['id'], 5, '0', STR_PAD_LEFT) ?></a></td>
                                        <td><?= htmlspecialchars($t['device_model'] ?? $t['device_name'] ?? 'Unknown') ?></td>
                                        <td><?= getStatusBadge($t['status']) ?></td>
                                        <td><?= date('d M Y', strtotime($t['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No repair tickets found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Invoices</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($invoices) > 0): ?>
                                    <?php foreach ($invoices as $i): ?>
                                    <tr>
                                        <td><a href="../invoices/view.php?id=<?= $i['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($i['invoice_number'] ?? '#'.str_pad($i['id'], 5, '0', STR_PAD_LEFT)) ?></a></td>
                                        <td>₹<?= number_format($i['total_amount'] ?? $i['amount'] ?? 0, 2) ?></td>
                                        <td><?= getInvoiceStatusBadge($i['status'] ?? 'Completed') ?></td>
                                        <td><?= date('d M Y', strtotime($i['created_at'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">No invoices found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteModalLabel">Delete Customer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <strong><?= htmlspecialchars($customer['name']) ?></strong>? This action cannot be undone.
      </div>
      <div class="modal-footer">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="delete">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
