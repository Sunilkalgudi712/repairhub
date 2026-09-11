<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_paid') {
        $stmt = $pdo->prepare("UPDATE invoices SET status = 'Paid', paid_amount = total_amount, due_amount = 0, payment_date = CURDATE() WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_message'] = "Invoice marked as paid.";
        $_SESSION['flash_type'] = "success";
        header("Location: view.php?id=$id");
        exit;
    } elseif ($_POST['action'] === 'delete') {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM invoices WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $_SESSION['flash_message'] = "Invoice deleted.";
        $_SESSION['flash_type'] = "success";
        header("Location: index.php");
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address, t.problem_description
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    LEFT JOIN repair_tickets t ON i.ticket_id = t.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

$stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);



$pageTitle = 'Invoice ' . htmlspecialchars($invoice['invoice_number']);
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></h1>
            <div>
                <a href="print.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print"></i> Print</a>
                <?php if ($invoice['status'] !== 'Paid'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Mark this invoice as Paid?');">
                    <input type="hidden" name="action" value="mark_paid">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Mark as Paid</button>
                </form>
                <?php endif; ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this invoice entirely? This cannot be undone.');">
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
            <div class="card-body p-5">
                <div class="row mb-5">
                    <div class="col-sm-6">
                        <h2 class="mb-3">RepairHub</h2>
                        <div>123 Tech Lane, Suite 100</div>
                        <div>Mumbai, MH 400001</div>
                        <div>Email: support@repairhub.com</div>
                        <div>Phone: +91 98765 43210</div>
                    </div>
                    <div class="col-sm-6 text-end">
                        <h4 class="text-muted">INVOICE</h4>
                        <div class="mb-1"><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></div>
                        <div class="mb-1"><strong>Date:</strong> <?= date('F j, Y', strtotime($invoice['created_at'])) ?></div>
                        <div class="mb-1"><strong>Due Date:</strong> <?= date('F j, Y', strtotime($invoice['due_date'])) ?></div>
                        <div class="mb-1"><strong>Status:</strong> <?= getInvoiceStatusBadge($invoice['status']) ?></div>
                    </div>
                </div>

                <div class="row mb-5">
                    <div class="col-sm-6">
                        <h6 class="mb-3">Bill To:</h6>
                        <div><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></div>
                        <?php if ($invoice['customer_address']): ?><div><?= nl2br(htmlspecialchars($invoice['customer_address'])) ?></div><?php endif; ?>
                        <?php if ($invoice['customer_phone']): ?><div>Phone: <?= htmlspecialchars($invoice['customer_phone']) ?></div><?php endif; ?>
                        <?php if ($invoice['customer_email']): ?><div>Email: <?= htmlspecialchars($invoice['customer_email']) ?></div><?php endif; ?>
                    </div>
                    <?php if ($invoice['ticket_id']): ?>
                    <div class="col-sm-6 text-end">
                        <h6 class="mb-3">Reference:</h6>
                        <div><strong>Ticket #:</strong> <?= htmlspecialchars($invoice['ticket_id']) ?></div>
                        <?php if ($invoice['problem_description']): ?>
                        <div class="text-muted small mt-1"><?= htmlspecialchars(substr($invoice['problem_description'], 0, 100)) ?>...</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Description</th>
                                <th class="text-center" style="width: 10%">Qty</th>
                                <th class="text-end" style="width: 20%">Unit Price</th>
                                <th class="text-end" style="width: 20%">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td class="text-center"><?= $item['quantity'] ?></td>
                                <td class="text-end">₹<?= number_format($item['unit_price'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($item['total_price'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="mb-4">
                            <h6>Notes</h6>
                            <p class="text-muted small"><?= nl2br(htmlspecialchars($invoice['notes'] ?? 'None')) ?></p>
                        </div>
                        <div>
                            <h6>Terms & Conditions</h6>
                            <p class="text-muted small"><?= nl2br(htmlspecialchars($invoice['terms'] ?? '')) ?></p>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <table class="table table-borderless table-sm text-end">
                            <tr>
                                <td>Subtotal:</td>
                                <td style="width: 30%">₹<?= number_format($invoice['subtotal'], 2) ?></td>
                            </tr>
                            <tr>
                                <td>Tax (<?= number_format($invoice['tax_rate'], 1) ?>%):</td>
                                <td>₹<?= number_format($invoice['tax_amount'], 2) ?></td>
                            </tr>
                            <?php if ($invoice['discount_amount'] > 0): ?>
                            <tr>
                                <td>Discount:</td>
                                <td class="text-danger">-₹<?= number_format($invoice['discount_amount'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="fw-bold fs-5 border-top">
                                <td>Grand Total:</td>
                                <td>₹<?= number_format($invoice['total_amount'], 2) ?></td>
                            </tr>
                            <?php if ($invoice['paid_amount'] > 0): ?>
                            <tr class="text-success">
                                <td>Paid Amount:</td>
                                <td>₹<?= number_format($invoice['paid_amount'], 2) ?></td>
                            </tr>
                            <tr class="fw-bold">
                                <td>Amount Due:</td>
                                <td>₹<?= number_format($invoice['due_amount'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
