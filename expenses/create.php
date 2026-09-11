<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$categories = ['Rent', 'Utilities', 'Salary', 'Office Supplies', 'Marketing', 'Maintenance', 'Tools & Equipment', 'Transportation', 'Other'];
$payment_methods = ['Cash', 'Credit Card', 'Bank Transfer', 'UPI', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, expense_date, description, payment_method, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['category'],
            $_POST['amount'],
            $_POST['expense_date'],
            $_POST['description'],
            $_POST['payment_method'],
            $_SESSION['user_id']
        ]);
        
        $_SESSION['flash_message'] = 'Expense added successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: /Reper_hub/expenses/index.php');
        exit;
    } catch (Exception $e) {
        $error = "Failed to add expense: " . $e->getMessage();
    }
}

$pageTitle = 'Add Expense';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="/Reper_hub/expenses/index.php" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="max-width: 600px;">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="mb-3">
                    <label class="form-label">Category *</label>
                    <select name="category" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>"><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Amount (₹) *</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required min="0.01">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Date *</label>
                    <input type="date" name="expense_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" class="form-select" required>
                        <?php foreach($payment_methods as $pm): ?>
                            <option value="<?= $pm ?>"><?= $pm ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100">Add Expense</button>
            </form>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
