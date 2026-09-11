<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    
    $ticketId = $_POST['ticket_id'];
    $assignedTo = $_POST['assigned_to'];
    
    if ($assignedTo) {
        $stmt = $pdo->prepare("UPDATE repair_tickets SET assigned_to = ?, status = 'In Progress' WHERE id = ?");
        $stmt->execute([$assignedTo, $ticketId]);
        $_SESSION['flash_message'] = "Worker assigned successfully.";
        $_SESSION['flash_type'] = "success";
    }
    header("Location: client_submissions.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch technicians for dropdown
$techStmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'Technician' AND is_active = 1");
$techStmt->execute();
$technicians = $techStmt->fetchAll();

// Pagination & Filters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$filter = $_GET['filter'] ?? 'all';
$whereClause = "WHERE rt.submitted_by_client = 1";
$params = [];

if ($filter === 'unassigned') {
    $whereClause .= " AND rt.assigned_to IS NULL";
} elseif ($filter === 'assigned') {
    $whereClause .= " AND rt.assigned_to IS NOT NULL";
}

$query = "
    SELECT rt.*, c.name as customer_name, t.name as technician_name
    FROM repair_tickets rt
    LEFT JOIN customers c ON rt.customer_id = c.id
    LEFT JOIN users t ON rt.assigned_to = t.id
    $whereClause
    ORDER BY rt.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$countQuery = "SELECT COUNT(*) FROM repair_tickets rt $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$pageTitle = 'Client Submissions';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Client Submissions</h2>
        <div class="btn-group">
            <a href="?filter=all" class="btn btn-outline-primary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
            <a href="?filter=unassigned" class="btn btn-outline-primary <?= $filter === 'unassigned' ? 'active' : '' ?>">Unassigned</a>
            <a href="?filter=assigned" class="btn btn-outline-primary <?= $filter === 'assigned' ? 'active' : '' ?>">Assigned</a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
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
                            <th>Submitted Date</th>
                            <th>Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                        <tr><td colspan="7" class="text-center py-4">No client submissions found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): ?>
                            <tr>
                                <td><a href="../tickets/view.php?id=<?= $t['id'] ?>" class="fw-bold"><?= htmlspecialchars($t['ticket_id']) ?></a></td>
                                <td><?= htmlspecialchars($t['customer_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($t['device_type'] . ' ' . $t['device_brand']) ?></td>
                                <td><span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($t['problem_description']) ?>"><?= htmlspecialchars($t['problem_description']) ?></span></td>
                                <td><?= getStatusBadge($t['status']) ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($t['created_at'])) ?></td>
                                <td>
                                    <?php if ($t['assigned_to']): ?>
                                        <?= htmlspecialchars($t['technician_name']) ?>
                                    <?php else: ?>
                                        <form method="POST" action="client_submissions.php" class="d-flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                            <select name="assigned_to" class="form-select form-select-sm" required>
                                                <option value="">Assign Worker...</option>
                                                <?php foreach ($technicians as $tech): ?>
                                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary">Assign</button>
                                        </form>
                                    <?php endif; ?>
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
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
