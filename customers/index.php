<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

$pageTitle = 'Customers';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination and Search
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where = "1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (name LIKE :search OR phone LIKE :search OR email LIKE :search)";
    $params['search'] = "%$search%";
}

// Count total
$countSql = "SELECT COUNT(*) FROM customers WHERE $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Fetch customers
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM repair_tickets rt WHERE rt.customer_id = c.id) as total_tickets,
        (SELECT COALESCE(SUM(total_amount), 0) FROM invoices i WHERE i.customer_id = c.id AND i.status != 'Cancelled') as total_spent
        FROM customers c
        WHERE $where 
        ORDER BY c.created_at DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for session alerts
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <?php if ($flashMessage): ?>
    <div class="alert alert-<?= htmlspecialchars($flashType) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flashMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customers</h2>
        <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Customer</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center mb-4">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, phone, email..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <?php if ($search !== ''): ?>
                <div class="col-auto">
                    <a href="index.php" class="btn btn-outline-danger">Clear</a>
                </div>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>City</th>
                            <th>Total Tickets</th>
                            <th>Total Spent</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($customers) > 0): ?>
                            <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><a href="view.php?id=<?= $c['id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($c['name']) ?></a></td>
                                <td><?= htmlspecialchars($c['phone']) ?></td>
                                <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
                                <td><?= htmlspecialchars($c['city'] ?? '') ?></td>
                                <td><span class="badge bg-secondary"><?= $c['total_tickets'] ?></span></td>
                                <td><?= '₹' . number_format($c['total_spent'], 2) ?></td>
                                <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                <td>
                                    <a href="view.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
                                    <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center text-muted">No customers found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-3">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?><?= $search ? '&search='.urlencode($search) : '' ?>">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?><?= $search ? '&search='.urlencode($search) : '' ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
