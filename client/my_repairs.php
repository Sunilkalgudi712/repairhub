<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'My Repairs';

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer_id = $customer['id'];

$tab = $_GET['tab'] ?? 'all';

$query = "SELECT * FROM repair_tickets WHERE customer_id = ?";
$params = [$customer_id];

if ($tab === 'active') {
    $query .= " AND status IN ('Pending', 'In Progress', 'Waiting for Parts')";
} elseif ($tab === 'completed') {
    $query .= " AND status IN ('Completed', 'Delivered', 'Ready for Pickup')";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$repairs = $stmt->fetchAll();

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">My Repairs</h4>
            <a href="submit_device.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>New Repair Request</a>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                <ul class="nav nav-tabs border-bottom">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'all' ? 'active fw-bold' : '' ?>" href="?tab=all">All Repairs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'active' ? 'active fw-bold' : '' ?>" href="?tab=active">Active</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'completed' ? 'active fw-bold' : '' ?>" href="?tab=completed">Completed</a>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Ticket ID</th>
                                <th>Device</th>
                                <th>Issue</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($repairs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 text-light"></i>
                                        <h5>No repairs found</h5>
                                        <p>You haven't submitted any repairs yet or none match this filter.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($repairs as $repair): ?>
                                    <tr>
                                        <td class="ps-4"><a href="repair_detail.php?id=<?= $repair['id'] ?>" class="fw-bold text-decoration-none"><?= htmlspecialchars($repair['ticket_id']) ?></a></td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($repair['device_brand'] . ' ' . $repair['device_model']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($repair['device_type']) ?></div>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 200px;">
                                                <?= htmlspecialchars($repair['problem_description']) ?>
                                            </span>
                                        </td>
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
                                        <td class="text-end pe-4">
                                            <a href="repair_detail.php?id=<?= $repair['id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
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

<?php include '../includes/footer.php'; ?>
