<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$sources = ['Walk-in', 'Phone Call', 'Website', 'Referral', 'Social Media', 'Other'];
$statuses = ['New', 'Contacted', 'Interested', 'Converted', 'Lost'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO leads (name, email, phone, source, device_type, problem_description, estimated_budget, assigned_to, follow_up_date, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['source'],
            $_POST['device_type'],
            $_POST['problem_description'],
            empty($_POST['estimated_budget']) ? null : $_POST['estimated_budget'],
            empty($_POST['assigned_to']) ? null : $_POST['assigned_to'],
            empty($_POST['follow_up_date']) ? null : $_POST['follow_up_date'],
            $_POST['notes'],
            $_POST['status'],
            $_SESSION['user_id']
        ]);
        
        $_SESSION['flash_message'] = 'Lead added successfully.';
        $_SESSION['flash_type'] = 'success';
        header("Location: " . APP_URL . "/leads/index.php");
        exit;
    } catch (Exception $e) {
        $error = "Failed to add lead: " . $e->getMessage();
    }
}

$users = $pdo->query("SELECT id, name FROM users WHERE is_active = 1")->fetchAll();

$pageTitle = 'Add Lead';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= APP_URL ?>/leads/index.php" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Source *</label>
                        <select name="source" class="form-select" required>
                            <?php foreach($sources as $src): ?>
                                <option value="<?= $src ?>"><?= $src ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Device Type</label>
                        <input type="text" name="device_type" class="form-control" placeholder="e.g. iPhone 13">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Estimated Budget (₹)</label>
                        <input type="number" step="0.01" name="estimated_budget" class="form-control">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Problem Description</label>
                        <textarea name="problem_description" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status *</label>
                        <select name="status" class="form-select" required>
                            <?php foreach($statuses as $st): ?>
                                <option value="<?= $st ?>"><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Assigned To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" name="follow_up_date" class="form-control">
                    </div>

                    <div class="col-md-12 mb-4">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Lead</button>
            </form>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
