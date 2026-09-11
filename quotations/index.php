<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

$pageTitle = 'Quotations';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

$whereConditions = [];
$params = [];

if ($search !== '') {
    $whereConditions[] = "(q.quotation_number LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statusFilter !== '') {
    $whereConditions[] = "q.status = ?";
    $params[] = $statusFilter;
}

$whereSQL = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Summary queries
$totalQuotations = $pdo->query("SELECT COUNT(*) FROM quotations")->fetchColumn();
$pendingApproval = $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'Sent'")->fetchColumn();
$approvedCount = $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'Approved'")->fetchColumn();
$totalValue = $pdo->query("SELECT SUM(total_amount) FROM quotations WHERE status = 'Approved'")->fetchColumn() ?: 0;

// Data query
$query = "SELECT q.*, c.name as customer_name, rt.ticket_id 
          FROM quotations q 
          LEFT JOIN customers c ON q.customer_id = c.id 
          LEFT JOIN repair_tickets rt ON q.ticket_id = rt.id 
          $whereSQL 
          ORDER BY q.created_at DESC 
          LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$quotations = $stmt->fetchAll();

// Total for pagination
$countQuery = "SELECT COUNT(*) FROM quotations q LEFT JOIN customers c ON q.customer_id = c.id $whereSQL";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Quotations</h2>
        <a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Quotation</a>
    </div>
    
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type']) ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-file-invoice"></i> Total Quotations</h6>
                    <h3 class="mb-0"><?= $totalQuotations ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-clock"></i> Pending Approval</h6>
                    <h3 class="mb-0"><?= $pendingApproval ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-check-circle"></i> Approved</h6>
                    <h3 class="mb-0"><?= $approvedCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-rupee-sign"></i> Total Value (Approved)</h6>
                    <h3 class="mb-0"><?= formatCurrency($totalValue) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="index.php" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search by Quotation # or Customer" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php
                        $statuses = ['Draft', 'Sent', 'Approved', 'Rejected', 'Expired', 'Converted'];
                        foreach ($statuses as $status) {
                            $selected = $status === $statusFilter ? 'selected' : '';
                            echo "<option value=\"$status\" $selected>$status</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                            <th>Quotation #</th>
                            <th>Customer</th>
                            <th>Ticket #</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Valid Until</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($quotations)): ?>
                        <tr><td colspan="7" class="text-center py-4">No quotations found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($quotations as $q): ?>
                            <tr>
                                <td><a href="view.php?id=<?= $q['id'] ?>" class="fw-bold"><?= htmlspecialchars($q['quotation_number']) ?></a></td>
                                <td><?= htmlspecialchars($q['customer_name'] ?? 'Walk-in Customer') ?></td>
                                <td><?= !empty($q['ticket_id']) ? htmlspecialchars($q['ticket_id']) : '-' ?></td>
                                <td><?= formatCurrency($q['total_amount']) ?></td>
                                <td><?= getQuotationStatusBadge($q['status']) ?></td>
                                <td><?= htmlspecialchars($q['valid_until']) ?></td>
                                <td><?= date('M d, Y', strtotime($q['created_at'])) ?></td>
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
                <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
