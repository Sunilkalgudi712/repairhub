<?php
session_start();
require_once '../config.php';
requireWorker();

$pageTitle = 'My Tasks';
include '../includes/worker_header.php';
include '../includes/worker_sidebar.php';

$userId = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'Active';
$priorityFilter = isset($_GET['priority']) ? $_GET['priority'] : '';

$isAdminUser = function_exists('isAdmin') && isAdmin();

if ($isAdminUser) {
    $where = ["1=1"];
    $params = [];
} else {
    $where = ["t.assigned_to = ?"];
    $params = [$userId];
}

if ($statusFilter === 'Active') {
    $where[] = "t.status IN ('Pending', 'In Progress', 'Waiting for Parts', 'Ready for Pickup')";
} elseif ($statusFilter === 'Completed') {
    $where[] = "t.status IN ('Completed', 'Delivered')";
} elseif ($statusFilter !== 'All') {
    $where[] = "t.status = ?";
    $params[] = $statusFilter;
}

if ($priorityFilter) {
    $where[] = "t.priority = ?";
    $params[] = $priorityFilter;
}

$whereClause = implode(' AND ', $where);

// Count query
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets t WHERE $whereClause");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Main query
$query = "
    SELECT t.*, c.name as customer_name 
    FROM repair_tickets t
    LEFT JOIN customers c ON t.customer_id = c.id
    WHERE $whereClause
    ORDER BY t.priority DESC, t.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Tasks</h2>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Status Filter</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active Tasks</option>
                        <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Tasks</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="In Progress" <?= $statusFilter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Waiting for Parts" <?= $statusFilter === 'Waiting for Parts' ? 'selected' : '' ?>>Waiting for Parts</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Priority Filter</label>
                    <select name="priority" class="form-select" onchange="this.form.submit()">
                        <option value="">All Priorities</option>
                        <option value="High" <?= $priorityFilter === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Normal" <?= $priorityFilter === 'Normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="Low" <?= $priorityFilter === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
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
                            <th>Ticket ID</th>
                            <th>Customer Name</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Created Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tasks) > 0): ?>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td><a href="task_detail.php?id=<?= $task['id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($task['ticket_id']) ?></a></td>
                                    <td><?= htmlspecialchars($task['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($task['device_type'] . ' ' . $task['device_brand'] . ' ' . $task['device_model']) ?></td>
                                    <td><?= getStatusBadge($task['status'] ?? 'Pending') ?></td>
                                    <td><?= getPriorityBadge($task['priority'] ?? 'Normal') ?></td>
                                    <td><?= $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : '-' ?></td>
                                    <td><?= date('M d, Y', strtotime($task['created_at'])) ?></td>
                                    <td><a href="task_detail.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-primary">Details</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4">No tasks found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>&priority=<?= urlencode($priorityFilter) ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&priority=<?= urlencode($priorityFilter) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>&priority=<?= urlencode($priorityFilter) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

  </div>
</div>
<?php include '../includes/footer.php'; ?>
