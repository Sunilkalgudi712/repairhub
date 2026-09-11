<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $customerId = $_POST['customer_id'] ?: null;
    $ticketId = $_POST['ticket_id'] ?: null;
    $validUntil = $_POST['valid_until'];
    $notes = $_POST['notes'];
    $terms = $_POST['terms'];
    $taxRate = $_POST['tax_rate'];
    $discountAmount = $_POST['discount_amount'] ?: 0;
    
    $descriptions = $_POST['description'] ?? [];
    $types = $_POST['type'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unitPrices = $_POST['unit_price'] ?? [];
    
    if (empty($descriptions)) {
        $_SESSION['flash_message'] = "At least one item is required.";
        $_SESSION['flash_type'] = "danger";
        header("Location: create.php");
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $quotationNumber = generateQuotationNumber($pdo);
        
        $subtotal = 0;
        foreach ($descriptions as $i => $desc) {
            $qty = (float)$quantities[$i];
            $price = (float)$unitPrices[$i];
            $subtotal += ($qty * $price);
        }
        
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        
        $stmt = $pdo->prepare("INSERT INTO quotations (quotation_number, ticket_id, customer_id, subtotal, tax_rate, tax_amount, discount_amount, total_amount, status, valid_until, notes, terms, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Draft', ?, ?, ?, ?, NOW())");
        $stmt->execute([$quotationNumber, $ticketId, $customerId, $subtotal, $taxRate, $taxAmount, $discountAmount, $totalAmount, $validUntil, $notes, $terms, $_SESSION['user_id']]);
        
        $quotationId = $pdo->lastInsertId();
        
        $itemStmt = $pdo->prepare("INSERT INTO quotation_items (quotation_id, description, quantity, unit_price, total_price, type) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($descriptions as $i => $desc) {
            $qty = (float)$quantities[$i];
            $price = (float)$unitPrices[$i];
            $total = $qty * $price;
            $type = $types[$i];
            $itemStmt->execute([$quotationId, $desc, $qty, $price, $total, $type]);
        }
        
        $pdo->commit();
        
        $_SESSION['flash_message'] = "Quotation created successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: view.php?id=$quotationId");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Error creating quotation: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'New Quotation';
include '../includes/header.php';
include '../includes/sidebar.php';

$presetTicketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : '';
$presetCustomerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : '';

// Get open tickets for customer if preset
$openTickets = [];
if ($presetCustomerId) {
    $stmt = $pdo->prepare("SELECT id, ticket_id, device_type FROM repair_tickets WHERE customer_id = ? AND status NOT IN ('Completed', 'Delivered', 'Cancelled')");
    $stmt->execute([$presetCustomerId]);
    $openTickets = $stmt->fetchAll();
}
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>New Quotation</h2>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <form method="POST" action="create.php" id="quotationForm">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="row">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Quotation Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Customer</label>
                                <input type="text" id="customerSearch" class="form-control" placeholder="Search customer by name or phone..." autocomplete="off">
                                <div id="customerResults" class="list-group position-absolute w-100" style="z-index: 1000; display:none;"></div>
                                <input type="hidden" name="customer_id" id="customerId" value="<?= $presetCustomerId ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Related Ticket (Optional)</label>
                                <select name="ticket_id" id="ticketId" class="form-select">
                                    <option value="">-- None --</option>
                                    <?php foreach ($openTickets as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $t['id'] == $presetTicketId ? 'selected' : '' ?>><?= $t['ticket_id'] ?> (<?= $t['device_type'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h6 class="mb-3">Line Items</h6>
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle" id="invoiceItems">
                                <thead class="table-light">
                                    <tr>
                                        <th width="35%">Description</th>
                                        <th width="15%">Type</th>
                                        <th width="10%">Qty</th>
                                        <th width="15%">Unit Price (₹)</th>
                                        <th width="15%">Total (₹)</th>
                                        <th width="10%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Rows added via JS -->
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLineItem('invoiceItems')"><i class="fas fa-plus"></i> Add Item</button>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Notes (Visible to client)</label>
                                <textarea name="notes" class="form-control" rows="3"></textarea>
                                
                                <label class="form-label mt-3">Terms & Conditions</label>
                                <textarea name="terms" class="form-control" rows="3">1. Quotation valid until specified date.
2. 50% advance payment required to commence work.</textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal:</span>
                                            <span id="displaySubtotal">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2 align-items-center">
                                            <span>Tax Rate (%):</span>
                                            <input type="number" name="tax_rate" id="taxRate" class="form-control form-control-sm w-25 text-end" value="18" min="0" step="0.1" onchange="calculateTotals()">
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Tax Amount:</span>
                                            <span id="displayTax">₹0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2 align-items-center">
                                            <span>Discount (₹):</span>
                                            <input type="number" name="discount_amount" id="discountAmount" class="form-control form-control-sm w-25 text-end" value="0" min="0" step="1" onchange="calculateTotals()">
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between fw-bold fs-5">
                                            <span>Grand Total:</span>
                                            <span id="displayTotal">₹0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Valid Until</label>
                            <input type="date" name="valid_until" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Save Quotation</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
function calculateTotals() {
    let subtotal = 0;
    $('#invoiceItems tbody tr').each(function() {
        let qty = parseFloat($(this).find('.qty-input').val()) || 0;
        let price = parseFloat($(this).find('.price-input').val()) || 0;
        let total = qty * price;
        $(this).find('.row-total').text('₹' + total.toFixed(2));
        subtotal += total;
    });
    
    let taxRate = parseFloat($('#taxRate').val()) || 0;
    let discount = parseFloat($('#discountAmount').val()) || 0;
    
    let taxAmount = subtotal * (taxRate / 100);
    let grandTotal = subtotal + taxAmount - discount;
    
    $('#displaySubtotal').text('₹' + subtotal.toFixed(2));
    $('#displayTax').text('₹' + taxAmount.toFixed(2));
    $('#displayTotal').text('₹' + grandTotal.toFixed(2));
}

function addLineItem(tableId) {
    let rowHtml = `
        <tr>
            <td><input type="text" name="description[]" class="form-control form-control-sm" required></td>
            <td>
                <select name="type[]" class="form-select form-select-sm">
                    <option value="service">Service</option>
                    <option value="part">Part</option>
                    <option value="other">Other</option>
                </select>
            </td>
            <td><input type="number" name="quantity[]" class="form-control form-control-sm qty-input" value="1" min="1" onchange="calculateTotals()" required></td>
            <td><input type="number" name="unit_price[]" class="form-control form-control-sm price-input" value="0.00" min="0" step="0.01" onchange="calculateTotals()" required></td>
            <td class="row-total text-end">₹0.00</td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" onclick="$(this).closest('tr').remove(); calculateTotals();"><i class="fas fa-trash"></i></button></td>
        </tr>
    `;
    $('#' + tableId + ' tbody').append(rowHtml);
}

$(document).ready(function() {
    addLineItem('invoiceItems'); // Add one default row
    
    // Simple autocomplete dummy logic for demonstration.
    // In real app, make AJAX call to search customers.
    $('#customerSearch').on('keyup', function() {
        // Mock AJAX
    });
});
</script>
<?php include '../includes/footer.php'; ?>
