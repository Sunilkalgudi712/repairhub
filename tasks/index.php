<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    $taskId = (int)$_POST['task_id'];
    $newStatus = $_POST['status'];
    $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?")->execute([$newStatus, $taskId]);
    
    $_SESSION['flash_message'] = 'Task status updated.';
    $_SESSION['flash_type'] = 'success';
    header("Location: " . APP_URL . "/tasks/index.php");
    exit;
}

$pageTitle = 'Tasks';
include '../includes/header.php';
include '../includes/sidebar.php';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];

if (!empty($_GET['status'])) {
    $whereClauses[] = "t.status = :status";
    $params[':status'] = $_GET['status'];
}
if (!empty($_GET['priority'])) {
    $whereClauses[] = "t.priority = :priority";
    $params[':priority'] = $_GET['priority'];
}
if (!empty($_GET['assigned_to'])) {
    $whereClauses[] = "t.assigned_to = :assigned_to";
    $params[':assigned_to'] = $_GET['assigned_to'];
}

$whereSql = implode(' AND ', $whereClauses);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $whereSql");
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$totalPages = ceil($countStmt->fetchColumn() / $limit);

$stmt = $pdo->prepare("
    SELECT t.*, u.name as assigned_to_name, tk.ticket_id 
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    LEFT JOIN repair_tickets tk ON t.ticket_id = tk.id
    WHERE $whereSql 
    ORDER BY t.due_date ASC, t.priority DESC 
    LIMIT $limit OFFSET $offset
");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$tasks = $stmt->fetchAll();

$users = $pdo->query("SELECT id, name FROM users")->fetchAll();


?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= APP_URL ?>/tasks/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Task</a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach(['Pending', 'In Progress', 'Completed', 'Cancelled'] as $st): ?>
                            <option value="<?= $st ?>" <?= (($_GET['status']??'') == $st) ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="">All</option>
                        <option value="High" <?= (($_GET['priority']??'') == 'High') ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= (($_GET['priority']??'') == 'Medium') ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= (($_GET['priority']??'') == 'Low') ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">Anyone</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (($_GET['assigned_to']??'') == $u['id']) ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Ticket</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Status Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr><td colspan="6" class="text-center py-3">No tasks found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $t): 
                                $isOverdue = (!empty($t['due_date']) && strtotime($t['due_date']) < time() && !in_array($t['status'], ['Completed', 'Cancelled']));
                            ?>
                                <tr class="<?= $isOverdue ? 'table-danger' : '' ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($t['title']) ?></strong>
                                        <?php if($t['description']): ?>
                                            <div class="text-muted small text-truncate" style="max-width: 200px;"><?= htmlspecialchars($t['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($t['ticket_id']): ?>
                                            <a href="<?= APP_URL ?>/tickets/view.php?id=<?= $t['ticket_id'] ?>"><?= htmlspecialchars($t['ticket_id']) ?></a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($t['assigned_to_name'] ?? 'Unassigned') ?></td>
                                     <td><?= getPriorityBadge($t['priority'] ?? 'Medium') ?></td>
                                    <td>
                                        <?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : 'No Due Date' ?>
                                        <?php if($isOverdue): ?> <span class="badge bg-danger">Overdue</span> <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                            <select name="status" class="form-select form-select-sm me-2" onchange="this.form.submit()">
                                                <?php foreach(['Pending', 'In Progress', 'Completed', 'Cancelled'] as $st): ?>
                                                    <option value="<?= $st ?>" <?= $t['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page'=>1])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
