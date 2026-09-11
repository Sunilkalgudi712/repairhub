<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'Client Dashboard';

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer_id = $customer['id'];

// Get counts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ? AND status NOT IN ('Completed', 'Delivered', 'Cancelled')");
$stmt->execute([$customer_id]);
$active_repairs = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$total_repairs = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id = ? AND status IN ('Sent', 'Overdue')");
$stmt->execute([$customer_id]);
$pending_invoices = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM invoices WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$total_spent = $stmt->fetchColumn();

// Get recent repairs
$stmt = $pdo->prepare("
    SELECT id, ticket_id, device_brand, device_model, status, created_at 
    FROM repair_tickets 
    WHERE customer_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$customer_id]);
$recent_repairs = $stmt->fetchAll();

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<style>
.client-welcome-card {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
    border-radius: 15px;
    padding: 2rem;
    margin-bottom: 2rem;
}
</style>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        

        <div class="client-welcome-card d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <?php 
                $clientDisplayName = (isset($customer['name']) && !in_array($customer['name'], ['Admin Account', 'Demo Customer'])) 
                    ? $customer['name'] 
                    : 'Customer Account';
                ?>
                <h2 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($clientDisplayName) ?>!</h2>
                <p class="mb-0 opacity-75">You have <?= $active_repairs ?> active repair<?= $active_repairs != 1 ? 's' : '' ?> in progress.</p>
            </div>
            <div class="mt-3 mt-md-0 gap-2 d-flex">
                <a href="submit_device.php" class="btn btn-light text-primary fw-semibold"><i class="fas fa-plus me-2"></i>Submit New Device</a>
                <a href="my_repairs.php" class="btn btn-outline-light"><i class="fas fa-list me-2"></i>View All Repairs</a>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">Active Repairs</h6>
                                <h3 class="mb-0 fw-bold"><?= $active_repairs ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                <i class="fas fa-tools fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">Total Repairs</h6>
                                <h3 class="mb-0 fw-bold"><?= $total_repairs ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                                <i class="fas fa-clipboard-list fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">Pending Invoices</h6>
                                <h3 class="mb-0 fw-bold"><?= $pending_invoices ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                                <i class="fas fa-file-invoice-dollar fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted mb-2">Total Spent</h6>
                                <h3 class="mb-0 fw-bold"><?= formatCurrency($total_spent) ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                <i class="fas fa-rupee-sign fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Repairs</h5>
                        <a href="my_repairs.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($recent_repairs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No recent repairs found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($recent_repairs as $repair): ?>
                                            <tr>
                                                <td><span class="fw-medium"><?= htmlspecialchars($repair['ticket_id']) ?></span></td>
                                                <td><?= htmlspecialchars($repair['device_brand'] . ' ' . $repair['device_model']) ?></td>
                                                <td>
                                                    <?php
                                                    $statusBadge = 'bg-secondary';
                                                    if ($repair['status'] == 'Pending') $statusBadge = 'bg-warning text-dark';
                                                    elseif ($repair['status'] == 'In Progress') $statusBadge = 'bg-primary';
                                                    elseif ($repair['status'] == 'Waiting for Parts') $statusBadge = 'bg-info';
                                                    elseif (in_array($repair['status'], ['Ready for Pickup', 'Completed'])) $statusBadge = 'bg-success';
                                                    elseif ($repair['status'] == 'Cancelled') $statusBadge = 'bg-danger';
                                                    ?>
                                                    <span class="badge <?= $statusBadge ?>"><?= htmlspecialchars($repair['status']) ?></span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($repair['created_at'])) ?></td>
                                                <td>
                                                    <a href="repair_detail.php?id=<?= $repair['id'] ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
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

<?php include '../includes/footer.php'; ?>
