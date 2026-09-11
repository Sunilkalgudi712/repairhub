<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }
$pageTitle = 'Employees';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination and Filters
$statusFilter = $_GET['status'] ?? '';
$where = [];
$params = [];

if ($statusFilter === 'active') {
    $where[] = 'is_active = 1';
} elseif ($statusFilter === 'inactive') {
    $where[] = 'is_active = 0';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$limit = 15;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Total count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSql");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch employees
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM repair_tickets WHERE assigned_to = u.id) as tickets_assigned 
          FROM users u $whereSql 
          ORDER BY u.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Employees</h2>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Employee</a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Status Filter</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-secondary">Filter</button>
                        <a href="index.php" class="btn btn-link">Clear</a>
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
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Tickets</th>
                                <th>Last Login</th>
                                <th>Joined Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr><td colspan="8" class="text-center py-4">No employees found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: bold;">
                                                    <?= htmlspecialchars(getInitials($emp['name'])) ?>
                                                </div>
                                                <a href="edit.php?id=<?= $emp['id'] ?>" class="text-decoration-none text-dark fw-bold">
                                                    <?= htmlspecialchars($emp['name']) ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small"><i class="fas fa-envelope text-muted me-1"></i><?= htmlspecialchars($emp['email'] ?? '') ?></div>
                                            <div class="small"><i class="fas fa-phone text-muted me-1"></i><?= htmlspecialchars($emp['phone'] ?? '') ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $roleClass = 'bg-secondary';
                                            if ($emp['role'] === 'Admin') $roleClass = 'bg-danger';
                                            elseif ($emp['role'] === 'Technician') $roleClass = 'bg-primary';
                                            elseif ($emp['role'] === 'Receptionist') $roleClass = 'bg-info text-dark';
                                            ?>
                                            <span class="badge <?= $roleClass ?>"><?= htmlspecialchars($emp['role'] ?? 'User') ?></span>
                                        </td>
                                        <td>
                                            <?php if (isset($emp['is_active']) && $emp['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= number_format($emp['tickets_assigned'] ?? 0) ?></span>
                                        </td>
                                        <td>
                                            <?= empty($emp['last_login']) ? 'Never' : date('M d, Y H:i', strtotime($emp['last_login'])) ?>
                                        </td>
                                        <td>
                                            <?= empty($emp['created_at']) ? 'N/A' : date('M d, Y', strtotime($emp['created_at'])) ?>
                                        </td>
                                        <td>
                                            <a href="edit.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= htmlspecialchars($statusFilter) ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= htmlspecialchars($statusFilter) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= htmlspecialchars($statusFilter) ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
