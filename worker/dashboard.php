<?php
session_start();
require_once '../config.php';
requireWorker();

$pageTitle = 'Worker Dashboard';
include '../includes/worker_header.php';
include '../includes/worker_sidebar.php';

$userId = $_SESSION['user_id'];

// Get worker name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$worker = $stmt->fetch();
$workerName = $worker ? $worker['name'] : 'Worker';

// Stats
$today = date('Y-m-d');
$isAdminUser = function_exists('isAdmin') && isAdmin();

if ($isAdminUser) {
    $todayAssignedStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE status IN ('Pending', 'In Progress', 'Waiting for Parts', 'Ready for Pickup') AND DATE(created_at) = ?");
    $todayAssignedStmt->execute([$today]);
    $todayAssigned = $todayAssignedStmt->fetchColumn();

    $inProgressStmt = $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE status = 'In Progress'");
    $inProgress = $inProgressStmt->fetchColumn();

    $completedTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE status = 'Completed' AND DATE(completed_at) = ?");
    $completedTodayStmt->execute([$today]);
    $completedToday = $completedTodayStmt->fetchColumn();

    $totalCompletedStmt = $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE status = 'Completed'");
    $totalCompleted = $totalCompletedStmt->fetchColumn();

    $tasksStmt = $pdo->prepare("
        SELECT t.*, c.name as customer_name 
        FROM repair_tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.status NOT IN ('Completed', 'Delivered', 'Cancelled')
        ORDER BY t.priority DESC, t.due_date ASC
    ");
    $tasksStmt->execute();
    $tasks = $tasksStmt->fetchAll();
} else {
    $todayAssignedStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE assigned_to = ? AND status IN ('Pending', 'In Progress', 'Waiting for Parts', 'Ready for Pickup') AND DATE(created_at) = ?");
    $todayAssignedStmt->execute([$userId, $today]);
    $todayAssigned = $todayAssignedStmt->fetchColumn();

    $inProgressStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE assigned_to = ? AND status = 'In Progress'");
    $inProgressStmt->execute([$userId]);
    $inProgress = $inProgressStmt->fetchColumn();

    $completedTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE assigned_to = ? AND status = 'Completed' AND DATE(completed_at) = ?");
    $completedTodayStmt->execute([$userId, $today]);
    $completedToday = $completedTodayStmt->fetchColumn();

    $totalCompletedStmt = $pdo->prepare("SELECT COUNT(*) FROM repair_tickets WHERE assigned_to = ? AND status = 'Completed'");
    $totalCompletedStmt->execute([$userId]);
    $totalCompleted = $totalCompletedStmt->fetchColumn();

    $tasksStmt = $pdo->prepare("
        SELECT t.*, c.name as customer_name 
        FROM repair_tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.assigned_to = ? AND t.status NOT IN ('Completed', 'Delivered', 'Cancelled')
        ORDER BY t.priority DESC, t.due_date ASC
    ");
    $tasksStmt->execute([$userId]);
    $tasks = $tasksStmt->fetchAll();
}
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome back, <?= htmlspecialchars($isAdminUser ? 'Alex Technician' : $workerName) ?>! <span class="badge bg-warning text-dark fs-6 align-middle">Technician</span></h2>
    </div>

    <div class="row mb-4">
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Today's Assigned</h6>
                    <h2 class="display-5"><?= $todayAssigned ?></h2>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">In Progress</h6>
                    <h2 class="display-5"><?= $inProgress ?></h2>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Completed Today</h6>
                    <h2 class="display-5"><?= $completedToday ?></h2>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg-3 mb-3">
            <div class="card border-0 shadow-sm bg-secondary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title">Total Completed</h6>
                    <h2 class="display-5"><?= $totalCompleted ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Today's Tasks</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Ticket</th>
                                    <th>Customer</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($tasks) > 0): ?>
                                    <?php foreach ($tasks as $task): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($task['ticket_id']) ?></td>
                                            <td><?= htmlspecialchars($task['customer_name']) ?></td>
                                            <td><?= htmlspecialchars($task['device_type'] . ' ' . $task['device_brand']) ?></td>
                                            <td><?= getStatusBadge($task['status'] ?? 'Pending') ?></td>
                                            <td><?= getPriorityBadge($task['priority'] ?? 'Normal') ?></td>
                                            <td><a href="task_detail.php?id=<?= $task['id'] ?>" class="btn btn-sm btn-primary">View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-3">No active tasks assigned to you.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">My Schedule This Week</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Manage your availability and working hours.</p>
                    <a href="availability.php" class="btn btn-outline-primary btn-sm w-100 mb-2">Manage Availability</a>
                </div>
            </div>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
