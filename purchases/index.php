<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header("Location: " . APP_URL . "/auth/login.php"); exit; }

$pageTitle = 'Purchases';
include '../includes/header.php';
include '../includes/sidebar.php';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch total
$totalStmt = $pdo->query("SELECT COUNT(*) FROM purchases");
$totalRows = $totalStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch purchases
$stmt = $pdo->prepare("SELECT * FROM purchases ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$purchases = $stmt->fetchAll();
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?= htmlspecialchars($pageTitle) ?></h1>
        <a href="<?= APP_URL ?>/purchases/create.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Purchase</a>
    </div>

    <!-- flash messages -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show">
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
                            <th>Purchase #</th>
                            <th>Supplier</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Payment Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchases)): ?>
                            <tr><td colspan="7" class="text-center py-3">No purchases found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($purchases as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['purchase_number']) ?></td>
                                    <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                                    <td>₹<?= number_format($p['total_amount'], 2) ?></td>
                                    <td>
                                        <?php 
                                            $badgeClass = 'bg-secondary';
                                            if ($p['status'] == 'Ordered') $badgeClass = 'bg-primary';
                                            elseif ($p['status'] == 'Received') $badgeClass = 'bg-success';
                                            elseif ($p['status'] == 'Partial') $badgeClass = 'bg-warning text-dark';
                                            elseif ($p['status'] == 'Cancelled') $badgeClass = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($p['payment_status'] ?? 'Pending') ?></td>
                                    <td><?= htmlspecialchars(date('d M Y', strtotime($p['created_at']))) ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/purchases/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
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
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

  </div>
</div>
<?php include '../includes/footer.php'; ?>
