<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF validation failed');
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, ticket_id, assigned_to, priority, status, due_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            empty($_POST['ticket_id']) ? null : $_POST['ticket_id'],
            empty($_POST['assigned_to']) ? null : $_POST['assigned_to'],
            $_POST['priority'],
            $_POST['status'],
            empty($_POST['due_date']) ? null : $_POST['due_date'],
            $_SESSION['user_id']
        ]);
        
        $_SESSION['flash_message'] = 'Task created successfully.';
        $_SESSION['flash_type'] = 'success';
        header("Location: " . APP_URL . "/tasks/index.php");
        exit;
    } catch (Exception $e) {
        $error = "Failed to create task: " . $e->getMessage();
    }
}

$users = $pdo->query("SELECT id, name FROM users WHERE is_active = 1")->fetchAll();
$tickets = $pdo->query("SELECT t.id, t.ticket_id, c.name as customer_name FROM repair_tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE t.status NOT IN ('Completed', 'Delivered', 'Cancelled')")->fetchAll();

$pageTitle = 'Add Task';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= APP_URL ?>/tasks/index.php" class="btn btn-outline-secondary">Back to List</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm" style="max-width: 700px;">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Related Ticket (Optional)</label>
                        <select name="ticket_id" class="form-select">
                            <option value="">-- None --</option>
                            <?php foreach($tickets as $tk): ?>
                                <option value="<?= $tk['id'] ?>"><?= htmlspecialchars($tk['ticket_id'] . ' - ' . $tk['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Assign To</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $u['id'] == $_SESSION['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Create Task</button>
            </form>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
