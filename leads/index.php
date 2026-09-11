<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

$pageTitle = 'Leads';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination and Filters
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$whereClauses = ["1=1"];
$params = [];

if (!empty($_GET['status'])) {
    $whereClauses[] = "status = :status";
    $params[':status'] = $_GET['status'];
}
if (!empty($_GET['source'])) {
    $whereClauses[] = "source = :source";
    $params[':source'] = $_GET['source'];
}

$whereSql = implode(' AND ', $whereClauses);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE $whereSql");
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$totalPages = ceil($countStmt->fetchColumn() / $limit);

$stmt = $pdo->prepare("SELECT l.*, u.name as assigned_to_name FROM leads l LEFT JOIN users u ON l.assigned_to = u.id WHERE $whereSql ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$leads = $stmt->fetchAll();

$sources = ['Walk-in', 'Phone Call', 'Website', 'Referral', 'Social Media', 'Other'];
$statuses = ['New', 'Contacted', 'Interested', 'Converted', 'Lost'];

function getLeadBadgeClass($status) {
    switch ($status) {
        case 'New': return 'bg-info';
        case 'Contacted': return 'bg-primary';
        case 'Interested': return 'bg-warning text-dark';
        case 'Converted': return 'bg-success';
        case 'Lost': return 'bg-danger';
        default: return 'bg-secondary';
    }
}
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="/Reper_hub/leads/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Lead</a>
    </div>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['flash_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach($statuses as $st): ?>
                            <option value="<?= $st ?>" <?= (($_GET['status']??'') == $st) ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-select">
                        <option value="">All Sources</option>
                        <?php foreach($sources as $src): ?>
                            <option value="<?= $src ?>" <?= (($_GET['source']??'') == $src) ? 'selected' : '' ?>><?= $src ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
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
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Source</th>
                            <th>Device</th>
                            <th>Status</th>
                            <th>Follow-up</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leads)): ?>
                            <tr><td colspan="8" class="text-center py-3">No leads found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($leads as $l): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($l['name']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($l['phone']) ?><br>
                                        <small class="text-muted"><?= htmlspecialchars($l['email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($l['source']) ?></td>
                                    <td><?= htmlspecialchars($l['device_type'] ?? 'N/A') ?></td>
                                    <td><span class="badge <?= getLeadBadgeClass($l['status']) ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                                    <td><?= $l['follow_up_date'] ? date('d M Y', strtotime($l['follow_up_date'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($l['assigned_to_name'] ?? 'Unassigned') ?></td>
                                    <td>
                                        <a href="/Reper_hub/leads/view.php?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
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
