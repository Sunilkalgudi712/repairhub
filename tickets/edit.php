<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/auth/login.php");
    exit;
}

if (!function_exists('csrfField')) {
    function csrfField() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }
}
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

$device_types = defined('DEVICE_TYPES') ? DEVICE_TYPES : ['Smartphone', 'Laptop', 'Desktop', 'Tablet', 'Console', 'Printer', 'Other'];
$priorities = defined('PRIORITIES') ? PRIORITIES : ['Low', 'Normal', 'High', 'Urgent'];

$stmt = $pdo->prepare("SELECT t.*, c.name, c.phone FROM repair_tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE t.id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->query("SELECT id, name FROM users WHERE is_active = 1");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid form submission.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $customer_id = $_POST['customer_id'] ?? $ticket['customer_id'];
        $device_type = $_POST['device_type'] ?? '';
        $device_brand = $_POST['device_brand'] ?? '';
        $device_model = $_POST['device_model'] ?? '';
        $serial_number = $_POST['serial_number'] ?? '';
        $imei_number = $_POST['imei_number'] ?? '';
        $device_password = $_POST['device_password'] ?? '';
        $problem_description = $_POST['problem_description'] ?? '';
        $device_condition = $_POST['device_condition'] ?? '';
        $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
        $priority = $_POST['priority'] ?? 'Normal';
        $estimated_cost = !empty($_POST['estimated_cost']) ? (float)$_POST['estimated_cost'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

        if (empty($device_type) || empty($device_brand) || empty($device_model) || empty($problem_description)) {
            $_SESSION['flash_message'] = 'Please fill all required fields.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            try {
                $sql = "UPDATE repair_tickets SET 
                        customer_id = ?, device_type = ?, device_brand = ?, device_model = ?, 
                        serial_number = ?, imei_number = ?, device_password = ?, problem_description = ?, 
                        device_condition = ?, assigned_to = ?, priority = ?, estimated_cost = ?, 
                        due_date = ?, updated_at = NOW()
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $customer_id, $device_type, $device_brand, $device_model, 
                    $serial_number, $imei_number, $device_password, $problem_description, 
                    $device_condition, $assigned_to, $priority, $estimated_cost, 
                    $due_date, $id
                ]);

                $sqlLog = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'updated', 'ticket', ?, ?)";
                $pdo->prepare($sqlLog)->execute([$_SESSION['user_id'], $id, "Updated ticket " . $ticket['ticket_id']]);

                $_SESSION['flash_message'] = 'Ticket updated successfully!';
                $_SESSION['flash_type'] = 'success';
                header("Location: view.php?id=$id");
                exit;

            } catch (PDOException $e) {
                $_SESSION['flash_message'] = 'Error updating ticket: ' . $e->getMessage();
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
}

$pageTitle = 'Edit Ticket - ' . htmlspecialchars($ticket['ticket_id']);
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Edit Ticket #<?= htmlspecialchars($ticket['ticket_id']) ?></h2>
            <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Cancel</a>
        </div>

        <form method="POST" action="edit.php?id=<?= $id ?>">
            <?= csrfField() ?>
            
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    
                    <!-- Customer Section -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">Customer Information</h5>
                        </div>
                        <div class="card-body position-relative">
                            <div class="mb-3" id="searchContainer" style="display:none;">
                                <label class="form-label text-danger">Search Customer *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="customerSearch" placeholder="Type name, email or phone...">
                                </div>
                                <div class="list-group position-absolute w-100" id="customerResults" style="z-index: 1000; display:none; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            
                            <input type="hidden" id="customerId" name="customer_id" value="<?= htmlspecialchars($ticket['customer_id']) ?>" required>
                            
                            <div class="mb-2" id="selectedCustomerDisplay">
                                <label class="form-label">Current Customer</label>
                                <div class="alert alert-info py-2 mb-0 d-flex justify-content-between align-items-center">
                                    <span id="customerNameDisplay" class="fw-bold"><?= htmlspecialchars($ticket['name'] . ' (' . $ticket['phone'] . ')') ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" id="clearCustomer" title="Change Customer"><i class="fas fa-exchange-alt"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Device Section -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">Device Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-danger">Device Type *</label>
                                    <select class="form-select" name="device_type" required>
                                        <option value="">Select Type</option>
                                        <?php foreach ($device_types as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>" <?= $ticket['device_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-danger">Brand *</label>
                                    <input type="text" class="form-control" name="device_brand" value="<?= htmlspecialchars($ticket['device_brand']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-danger">Model *</label>
                                    <input type="text" class="form-control" name="device_model" value="<?= htmlspecialchars($ticket['device_model']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password / PIN</label>
                                    <input type="text" class="form-control" name="device_password" value="<?= htmlspecialchars($ticket['device_password']) ?>" placeholder="If required for testing">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" name="serial_number" value="<?= htmlspecialchars($ticket['serial_number']) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">IMEI Number (Phones/Tablets)</label>
                                    <input type="text" class="form-control" name="imei_number" value="<?= htmlspecialchars($ticket['imei_number']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Problem Section -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">Problem & Diagnosis</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label text-danger">Problem Description *</label>
                                <textarea class="form-control" name="problem_description" rows="4" required><?= htmlspecialchars($ticket['problem_description']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Device Condition / Notes</label>
                                <textarea class="form-control" name="device_condition" rows="3"><?= htmlspecialchars($ticket['device_condition']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    
                    <!-- Assignment Section -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">Assignment & Scheduling</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Assign To</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $ticket['assigned_to'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <?php foreach ($priorities as $p): ?>
                                        <option value="<?= htmlspecialchars($p) ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estimated Due Date</label>
                                <input type="date" class="form-control" name="due_date" value="<?= htmlspecialchars($ticket['due_date']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estimated Cost (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" name="estimated_cost" value="<?= htmlspecialchars($ticket['estimated_cost']) ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Changes</button>
                    </div>

                </div>
            </div>
        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customerSearch');
    const searchContainer = document.getElementById('searchContainer');
    const resultsDiv = document.getElementById('customerResults');
    const customerIdInput = document.getElementById('customerId');
    const selectedDisplay = document.getElementById('selectedCustomerDisplay');
    const customerNameDisplay = document.getElementById('customerNameDisplay');
    const clearBtn = document.getElementById('clearCustomer');

    searchInput.addEventListener('input', function() {
        let query = this.value;
        if (query.length >= 2) {
            fetch(`../customers/search_ajax.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    resultsDiv.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(customer => {
                            let a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.innerHTML = `<strong>${customer.name}</strong> - ${customer.phone}`;
                            a.onclick = function(e) {
                                e.preventDefault();
                                customerIdInput.value = customer.id;
                                customerNameDisplay.textContent = `${customer.name} (${customer.phone})`;
                                searchContainer.style.display = 'none';
                                selectedDisplay.style.display = 'block';
                                resultsDiv.style.display = 'none';
                            };
                            resultsDiv.appendChild(a);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.style.display = 'none';
                    }
                })
                .catch(err => console.error(err));
        } else {
            resultsDiv.style.display = 'none';
        }
    });

    clearBtn.addEventListener('click', function() {
        customerIdInput.value = '';
        searchInput.value = '';
        searchContainer.style.display = 'block';
        selectedDisplay.style.display = 'none';
    });

    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
