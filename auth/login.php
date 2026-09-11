<?php
session_start();
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['flash_message'] = "Please enter both email and password.";
        $_SESSION['flash_type'] = "danger";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Check if account is active
            if (isset($user['is_active']) && !$user['is_active']) {
                $_SESSION['flash_message'] = "Your account has been deactivated. Contact admin.";
                $_SESSION['flash_type'] = "danger";
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];

                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

                // Role-based redirect
                switch ($user['role']) {
                    case 'Client':
                        header("Location: " . APP_URL . "/client/dashboard.php");
                        break;
                    case 'Technician':
                        header("Location: " . APP_URL . "/worker/dashboard.php");
                        break;
                    default: // Admin, Receptionist
                        header("Location: " . APP_URL . "/index.php");
                        break;
                }
                exit;
            }
        } else {
            $_SESSION['flash_message'] = "Invalid email or password.";
            $_SESSION['flash_type'] = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RepairHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="auth-wrapper d-flex align-items-center justify-content-center min-vh-100">
        <div class="auth-card card border-0 shadow-sm p-4" style="max-width: 400px; width: 100%;">
            <div class="text-center mb-4">
                <i class="fas fa-tools text-primary fs-1 mb-2"></i>
                <h3 class="mb-0">RepairHub</h3>
                <p class="text-muted small">Computer Repair Shop Management</p>
            </div>
            
            <?php if (isset($_SESSION['flash_message'])): ?>
                <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['flash_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
                ?>
            <?php endif; ?>

            <form action="login.php" method="POST" id="loginForm">
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <label for="password" class="form-label">Password</label>
                        <a href="forgot_password.php" class="text-decoration-none small">Forgot Password?</a>
                    </div>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Sign In</button>
            </form>
            
            <div class="text-center mb-2">
                <span class="text-muted small">Are you a customer? </span>
                <a href="<?= APP_URL ?>/client/register.php" class="text-decoration-none small fw-semibold">Customer Registration</a>
            </div>
            <div class="text-center mb-3">
                <span class="text-muted small">Staff? </span>
                <a href="register.php" class="text-decoration-none small">Staff Register</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
