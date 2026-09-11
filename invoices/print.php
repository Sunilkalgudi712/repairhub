<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT i.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address
    FROM invoices i
    LEFT JOIN customers c ON i.customer_id = c.id
    WHERE i.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    die("Invoice not found.");
}

if (function_exists('isClient') && isClient()) {
    $custStmt = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
    $custStmt->execute([$_SESSION['user_id']]);
    $myCustId = $custStmt->fetchColumn();
    if ($invoice['customer_id'] != $myCustId) {
        die("Access denied.");
    }
}

$stmtItems = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fff; font-size: 14px; color: #333; }
        .invoice-header { border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <button onclick="window.print()" class="btn btn-primary">Print Invoice</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>

        <div class="invoice-header row">
            <div class="col-6">
                <h2 class="fw-bold mb-1">RepairHub</h2>
                <div>123 Tech Lane, Suite 100</div>
                <div>Mumbai, MH 400001</div>
                <div>Phone: +91 98765 43210</div>
                <div>GSTIN: 27AAAAA0000A1Z5</div>
            </div>
            <div class="col-6 text-end">
                <h1 class="text-uppercase mb-3" style="color: #666;">Invoice</h1>
                <div><strong>Invoice #:</strong> <?= htmlspecialchars($invoice['invoice_number']) ?></div>
                <div><strong>Date:</strong> <?= date('F j, Y', strtotime($invoice['created_at'])) ?></div>
                <div><strong>Due Date:</strong> <?= date('F j, Y', strtotime($invoice['due_date'])) ?></div>
                <div class="mt-2 fw-bold <?= $invoice['status'] === 'Paid' ? 'text-success' : 'text-danger' ?>">
                    STATUS: <?= strtoupper($invoice['status']) ?>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <h6 class="text-uppercase text-muted border-bottom pb-2 mb-3">Bill To</h6>
                <div class="fw-bold"><?= htmlspecialchars($invoice['customer_name']) ?></div>
                <?php if ($invoice['customer_address']): ?><div><?= nl2br(htmlspecialchars($invoice['customer_address'])) ?></div><?php endif; ?>
                <?php if ($invoice['customer_phone']): ?><div><?= htmlspecialchars($invoice['customer_phone']) ?></div><?php endif; ?>
                <?php if ($invoice['customer_email']): ?><div><?= htmlspecialchars($invoice['customer_email']) ?></div><?php endif; ?>
            </div>
        </div>

        <table class="table table-bordered mb-5">
            <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th class="text-center" style="width: 10%">Qty</th>
                    <th class="text-end" style="width: 20%">Unit Price</th>
                    <th class="text-end" style="width: 20%">Amount</th>
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

        <div class="row">
            <div class="col-6">
                <?php if ($invoice['notes']): ?>
                <div class="mb-4">
                    <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2">Notes</h6>
                    <div><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($invoice['terms']): ?>
                <div>
                    <h6 class="text-uppercase text-muted border-bottom pb-2 mb-2">Terms & Conditions</h6>
                    <div class="small"><?= nl2br(htmlspecialchars($invoice['terms'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-6">
                <table class="table table-borderless table-sm text-end">
                    <tr>
                        <td>Subtotal:</td>
                        <td style="width: 35%">₹<?= number_format($invoice['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Tax (<?= number_format($invoice['tax_rate'], 1) ?>%):</td>
                        <td>₹<?= number_format($invoice['tax_amount'], 2) ?></td>
                    </tr>
                    <?php if ($invoice['discount_amount'] > 0): ?>
                    <tr>
                        <td>Discount:</td>
                        <td>-₹<?= number_format($invoice['discount_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="fw-bold border-top">
                        <td class="pt-2">Grand Total:</td>
                        <td class="pt-2 fs-5">₹<?= number_format($invoice['total_amount'], 2) ?></td>
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

        <div class="text-center mt-5 pt-5 border-top text-muted small">
            Thank you for your business!
        </div>
    </div>
</body>
</html>
