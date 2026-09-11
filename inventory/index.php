<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

$pageTitle = 'Inventory';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name LIKE :search OR sku LIKE :search)";
    $params['search'] = "%$search%";
}
if ($category !== '') {
    $where[] = "category = :category";
    $params['category'] = $category;
}

if ($status === 'in_stock') {
    $where[] = "quantity > min_stock_level";
} elseif ($status === 'low_stock') {
    $where[] = "quantity <= min_stock_level AND quantity > 0";
} elseif ($status === 'out_of_stock') {
    $where[] = "quantity = 0";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Summary queries
    $summary = $pdo->query("SELECT 
        COUNT(id) as total_items, 
        SUM(quantity * cost_price) as total_value,
        SUM(CASE WHEN quantity <= min_stock_level AND quantity > 0 THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_count
        FROM inventory")->fetch(PDO::FETCH_ASSOC);

    // Categories for filter
    $categories = $pdo->query("SELECT DISTINCT category FROM inventory WHERE category != '' AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

    // Main query
    $query = "SELECT * FROM inventory $whereClause ORDER BY name ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue(":$key", $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pagination count
    $countQuery = "SELECT COUNT(*) FROM inventory $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $val) {
        $countStmt->bindValue(":$key", $val);
    }
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $perPage);
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
    $_SESSION['flash_type'] = "danger";
    $items = [];
    $totalItems = 0;
    $totalPages = 1;
    $summary = ['total_items' => 0, 'total_value' => 0, 'low_stock_count' => 0, 'out_of_stock_count' => 0];
    $categories = [];
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-boxes me-2"></i> Inventory</h2>
        <a href="create.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add Item</a>
    </div>

    <?php if(isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-0">Total Items</h6>
                    <h3 class="display-6 mb-0"><?= number_format($summary['total_items'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-0">Total Value</h6>
                    <h3 class="display-6 mb-0">₹<?= number_format($summary['total_value'] ?? 0, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-0">Low Stock</h6>
                    <h3 class="display-6 mb-0"><?= number_format($summary['low_stock_count'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body">
                    <h6 class="card-title text-uppercase mb-0">Out of Stock</h6>
                    <h3 class="display-6 mb-0"><?= number_format($summary['out_of_stock_count'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or SKU" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Stock Status</option>
                        <option value="in_stock" <?= $status === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                        <option value="low_stock" <?= $status === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out_of_stock" <?= $status === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Qty</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Supplier</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): 
                                $isOut = $item['quantity'] == 0;
                                $isLow = $item['quantity'] <= $item['min_stock_level'] && !$isOut;
                                $badgeClass = $isOut ? 'bg-danger' : ($isLow ? 'bg-warning text-dark' : 'bg-success');
                                $rowClass = ($isOut || $isLow) ? 'table-warning' : '';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <a href="edit.php?id=<?= $item['id'] ?>" class="text-decoration-none fw-bold">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($item['sku'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($item['category'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge <?= $badgeClass ?> fs-6">
                                        <?= number_format($item['quantity']) ?>
                                    </span>
                                </td>
                                <td>₹<?= number_format($item['cost_price'] ?? 0, 2) ?></td>
                                <td>₹<?= number_format($item['selling_price'] ?? 0, 2) ?></td>
                                <td><?= htmlspecialchars($item['supplier'] ?? 'N/A') ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="adjust.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-boxes"></i> Adjust Stock</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center py-4">No items found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-0 py-3">
            <nav>
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>">Previous</a>
                    </li>
                    <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>&status=<?= urlencode($status) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
