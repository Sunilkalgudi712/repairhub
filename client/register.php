<?php
session_start();
require_once '../config.php';

// If already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Client') {
        header("Location: " . APP_URL . "/client/dashboard.php");
    } else {
        header("Location: " . APP_URL . "/dashboard.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email is already registered.';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert into users
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, ?, 'Client', 1, NOW())");
                $stmt->execute([$name, $email, $hashed_password]);
                $user_id = $pdo->lastInsertId();

                // Insert into customers
                $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $phone, $user_id]);

                $pdo->commit();

                // Auto-login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['role'] = 'Client';
                $_SESSION['flash_message'] = 'Registration successful. Welcome!';
                $_SESSION['flash_type'] = 'success';

                header("Location: " . APP_URL . "/client/dashboard.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'An error occurred during registration. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration - RepairHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <style>
        .auth-wrapper { min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; }
        .auth-card { max-width: 450px; width: 100%; padding: 2rem; border: none; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background-color: #fff; }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary"><i class="fas fa-tools"></i> RepairHub</h2>
                <p class="text-muted">Create a Customer Account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Register</button>
            </form>
            
            <div class="text-center">
                <p class="mb-0">Already have an account? <a href="<?= APP_URL ?>/auth/login.php" class="text-decoration-none">Sign In</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
