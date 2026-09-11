<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

$pageTitle = 'Invoices';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where = ["1=1"];
$params = [];

if ($search) {
    $where[] = "(i.invoice_number LIKE :search OR c.name LIKE :search OR i.id LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($status) {
    $where[] = "i.status = :status";
    $params[':status'] = $status;
}
if ($dateFrom) {
    $where[] = "i.created_at >= :dateFrom";
    $params[':dateFrom'] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[] = "i.created_at <= :dateTo";
    $params[':dateTo'] = $dateTo . ' 23:59:59';
}

$whereClause = implode(" AND ", $where);

// Summary metrics
$summary = $pdo->query("
    SELECT 
        COUNT(id) as total_invoices,
        SUM(paid_amount) as paid_amount,
        SUM(CASE WHEN status NOT IN ('Paid', 'Cancelled') THEN due_amount ELSE 0 END) as unpaid_amount,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('Paid', 'Cancelled') THEN 1 ELSE 0 END) as overdue_count
    FROM invoices
")->fetch(PDO::FETCH_ASSOC);

// Count total rows for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN customers c ON i.customer_id = c.id WHERE $whereClause");
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch invoices
$query = "
    SELECT i.*, c.name as customer_name 
    FROM invoices i 
    LEFT JOIN customers c ON i.customer_id = c.id 
    WHERE $whereClause 
    ORDER BY i.created_at DESC 
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Invoices</h1>
            <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Invoice</a>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Invoices</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['total_invoices'] ?? 0) ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-file-invoice fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Paid Amount</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?= number_format($summary['paid_amount'] ?? 0, 2) ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-rupee-sign fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Unpaid Amount</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">₹<?= number_format($summary['unpaid_amount'] ?? 0, 2) ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-hourglass-half fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-0 shadow-sm h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Overdue Count</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['overdue_count'] ?? 0) ?></div>
                            </div>
                            <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search invoices..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (['Draft', 'Sent', 'Paid', 'Overdue', 'Cancelled'] as $st): ?>
                            <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invoices List -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Ticket</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                            <tr><td colspan="8" class="text-center py-4">No invoices found.</td></tr>
                            <?php else: foreach ($invoices as $inv): ?>
                            <tr>
                                <td><a href="view.php?id=<?= $inv['id'] ?>" class="fw-bold"><?= htmlspecialchars($inv['invoice_number'] ?? 'INV-'.$inv['id']) ?></a></td>
                                <td><?= htmlspecialchars($inv['customer_name'] ?? 'Unknown') ?></td>
                                <td>
                                    <?php if (!empty($inv['ticket_id'])): ?>
                                    <a href="<?= APP_URL ?>/tickets/view.php?id=<?= $inv['ticket_id'] ?>">#<?= htmlspecialchars($inv['ticket_id']) ?></a>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>₹<?= number_format($inv['total_amount'], 2) ?></td>
                                <td class="text-success">₹<?= number_format($inv['paid_amount'] ?? 0, 2) ?></td>
                                <td class="text-danger">₹<?= number_format($inv['due_amount'], 2) ?></td>
                                <td><?= getInvoiceStatusBadge($inv['status']) ?></td>
                                <td><?= date('M j, Y', strtotime($inv['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top-0">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
