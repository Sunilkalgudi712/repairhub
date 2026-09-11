<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'My Invoices';

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer_id = $customer['id'];

// Get invoices
$stmt = $pdo->prepare("
    SELECT i.*, t.device_brand, t.device_model 
    FROM invoices i 
    LEFT JOIN repair_tickets t ON i.ticket_id = t.id 
    WHERE i.customer_id = ? 
    ORDER BY i.created_at DESC
");
$stmt->execute([$customer_id]);
$invoices = $stmt->fetchAll();

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">My Invoices</h4>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Invoice #</th>
                                <th>Date</th>
                                <th>Device / Ticket</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($invoices)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-file-invoice-dollar fa-3x mb-3 text-light"></i>
                                        <h5>No invoices found</h5>
                                        <p>You don't have any invoices yet.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($invoices as $invoice): ?>
                                    <tr>
                                        <td class="ps-4 fw-medium"><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                        <td><?= date('M d, Y', strtotime($invoice['created_at'])) ?></td>
                                        <td>
                                            <?php if($invoice['device_brand']): ?>
                                                <div class="fw-medium"><?= htmlspecialchars($invoice['device_brand'] . ' ' . $invoice['device_model']) ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">General Service</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold"><?= formatCurrency($invoice['total_amount']) ?></td>
                                        <td>
                                            <?php
                                                $invBadge = 'bg-danger';
                                                if($invoice['status'] == 'Paid') $invBadge = 'bg-success';
                                                elseif($invoice['status'] == 'Partially Paid') $invBadge = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?= $invBadge ?>"><?= htmlspecialchars($invoice['status']) ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= APP_URL ?>/invoices/print.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-print me-1"></i> View / Print</a>
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
