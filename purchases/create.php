<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    try {
        $pdo->beginTransaction();
        
        $supplier_name = $_POST['supplier_name'];
        $supplier_contact = $_POST['supplier_contact'];
        $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : null;
        $notes = $_POST['notes'];
        
        // Calculate totals
        $subtotal = 0;
        $tax = (float)($_POST['tax_amount'] ?? 0);
        
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $subtotal += ((float)$item['qty'] * (float)$item['unit_price']);
            }
        }
        $total_amount = $subtotal + $tax;
        
        // Generate purchase number
        $stmt = $pdo->query("SELECT COUNT(*) FROM purchases WHERE DATE(created_at) = CURDATE()");
        $dailyCount = $stmt->fetchColumn() + 1;
        $purchase_number = 'PUR-' . date('Ymd') . '-' . str_pad($dailyCount, 3, '0', STR_PAD_LEFT);
        
        $stmt = $pdo->prepare("INSERT INTO purchases (purchase_number, supplier_name, supplier_contact, expected_date, notes, subtotal, tax_amount, total_amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Ordered', ?)");
        $stmt->execute([
            $purchase_number, $supplier_name, $supplier_contact, $expected_date, $notes,
            $subtotal, $tax, $total_amount, $_SESSION['user_id']
        ]);
        $purchase_id = $pdo->lastInsertId();
        
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            $itemStmt = $pdo->prepare("INSERT INTO purchase_items (purchase_id, inventory_id, item_name, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($_POST['items'] as $item) {
                $inv_id = !empty($item['inventory_id']) ? $item['inventory_id'] : null;
                $qty = (int)$item['qty'];
                $price = (float)$item['unit_price'];
                $item_total = $qty * $price;
                $itemStmt->execute([$purchase_id, $inv_id, $item['item_name'], $qty, $price, $item_total]);
            }
        }
        
        $pdo->commit();
        $_SESSION['flash_message'] = 'Purchase created successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: /Reper_hub/purchases/index.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to create purchase: " . $e->getMessage();
    }
}

// Fetch inventory for dropdown
$inventory_items = $pdo->query("SELECT id, name FROM inventory ORDER BY name ASC")->fetchAll();

$pageTitle = 'New Purchase';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="/Reper_hub/purchases/index.php" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Supplier Name *</label>
                        <input type="text" name="supplier_name" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Supplier Contact</label>
                        <input type="text" name="supplier_contact" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Expected Date</label>
                        <input type="date" name="expected_date" class="form-control">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <h5 class="mb-3">Line Items</h5>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered align-middle" id="purchaseItems">
                        <thead class="table-light">
                            <tr>
                                <th width="30%">Item / Inventory Link</th>
                                <th>Item Name</th>
                                <th width="15%">Qty</th>
                                <th width="15%">Unit Price (₹)</th>
                                <th width="15%">Total (₹)</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- items injected via JS -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="addLineItem()">+ Add Item</button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Tax Amount (₹):</td>
                                <td><input type="number" step="0.01" name="tax_amount" id="tax_amount" class="form-control form-control-sm" value="0" onchange="calculateTotals()"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Grand Total (₹):</td>
                                <td><input type="text" id="grand_total" class="form-control form-control-sm" readonly></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">Create Purchase</button>
            </form>
        </div>
    </div>
  </div>
</div>

<script>
let itemIndex = 0;
const inventoryOptions = `<option value="">-- Custom Item --</option>` + 
<?php foreach ($inventory_items as $inv): ?>
`<option value="<?= $inv['id'] ?>"><?= htmlspecialchars(addslashes($inv['name'])) ?></option>` +
<?php endforeach; ?>
'';

function addLineItem() {
    const tbody = document.querySelector('#purchaseItems tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="items[${itemIndex}][inventory_id]" class="form-select form-select-sm" onchange="updateItemName(this)">
                ${inventoryOptions}
            </select>
        </td>
        <td>
            <input type="text" name="items[${itemIndex}][item_name]" class="form-control form-control-sm item-name" required>
        </td>
        <td>
            <input type="number" name="items[${itemIndex}][qty]" class="form-control form-control-sm qty" value="1" min="1" required onchange="calculateTotals()">
        </td>
        <td>
            <input type="number" step="0.01" name="items[${itemIndex}][unit_price]" class="form-control form-control-sm price" value="0" required onchange="calculateTotals()">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm line-total" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('tr').remove(); calculateTotals();"><i class="fas fa-trash"></i></button>
        </td>
    `;
    tbody.appendChild(tr);
    itemIndex++;
}

function updateItemName(select) {
    const nameInput = select.closest('tr').querySelector('.item-name');
    if (select.value) {
        nameInput.value = select.options[select.selectedIndex].text;
    }
}

function calculateTotals() {
    let subtotal = 0;
    document.querySelectorAll('#purchaseItems tbody tr').forEach(tr => {
        const qty = parseFloat(tr.querySelector('.qty').value) || 0;
        const price = parseFloat(tr.querySelector('.price').value) || 0;
        const lineTotal = qty * price;
        tr.querySelector('.line-total').value = lineTotal.toFixed(2);
        subtotal += lineTotal;
    });
    const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
    document.getElementById('grand_total').value = (subtotal + tax).toFixed(2);
}

// Add first row by default
window.onload = addLineItem;
</script>

<?php include '../includes/footer.php'; ?>
