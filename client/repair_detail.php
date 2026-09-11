<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'Repair Details';
$id = $_GET['id'] ?? 0;

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer_id = $customer['id'];

// Get ticket details and verify ownership (or Admin)
if (function_exists('isAdmin') && isAdmin()) {
    $stmt = $pdo->prepare("SELECT * FROM repair_tickets WHERE id = ?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM repair_tickets WHERE id = ? AND customer_id = ?");
    $stmt->execute([$id, $customer_id]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_message'] = 'Repair ticket not found or access denied.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: my_repairs.php');
    exit;
}

// Handle Quotation Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verifyCsrfToken();
    $quotation_id = $_POST['quotation_id'] ?? 0;
    
    // Verify quotation belongs to this ticket
    $stmt = $pdo->prepare("SELECT id FROM quotations WHERE id = ? AND ticket_id = ?");
    $stmt->execute([$quotation_id, $id]);
    if ($stmt->fetch()) {
        if ($_POST['action'] === 'approve') {
            $stmt = $pdo->prepare("UPDATE quotations SET status = 'Approved', approved_at = NOW() WHERE id = ?");
            $stmt->execute([$quotation_id]);
            $_SESSION['flash_message'] = 'Quotation approved successfully.';
            $_SESSION['flash_type'] = 'success';
        } elseif ($_POST['action'] === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            $stmt = $pdo->prepare("UPDATE quotations SET status = 'Rejected', rejected_at = NOW(), rejection_reason = ? WHERE id = ?");
            $stmt->execute([$reason, $quotation_id]);
            $_SESSION['flash_message'] = 'Quotation rejected.';
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: repair_detail.php?id=$id");
        exit;
    }
}

// Get service progress
$stmt = $pdo->prepare("SELECT * FROM service_progress WHERE ticket_id = ? ORDER BY created_at ASC");
$stmt->execute([$id]);
$progress = $stmt->fetchAll();

// Get quotation if exists
$stmt = $pdo->prepare("SELECT * FROM quotations WHERE ticket_id = ? AND status != 'Draft' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$id]);
$quotation = $stmt->fetch();

// Get invoice if exists
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE ticket_id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<style>
.progress-timeline { position: relative; padding-left: 30px; }
.progress-timeline::before { content: ''; position: absolute; left: 11px; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
.timeline-item { position: relative; margin-bottom: 1.5rem; }
.timeline-item::before { content: ''; position: absolute; left: -25px; top: 5px; width: 14px; height: 14px; border-radius: 50%; background: #0d6efd; border: 3px solid #fff; box-shadow: 0 0 0 1px #0d6efd; }
.timeline-img { max-width: 200px; border-radius: 8px; cursor: pointer; }
</style>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Repair Details - <?= htmlspecialchars($ticket['ticket_id']) ?></h4>
            <a href="my_repairs.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Device Details -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="fw-bold mb-0">Device Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Device</div>
                            <div class="col-sm-8 fw-medium"><?= htmlspecialchars($ticket['device_brand'] . ' ' . $ticket['device_model']) ?> (<?= htmlspecialchars($ticket['device_type']) ?>)</div>
                        </div>
                        <?php if(!empty($ticket['serial_number'])): ?>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Serial / IMEI</div>
                            <div class="col-sm-8"><?= htmlspecialchars($ticket['serial_number']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="row mb-3">
                            <div class="col-sm-4 text-muted">Reported Issue</div>
                            <div class="col-sm-8"><?= nl2br(htmlspecialchars($ticket['problem_description'])) ?></div>
                        </div>
                        <?php if(!empty($ticket['device_condition'])): ?>
                        <div class="row">
                            <div class="col-sm-4 text-muted">Physical Condition</div>
                            <div class="col-sm-8"><?= nl2br(htmlspecialchars($ticket['device_condition'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quotation Section -->
                <?php if($quotation): ?>
                <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
                    <div class="card-header bg-white pt-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-info"><i class="fas fa-file-invoice me-2"></i>Service Quotation</h6>
                        <?php
                            $qBadge = 'bg-secondary';
                            if ($quotation['status'] == 'Sent') $qBadge = 'bg-warning text-dark';
                            elseif ($quotation['status'] == 'Approved') $qBadge = 'bg-success';
                            elseif ($quotation['status'] == 'Rejected') $qBadge = 'bg-danger';
                        ?>
                        <span class="badge <?= $qBadge ?>"><?= htmlspecialchars($quotation['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Quotation #<?= htmlspecialchars($quotation['quotation_number']) ?></span>
                            <span class="fw-bold fs-5 text-primary"><?= formatCurrency($quotation['total_amount']) ?></span>
                        </div>
                        <?php if(!empty($quotation['notes'])): ?>
                            <p class="small text-muted mb-4"><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
                        <?php endif; ?>
                        
                        <?php if($quotation['status'] == 'Sent'): ?>
                            <form method="POST" action="" class="d-flex gap-2">
                                <?= csrfField() ?>
                                <input type="hidden" name="quotation_id" value="<?= $quotation['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-success flex-grow-1" onclick="return confirm('Are you sure you want to approve this quotation?');"><i class="fas fa-check me-2"></i>Approve Quotation</button>
                                <button type="button" class="btn btn-outline-danger flex-grow-1" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="fas fa-times me-2"></i>Reject</button>
                            </form>
                        <?php endif; ?>
                        
                        <?php if($quotation['status'] == 'Rejected' && !empty($quotation['rejection_reason'])): ?>
                            <div class="alert alert-danger mb-0 mt-3">
                                <small class="fw-bold">Rejection Reason:</small><br>
                                <?= htmlspecialchars($quotation['rejection_reason']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Reject Modal -->
                <?php if($quotation && $quotation['status'] == 'Sent'): ?>
                <div class="modal fade" id="rejectModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" action="" class="modal-content">
                            <?= csrfField() ?>
                            <input type="hidden" name="quotation_id" value="<?= $quotation['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Quotation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Please provide a reason for rejecting this quotation. Our technician will review and may provide an updated estimate.</p>
                                <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Enter reason..."></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">Submit Rejection</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <!-- Progress Timeline -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="fw-bold mb-0">Service Progress Timeline</h6>
                    </div>
                    <div class="card-body">
                        <?php if(empty($progress)): ?>
                            <p class="text-muted text-center py-3">No updates yet.</p>
                        <?php else: ?>
                            <div class="progress-timeline mt-3">
                                <?php foreach($progress as $item): ?>
                                    <div class="timeline-item">
                                        <div class="small text-muted mb-1"><?= date('M d, Y h:i A', strtotime($item['created_at'])) ?></div>
                                        <div class="fw-bold mb-1"><?= htmlspecialchars($item['status_update']) ?></div>
                                        <?php if(!empty($item['description'])): ?>
                                            <p class="mb-2 text-dark"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                                        <?php endif; ?>
                                        <?php if(!empty($item['image_path'])): ?>
                                            <a href="<?= APP_URL ?>/<?= htmlspecialchars($item['image_path']) ?>" target="_blank">
                                                <img src="<?= APP_URL ?>/<?= htmlspecialchars($item['image_path']) ?>" alt="Update image" class="timeline-img img-thumbnail">
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
            
            <div class="col-lg-4">
                <!-- Status Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center p-4">
                        <h6 class="text-muted mb-3">Current Status</h6>
                        <?php
                        $statusBadge = 'bg-secondary';
                        if ($ticket['status'] == 'Pending') $statusBadge = 'bg-warning text-dark';
                        elseif ($ticket['status'] == 'In Progress') $statusBadge = 'bg-primary';
                        elseif ($ticket['status'] == 'Waiting for Parts') $statusBadge = 'bg-info';
                        elseif (in_array($ticket['status'], ['Ready for Pickup', 'Completed'])) $statusBadge = 'bg-success';
                        elseif ($ticket['status'] == 'Cancelled') $statusBadge = 'bg-danger';
                        ?>
                        <div class="badge <?= $statusBadge ?> fs-5 py-2 px-3 mb-3 d-inline-block rounded-pill"><?= htmlspecialchars($ticket['status']) ?></div>
                        <div class="small text-muted">Submitted on: <?= date('M d, Y', strtotime($ticket['created_at'])) ?></div>
                    </div>
                </div>

                <!-- Invoice Details (If any) -->
                <?php if($invoice): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                        <h6 class="fw-bold mb-0">Invoice Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Invoice #</span>
                            <span class="fw-medium"><?= htmlspecialchars($invoice['invoice_number']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total Amount</span>
                            <span class="fw-bold text-dark"><?= formatCurrency($invoice['total_amount']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Status</span>
                            <?php
                                $invBadge = 'bg-danger';
                                if($invoice['status'] == 'Paid') $invBadge = 'bg-success';
                                elseif($invoice['status'] == 'Partially Paid') $invBadge = 'bg-warning text-dark';
                            ?>
                            <span class="badge <?= $invBadge ?>"><?= htmlspecialchars($invoice['status']) ?></span>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <!-- Link to admin print page if allowed, or create a client print page -->
                            <a href="<?= APP_URL ?>/invoices/print.php?id=<?= $invoice['id'] ?>" target="_blank" class="btn btn-outline-primary"><i class="fas fa-print me-2"></i>Print / Download</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php include '../includes/footer.php'; ?>
