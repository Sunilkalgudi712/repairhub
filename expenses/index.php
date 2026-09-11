<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

$pageTitle = 'Expenses';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination and Filters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];

if (!empty($_GET['category'])) {
    $whereClauses[] = "category = :category";
    $params[':category'] = $_GET['category'];
}
if (!empty($_GET['start_date'])) {
    $whereClauses[] = "expense_date >= :start_date";
    $params[':start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $whereClauses[] = "expense_date <= :end_date";
    $params[':end_date'] = $_GET['end_date'];
}
if (!empty($_GET['search'])) {
    $whereClauses[] = "(description LIKE :search OR category LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$whereSql = implode(' AND ', $whereClauses);

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE $whereSql");
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$totalPages = ceil($countStmt->fetchColumn() / $limit);

// Fetch
$stmt = $pdo->prepare("SELECT e.*, u.name as created_by_name FROM expenses e LEFT JOIN users u ON e.created_by = u.id WHERE $whereSql ORDER BY expense_date DESC, e.id DESC LIMIT $limit OFFSET $offset");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$expenses = $stmt->fetchAll();

// Summaries
$monthTotal = $pdo->query("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE())")->fetchColumn() ?: 0;
$weekTotal = $pdo->query("SELECT SUM(amount) FROM expenses WHERE YEARWEEK(expense_date, 1) = YEARWEEK(CURDATE(), 1)")->fetchColumn() ?: 0;
// Avg daily for current month: total month / current day of month
$currentDay = (int)date('j');
$avgDaily = $monthTotal / $currentDay;

// Handle delete via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!empty($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $delId = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM expenses WHERE id = ?")->execute([$delId]);
        $_SESSION['flash_message'] = 'Expense deleted.';
        $_SESSION['flash_type'] = 'success';
        header("Location: index.php");
        exit;
    }
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$categories = ['Rent', 'Utilities', 'Salary', 'Office Supplies', 'Marketing', 'Maintenance', 'Tools & Equipment', 'Transportation', 'Other'];
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="/Reper_hub/expenses/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Expense</a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Total This Month</h6>
                    <h3 class="mb-0">₹<?= number_format($monthTotal, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Total This Week</h6>
                    <h3 class="mb-0">₹<?= number_format($weekTotal, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body">
                    <h6 class="text-muted">Average Daily (This Month)</h6>
                    <h3 class="mb-0">₹<?= number_format($avgDaily, 2) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Description...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= (($_GET['category']??'') == $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
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
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Created By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($expenses)): ?>
                            <tr><td colspan="7" class="text-center py-3">No expenses found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($expenses as $e): ?>
                                <tr>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($e['expense_date']))) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($e['category']) ?></span></td>
                                    <td><?= htmlspecialchars($e['description']) ?></td>
                                    <td class="fw-bold">₹<?= number_format($e['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($e['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($e['created_by_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete expense?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
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

    <!-- pagination -->
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
