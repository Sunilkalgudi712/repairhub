<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Filters
$ratingFilter = $_GET['rating'] ?? '';
$techFilter = $_GET['technician'] ?? '';

$whereConditions = [];
$params = [];

if ($ratingFilter !== '') {
    $whereConditions[] = "f.rating = ?";
    $params[] = $ratingFilter;
}

if ($techFilter !== '') {
    $whereConditions[] = "f.technician_id = ?";
    $params[] = $techFilter;
}

$whereSQL = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Summary Data
$totalReviews = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$avgRating = $pdo->query("SELECT AVG(rating) FROM feedback")->fetchColumn() ?: 0;
$fiveStarCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE rating = 5")->fetchColumn();
$reviewsThisMonth = $pdo->query("SELECT COUNT(*) FROM feedback WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn();

// Fetch Feedback
$query = "
    SELECT f.*, c.name as customer_name, rt.ticket_id, t.name as technician_name
    FROM feedback f
    LEFT JOIN customers c ON f.customer_id = c.id
    LEFT JOIN repair_tickets rt ON f.ticket_id = rt.id
    LEFT JOIN users t ON f.technician_id = t.id
    $whereSQL
    ORDER BY f.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$feedbackList = $stmt->fetchAll();

// Pagination Total
$countQuery = "SELECT COUNT(*) FROM feedback f $whereSQL";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Technicians for dropdown
$techs = $pdo->query("SELECT id, name FROM users WHERE role = 'Technician'")->fetchAll();

$pageTitle = 'Customer Feedback';
include '../includes/header.php';
include '../includes/sidebar.php';

function renderStars($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<i class="fas fa-star text-warning"></i>';
        } else {
            $html .= '<i class="far fa-star text-warning"></i>';
        }
    }
    return $html;
}
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Customer Feedback</h2>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-comments"></i> Total Reviews</h6>
                    <h3 class="mb-0"><?= $totalReviews ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-star-half-alt"></i> Avg Rating</h6>
                    <h3 class="mb-0"><?= number_format($avgRating, 1) ?> <small class="fs-6"><?= renderStars(round($avgRating)) ?></small></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-star"></i> 5-Star Reviews</h6>
                    <h3 class="mb-0"><?= $fiveStarCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-calendar-alt"></i> Reviews This Month</h6>
                    <h3 class="mb-0"><?= $reviewsThisMonth ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <select name="rating" class="form-select">
                        <option value="">All Ratings</option>
                        <option value="5" <?= $ratingFilter === '5' ? 'selected' : '' ?>>5 Stars</option>
                        <option value="4" <?= $ratingFilter === '4' ? 'selected' : '' ?>>4 Stars</option>
                        <option value="3" <?= $ratingFilter === '3' ? 'selected' : '' ?>>3 Stars</option>
                        <option value="2" <?= $ratingFilter === '2' ? 'selected' : '' ?>>2 Stars</option>
                        <option value="1" <?= $ratingFilter === '1' ? 'selected' : '' ?>>1 Star</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="technician" class="form-select">
                        <option value="">All Technicians</option>
                        <?php foreach ($techs as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $techFilter == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="feedback.php" class="btn btn-outline-secondary w-100">Reset</a>
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
                            <th width="15%">Customer</th>
                            <th width="10%">Ticket #</th>
                            <th width="15%">Rating</th>
                            <th width="35%">Review</th>
                            <th width="15%">Technician</th>
                            <th width="10%">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($feedbackList)): ?>
                        <tr><td colspan="6" class="text-center py-4">No feedback found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($feedbackList as $fb): ?>
                            <tr>
                                <td><span class="fw-bold"><?= htmlspecialchars($fb['customer_name'] ?? 'Unknown') ?></span></td>
                                <td><?= !empty($fb['ticket_id']) ? '<a href="../tickets/view.php?id='.$fb['ticket_id'].'">'.htmlspecialchars($fb['ticket_id']).'</a>' : '-' ?></td>
                                <td class="star-display fs-5"><?= renderStars($fb['rating']) ?></td>
                                <td><?= nl2br(htmlspecialchars($fb['review'])) ?></td>
                                <td><?= htmlspecialchars($fb['technician_name'] ?? 'Unassigned') ?></td>
                                <td><?= date('M d, Y', strtotime($fb['created_at'])) ?></td>
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
                <a class="page-link" href="?page=<?= $page - 1 ?>&rating=<?= $ratingFilter ?>&technician=<?= $techFilter ?>">Previous</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>&rating=<?= $ratingFilter ?>&technician=<?= $techFilter ?>"><?= $i ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>&rating=<?= $ratingFilter ?>&technician=<?= $techFilter ?>">Next</a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
