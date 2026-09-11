<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

if (!isset($_GET['id'])) {
    die("Quotation ID required.");
}

$quotationId = (int)$_GET['id'];

// Fetch quotation details
$stmt = $pdo->prepare("
    SELECT q.*, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address,
           rt.ticket_id
    FROM quotations q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN repair_tickets rt ON q.ticket_id = rt.id
    WHERE q.id = ?
");
$stmt->execute([$quotationId]);
$quotation = $stmt->fetch();

if (!$quotation) {
    die("Quotation not found.");
}

$itemStmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$itemStmt->execute([$quotationId]);
$items = $itemStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quotation - <?= htmlspecialchars($quotation['quotation_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; color: #333; font-size: 14px; }
        .print-container { max-width: 800px; margin: 0 auto; padding: 30px; }
        @media print {
            body { margin: 0; padding: 0; }
            .print-container { padding: 0; max-width: 100%; border: none; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-container border mt-4 mb-4">
        <div class="row mb-4">
            <div class="col-sm-6">
                <h1 class="text-uppercase text-primary mb-0">Quotation</h1>
                <p class="mb-0 text-muted"><?= htmlspecialchars($quotation['quotation_number']) ?></p>
            </div>
            <div class="col-sm-6 text-end">
                <h3 class="mb-1">RepairHub</h3>
                <p class="mb-0 text-muted small">
                    123 Tech Street, Silicon Valley<br>
                    City, State, 12345<br>
                    Phone: (123) 456-7890<br>
                    Email: contact@repairhub.com
                </p>
            </div>
        </div>
        
        <hr>
        
        <div class="row mb-4 mt-4">
            <div class="col-sm-6">
                <h6 class="text-uppercase text-muted">Quotation To:</h6>
                <h5 class="mb-1"><?= htmlspecialchars($quotation['customer_name'] ?? 'Walk-in Customer') ?></h5>
                <?php if ($quotation['customer_phone']): ?>
                    <p class="mb-0 small">Phone: <?= htmlspecialchars($quotation['customer_phone']) ?></p>
                <?php endif; ?>
                <?php if ($quotation['customer_email']): ?>
                    <p class="mb-0 small">Email: <?= htmlspecialchars($quotation['customer_email']) ?></p>
                <?php endif; ?>
                <?php if ($quotation['customer_address']): ?>
                    <p class="mb-0 small"><?= nl2br(htmlspecialchars($quotation['customer_address'])) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-sm-6 text-end">
                <table class="table table-sm table-borderless m-0">
                    <tr>
                        <td class="text-muted text-end">Date:</td>
                        <td class="text-end fw-bold"><?= date('M d, Y', strtotime($quotation['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted text-end">Valid Until:</td>
                        <td class="text-end fw-bold"><?= date('M d, Y', strtotime($quotation['valid_until'])) ?></td>
                    </tr>
                    <?php if (!empty($quotation['ticket_id'])): ?>
                    <tr>
                        <td class="text-muted text-end">Ticket #:</td>
                        <td class="text-end fw-bold"><?= htmlspecialchars($quotation['ticket_id']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <table class="table table-bordered mb-4">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="5%">#</th>
                    <th width="45%">Description</th>
                    <th class="text-center" width="15%">Type</th>
                    <th class="text-center" width="10%">Qty</th>
                    <th class="text-end" width="12.5%">Unit Price</th>
                    <th class="text-end" width="12.5%">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($items as $item): ?>
                <tr>
                    <td class="text-center"><?= $i++ ?></td>
                    <td><?= htmlspecialchars($item['description']) ?></td>
                    <td class="text-center"><?= ucfirst($item['type']) ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end"><?= formatCurrency($item['unit_price']) ?></td>
                    <td class="text-end fw-bold"><?= formatCurrency($item['total_price']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="row">
            <div class="col-sm-7">
                <?php if ($quotation['notes']): ?>
                <div class="mb-3">
                    <h6 class="text-muted">Notes:</h6>
                    <p class="small"><?= nl2br(htmlspecialchars($quotation['notes'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($quotation['terms']): ?>
                <div>
                    <h6 class="text-muted">Terms & Conditions:</h6>
                    <p class="small text-muted"><?= nl2br(htmlspecialchars($quotation['terms'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-sm-5">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td class="text-end">Subtotal:</td>
                        <td class="text-end fw-bold"><?= formatCurrency($quotation['subtotal']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-end">Tax (<?= $quotation['tax_rate'] ?>%):</td>
                        <td class="text-end fw-bold"><?= formatCurrency($quotation['tax_amount']) ?></td>
                    </tr>
                    <?php if ($quotation['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-end">Discount:</td>
                        <td class="text-end fw-bold text-danger">-<?= formatCurrency($quotation['discount_amount']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="border-top">
                        <td class="text-end fs-5"><strong>Total:</strong></td>
                        <td class="text-end fs-5"><strong><?= formatCurrency($quotation['total_amount']) ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="text-center mt-5 text-muted small">
            <p>Thank you for your business!</p>
            <p class="no-print"><button onclick="window.print()" class="btn btn-primary btn-sm mt-3">Print Again</button></p>
        </div>
    </div>
</body>
</html>
