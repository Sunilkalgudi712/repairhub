<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /Reper_hub/auth/login.php');
    exit;
}

// Fallback functions if they are missing from config.php
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
if (!function_exists('generateTicketId')) {
    function generateTicketId($pdo) {
        $prefix = 'TKT-';
        $number = date('Ymd') . '-';
        $random = mt_rand(1000, 9999);
        return $prefix . $number . $random;
    }
}

$device_types = defined('DEVICE_TYPES') ? DEVICE_TYPES : ['Smartphone', 'Laptop', 'Desktop', 'Tablet', 'Console', 'Printer', 'Other'];
$priorities = defined('PRIORITIES') ? PRIORITIES : ['Low', 'Normal', 'High', 'Urgent'];

$stmt = $pdo->query("SELECT id, name FROM users WHERE is_active = 1");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid form submission.';
        $_SESSION['flash_type'] = 'danger';
        header("Location: create.php");
        exit;
    }

    $customer_id = $_POST['customer_id'] ?? null;
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

    if (empty($customer_id) || empty($device_type) || empty($device_brand) || empty($device_model) || empty($problem_description)) {
        $_SESSION['flash_message'] = 'Please fill all required fields.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            $ticket_id = generateTicketId($pdo);
            
            $sql = "INSERT INTO repair_tickets (ticket_id, customer_id, device_type, device_brand, device_model, serial_number, imei_number, device_password, problem_description, device_condition, assigned_to, priority, estimated_cost, due_date, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$ticket_id, $customer_id, $device_type, $device_brand, $device_model, $serial_number, $imei_number, $device_password, $problem_description, $device_condition, $assigned_to, $priority, $estimated_cost, $due_date, $_SESSION['user_id']]);
            
            $new_ticket_db_id = $pdo->lastInsertId();

            // Status history
            $sqlHistory = "INSERT INTO ticket_status_history (ticket_id, status, user_id, notes) VALUES (?, 'Pending', ?, 'Ticket created')";
            $pdo->prepare($sqlHistory)->execute([$new_ticket_db_id, $_SESSION['user_id']]);

            // Handle file uploads
            if (isset($_FILES['device_images']) && !empty($_FILES['device_images']['name'][0])) {
                $upload_dir = '../uploads/devices/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                foreach ($_FILES['device_images']['tmp_name'] as $key => $tmp_name) {
                    $file_name = $_FILES['device_images']['name'][$key];
                    $file_size = $_FILES['device_images']['size'][$key];
                    $file_tmp = $_FILES['device_images']['tmp_name'][$key];
                    $file_type = $_FILES['device_images']['type'][$key];
                    
                    if (strpos($file_type, 'image/') === 0) {
                        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
                        $new_name = $new_ticket_db_id . '_' . uniqid() . '.' . $ext;
                        $dest = $upload_dir . $new_name;
                        
                        if (move_uploaded_file($file_tmp, $dest)) {
                            $sqlImage = "INSERT INTO device_images (ticket_id, image_path) VALUES (?, ?)";
                            $pdo->prepare($sqlImage)->execute([$new_ticket_db_id, 'uploads/devices/' . $new_name]);
                        }
                    }
                }
            }

            // Log activity
            $sqlLog = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'created', 'ticket', ?, ?)";
            $pdo->prepare($sqlLog)->execute([$_SESSION['user_id'], $new_ticket_db_id, "Created ticket $ticket_id"]);

            $pdo->commit();
            
            $_SESSION['flash_message'] = 'Ticket created successfully!';
            $_SESSION['flash_type'] = 'success';
            header("Location: view.php?id=$new_ticket_db_id");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = 'Error creating ticket: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }
    }
}

$pageTitle = 'Create New Ticket';
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
            <h2 class="mb-0">Create New Ticket</h2>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Tickets</a>
        </div>

        <form method="POST" enctype="multipart/form-data" action="create.php">
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
                            <div class="mb-3">
                                <label class="form-label text-danger">Search Customer *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="customerSearch" placeholder="Type name, email or phone...">
                                </div>
                                <input type="hidden" id="customerId" name="customer_id" required>
                                <div class="list-group position-absolute w-100" id="customerResults" style="z-index: 1000; display:none; max-height: 200px; overflow-y: auto;"></div>
                            </div>
                            <div class="mb-2" id="selectedCustomerDisplay" style="display:none;">
                                <div class="alert alert-info py-2 mb-0 d-flex justify-content-between align-items-center">
                                    <span id="customerNameDisplay" class="fw-bold"></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0" id="clearCustomer"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <div class="text-end mt-2">
                                <a href="../customers/create.php" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-user-plus"></i> New Customer
                                </a>
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
                                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-danger">Brand *</label>
                                    <input type="text" class="form-control" name="device_brand" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-danger">Model *</label>
                                    <input type="text" class="form-control" name="device_model" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Password / PIN</label>
                                    <input type="text" class="form-control" name="device_password" placeholder="If required for testing">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" class="form-control" name="serial_number">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">IMEI Number (Phones/Tablets)</label>
                                    <input type="text" class="form-control" name="imei_number">
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
                                <textarea class="form-control" name="problem_description" rows="4" required placeholder="Describe the issue reported by the customer..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Device Condition / Notes</label>
                                <textarea class="form-control" name="device_condition" rows="3" placeholder="Scratches, dents, included accessories, etc."></textarea>
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
                                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <?php foreach ($priorities as $p): ?>
                                        <option value="<?= htmlspecialchars($p) ?>" <?= $p === 'Normal' ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estimated Due Date</label>
                                <input type="date" class="form-control" name="due_date">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Estimated Cost (₹)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" step="0.01" class="form-control" name="estimated_cost" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Media Section -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">Device Images</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Upload Images</label>
                                <input class="form-control" type="file" name="device_images[]" multiple accept="image/*">
                                <div class="form-text">Take photos of device condition before repair.</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Create Ticket</button>
                    </div>

                </div>
            </div>
        </form>

    </div>
</div>

<!-- Autocomplete Script (assuming jQuery is loaded in header/footer) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('customerSearch');
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
                                searchInput.style.display = 'none';
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
        searchInput.style.display = 'block';
        selectedDisplay.style.display = 'none';
    });

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.style.display = 'none';
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
