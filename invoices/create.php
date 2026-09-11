<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

$pageTitle = 'Create Invoice';

$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;

$ticket = null;
$customer = null;

if ($ticket_id) {
    $stmt = $pdo->prepare("SELECT t.*, c.name as customer_name, c.email as customer_email FROM repair_tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE t.id = ?");
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ticket) {
        $customer_id = $ticket['customer_id'];
    }
}

if ($customer_id) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }

    try {
        $pdo->beginTransaction();

        $postCustomerId = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
        $postTicketId = !empty($_POST['ticket_id']) ? $_POST['ticket_id'] : null;
        $taxRate = (float)($_POST['tax_rate'] ?? 18);
        $discountAmount = (float)($_POST['discount_amount'] ?? 0);
        $dueDate = $_POST['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $paymentMethod = $_POST['payment_method'] ?? 'Cash';
        $notes = $_POST['notes'] ?? '';
        $terms = $_POST['terms'] ?? '';
        
        $status = 'Draft';

        // Process line items
        $descriptions = $_POST['item_description'] ?? [];
        $inventoryIds = $_POST['item_inventory_id'] ?? [];
        $quantities = $_POST['item_qty'] ?? [];
        $unitPrices = $_POST['item_price'] ?? [];

        $subtotal = 0;
        $items = [];

        foreach ($descriptions as $index => $desc) {
            $qty = (float)($quantities[$index] ?? 1);
            $price = (float)($unitPrices[$index] ?? 0);
            $invId = !empty($inventoryIds[$index]) ? $inventoryIds[$index] : null;
            $total = $qty * $price;
            $subtotal += $total;

            $items[] = [
                'description' => $desc,
                'inventory_id' => $invId,
                'quantity' => $qty,
                'unit_price' => $price,
                'total' => $total
            ];
        }

        $taxAmount = $subtotal * ($taxRate / 100);
        $grandTotal = $subtotal + $taxAmount - $discountAmount;
        $dueAmount = $grandTotal; // Initially due is full amount

        // Generate invoice number
        $invNumStmt = $pdo->query("SELECT MAX(id) FROM invoices");
        $maxId = $invNumStmt->fetchColumn();
        $invoiceNumber = 'INV-' . str_pad(($maxId ? $maxId + 1 : 1), 6, '0', STR_PAD_LEFT);

        // Insert invoice
        $stmt = $pdo->prepare("
            INSERT INTO invoices (invoice_number, customer_id, ticket_id, status, subtotal, tax_rate, tax_amount, discount_amount, total_amount, paid_amount, due_amount, payment_method, due_date, notes, terms, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceNumber, $postCustomerId, $postTicketId, $status, $subtotal, $taxRate, $taxAmount, $discountAmount, $grandTotal, $dueAmount, $paymentMethod, $dueDate, $notes, $terms, $_SESSION['user_id']
        ]);
        
        $invoiceId = $pdo->lastInsertId();

        // Insert line items
        $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, inventory_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($items as $item) {
            $itemStmt->execute([
                $invoiceId, $item['inventory_id'], $item['description'], $item['quantity'], $item['unit_price'], $item['total']
            ]);

            if ($item['inventory_id']) {
                $updInv = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                $updInv->execute([$item['quantity'], $item['inventory_id'], $item['quantity']]);
            }
        }

        $pdo->commit();
        $_SESSION['flash_message'] = "Invoice created successfully!";
        $_SESSION['flash_type'] = "success";
        header("Location: view.php?id=$invoiceId");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Error creating invoice: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Create Invoice</h1>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Invoices</a>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <form method="POST" id="invoiceForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Invoice Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Customer *</label>
                                    <input type="hidden" name="customer_id" id="customerId" value="<?= htmlspecialchars($customer['id'] ?? '') ?>" required>
                                    <input type="text" class="form-control" id="customerSearch" placeholder="Search Customer..." value="<?= htmlspecialchars($customer['name'] ?? '') ?>" autocomplete="off" required>
                                    <div id="customerResults" class="list-group position-absolute w-100" style="z-index: 1000;"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Ticket Reference (Optional)</label>
                                    <input type="text" name="ticket_id" class="form-control" value="<?= htmlspecialchars($ticket_id ?? '') ?>" readonly>
                                </div>
                            </div>
                            
                            <h6 class="mt-4 mb-3">Line Items</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle" id="invoiceItems">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 40%">Description</th>
                                            <th style="width: 15%">Qty</th>
                                            <th style="width: 20%">Unit Price (₹)</th>
                                            <th style="width: 20%">Total (₹)</th>
                                            <th style="width: 5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <input type="text" name="item_description[]" class="form-control" required placeholder="Service/Item name">
                                                <input type="hidden" name="item_inventory_id[]" value="">
                                            </td>
                                            <td><input type="number" name="item_qty[]" class="form-control qty-input" value="1" min="1" step="0.01" required></td>
                                            <td><input type="number" name="item_price[]" class="form-control price-input" value="0.00" min="0" step="0.01" required></td>
                                            <td><input type="text" class="form-control row-total" value="0.00" readonly></td>
                                            <td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" id="addItemBtn"><i class="fas fa-plus"></i> Add Item</button>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <span>Subtotal</span>
                                <strong>₹<span id="subtotal">0.00</span></strong>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" name="tax_rate" id="taxRate" class="form-control text-end" value="18" step="0.1" min="0">
                            </div>
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <span>Tax Amount</span>
                                <strong>₹<span id="taxAmount">0.00</span></strong>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Discount (₹)</label>
                                <input type="number" name="discount_amount" id="discountAmount" class="form-control text-end" value="0" step="0.01" min="0">
                            </div>
                            <hr>
                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <h5>Grand Total</h5>
                                <h5 class="text-primary">₹<span id="grandTotal">0.00</span></h5>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="Cash">Cash</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="UPI">UPI</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Terms & Conditions</label>
                                <textarea name="terms" class="form-control" rows="2">Thank you for your business. Payment is expected within 7 days.</textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100"><i class="fas fa-save"></i> Save Invoice</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function calculateTotals() {
        let subtotal = 0;
        document.querySelectorAll('#invoiceItems tbody tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const total = qty * price;
            row.querySelector('.row-total').value = total.toFixed(2);
            subtotal += total;
        });
        
        const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
        const discount = parseFloat(document.getElementById('discountAmount').value) || 0;
        
        const taxAmount = subtotal * (taxRate / 100);
        const grandTotal = subtotal + taxAmount - discount;
        
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('taxAmount').textContent = taxAmount.toFixed(2);
        document.getElementById('grandTotal').textContent = grandTotal.toFixed(2);
    }

    document.getElementById('invoiceItems').addEventListener('input', function(e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            calculateTotals();
        }
    });

    document.getElementById('taxRate').addEventListener('input', calculateTotals);
    document.getElementById('discountAmount').addEventListener('input', calculateTotals);

    document.getElementById('addItemBtn').addEventListener('click', function() {
        const tbody = document.querySelector('#invoiceItems tbody');
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <input type="text" name="item_description[]" class="form-control" required placeholder="Service/Item name">
                <input type="hidden" name="item_inventory_id[]" value="">
            </td>
            <td><input type="number" name="item_qty[]" class="form-control qty-input" value="1" min="1" step="0.01" required></td>
            <td><input type="number" name="item_price[]" class="form-control price-input" value="0.00" min="0" step="0.01" required></td>
            <td><input type="text" class="form-control row-total" value="0.00" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger remove-row"><i class="fas fa-trash"></i></button></td>
        `;
        tbody.appendChild(newRow);
        calculateTotals();
    });

    document.getElementById('invoiceItems').addEventListener('click', function(e) {
        if (e.target.closest('.remove-row')) {
            const row = e.target.closest('tr');
            if (document.querySelectorAll('#invoiceItems tbody tr').length > 1) {
                row.remove();
                calculateTotals();
            }
        }
    });

    calculateTotals();
});
</script>
<?php include '../includes/footer.php'; ?>
