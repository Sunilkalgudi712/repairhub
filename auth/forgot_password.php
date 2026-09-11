<?php
session_start();
require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (!empty($email)) {
        $_SESSION['flash_message'] = "Password reset instructions have been sent to your email.";
        $_SESSION['flash_type'] = "info";
    } else {
        $_SESSION['flash_message'] = "Please enter your email address.";
        $_SESSION['flash_type'] = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RepairHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="auth-wrapper d-flex align-items-center justify-content-center min-vh-100 py-4">
        <div class="auth-card card border-0 shadow-sm p-4" style="max-width: 400px; width: 100%;">
            <div class="text-center mb-4">
                <i class="fas fa-tools text-primary fs-1 mb-2"></i>
                <h3 class="mb-0">RepairHub</h3>
                <p class="text-muted small">Reset Password</p>
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

            <form action="forgot_password.php" method="POST">
                <div class="mb-4">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                    <div class="form-text">Enter the email address associated with your account.</div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Link</button>
            </form>
            
            <div class="text-center">
                <a href="login.php" class="text-decoration-none small"><i class="fas fa-arrow-left me-1"></i> Back to Login</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
