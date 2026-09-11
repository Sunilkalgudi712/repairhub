<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Add Employee';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? '';

        if (empty($name)) $errors[] = "Name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($password) || strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
        if (!in_array($role, ['Admin', 'Technician', 'Receptionist'])) $errors[] = "Invalid role selected.";

        if (empty($errors)) {
            // Check if email unique
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = "Email is already in use.";
            } else {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                    $insertStmt->execute([$name, $email, $phone, $hashed_password, $role]);
                    $newUserId = $pdo->lastInsertId();

                    if ($role === 'Technician') {
                        $schedStmt = $pdo->prepare("INSERT IGNORE INTO worker_schedule (user_id, day_of_week, is_working, start_time, end_time, max_jobs) VALUES (?, ?, ?, '09:00:00', '18:00:00', 5)");
                        for ($day = 0; $day <= 6; $day++) {
                            $isWorking = ($day === 0) ? 0 : 1; // Sunday off by default
                            $schedStmt->execute([$newUserId, $day, $isWorking]);
                        }
                    }
                    
                    $_SESSION['flash_message'] = "Employee added successfully.";
                    $_SESSION['flash_type'] = "success";
                    header('Location: index.php');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Add Employee</h2>
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

        <div class="card border-0 shadow-sm" style="max-width: 600px;">
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="">Select Role...</option>
                            <option value="Admin" <?= (isset($_POST['role']) && $_POST['role'] === 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="Technician" <?= (isset($_POST['role']) && $_POST['role'] === 'Technician') ? 'selected' : '' ?>>Technician</option>
                            <option value="Receptionist" <?= (isset($_POST['role']) && $_POST['role'] === 'Receptionist') ? 'selected' : '' ?>>Receptionist</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <div class="form-text">Minimum 6 characters.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Save Employee</button>
                        <a href="index.php" class="btn btn-link">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
<?php include '../includes/footer.php'; ?>
