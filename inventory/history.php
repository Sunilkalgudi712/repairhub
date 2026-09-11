<?php
/**
 * RepairHub — Inventory History & Audit Trail
 * Displays chronological audit records for stock adjustments, price changes, category changes, and creations.
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/auth/login.php");
    exit;
}

// Ensure table exists
static $historyTableReady = false;
if (!$historyTableReady) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `inventory_id` INT NOT NULL,
        `item_name` VARCHAR(200) NOT NULL,
        `user_id` INT DEFAULT NULL,
        `user_name` VARCHAR(100) DEFAULT NULL,
        `action_type` VARCHAR(50) NOT NULL,
        `field_name` VARCHAR(50) DEFAULT NULL,
        `old_value` TEXT DEFAULT NULL,
        `new_value` TEXT DEFAULT NULL,
        `change_description` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_inventory_id` (`inventory_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_action_type` (`action_type`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Auto-seed existing inventory items if history table is completely empty
    $countExisting = (int)$pdo->query("SELECT COUNT(*) FROM inventory_history")->fetchColumn();
    if ($countExisting === 0) {
        $existingItems = $pdo->query("SELECT * FROM inventory ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($existingItems as $it) {
            $desc = "Initial item record. Starting stock: {$it['quantity']} units. Category: " . ($it['category'] ?: 'Uncategorized') . ", Cost: " . CURRENCY_SYMBOL . number_format($it['cost_price'], 2) . ", Price: " . CURRENCY_SYMBOL . number_format($it['selling_price'], 2);
            $stmt = $pdo->prepare("INSERT INTO inventory_history (inventory_id, item_name, user_id, user_name, action_type, field_name, old_value, new_value, change_description, created_at)
                                   VALUES (?, ?, ?, ?, 'created', 'all', NULL, ?, ?, ?)");
            $stmt->execute([$it['id'], $it['name'], 1, 'Admin', $it['quantity'], $desc, $it['created_at'] ?: date('Y-m-d H:i:s')]);
        }

        // Also backfill any stock_adjustments if they exist
        try {
            $adjs = $pdo->query("SELECT sa.*, i.name as item_name, u.name as user_name 
                                 FROM stock_adjustments sa 
                                 LEFT JOIN inventory i ON sa.inventory_id = i.id 
                                 LEFT JOIN users u ON sa.user_id = u.id 
                                 ORDER BY sa.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($adjs as $a) {
                $desc = "Stock adjusted (" . $a['type'] . " " . $a['quantity'] . "). Reason: " . ($a['reason'] ?: 'Stock adjustment');
                $stmt = $pdo->prepare("INSERT INTO inventory_history (inventory_id, item_name, user_id, user_name, action_type, field_name, old_value, new_value, change_description, created_at)
                                       VALUES (?, ?, ?, ?, 'quantity_change', 'quantity', NULL, ?, ?, ?)");
                $stmt->execute([$a['inventory_id'], $a['item_name'] ?: 'Item #' . $a['inventory_id'], $a['user_id'], $a['user_name'] ?: 'System', $a['quantity'], $desc, $a['created_at']]);
            }
        } catch (Exception $e) {}
    }
    $historyTableReady = true;
}

// Request parameters
$itemId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$search = trim($_GET['search'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$export = trim($_GET['export'] ?? '');

// Target item if filtering by item
$singleItem = null;
if ($itemId) {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$itemId]);
    $singleItem = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Build query
$where = ["1=1"];
$params = [];

if ($itemId) {
    $where[] = "h.inventory_id = ?";
    $params[] = $itemId;
}
if ($search !== '') {
    $where[] = "(h.item_name LIKE ? OR h.change_description LIKE ? OR i.sku LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp;
    $params[] = $sp;
    $params[] = $sp;
}
if ($actionType !== '') {
    $where[] = "h.action_type = ?";
    $params[] = $actionType;
}
if ($userId) {
    $where[] = "h.user_id = ?";
    $params[] = $userId;
}
if ($dateFrom !== '') {
    $where[] = "DATE(h.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "DATE(h.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = implode(" AND ", $where);

// Handle CSV Export
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=inventory_history_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date & Time', 'Item ID', 'Item Name', 'SKU', 'Action Type', 'Field Changed', 'Old Value', 'New Value', 'Details', 'User']);

    $exportSql = "SELECT h.*, i.sku FROM inventory_history h 
                  LEFT JOIN inventory i ON h.inventory_id = i.id 
                  WHERE $whereSql ORDER BY h.created_at DESC";
    $stmt = $pdo->prepare($exportSql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['inventory_id'],
            $row['item_name'],
            $row['sku'] ?? '',
            $row['action_type'],
            $row['field_name'] ?? '',
            $row['old_value'] ?? '',
            $row['new_value'] ?? '',
            $row['change_description'],
            $row['user_name'] ?? 'System'
        ]);
    }
    fclose($output);
    exit;
}

// Pagination
$perPage = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inventory_history h LEFT JOIN inventory i ON h.inventory_id = i.id WHERE $whereSql");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch records
$dataSql = "SELECT h.*, i.sku, u.role as user_role 
            FROM inventory_history h 
            LEFT JOIN inventory i ON h.inventory_id = i.id 
            LEFT JOIN users u ON h.user_id = u.id 
            WHERE $whereSql 
            ORDER BY h.created_at DESC 
            LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$historyRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users for filter dropdown
$users = $pdo->query("SELECT id, name, role FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$metricsStmt = $pdo->query("SELECT 
    COUNT(*) as total_events,
    SUM(CASE WHEN action_type = 'created' THEN 1 ELSE 0 END) as total_created,
    SUM(CASE WHEN action_type = 'quantity_change' THEN 1 ELSE 0 END) as total_qty_changes,
    SUM(CASE WHEN action_type IN ('price_change', 'category_change') THEN 1 ELSE 0 END) as total_price_cat_changes
FROM inventory_history");
$metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = $singleItem ? 'History: ' . htmlspecialchars($singleItem['name']) : 'Inventory Audit & Change History';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">

    <!-- Header & Navigation -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/inventory/index.php">Inventory</a></li>
                    <?php if ($singleItem): ?>
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/inventory/edit.php?id=<?= $singleItem['id'] ?>"><?= htmlspecialchars($singleItem['name']) ?></a></li>
                        <li class="breadcrumb-item active">Audit History</li>
                    <?php else: ?>
                        <li class="breadcrumb-item active">Audit & Stock History</li>
                    <?php endif; ?>
                </ol>
            </nav>
            <h2 class="mb-0">
                <i class="fas fa-history text-primary me-2"></i>
                <?= $singleItem ? 'Audit Trail: ' . htmlspecialchars($singleItem['name']) : 'Inventory Audit & History Log' ?>
            </h2>
            <p class="text-muted small mb-0">Track who added items, modified quantities, updated categories, changed prices, and adjusted stock.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($singleItem): ?>
                <a href="<?= APP_URL ?>/inventory/adjust.php?id=<?= $singleItem['id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-boxes me-1"></i> Adjust Stock
                </a>
                <a href="<?= APP_URL ?>/inventory/edit.php?id=<?= $singleItem['id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> Edit Item
                </a>
                <a href="<?= APP_URL ?>/inventory/history.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-1"></i> All Items History
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/inventory/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Add Item
                </a>
            <?php endif; ?>
            
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="<?= APP_URL ?>/inventory/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Items
            </a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <?php if (!$singleItem): ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
                        <i class="fas fa-list-check fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Audit Events</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($metrics['total_events'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
                        <i class="fas fa-plus-circle fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Items Created</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($metrics['total_created'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info me-3">
                        <i class="fas fa-boxes fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Stock Adjustments</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($metrics['total_qty_changes'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
                        <i class="fas fa-tags fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Price / Category Updates</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($metrics['total_price_cat_changes'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="history.php" class="row g-3 align-items-center">
                <?php if ($itemId): ?>
                    <input type="hidden" name="id" value="<?= $itemId ?>">
                <?php endif; ?>

                <div class="col-md-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search item, SKU, details..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </div>

                <div class="col-md-2">
                    <select name="action_type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Action Types</option>
                        <option value="created" <?= $actionType === 'created' ? 'selected' : '' ?>>Item Added</option>
                        <option value="quantity_change" <?= $actionType === 'quantity_change' ? 'selected' : '' ?>>Stock Quantity Changed</option>
                        <option value="price_change" <?= $actionType === 'price_change' ? 'selected' : '' ?>>Price Changed</option>
                        <option value="category_change" <?= $actionType === 'category_change' ? 'selected' : '' ?>>Category Changed</option>
                        <option value="updated" <?= $actionType === 'updated' ? 'selected' : '' ?>>Details Updated</option>
                        <option value="deleted" <?= $actionType === 'deleted' ? 'selected' : '' ?>>Item Deleted</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="user_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <div class="input-group">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="From Date" onchange="this.form.submit()">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="To Date" onchange="this.form.submit()">
                    </div>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                    <?php if ($search !== '' || $actionType !== '' || $userId !== null || $dateFrom !== '' || $dateTo !== ''): ?>
                        <a href="history.php<?= $itemId ? '?id=' . $itemId : '' ?>" class="btn btn-outline-danger" title="Clear Filters">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- History Records Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-semibold">
                <i class="fas fa-list me-1 text-primary"></i> 
                Showing <?= count($historyRecords) ?> of <?= number_format($totalRecords) ?> audit events
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 170px;">Date & Time</th>
                            <th>Item Name</th>
                            <th>Action Type</th>
                            <th>Field Changed</th>
                            <th>Previous → New Value</th>
                            <th>Description / Reason</th>
                            <th>Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historyRecords) > 0): ?>
                            <?php foreach ($historyRecords as $row): ?>
                                <tr>
                                    <td class="text-nowrap small">
                                        <div class="fw-semibold text-dark"><?= date('d M Y, h:i A', strtotime($row['created_at'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars(timeAgo($row['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <a href="<?= APP_URL ?>/inventory/edit.php?id=<?= $row['inventory_id'] ?>" class="fw-semibold text-decoration-none text-primary">
                                            <?= htmlspecialchars($row['item_name']) ?>
                                        </a>
                                        <?php if (!empty($row['sku'])): ?>
                                            <div class="small text-muted font-monospace"><?= htmlspecialchars($row['sku']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['action_type'] === 'created'): ?>
                                            <span class="badge bg-success"><i class="fas fa-plus-circle me-1"></i> Item Added</span>
                                        <?php elseif ($row['action_type'] === 'quantity_change'): ?>
                                            <span class="badge bg-primary"><i class="fas fa-boxes me-1"></i> Stock Change</span>
                                        <?php elseif ($row['action_type'] === 'price_change'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-tags me-1"></i> Price Change</span>
                                        <?php elseif ($row['action_type'] === 'category_change'): ?>
                                            <span class="badge bg-info text-white"><i class="fas fa-folder me-1"></i> Category</span>
                                        <?php elseif ($row['action_type'] === 'deleted'): ?>
                                            <span class="badge bg-danger"><i class="fas fa-trash me-1"></i> Deleted</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="fas fa-edit me-1"></i> Updated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace text-uppercase" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars($row['field_name'] ?: 'Item') ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <?php if ($row['old_value'] !== null || $row['new_value'] !== null): ?>
                                            <span class="text-muted text-decoration-line-through"><?= htmlspecialchars($row['old_value'] ?? 'None') ?></span>
                                            <i class="fas fa-arrow-right text-primary mx-1" style="font-size: 0.7rem;"></i>
                                            <span class="fw-bold text-success"><?= htmlspecialchars($row['new_value'] ?? 'None') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; font-size: 0.8rem; font-weight: 600;">
                                                <?= strtoupper(substr($row['user_name'] ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold small text-dark"><?= htmlspecialchars($row['user_name'] ?? 'System') ?></div>
                                                <?php if (!empty($row['user_role'])): ?>
                                                    <span class="badge bg-secondary bg-opacity-25 text-secondary" style="font-size: 0.65rem;">
                                                        <?= htmlspecialchars($row['user_role']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-history fa-3x mb-3 text-muted opacity-50"></i>
                                    <h5>No Audit Records Found</h5>
                                    <p class="mb-0 small">Try adjusting your filters or date range.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top py-3">
            <nav>
                <ul class="pagination justify-content-end mb-0">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>">Previous</a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['page' => ''])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
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
