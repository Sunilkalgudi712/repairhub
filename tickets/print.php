<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Reper_hub/auth/login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die("Invalid ticket ID.");
}

$sql = "SELECT t.*, c.name, c.phone, c.email, c.address 
        FROM repair_tickets t 
        LEFT JOIN customers c ON t.customer_id = c.id 
        WHERE t.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Ticket not found.");
}

// Optional: get shop settings if they exist in DB, else use defaults
$shop_name = "RepairHub";
$shop_phone = "+91 98765 43210";
$shop_email = "contact@repairhub.com";
$shop_address = "123 Tech Street, IT Park, Mumbai, 400001";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Receipt - <?= htmlspecialchars($ticket['ticket_id']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: white; color: black; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
        .receipt-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .receipt-header { border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 20px; }
        .info-block { margin-bottom: 20px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="receipt-container">
        
        <div class="text-end mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary">Print</button>
            <button onclick="window.close()" class="btn btn-secondary">Close</button>
        </div>

        <div class="receipt-header text-center">
            <h1 class="fw-bold mb-1"><?= htmlspecialchars($shop_name) ?></h1>
            <p class="mb-0"><?= htmlspecialchars($shop_address) ?></p>
            <p class="mb-0">Phone: <?= htmlspecialchars($shop_phone) ?> | Email: <?= htmlspecialchars($shop_email) ?></p>
            <h3 class="mt-3 fw-bold">Repair Ticket Receipt</h3>
        </div>

        <div class="row info-block">
            <div class="col-6">
                <h5 class="fw-bold border-bottom pb-1">Customer Info</h5>
                <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($ticket['name']) ?></p>
                <p class="mb-1"><strong>Phone:</strong> <?= htmlspecialchars($ticket['phone']) ?></p>
                <?php if ($ticket['email']): ?>
                <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($ticket['email']) ?></p>
                <?php endif; ?>
            </div>
            <div class="col-6 text-end">
                <h5 class="fw-bold border-bottom pb-1">Ticket Details</h5>
                <p class="mb-1"><strong>Ticket #:</strong> <?= htmlspecialchars($ticket['ticket_id']) ?></p>
                <p class="mb-1"><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($ticket['created_at'])) ?></p>
                <p class="mb-1"><strong>Status:</strong> <?= htmlspecialchars($ticket['status']) ?></p>
            </div>
        </div>

        <div class="info-block">
            <h5 class="fw-bold border-bottom pb-1">Device Details</h5>
            <table class="table table-bordered table-sm mb-0">
                <tbody>
                    <tr>
                        <th width="20%">Device Type</th>
                        <td><?= htmlspecialchars($ticket['device_type']) ?></td>
                        <th width="20%">Brand & Model</th>
                        <td><?= htmlspecialchars($ticket['device_brand'] . ' ' . $ticket['device_model']) ?></td>
                    </tr>
                    <tr>
                        <th>Serial Number</th>
                        <td><?= htmlspecialchars($ticket['serial_number']) ?: 'N/A' ?></td>
                        <th>IMEI Number</th>
                        <td><?= htmlspecialchars($ticket['imei_number']) ?: 'N/A' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="info-block">
            <h5 class="fw-bold border-bottom pb-1">Problem Description</h5>
            <p><?= nl2br(htmlspecialchars($ticket['problem_description'])) ?></p>
        </div>

        <?php if ($ticket['device_condition']): ?>
        <div class="info-block">
            <h5 class="fw-bold border-bottom pb-1">Device Condition / Accessories</h5>
            <p><?= nl2br(htmlspecialchars($ticket['device_condition'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="row info-block mt-4">
            <div class="col-6">
                <h5 class="fw-bold border-bottom pb-1">Estimated Cost</h5>
                <h4 class="fw-bold">₹<?= number_format((float)$ticket['estimated_cost'], 2) ?></h4>
                <small class="text-muted">* Actual cost may vary after complete diagnosis.</small>
            </div>
        </div>

        <div class="mt-5 text-center">
            <div class="row">
                <div class="col-6">
                    <p>_______________________</p>
                    <p>Customer Signature</p>
                </div>
                <div class="col-6">
                    <p>_______________________</p>
                    <p>Authorized Signatory</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-muted small">Thank you for choosing <?= htmlspecialchars($shop_name) ?>!</p>
        </div>

    </div>
</body>
</html>
