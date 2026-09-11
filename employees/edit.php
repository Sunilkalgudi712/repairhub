<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Edit Employee';
$errors = [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errors[] = "Invalid CSRF token.";
        } elseif ($id == $_SESSION['user_id']) {
            $errors[] = "You cannot delete your own account.";
        } else {
            try {
                // Delete user
                $delStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delStmt->execute([$id]);
                $_SESSION['flash_message'] = "Employee deleted successfully.";
                $_SESSION['flash_type'] = "success";
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = "Cannot delete employee. They might have associated records. Consider deactivating instead.";
            }
        }
    } else {
        // Update user
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $errors[] = "Invalid CSRF token.";
        } else {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $role = $_POST['role'] ?? '';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($name)) $errors[] = "Name is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
            if (!in_array($role, ['Admin', 'Technician', 'Receptionist'])) $errors[] = "Invalid role selected.";
            
            if (!empty($password)) {
                if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
                if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    $errors[] = "Email is already in use by another user.";
                } else {
                    try {
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, is_active = ?, password = ? WHERE id = ?");
                            $updateStmt->execute([$name, $email, $phone, $role, $is_active, $hashed_password, $id]);
                        } else {
                            $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, is_active = ? WHERE id = ?");
                            $updateStmt->execute([$name, $email, $phone, $role, $is_active, $id]);
                        }

                        if ($role === 'Technician') {
                            $schedStmt = $pdo->prepare("INSERT IGNORE INTO worker_schedule (user_id, day_of_week, is_working, start_time, end_time, max_jobs) VALUES (?, ?, ?, '09:00:00', '18:00:00', 5)");
                            for ($day = 0; $day <= 6; $day++) {
                                $isWorking = ($day === 0) ? 0 : 1;
                                $schedStmt->execute([$id, $day, $isWorking]);
                            }
                        }
                        
                        $_SESSION['flash_message'] = "Employee updated successfully.";
                        $_SESSION['flash_type'] = "success";
                        header("Location: edit.php?id=$id");
                        exit;
                    } catch (PDOException $e) {
                        $errors[] = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Performance Stats
$stats = [
    'total_tickets' => 0,
    'completed_tickets' => 0,
    'in_progress_tickets' => 0,
    'revenue_generated' => 0
];

$statStmt = $pdo->prepare("SELECT 
    COUNT(id) as total,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
    FROM repair_tickets WHERE assigned_to = ?");
$statStmt->execute([$id]);
$tStats = $statStmt->fetch(PDO::FETCH_ASSOC);
if ($tStats) {
    $stats['total_tickets'] = $tStats['total'] ?? 0;
    $stats['completed_tickets'] = $tStats['completed'] ?? 0;
    $stats['in_progress_tickets'] = $tStats['in_progress'] ?? 0;
}

$revStmt = $pdo->prepare("SELECT SUM(paid_amount) as total_rev FROM invoices WHERE created_by = ? AND status = 'Paid'");
$revStmt->execute([$id]);
$stats['revenue_generated'] = $revStmt->fetchColumn() ?: 0;

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Edit Employee: <?= htmlspecialchars($employee['name']) ?></h2>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back to Employees</a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? $employee['name']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $employee['email']) ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? $employee['phone']) ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Role <span class="text-danger">*</span></label>
                                    <select name="role" class="form-select" required>
                                        <?php $r = $_POST['role'] ?? $employee['role']; ?>
                                        <option value="Admin" <?= $r === 'Admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="Technician" <?= $r === 'Technician' ? 'selected' : '' ?>>Technician</option>
                                        <option value="Receptionist" <?= $r === 'Receptionist' ? 'selected' : '' ?>>Receptionist</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3 form-check form-switch">
                                <?php $isActive = isset($_POST['is_active']) ? true : (isset($employee['is_active']) && $employee['is_active']); ?>
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $isActive ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isActive">Active Account</label>
                            </div>

                            <hr class="my-4">
                            <h5 class="mb-3">Change Password (Optional)</h5>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" minlength="6">
                                    <div class="form-text">Leave blank to keep current password.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" minlength="6">
                                </div>
                            </div>

                            <div class="mt-4 d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="submit" class="btn btn-primary">Update Employee</button>
                                    <a href="index.php" class="btn btn-link">Cancel</a>
                                </div>
                                <?php if ($id != clone $_SESSION['user_id']): // prevent delete self ?>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        Delete Employee
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h5 class="mb-0"><i class="fas fa-chart-line text-primary me-2"></i>Performance Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span class="text-muted">Total Tickets Assigned</span>
                            <span class="fw-bold fs-5"><?= number_format($stats['total_tickets']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span class="text-muted">Completed Tickets</span>
                            <span class="fw-bold fs-5 text-success"><?= number_format($stats['completed_tickets']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                            <span class="text-muted">In Progress</span>
                            <span class="fw-bold fs-5 text-primary"><?= number_format($stats['in_progress_tickets']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Revenue Generated</span>
                            <span class="fw-bold fs-5">₹<?= number_format($stats['revenue_generated'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<?php if ($id != clone $_SESSION['user_id']): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete employee <strong><?= htmlspecialchars($employee['name']) ?></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <button type="submit" class="btn btn-danger">Yes, Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
