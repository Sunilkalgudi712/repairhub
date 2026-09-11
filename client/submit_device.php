<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'Submit New Device';

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer_id = $customer['id'];

// Default device types
$device_types = ['Smartphone', 'Laptop', 'Desktop', 'Tablet', 'Smartwatch', 'Console', 'Other'];
if (defined('DEVICE_TYPES') && is_array(DEVICE_TYPES)) {
    $device_types = DEVICE_TYPES;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $device_type = trim($_POST['device_type'] ?? '');
    $device_brand = trim($_POST['device_brand'] ?? '');
    $device_model = trim($_POST['device_model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $problem_description = trim($_POST['problem_description'] ?? '');
    $device_condition = trim($_POST['device_condition'] ?? '');
    
    if (empty($device_type) || empty($device_brand) || empty($device_model) || empty($problem_description)) {
        $_SESSION['flash_message'] = 'Please fill in all required fields.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        try {
            $pdo->beginTransaction();
            
            $ticket_id = generateTicketId($pdo);
            
            $stmt = $pdo->prepare("
                INSERT INTO repair_tickets (
                    ticket_id, customer_id, device_type, device_brand, device_model, 
                    serial_number, problem_description, device_condition, status, 
                    submitted_by_client, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 1, NOW())
            ");
            $stmt->execute([
                $ticket_id, $customer_id, $device_type, $device_brand, $device_model,
                $serial_number, $problem_description, $device_condition
            ]);
            
            $repair_id = $pdo->lastInsertId();
            
            // Handle image uploads
            if (!empty($_FILES['device_images']['name'][0])) {
                $upload_dir = '../uploads/devices/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['device_images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['device_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_ext = strtolower(pathinfo($_FILES['device_images']['name'][$key], PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (in_array($file_ext, $allowed_exts)) {
                            $new_filename = uniqid('dev_') . '_' . time() . '.' . $file_ext;
                            if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                                // Assuming there's a device_images table, or we just insert into service_progress as initial photos
                                $stmt_img = $pdo->prepare("INSERT INTO service_progress (ticket_id, user_id, status_update, description, image_path, created_at) VALUES (?, ?, 'Initial Submission', 'Customer uploaded device image.', ?, NOW())");
                                $stmt_img->execute([$repair_id, $_SESSION['user_id'], 'uploads/devices/' . $new_filename]);
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            $_SESSION['flash_message'] = "Repair request submitted successfully! Your Ticket ID is $ticket_id.";
            $_SESSION['flash_type'] = 'success';
            header('Location: my_repairs.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = 'An error occurred. Please try again.';
            $_SESSION['flash_type'] = 'danger';
        }
    }
}

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Submit New Device for Repair</h4>
            <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Device Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Device Type <span class="text-danger">*</span></label>
                            <select name="device_type" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach($device_types as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Brand <span class="text-danger">*</span></label>
                            <input type="text" name="device_brand" class="form-control" required placeholder="e.g., Apple, Samsung, Dell">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model <span class="text-danger">*</span></label>
                            <input type="text" name="device_model" class="form-control" required placeholder="e.g., iPhone 13, Galaxy S21">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Serial / IMEI Number</label>
                            <input type="text" name="serial_number" class="form-control" placeholder="Optional">
                        </div>
                    </div>
                    
                    <h5 class="fw-bold mb-3 border-bottom pb-2">Issue Details</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label">Problem Description <span class="text-danger">*</span></label>
                            <textarea name="problem_description" class="form-control" rows="4" required placeholder="Please describe the issue in detail..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Device Condition (Physical)</label>
                            <textarea name="device_condition" class="form-control" rows="2" placeholder="e.g., Screen cracked, scratches on back..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Upload Device Images (Optional)</label>
                            <input type="file" name="device_images[]" class="form-control" accept="image/*" multiple>
                            <div class="form-text">You can upload multiple images showing the device condition.</div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
        
    </div>
</div>

<?php include '../includes/footer.php'; ?>
