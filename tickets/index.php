<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/auth/login.php");
    exit;
}

$pageTitle = 'Repair Tickets';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$assigned_to = $_GET['assigned_to'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_clauses = ["1=1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(t.ticket_id LIKE ? 
        OR c.name LIKE ? 
        OR c.phone LIKE ? 
        OR c.email LIKE ? 
        OR t.device_brand LIKE ? 
        OR t.device_model LIKE ? 
        OR t.device_type LIKE ? 
        OR t.serial_number LIKE ? 
        OR t.problem_description LIKE ?)";
    $searchParam = "%$search%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
}
if ($status !== '') {
    $where_clauses[] = "t.status = ?";
    $params[] = $status;
}
if ($priority !== '') {
    $where_clauses[] = "t.priority = ?";
    $params[] = $priority;
}
if ($assigned_to !== '') {
    $where_clauses[] = "t.assigned_to = ?";
    $params[] = $assigned_to;
}
if ($date_from !== '') {
    $where_clauses[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $where_clauses[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = implode(' AND ', $where_clauses);

// Count total
$count_sql = "SELECT COUNT(*) FROM repair_tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_tickets = $stmt->fetchColumn();
$total_pages = ceil($total_tickets / $limit);

// Fetch data
$sql = "SELECT t.*, c.name, c.phone, u.name as assignee_name 
        FROM repair_tickets t 
        LEFT JOIN customers c ON t.customer_id = c.id 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE $where_sql 
        ORDER BY t.created_at DESC 
        LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for filter
$stmt = $pdo->query("SELECT id, name FROM users WHERE is_active = 1");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_statuses = ['Pending', 'In Progress', 'Waiting for Parts', 'Ready for Pickup', 'Completed', 'Delivered', 'Cancelled'];
$all_priorities = ['Low', 'Normal', 'High', 'Urgent'];


$start_count = ($total_tickets > 0) ? $offset + 1 : 0;
$end_count = min($offset + $limit, $total_tickets);
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Repair Tickets</h2>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Ticket</a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3 align-items-center" id="ticketFilterForm">
                    <div class="col-md-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" id="ticketSearchInput" 
                                   placeholder="Search ID, Customer, Device..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                            <button type="submit" class="btn btn-primary" title="Search Database">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($all_statuses as $s): ?>
                                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="priority" class="form-select" onchange="this.form.submit()">
                            <option value="">All Priorities</option>
                            <?php foreach ($all_priorities as $p): ?>
                                <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="assigned_to" class="form-select" onchange="this.form.submit()">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= $assigned_to == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group">
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" title="From Date" onchange="this.form.submit()">
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" title="To Date" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-outline-secondary flex-fill" title="Apply Filters"><i class="fas fa-filter"></i></button>
                        <?php if ($search !== '' || $status !== '' || $priority !== '' || $assigned_to !== '' || $date_from !== '' || $date_to !== ''): ?>
                            <a href="index.php" class="btn btn-outline-danger" title="Clear All Filters"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <h6 class="mb-0 text-muted">Showing <?= $start_count ?>-<?= $end_count ?> of <?= $total_tickets ?> tickets</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket ID</th>
                                <th>Customer</th>
                                <th>Device</th>
                                <th>Problem</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned To</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tickets) > 0): ?>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td><a href="view.php?id=<?= $ticket['id'] ?>" class="fw-bold"><?= htmlspecialchars($ticket['ticket_id']) ?></a></td>
                                        <td>
                                            <?= htmlspecialchars($ticket['name']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($ticket['phone']) ?></small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($ticket['device_type'] . ' - ' . $ticket['device_brand']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($ticket['device_model']) ?></small>
                                        </td>
                                        <td>
                                            <span class="d-inline-block text-truncate" style="max-width: 150px;">
                                                <?= htmlspecialchars($ticket['problem_description']) ?>
                                            </span>
                                        </td>
                                        <td><?= getStatusBadge($ticket['status']) ?></td>
                                        <td><?= getPriorityBadge($ticket['priority']) ?></td>
                                        <td>
                                            <?php if ($ticket['assignee_name']): ?>
                                                <?= htmlspecialchars($ticket['assignee_name']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($ticket['created_at'])) ?></td>
                                        <td>
                                            <a href="view.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">No tickets found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-top">
                <nav>
                    <ul class="pagination justify-content-end mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">Previous</a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
