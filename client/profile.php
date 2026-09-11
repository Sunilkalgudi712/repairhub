<?php
session_start();
require_once '../config.php';
requireClient();

$pageTitle = 'My Profile';

// Get customer info
$customer = getCurrentClientCustomer($pdo);
if (!$customer) die("Customer record not found.");
$customer['login_email'] = $customer['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $alt_phone = trim($_POST['alt_phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        
        if (empty($name) || empty($email) || empty($phone)) {
            $_SESSION['flash_message'] = 'Name, Email, and Phone are required.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            // Check email uniqueness in users
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $_SESSION['flash_message'] = 'Email is already in use.';
                $_SESSION['flash_type'] = 'danger';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Update user
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $_SESSION['user_id']]);
                    
                    // Update customer
                    $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, alt_phone = ?, address = ?, city = ?, state = ?, pincode = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $phone, $alt_phone, $address, $city, $state, $pincode, $customer['id']]);
                    
                    $pdo->commit();
                    
                    $_SESSION['user_name'] = $name;
                    $_SESSION['flash_message'] = 'Profile updated successfully.';
                    $_SESSION['flash_type'] = 'success';
                    
                    header('Location: profile.php');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['flash_message'] = 'Database error occurred.';
                    $_SESSION['flash_type'] = 'danger';
                }
            }
        }
    } elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['flash_message'] = 'All password fields are required.';
            $_SESSION['flash_type'] = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['flash_message'] = 'New passwords do not match.';
            $_SESSION['flash_type'] = 'danger';
        } elseif (strlen($new_password) < 6) {
            $_SESSION['flash_message'] = 'New password must be at least 6 characters.';
            $_SESSION['flash_type'] = 'danger';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (password_verify($current_password, $user['password'])) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $_SESSION['user_id']]);
                
                $_SESSION['flash_message'] = 'Password updated successfully.';
                $_SESSION['flash_type'] = 'success';
                header('Location: profile.php');
                exit;
            } else {
                $_SESSION['flash_message'] = 'Incorrect current password.';
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
}

include '../includes/client_header.php';
include '../includes/client_sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <h4 class="mb-4">My Profile</h4>
        
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0">Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['login_email']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alternate Phone</label>
                                    <input type="text" name="alt_phone" class="form-control" value="<?= htmlspecialchars($customer['alt_phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($customer['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State</label>
                                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($customer['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Pincode</label>
                                    <input type="text" name="pincode" class="form-control" value="<?= htmlspecialchars($customer['pincode'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                        <h5 class="fw-bold mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php include '../includes/footer.php'; ?>
