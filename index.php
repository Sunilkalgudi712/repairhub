<?php
require_once 'config.php';
requireLogin();

$pageTitle = 'Dashboard';

// Helper function for time ago
function time_ago($datetime, $full = false) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// Initialize stats
$todaysTickets = 0;
$pendingRepairs = 0;
$completedToday = 0;
$revenueToday = 0;

$revenueDates = [];
$revenueTotals = [];
$statusLabels = [];
$statusCounts = [];

$recentTickets = [];
$recentActivity = [];
$lowStockItems = [];

try {
    // Stat 1: Today's Tickets
    $stmt = $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE DATE(created_at) = CURDATE()");
    $todaysTickets = $stmt->fetchColumn() ?: 0;

    // Stat 2: Pending Repairs
    $stmt = $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE status IN ('Pending', 'In Progress', 'Waiting for Parts')");
    $pendingRepairs = $stmt->fetchColumn() ?: 0;

    // Stat 3: Completed Today
    $stmt = $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE status = 'Completed' AND DATE(completed_at) = CURDATE()");
    $completedToday = $stmt->fetchColumn() ?: 0;

    // Stat 4: Revenue Today
    $stmt = $pdo->query("SELECT SUM(paid_amount) FROM invoices WHERE DATE(payment_date) = CURDATE()");
    $revenueToday = $stmt->fetchColumn() ?: 0;

    // Chart 1: Revenue last 7 days
    $stmt = $pdo->query("SELECT DATE(payment_date) as pdate, SUM(paid_amount) as total 
                         FROM invoices 
                         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                         GROUP BY DATE(payment_date) 
                         ORDER BY pdate ASC");
    $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in last 7 days to ensure chart has all days even if 0 revenue
    for ($i = 6; $i >= 0; $i--) {
        $dateStr = date('Y-m-d', strtotime("-$i days"));
        $revenueDates[] = date('M d', strtotime("-$i days"));
        
        $foundTotal = 0;
        foreach ($revenueData as $row) {
            if ($row['pdate'] == $dateStr) {
                $foundTotal = $row['total'];
                break;
            }
        }
        $revenueTotals[] = (float)$foundTotal;
    }

    // Chart 2: Tickets by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM repair_tickets GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statusLabels[] = $row['status'];
        $statusCounts[] = $row['count'];
    }

    // Recent Tickets (last 10)
    $stmt = $pdo->query("SELECT t.id, t.ticket_id, CONCAT(t.device_type, ' ', IFNULL(t.device_brand, '')) as device, t.status, t.created_at, c.name as customer_name 
                         FROM repair_tickets t 
                         LEFT JOIN customers c ON t.customer_id = c.id 
                         ORDER BY t.created_at DESC LIMIT 10");
    $recentTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Activity (last 10)
    $stmt = $pdo->query("SELECT a.action, a.created_at, u.name as user_name 
                         FROM activity_log a 
                         LEFT JOIN users u ON a.user_id = u.id 
                         ORDER BY a.created_at DESC LIMIT 10");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Low Stock Alerts
    $stmt = $pdo->query("SELECT name as item_name, quantity, min_stock_level 
                         FROM inventory 
                         WHERE quantity <= min_stock_level 
                         ORDER BY quantity ASC");
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Phase 2: Pending Client Submissions
    $stmt = $pdo->query("SELECT COUNT(*) FROM repair_tickets WHERE submitted_by_client = 1 AND assigned_to IS NULL");
    $pendingSubmissions = $stmt ? (int)$stmt->fetchColumn() : 0;

    // Phase 2: Pending Quotation Approvals
    $stmt = $pdo->query("SELECT COUNT(*) FROM quotations WHERE status = 'Sent'");
    $pendingQuotations = $stmt ? (int)$stmt->fetchColumn() : 0;

    // Phase 2: Active Technicians Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Technician' AND is_active = 1");
    $activeTechnicians = $stmt ? (int)$stmt->fetchColumn() : 0;

} catch (PDOException $e) {
    // Silently handle errors for dashboard, or log them
    error_log("Dashboard query error: " . $e->getMessage());
}

// User name greeting
$userName = $_SESSION['user_name'] ?? 'User';

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <?php if (function_exists('displayFlashMessage')) displayFlashMessage(); ?>
    
    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h3 class="mb-1">Welcome back, <?php echo htmlspecialchars($userName); ?>! 👋</h3>
            <p class="text-muted mb-0"><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <a href="tickets/create.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> New Ticket</a>
            <a href="customers/create.php" class="btn btn-outline-secondary"><i class="fas fa-user-plus me-1"></i> New Customer</a>
            <a href="invoices/create.php" class="btn btn-outline-secondary"><i class="fas fa-file-invoice-dollar me-1"></i> New Invoice</a>
        </div>
    </div>

    <!-- Summary Stat Cards -->
    <div class="row g-4 mb-4">
        <!-- Today's Tickets -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Today's Tickets</p>
                            <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($todaysTickets); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary-soft p-3 rounded text-primary">
                            <i class="fas fa-ticket-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Repairs -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Pending Repairs</p>
                            <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($pendingRepairs); ?></h3>
                        </div>
                        <div class="stat-icon bg-warning-soft p-3 rounded text-warning">
                            <i class="fas fa-tools fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Today -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Completed Today</p>
                            <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars($completedToday); ?></h3>
                        </div>
                        <div class="stat-icon bg-success-soft p-3 rounded text-success">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Today -->
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card stat-card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Revenue Today</p>
                            <h3 class="mb-0 fw-bold">
                                <?php echo function_exists('formatCurrency') ? formatCurrency($revenueToday) : '₹' . number_format($revenueToday, 2); ?>
                            </h3>
                        </div>
                        <div class="stat-icon bg-success-soft p-3 rounded text-success" style="background-color: #d1e7dd; color: #0f5132 !important;">
                            <i class="fas fa-rupee-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Phase 2: Operations & Client Highlights -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-primary bg-opacity-10 border-start border-primary border-4">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Client Submissions</div>
                        <div class="h5 mb-0 fw-bold text-primary"><?= $pendingSubmissions ?> Waiting Assignment</div>
                    </div>
                    <a href="admin/client_submissions.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-inbox me-1"></i> Review
                    </a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-info bg-opacity-10 border-start border-info border-4">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Quotations</div>
                        <div class="h5 mb-0 fw-bold text-info"><?= $pendingQuotations ?> Sent / Pending</div>
                    </div>
                    <a href="quotations/index.php" class="btn btn-sm btn-info text-white">
                        <i class="fas fa-file-contract me-1"></i> View Quotes
                    </a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-success bg-opacity-10 border-start border-success border-4">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Technicians</div>
                        <div class="h5 mb-0 fw-bold text-success"><?= $activeTechnicians ?> Active Staff</div>
                    </div>
                    <a href="admin/worker_schedule.php" class="btn btn-sm btn-success">
                        <i class="fas fa-calendar-alt me-1"></i> Schedule
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Revenue Chart -->
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="mb-0">Revenue (Last 7 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Status Doughnut Chart -->
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="mb-0">Tickets by Status</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <canvas id="statusChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Two-column Layout: Tables & Alerts -->
    <div class="row g-4 mb-4">
        <!-- Recent Tickets -->
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Tickets</h5>
                    <a href="tickets/index.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Customer</th>
                                    <th>Device</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentTickets)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No recent tickets found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentTickets as $ticket): ?>
                                    <tr>
                                        <td><a href="tickets/view.php?id=<?php echo (int)$ticket['id']; ?>" class="text-decoration-none fw-bold">#<?php echo htmlspecialchars($ticket['ticket_id']); ?></a></td>
                                        <td><?php echo htmlspecialchars($ticket['customer_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($ticket['device']); ?></td>
                                        <td><?php echo getStatusBadge($ticket['status']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Low Stock Alerts -->
        <div class="col-12 col-lg-5 d-flex flex-column gap-4">
            
            <!-- Low Stock Alerts -->
            <?php if (!empty($lowStockItems)): ?>
            <div class="card border-0 shadow-sm border-start border-danger border-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="mb-0 text-danger"><i class="fas fa-exclamation-triangle me-2"></i> Low Stock Alerts</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Item Name</th>
                                    <th>Stock</th>
                                    <th>Min Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockItems as $item): ?>
                                <tr>
                                    <td class="ps-4 text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="text-danger fw-bold"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td><?php echo htmlspecialchars($item['min_stock_level']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm flex-grow-1">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentActivity)): ?>
                        <p class="text-muted mb-0">No recent activity.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($recentActivity as $activity): ?>
                            <li class="mb-3 border-bottom pb-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></p>
                                        <p class="mb-0 text-muted small"><?php echo htmlspecialchars($activity['action']); ?></p>
                                    </div>
                                    <small class="text-muted"><?php echo time_ago($activity['created_at']); ?></small>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Colors matching Bootstrap 5 themes
    const primaryColor = 'rgba(13, 110, 253, 0.8)';
    const primaryBorder = 'rgba(13, 110, 253, 1)';
    const successColor = 'rgba(25, 135, 84, 0.8)';
    const warningColor = 'rgba(255, 193, 7, 0.8)';
    const infoColor = 'rgba(13, 202, 240, 0.8)';
    const dangerColor = 'rgba(220, 53, 69, 0.8)';
    const secondaryColor = 'rgba(108, 117, 125, 0.8)';

    // 1. Revenue Chart (Bar)
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctxRevenue, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($revenueDates); ?>,
            datasets: [{
                label: 'Revenue (₹)',
                data: <?php echo json_encode($revenueTotals); ?>,
                backgroundColor: primaryColor,
                borderColor: primaryBorder,
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₹' + value;
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // 2. Status Chart (Doughnut)
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    
    // Assign colors based on status labels
    const statusLabels = <?php echo json_encode($statusLabels); ?>;
    const statusData = <?php echo json_encode($statusCounts); ?>;
    const statusColors = statusLabels.map(status => {
        switch(status.trim()) {
            case 'Pending': return warningColor;
            case 'In Progress': return primaryColor;
            case 'Waiting for Parts': return infoColor;
            case 'Ready for Pickup': return successColor;
            case 'Completed': return successColor;
            case 'Delivered': return secondaryColor;
            case 'Cancelled': return dangerColor;
            default: return secondaryColor;
        }
    });

    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: statusColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                }
            },
            cutout: '65%'
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
