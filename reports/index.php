<?php
session_start();
require_once '../config.php';
if (!isset($_SESSION['user_id'])) { header('Location: /Reper_hub/auth/login.php'); exit; }

$pageTitle = 'Reports & Analytics';

// Date range filtering
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

// Handle CSV / Excel Exports
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    $isExcel = ($_GET['format'] ?? '') === 'excel';
    $ext = $isExcel ? 'xls' : 'csv';
    $filename = "{$exportType}_report_{$startDate}_to_{$endDate}.{$ext}";
    
    if ($isExcel) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
    }
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    // Output BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($exportType === 'revenue') {
        fputcsv($output, ['Date', 'Invoice #', 'Status', 'Paid Amount', 'Created By']);
        $stmt = $pdo->prepare("SELECT i.created_at, i.invoice_number, i.status, i.paid_amount, u.name as created_by_name FROM invoices i LEFT JOIN users u ON i.created_by = u.id WHERE DATE(i.created_at) >= ? AND DATE(i.created_at) <= ? ORDER BY i.created_at DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [date('Y-m-d', strtotime($row['created_at'])), $row['invoice_number'], $row['status'], $row['paid_amount'], $row['created_by_name']]);
        }
    } elseif ($exportType === 'tickets') {
        fputcsv($output, ['Ticket ID', 'Customer', 'Status', 'Device Type', 'Created At', 'Completed At']);
        $stmt = $pdo->prepare("SELECT t.ticket_id, c.name as customer_name, t.status, t.device_type, t.created_at, t.completed_at FROM repair_tickets t LEFT JOIN customers c ON t.customer_id = c.id WHERE DATE(t.created_at) >= ? AND DATE(t.created_at) <= ? ORDER BY t.created_at DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['ticket_id'], $row['customer_name'] ?? 'Walk-in', $row['status'], $row['device_type'], $row['created_at'], $row['completed_at']]);
        }
    } elseif ($exportType === 'inventory') {
        fputcsv($output, ['SKU', 'Name', 'Category', 'Current Qty', 'Cost Price', 'Selling Price', 'Total Value']);
        $stmt = $pdo->query("SELECT sku, name, category, quantity, cost_price, selling_price, (quantity * cost_price) as total_value FROM inventory ORDER BY name ASC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['sku'], $row['name'], $row['category'], $row['quantity'], $row['cost_price'], $row['selling_price'], $row['total_value']]);
        }
    } elseif ($exportType === 'expenses') {
        fputcsv($output, ['Date', 'Category', 'Description', 'Amount']);
        $stmt = $pdo->prepare("SELECT expense_date, category, description, amount FROM expenses WHERE expense_date >= ? AND expense_date <= ? ORDER BY expense_date DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [$row['expense_date'], $row['category'], $row['description'], $row['amount']]);
        }
    } elseif ($exportType === 'feedback') {
        fputcsv($output, ['Date', 'Customer', 'Ticket #', 'Rating', 'Review', 'Technician']);
        $stmt = $pdo->prepare("SELECT f.created_at, c.name as customer_name, rt.ticket_id, f.rating, f.review, t.name as technician_name FROM feedback f LEFT JOIN customers c ON f.customer_id = c.id LEFT JOIN repair_tickets rt ON f.ticket_id = rt.id LEFT JOIN users t ON f.technician_id = t.id WHERE DATE(f.created_at) >= ? AND DATE(f.created_at) <= ? ORDER BY f.created_at DESC");
        $stmt->execute([$startDate, $endDate]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [date('Y-m-d', strtotime($row['created_at'])), $row['customer_name'] ?? 'Unknown', $row['ticket_id'] ?? '-', $row['rating'], $row['review'], $row['technician_name'] ?? 'Unassigned']);
        }
    }
    
    fclose($output);
    exit;
}

include '../includes/header.php';
include '../includes/sidebar.php';

// Data fetching for reports based on dates
// Revenue Data
$revStmt = $pdo->prepare("SELECT DATE(created_at) as dt, SUM(paid_amount) as daily_rev FROM invoices WHERE status = 'Paid' AND DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY DATE(created_at) ORDER BY dt ASC");
$revStmt->execute([$startDate, $endDate]);
$revenueData = $revStmt->fetchAll(PDO::FETCH_ASSOC);

$totalRevStmt = $pdo->prepare("SELECT SUM(paid_amount) as total_rev, COUNT(id) as total_inv, SUM(CASE WHEN status='Paid' THEN 1 ELSE 0 END) as total_paid FROM invoices WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
$totalRevStmt->execute([$startDate, $endDate]);
$revSummary = $totalRevStmt->fetch(PDO::FETCH_ASSOC);
$totalRevenue = $revSummary['total_rev'] ?: 0;
$totalInvoices = $revSummary['total_inv'] ?: 0;
$totalPaid = $revSummary['total_paid'] ?: 0;

$daysDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24) + 1;
$avgDaily = $totalRevenue / max(1, $daysDiff);

$revChartLabels = [];
$revChartData = [];
foreach ($revenueData as $r) {
    $revChartLabels[] = date('M d', strtotime($r['dt']));
    $revChartData[] = (float)$r['daily_rev'];
}

// Ticket Data
$tickStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM repair_tickets WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY status");
$tickStmt->execute([$startDate, $endDate]);
$ticketStatusCounts = $tickStmt->fetchAll(PDO::FETCH_ASSOC);
$ticketTotal = 0;
$ticketCompleted = 0;
$ticketPending = 0;
$tickChartLabels = [];
$tickChartData = [];
$tickChartColors = [];
$statusColors = [
    'Pending' => '#ffc107',
    'In Progress' => '#0d6efd',
    'Waiting for Parts' => '#0dcaf0',
    'Ready for Pickup' => '#198754',
    'Completed' => '#198754',
    'Delivered' => '#6c757d',
    'Cancelled' => '#dc3545'
];

foreach ($ticketStatusCounts as $t) {
    $tickChartLabels[] = $t['status'];
    $tickChartData[] = (int)$t['cnt'];
    $tickChartColors[] = $statusColors[$t['status']] ?? '#adb5bd';
    $ticketTotal += $t['cnt'];
    if ($t['status'] === 'Completed') $ticketCompleted += $t['cnt'];
    if (in_array($t['status'], ['Pending', 'In Progress', 'Waiting for Parts'])) $ticketPending += $t['cnt'];
}

$devStmt = $pdo->prepare("SELECT device_type, COUNT(*) as cnt FROM repair_tickets WHERE DATE(created_at) >= ? AND DATE(created_at) <= ? GROUP BY device_type ORDER BY cnt DESC LIMIT 5");
$devStmt->execute([$startDate, $endDate]);
$topDevices = $devStmt->fetchAll(PDO::FETCH_ASSOC);

// Employee Performance
$empStmt = $pdo->prepare("
    SELECT u.name, 
    COUNT(t.id) as assigned,
    SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed,
    (SELECT SUM(paid_amount) FROM invoices WHERE created_by = u.id AND status='Paid' AND DATE(created_at) >= ? AND DATE(created_at) <= ?) as revenue
    FROM users u 
    LEFT JOIN repair_tickets t ON u.id = t.assigned_to AND DATE(t.created_at) >= ? AND DATE(t.created_at) <= ?
    WHERE u.role IN ('Technician', 'Admin')
    GROUP BY u.id
");
$empStmt->execute([$startDate, $endDate, $startDate, $endDate]);
$empPerformance = $empStmt->fetchAll(PDO::FETCH_ASSOC);

$empChartLabels = [];
$empChartData = [];
foreach ($empPerformance as $ep) {
    if ($ep['assigned'] > 0 || $ep['completed'] > 0) {
        $empChartLabels[] = $ep['name'];
        $empChartData[] = (int)$ep['completed'];
    }
}

// Inventory
$invStmt = $pdo->query("SELECT COUNT(*) as total_items, SUM(quantity * cost_price) as stock_value, SUM(CASE WHEN quantity <= 5 AND quantity > 0 THEN 1 ELSE 0 END) as low_stock, SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock FROM inventory");
$invSummary = $invStmt->fetch(PDO::FETCH_ASSOC);

$topPartsStmt = $pdo->query("SELECT inv.name, SUM(ii.quantity) as total_used FROM invoice_items ii JOIN inventory inv ON ii.inventory_id = inv.id GROUP BY inv.id ORDER BY total_used DESC LIMIT 10");
$topParts = $topPartsStmt->fetchAll(PDO::FETCH_ASSOC);

// Expenses
$expStmt = $pdo->prepare("SELECT category, SUM(amount) as cat_total FROM expenses WHERE expense_date >= ? AND expense_date <= ? GROUP BY category");
$expStmt->execute([$startDate, $endDate]);
$expData = $expStmt->fetchAll(PDO::FETCH_ASSOC);
$totalExpenses = 0;
$expChartLabels = [];
$expChartData = [];
foreach ($expData as $e) {
    $totalExpenses += $e['cat_total'];
    $expChartLabels[] = $e['category'];
    $expChartData[] = (float)$e['cat_total'];
}

// Phase 2: Customer Feedback & Satisfaction Summary
$fbStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews, SUM(CASE WHEN rating=5 THEN 1 ELSE 0 END) as five_stars FROM feedback WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
$fbStmt->execute([$startDate, $endDate]);
$fbSummary = $fbStmt->fetch(PDO::FETCH_ASSOC);
$avgSatisfaction = $fbSummary['avg_rating'] ? round($fbSummary['avg_rating'], 1) : 0;
$totalFeedback = $fbSummary['total_reviews'] ?: 0;
$fiveStarReviews = $fbSummary['five_stars'] ?: 0;
?>
<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="mb-0">Reports & Analytics</h2>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-danger" onclick="window.print()">
                    <i class="fas fa-file-pdf me-1"></i> Print / Save PDF
                </button>
            </div>
        </div>

        <!-- Filter Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                        <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <ul class="nav nav-pills mb-4" id="reportTabs" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#revReport" type="button">Revenue</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#ticketReport" type="button">Tickets</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#empReport" type="button">Employees</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#invReport" type="button">Inventory</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#expReport" type="button">Expenses</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#fbReport" type="button"><i class="fas fa-star text-warning me-1"></i>Feedback</button></li>
        </ul>

        <div class="tab-content" id="reportTabsContent">
            <!-- Revenue Report -->
            <div class="tab-pane fade show active" id="revReport">
                <div class="d-flex justify-content-end mb-3">
                    <a href="?export=revenue&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-success"><i class="fas fa-file-csv me-2"></i>Export to CSV</a>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3"><div class="card border-0 shadow-sm bg-primary text-white"><div class="card-body"><h6 class="card-title text-white-50">Total Revenue</h6><h3 class="mb-0">₹<?= number_format($totalRevenue, 2) ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm bg-info text-white"><div class="card-body"><h6 class="card-title text-white-50">Average Daily</h6><h3 class="mb-0">₹<?= number_format($avgDaily, 2) ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm bg-success text-white"><div class="card-body"><h6 class="card-title text-white-50">Total Invoices</h6><h3 class="mb-0"><?= number_format($totalInvoices) ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm bg-secondary text-white"><div class="card-body"><h6 class="card-title text-white-50">Total Paid</h6><h3 class="mb-0"><?= number_format($totalPaid) ?></h3></div></div></div>
                </div>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Ticket Report -->
            <div class="tab-pane fade" id="ticketReport">
                <div class="d-flex justify-content-end mb-3">
                    <a href="?export=tickets&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-success"><i class="fas fa-file-csv me-2"></i>Export to CSV</a>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Tickets by Status</h5>
                                <div style="height: 300px; display: flex; justify-content: center;">
                                    <canvas id="ticketStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Summary</h5>
                                <ul class="list-group list-group-flush mb-4">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">Total Created <span class="badge bg-primary rounded-pill"><?= $ticketTotal ?></span></li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">Completed <span class="badge bg-success rounded-pill"><?= $ticketCompleted ?></span></li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">Pending/In Progress <span class="badge bg-warning text-dark rounded-pill"><?= $ticketPending ?></span></li>
                                </ul>
                                <h5 class="card-title">Top 5 Device Types</h5>
                                <table class="table table-sm">
                                    <thead><tr><th>Device Type</th><th>Count</th></tr></thead>
                                    <tbody>
                                        <?php foreach($topDevices as $td): ?>
                                            <tr><td><?= htmlspecialchars($td['device_type'] ?: 'Unknown') ?></td><td><?= $td['cnt'] ?></td></tr>
                                        <?php endforeach; ?>
                                        <?php if(empty($topDevices)): ?><tr><td colspan="2">No data</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Report -->
            <div class="tab-pane fade" id="empReport">
                <div class="row">
                    <div class="col-md-7 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Employee Performance</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr><th>Employee Name</th><th>Assigned</th><th>Completed</th><th>Rate</th><th>Revenue</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($empPerformance as $ep): 
                                                $rate = $ep['assigned'] > 0 ? round(($ep['completed'] / $ep['assigned']) * 100) : 0;
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($ep['name']) ?></td>
                                                <td><?= $ep['assigned'] ?></td>
                                                <td><?= $ep['completed'] ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="me-2"><?= $rate ?>%</span>
                                                        <div class="progress w-100" style="height: 5px;"><div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div></div>
                                                    </div>
                                                </td>
                                                <td>₹<?= number_format($ep['revenue'] ?? 0, 2) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Completed Tickets by Employee</h5>
                                <canvas id="empChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Report -->
            <div class="tab-pane fade" id="invReport">
                <div class="d-flex justify-content-end mb-3">
                    <a href="?export=inventory" class="btn btn-sm btn-success"><i class="fas fa-file-csv me-2"></i>Export to CSV</a>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><h6 class="card-title text-muted">Total Items</h6><h3 class="mb-0"><?= number_format($invSummary['total_items'] ?? 0) ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><h6 class="card-title text-muted">Stock Value</h6><h3 class="mb-0">₹<?= number_format($invSummary['stock_value'] ?? 0, 2) ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><h6 class="card-title text-muted">Low Stock</h6><h3 class="mb-0 text-warning"><?= number_format($invSummary['low_stock'] ?? 0) ?></h3></div></div></div>
                    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><h6 class="card-title text-muted">Out of Stock</h6><h3 class="mb-0 text-danger"><?= number_format($invSummary['out_of_stock'] ?? 0) ?></h3></div></div></div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Top 10 Most Used Parts (All Time)</h5>
                        <table class="table">
                            <thead><tr><th>Part Name</th><th>Total Quantity Used</th></tr></thead>
                            <tbody>
                                <?php foreach($topParts as $tp): ?>
                                    <tr><td><?= htmlspecialchars($tp['name']) ?></td><td><?= $tp['total_used'] ?></td></tr>
                                <?php endforeach; ?>
                                <?php if(empty($topParts)): ?><tr><td colspan="2">No data</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Expense Report -->
            <div class="tab-pane fade" id="expReport">
                <div class="d-flex justify-content-end mb-3">
                    <a href="?export=expenses&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-success"><i class="fas fa-file-csv me-2"></i>Export to CSV</a>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Expenses by Category</h5>
                                <div style="height: 300px; display: flex; justify-content: center;">
                                    <canvas id="expenseChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <h5 class="card-title">Expense Summary</h5>
                                <h3 class="text-danger mb-4">Total: ₹<?= number_format($totalExpenses, 2) ?></h3>
                                <table class="table table-sm">
                                    <thead><tr><th>Category</th><th>Amount</th><th>% of Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach($expData as $e): 
                                            $pct = $totalExpenses > 0 ? round(($e['cat_total'] / $totalExpenses) * 100, 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($e['category'] ?: 'Uncategorized') ?></td>
                                                <td>₹<?= number_format($e['cat_total'], 2) ?></td>
                                                <td><?= $pct ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Feedback Report -->
            <div class="tab-pane fade" id="fbReport">
                <div class="d-flex justify-content-end gap-2 mb-3">
                    <a href="?export=feedback&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-success">
                        <i class="fas fa-file-csv me-1"></i> Export to CSV
                    </a>
                    <a href="?export=feedback&format=excel&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-file-excel me-1"></i> Export to Excel
                    </a>
                </div>
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm bg-warning text-dark">
                            <div class="card-body">
                                <h6 class="card-title text-dark-50">Average Satisfaction</h6>
                                <h3 class="mb-0 fw-bold"><i class="fas fa-star text-dark me-1"></i><?= $avgSatisfaction ?> / 5.0</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title text-white-50">Total Feedback Received</h6>
                                <h3 class="mb-0 fw-bold"><?= number_format($totalFeedback) ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title text-white-50">5-Star Ratings</h6>
                                <h3 class="mb-0 fw-bold"><?= number_format($fiveStarReviews) ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Customer Reviews for Selected Period</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Ticket #</th>
                                        <th>Rating</th>
                                        <th>Review</th>
                                        <th>Technician</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recentFbStmt = $pdo->prepare("SELECT f.*, c.name as customer_name, rt.ticket_id, t.name as technician_name FROM feedback f LEFT JOIN customers c ON f.customer_id = c.id LEFT JOIN repair_tickets rt ON f.ticket_id = rt.id LEFT JOIN users t ON f.technician_id = t.id WHERE DATE(f.created_at) >= ? AND DATE(f.created_at) <= ? ORDER BY f.created_at DESC LIMIT 20");
                                    $recentFbStmt->execute([$startDate, $endDate]);
                                    $recentFbs = $recentFbStmt->fetchAll(PDO::FETCH_ASSOC);
                                    if (empty($recentFbs)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No feedback recorded for this date range.</td></tr>
                                    <?php else:
                                        foreach ($recentFbs as $rfb): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($rfb['created_at'])) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($rfb['customer_name'] ?? 'Unknown') ?></td>
                                            <td><?= !empty($rfb['ticket_id']) ? htmlspecialchars($rfb['ticket_id']) : '-' ?></td>
                                            <td class="text-warning">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $rfb['rating'] ? '' : 'text-muted opacity-25' ?>"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td><?= htmlspecialchars($rfb['review'] ?: 'No comment') ?></td>
                                            <td><?= htmlspecialchars($rfb['technician_name'] ?? 'Unassigned') ?></td>
                                        </tr>
                                    <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const ctxRev = document.getElementById('revenueChart');
    if(ctxRev) {
        new Chart(ctxRev, {
            type: 'bar',
            data: {
                labels: <?= json_encode($revChartLabels) ?>,
                datasets: [{
                    label: 'Daily Revenue (₹)',
                    data: <?= json_encode($revChartData) ?>,
                    backgroundColor: 'rgba(13, 110, 253, 0.5)',
                    borderColor: 'rgb(13, 110, 253)',
                    borderWidth: 1
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    }

    // Ticket Status Doughnut
    const ctxTick = document.getElementById('ticketStatusChart');
    if(ctxTick && <?= count($tickChartData) ?> > 0) {
        new Chart(ctxTick, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($tickChartLabels) ?>,
                datasets: [{
                    data: <?= json_encode($tickChartData) ?>,
                    backgroundColor: <?= json_encode($tickChartColors) ?>
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    // Employee Chart
    const ctxEmp = document.getElementById('empChart');
    if(ctxEmp && <?= count($empChartData) ?> > 0) {
        new Chart(ctxEmp, {
            type: 'bar',
            data: {
                labels: <?= json_encode($empChartLabels) ?>,
                datasets: [{
                    label: 'Completed Tickets',
                    data: <?= json_encode($empChartData) ?>,
                    backgroundColor: 'rgba(25, 135, 84, 0.5)',
                    borderColor: 'rgb(25, 135, 84)',
                    borderWidth: 1
                }]
            },
            options: { indexAxis: 'y', responsive: true }
        });
    }

    // Expense Pie
    const ctxExp = document.getElementById('expenseChart');
    if(ctxExp && <?= count($expChartData) ?> > 0) {
        new Chart(ctxExp, {
            type: 'pie',
            data: {
                labels: <?= json_encode($expChartLabels) ?>,
                datasets: [{
                    data: <?= json_encode($expChartData) ?>,
                    backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#0dcaf0', '#6f42c1']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
});
</script>
<?php include '../includes/footer.php'; ?>
