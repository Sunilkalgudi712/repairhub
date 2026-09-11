<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$quotationId = (int)$_GET['id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        $stmt = $pdo->prepare("UPDATE quotations SET status = 'Sent' WHERE id = ? AND status = 'Draft'");
        $stmt->execute([$quotationId]);
        $_SESSION['flash_message'] = "Quotation marked as Sent.";
        $_SESSION['flash_type'] = "success";
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM quotations WHERE id = ? AND status = 'Draft'");
        $stmt->execute([$quotationId]);
        $_SESSION['flash_message'] = "Quotation deleted.";
        $_SESSION['flash_type'] = "success";
        header("Location: index.php");
        exit;
    } elseif ($action === 'convert') {
        try {
            $pdo->beginTransaction();
            
            // Get quotation data
            $stmt = $pdo->prepare("SELECT * FROM quotations WHERE id = ?");
            $stmt->execute([$quotationId]);
            $q = $stmt->fetch();
            
            if ($q && $q['status'] === 'Approved') {
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . rand(100, 999); // Dummy invoice generation
                
                // Create invoice logic here
                // ...
                
                // Update quotation
                $stmt = $pdo->prepare("UPDATE quotations SET status = 'Converted', converted_invoice_id = 1 WHERE id = ?"); // dummy converted id
                $stmt->execute([$quotationId]);
                
                $pdo->commit();
                $_SESSION['flash_message'] = "Converted to Invoice successfully.";
                $_SESSION['flash_type'] = "success";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = "Error converting: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
    header("Location: view.php?id=" . $quotationId);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch quotation details
$stmt = $pdo->prepare("
    SELECT q.*, cu.name as customer_name, cu.email as customer_email, cu.phone as customer_phone,
           rt.ticket_id, c.name as creator_name
    FROM quotations q
    LEFT JOIN customers cu ON q.customer_id = cu.id
    LEFT JOIN repair_tickets rt ON q.ticket_id = rt.id
    LEFT JOIN users c ON q.created_by = c.id
    WHERE q.id = ?
");
$stmt->execute([$quotationId]);
$quotation = $stmt->fetch();

if (!$quotation) {
    header("Location: index.php");
    exit;
}

$itemStmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$itemStmt->execute([$quotationId]);
$items = $itemStmt->fetchAll();

$pageTitle = 'View Quotation ' . $quotation['quotation_number'];
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quotation <?= htmlspecialchars($quotation['quotation_number']) ?></h2>
        <div>
            <a href="print.php?id=<?= $quotation['id'] ?>" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print"></i> Print</a>
            <a href="index.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-9">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-5">
                    <!-- Quotation Header -->
                    <div class="row mb-5">
                        <div class="col-sm-6">
                            <h3 class="text-uppercase text-primary">Quotation</h3>
                            <p class="mb-1"><strong>Quotation #:</strong> <?= htmlspecialchars($quotation['quotation_number']) ?></p>
                            <p class="mb-1"><strong>Date:</strong> <?= date('M d, Y', strtotime($quotation['created_at'])) ?></p>
                            <p class="mb-1"><strong>Valid Until:</strong> <?= date('M d, Y', strtotime($quotation['valid_until'])) ?></p>
                            <p class="mb-1"><strong>Status:</strong> <?= getQuotationStatusBadge($quotation['status']) ?></p>
                        </div>
                        <div class="col-sm-6 text-end">
                            <h5 class="mb-1">RepairHub</h5>
                            <p class="text-muted mb-0">123 Tech Street, Silicon Valley<br>City, State, 12345<br>Phone: (123) 456-7890<br>Email: contact@repairhub.com</p>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row mb-5">
                        <div class="col-sm-6">
                            <h6 class="text-uppercase text-muted mb-2">Quotation To:</h6>
                            <h5 class="mb-1"><?= htmlspecialchars($quotation['customer_name'] ?? 'Walk-in Customer') ?></h5>
                            <?php if ($quotation['customer_phone']): ?>
                            <p class="mb-1"><i class="fas fa-phone fa-fw text-muted"></i> <?= htmlspecialchars($quotation['customer_phone']) ?></p>
                            <?php endif; ?>
                            <?php if ($quotation['customer_email']): ?>
                            <p class="mb-1"><i class="fas fa-envelope fa-fw text-muted"></i> <?= htmlspecialchars($quotation['customer_email']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($quotation['ticket_id'])): ?>
                        <div class="col-sm-6 text-end">
                            <h6 class="text-uppercase text-muted mb-2">Related Ticket:</h6>
                            <h5 class="mb-1"><?= htmlspecialchars($quotation['ticket_id']) ?></h5>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-responsive mb-4">
                        <table class="table table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th width="45%">Description</th>
                                    <th width="15%" class="text-center">Type</th>
                                    <th width="10%" class="text-center">Qty</th>
                                    <th width="15%" class="text-end">Unit Price</th>
                                    <th width="15%" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td class="text-center"><span class="badge bg-secondary"><?= ucfirst($item['type']) ?></span></td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                                    <td class="text-end fw-bold"><?= formatCurrency($item['total_price']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="row">
                        <div class="col-sm-7">
                            <?php if ($quotation['notes']): ?>
                            <div class="mb-4">
                                <h6>Notes:</h6>
                                <p class="text-muted"><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
                            </div>
                            <?php endif; ?>
                            <?php if ($quotation['terms']): ?>
                            <div class="mb-4">
                                <h6>Terms & Conditions:</h6>
                                <p class="text-muted small"><?= nl2br(htmlspecialchars($quotation['terms'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-5">
                            <table class="table table-sm table-borderless">
                                <tbody>
                                    <tr>
                                        <td>Subtotal:</td>
                                        <td class="text-end"><?= formatCurrency($quotation['subtotal']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Tax (<?= $quotation['tax_rate'] ?>%):</td>
                                        <td class="text-end"><?= formatCurrency($quotation['tax_amount']) ?></td>
                                    </tr>
                                    <?php if ($quotation['discount_amount'] > 0): ?>
                                    <tr>
                                        <td>Discount:</td>
                                        <td class="text-end text-danger">-<?= formatCurrency($quotation['discount_amount']) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="border-top border-2 border-dark fs-5 fw-bold">
                                        <td>Total:</td>
                                        <td class="text-end"><?= formatCurrency($quotation['total_amount']) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="d-grid gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        
                        <?php if ($quotation['status'] === 'Draft'): ?>
                            <button type="submit" name="action" value="send" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send to Client</button>
                            <a href="edit.php?id=<?= $quotation['id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-edit"></i> Edit</a>
                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to delete this draft?');"><i class="fas fa-trash"></i> Delete</button>
                        
                        <?php elseif ($quotation['status'] === 'Sent'): ?>
                            <div class="alert alert-info text-center py-2 mb-2">
                                <small>Waiting for client response</small>
                            </div>
                            <!-- Mock buttons for manual approval/rejection if client calls -->
                            <button type="button" class="btn btn-success"><i class="fas fa-check"></i> Mark Approved</button>
                            <button type="button" class="btn btn-danger"><i class="fas fa-times"></i> Mark Rejected</button>
                        
                        <?php elseif ($quotation['status'] === 'Approved'): ?>
                            <button type="submit" name="action" value="convert" class="btn btn-success"><i class="fas fa-file-invoice"></i> Convert to Invoice</button>
                        
                        <?php elseif ($quotation['status'] === 'Rejected'): ?>
                            <div class="alert alert-danger mb-0">
                                <strong>Rejected:</strong><br>
                                <?= htmlspecialchars($quotation['rejection_reason'] ?? 'No reason provided') ?>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
