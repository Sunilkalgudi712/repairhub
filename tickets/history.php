<?php
/**
 * RepairHub — Ticket History & Audit Trail
 * Displays chronological audit records for ticket creations, status changes, assignments, priority shifts, notes, and cost updates.
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/auth/login.php");
    exit;
}

// Ensure ticket_history table exists
static $ticketHistoryReady = false;
if (!$ticketHistoryReady) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ticket_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ticket_id` INT NOT NULL,
        `ticket_number` VARCHAR(50) NOT NULL,
        `user_id` INT DEFAULT NULL,
        `user_name` VARCHAR(100) DEFAULT NULL,
        `user_role` VARCHAR(50) DEFAULT NULL,
        `action_type` VARCHAR(50) NOT NULL,
        `field_name` VARCHAR(50) DEFAULT NULL,
        `old_value` TEXT DEFAULT NULL,
        `new_value` TEXT DEFAULT NULL,
        `change_description` TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_ticket_id` (`ticket_id`),
        KEY `idx_ticket_number` (`ticket_number`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_action_type` (`action_type`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Auto-seed existing tickets if ticket_history is empty
    $countExisting = (int)$pdo->query("SELECT COUNT(*) FROM ticket_history")->fetchColumn();
    if ($countExisting === 0) {
        $existingTickets = $pdo->query("
            SELECT t.*, c.name as customer_name, u.name as tech_name 
            FROM repair_tickets t 
            LEFT JOIN customers c ON t.customer_id = c.id 
            LEFT JOIN users u ON t.assigned_to = u.id 
            ORDER BY t.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($existingTickets as $tk) {
            $custName = $tk['customer_name'] ?: 'Customer';
            $desc = "Ticket created for {$custName} ({$tk['device_brand']} {$tk['device_model']}). Priority: {$tk['priority']}. Status: {$tk['status']}. Est Cost: " . CURRENCY_SYMBOL . number_format((float)$tk['estimated_cost'], 2);
            $stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, ticket_number, user_id, user_name, user_role, action_type, field_name, old_value, new_value, change_description, created_at)
                                   VALUES (?, ?, ?, ?, ?, 'created', 'all', NULL, ?, ?, ?)");
            $stmt->execute([
                $tk['id'],
                $tk['ticket_id'],
                1,
                'Admin',
                'Admin',
                $tk['status'],
                $desc,
                $tk['created_at'] ?: date('Y-m-d H:i:s')
            ]);

            if ($tk['assigned_to']) {
                $techName = $tk['tech_name'] ?: 'Technician #' . $tk['assigned_to'];
                $assignDesc = "Assigned to {$techName}";
                $stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, ticket_number, user_id, user_name, user_role, action_type, field_name, old_value, new_value, change_description, created_at)
                                       VALUES (?, ?, ?, ?, ?, 'assigned', 'assigned_to', NULL, ?, ?, ?)");
                $stmt->execute([
                    $tk['id'],
                    $tk['ticket_id'],
                    1,
                    'Admin',
                    'Admin',
                    $techName,
                    $assignDesc,
                    $tk['created_at'] ?: date('Y-m-d H:i:s')
                ]);
            }
        }

        // Backfill legacy ticket_status_history
        try {
            $oldStatuses = $pdo->query("
                SELECT tsh.*, t.ticket_id as ticket_number, u.name as user_name, u.role as user_role 
                FROM ticket_status_history tsh 
                JOIN repair_tickets t ON tsh.ticket_id = t.id 
                LEFT JOIN users u ON (tsh.changed_by = u.id OR tsh.user_id = u.id)
                ORDER BY tsh.created_at ASC
            ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($oldStatuses as $os) {
                $actorId = $os['changed_by'] ?: ($os['user_id'] ?: 1);
                $actorName = $os['user_name'] ?: 'Staff';
                $actorRole = $os['user_role'] ?: 'Staff';
                $fromSt = $os['old_status'] ?? 'Unknown';
                $toSt = $os['new_status'] ?? ($os['status'] ?? 'Updated');
                $histDesc = !empty($os['notes']) ? $os['notes'] : "Status changed from {$fromSt} to {$toSt}";

                $stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, ticket_number, user_id, user_name, user_role, action_type, field_name, old_value, new_value, change_description, created_at)
                                       VALUES (?, ?, ?, ?, ?, 'status_change', 'status', ?, ?, ?, ?)");
                $stmt->execute([
                    $os['ticket_id'],
                    $os['ticket_number'],
                    $actorId,
                    $actorName,
                    $actorRole,
                    $fromSt,
                    $toSt,
                    $histDesc,
                    $os['created_at'] ?: date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {}
    }
    $ticketHistoryReady = true;
}

// Request parameters
$ticketId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$search = trim($_GET['search'] ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$userId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$export = trim($_GET['export'] ?? '');

// Target ticket details if filtering by single ticket
$singleTicket = null;
if ($ticketId) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, u.name as tech_name 
        FROM repair_tickets t 
        LEFT JOIN customers c ON t.customer_id = c.id 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
    $singleTicket = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Build query conditions
$where = ["1=1"];
$params = [];

if ($ticketId) {
    $where[] = "h.ticket_id = ?";
    $params[] = $ticketId;
}
if ($search !== '') {
    $where[] = "(h.ticket_number LIKE ? OR h.change_description LIKE ? OR h.user_name LIKE ? OR t.problem_description LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp;
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
    header('Content-Disposition: attachment; filename=ticket_history_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date & Time', 'Ticket ID', 'Ticket Number', 'Action Type', 'Field Changed', 'Old Value', 'New Value', 'Details', 'User', 'Role']);

    $exportSql = "SELECT h.* FROM ticket_history h 
                  LEFT JOIN repair_tickets t ON h.ticket_id = t.id 
                  WHERE $whereSql ORDER BY h.created_at DESC";
    $stmt = $pdo->prepare($exportSql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['ticket_id'],
            $row['ticket_number'],
            $row['action_type'],
            $row['field_name'] ?? '',
            $row['old_value'] ?? '',
            $row['new_value'] ?? '',
            $row['change_description'],
            $row['user_name'] ?? 'System',
            $row['user_role'] ?? 'Staff'
        ]);
    }
    fclose($output);
    exit;
}

// Pagination
$perPage = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ticket_history h LEFT JOIN repair_tickets t ON h.ticket_id = t.id WHERE $whereSql");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch records
$dataSql = "SELECT h.*, t.status as current_status, t.priority as current_priority, t.device_brand, t.device_model 
            FROM ticket_history h 
            LEFT JOIN repair_tickets t ON h.ticket_id = t.id 
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
    SUM(CASE WHEN action_type = 'status_change' THEN 1 ELSE 0 END) as total_status_changes,
    SUM(CASE WHEN action_type = 'assigned' THEN 1 ELSE 0 END) as total_assignments,
    SUM(CASE WHEN action_type IN ('priority_change', 'cost_update') THEN 1 ELSE 0 END) as total_updates
FROM ticket_history");
$metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = $singleTicket ? 'History: ' . htmlspecialchars($singleTicket['ticket_id']) : 'Ticket Audit & Change History';
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
                    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/tickets/index.php">Repair Tickets</a></li>
                    <?php if ($singleTicket): ?>
                        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/tickets/view.php?id=<?= $singleTicket['id'] ?>"><?= htmlspecialchars($singleTicket['ticket_id']) ?></a></li>
                        <li class="breadcrumb-item active">Audit History</li>
                    <?php else: ?>
                        <li class="breadcrumb-item active">Audit & Change History</li>
                    <?php endif; ?>
                </ol>
            </nav>
            <h2 class="mb-0">
                <i class="fas fa-history text-primary me-2"></i>
                <?= $singleTicket ? 'Ticket Audit: ' . htmlspecialchars($singleTicket['ticket_id']) : 'Ticket Audit & History Log' ?>
            </h2>
            <p class="text-muted small mb-0">Complete audit log tracking who created tickets, updated statuses, assigned technicians, changed priorities, and edited costs.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($singleTicket): ?>
                <a href="<?= APP_URL ?>/tickets/view.php?id=<?= $singleTicket['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-eye me-1"></i> View Ticket
                </a>
                <a href="<?= APP_URL ?>/tickets/edit.php?id=<?= $singleTicket['id'] ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i> Edit Ticket
                </a>
                <a href="<?= APP_URL ?>/tickets/history.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-1"></i> All Tickets History
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/tickets/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> New Ticket
                </a>
            <?php endif; ?>
            
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success">
                <i class="fas fa-file-csv me-1"></i> Export CSV
            </a>
            <a href="<?= APP_URL ?>/tickets/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Tickets
            </a>
        </div>
    </div>

    <!-- Summary Metrics or Single Ticket Summary -->
    <?php if (!$singleTicket): ?>
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
                        <div class="text-muted small fw-semibold text-uppercase">Tickets Created</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($metrics['total_created'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 bg-info bg-opacity-10 text-info me-3">
                        <i class="fas fa-arrows-rotate fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Status Transitions</div>
                        <h4 class="mb-0 fw-bold"><?= number_format($metrics['total_status_changes'] ?? 0) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
                        <i class="fas fa-user-tag fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Assignments & Edits</div>
                        <h4 class="mb-0 fw-bold"><?= number_format(($metrics['total_assignments'] ?? 0) + ($metrics['total_updates'] ?? 0)) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Single Ticket Details Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-3">
                    <div class="text-muted small text-uppercase fw-semibold">Ticket</div>
                    <div class="fs-5 fw-bold text-primary"><?= htmlspecialchars($singleTicket['ticket_id']) ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($singleTicket['device_brand'] . ' ' . $singleTicket['device_model']) ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small text-uppercase fw-semibold">Current Status</div>
                    <div><?= getStatusBadge($singleTicket['status']) ?></div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="text-muted small text-uppercase fw-semibold">Priority</div>
                    <div><?= getPriorityBadge($singleTicket['priority']) ?></div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="text-muted small text-uppercase fw-semibold">Customer & Technician</div>
                    <div class="fw-bold"><?= htmlspecialchars($singleTicket['customer_name'] ?? 'Walk-in') ?></div>
                    <div class="text-muted small"><i class="fas fa-user-gear me-1"></i><?= htmlspecialchars($singleTicket['tech_name'] ?? 'Unassigned') ?></div>
                </div>
                <div class="col-12 col-md-2 text-md-end">
                    <div class="text-muted small text-uppercase fw-semibold">Est. Cost</div>
                    <div class="fs-5 fw-bold text-success">₹<?= number_format((float)$singleTicket['estimated_cost'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="history.php" class="row g-3 align-items-center">
                <?php if ($ticketId): ?>
                    <input type="hidden" name="id" value="<?= $ticketId ?>">
                <?php endif; ?>

                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Search ticket #, description..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <select name="action_type" class="form-select">
                        <option value="">All Action Types</option>
                        <option value="created" <?= $actionType === 'created' ? 'selected' : '' ?>>Ticket Created</option>
                        <option value="status_change" <?= $actionType === 'status_change' ? 'selected' : '' ?>>Status Change</option>
                        <option value="priority_change" <?= $actionType === 'priority_change' ? 'selected' : '' ?>>Priority Update</option>
                        <option value="assigned" <?= $actionType === 'assigned' ? 'selected' : '' ?>>Technician Assignment</option>
                        <option value="cost_update" <?= $actionType === 'cost_update' ? 'selected' : '' ?>>Cost Update</option>
                        <option value="device_update" <?= $actionType === 'device_update' ? 'selected' : '' ?>>Device Update</option>
                        <option value="note_added" <?= $actionType === 'note_added' ? 'selected' : '' ?>>Note Added</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <select name="user_id" class="form-select">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>" title="From Date">
                </div>

                <div class="col-6 col-md-2">
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>" title="To Date">
                </div>

                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill" title="Filter Records">
                        <i class="fas fa-filter"></i>
                    </button>
                    <?php if ($search !== '' || $actionType !== '' || $userId !== null || $dateFrom !== '' || $dateTo !== ''): ?>
                        <a href="history.php<?= $ticketId ? '?id=' . $ticketId : '' ?>" class="btn btn-outline-danger" title="Clear Filters">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit History Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <i class="fas fa-list text-primary me-2"></i> Audit Records
                <span class="badge bg-light text-dark border ms-2"><?= number_format($totalRecords) ?> total</span>
            </h5>
            <?php if ($totalRecords > 0): ?>
                <span class="text-muted small">
                    Showing <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $perPage, $totalRecords)) ?> of <?= number_format($totalRecords) ?>
                </span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 170px;">Date & Time</th>
                            <th style="width: 140px;">Ticket #</th>
                            <th style="width: 150px;">Action</th>
                            <th style="width: 120px;">Field</th>
                            <th>Change Details</th>
                            <th style="width: 180px;">Changed By</th>
                            <th style="width: 80px;" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historyRecords)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-clipboard-check fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">No audit history records found matching your filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historyRecords as $row): 
                                $badgeClass = 'bg-primary';
                                $badgeIcon = 'fa-sync-alt';
                                $actionLabel = ucwords(str_replace('_', ' ', $row['action_type']));

                                switch ($row['action_type']) {
                                    case 'created':
                                        $badgeClass = 'bg-success';
                                        $badgeIcon = 'fa-plus-circle';
                                        $actionLabel = 'Created';
                                        break;
                                    case 'status_change':
                                        $badgeClass = 'bg-primary';
                                        $badgeIcon = 'fa-arrows-rotate';
                                        $actionLabel = 'Status Change';
                                        break;
                                    case 'priority_change':
                                        $badgeClass = 'bg-warning text-dark';
                                        $badgeIcon = 'fa-flag';
                                        $actionLabel = 'Priority';
                                        break;
                                    case 'assigned':
                                        $badgeClass = 'bg-info text-dark';
                                        $badgeIcon = 'fa-user-tag';
                                        $actionLabel = 'Assigned';
                                        break;
                                    case 'cost_update':
                                        $badgeClass = 'bg-purple text-white';
                                        $badgeIcon = 'fa-coins';
                                        $actionLabel = 'Cost Update';
                                        break;
                                    case 'device_update':
                                        $badgeClass = 'bg-dark';
                                        $badgeIcon = 'fa-laptop';
                                        $actionLabel = 'Device Info';
                                        break;
                                    case 'note_added':
                                        $badgeClass = 'bg-secondary';
                                        $badgeIcon = 'fa-comment-dots';
                                        $actionLabel = 'Note Added';
                                        break;
                                }
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold small"><?= date('M d, Y', strtotime($row['created_at'])) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?= date('h:i:s A', strtotime($row['created_at'])) ?> (<?= timeAgo($row['created_at']) ?>)</div>
                                    </td>
                                    <td>
                                        <a href="<?= APP_URL ?>/tickets/view.php?id=<?= $row['ticket_id'] ?>" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($row['ticket_number']) ?>
                                        </a>
                                        <?php if (!empty($row['device_brand'])): ?>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($row['device_brand'] . ' ' . $row['device_model']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?> font-monospace">
                                            <i class="fas <?= $badgeIcon ?> me-1"></i><?= $actionLabel ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['field_name'] && $row['field_name'] !== 'all'): ?>
                                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['field_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small mb-1"><?= htmlspecialchars($row['change_description']) ?></div>
                                        <?php if ($row['old_value'] !== null && $row['new_value'] !== null && $row['old_value'] !== $row['new_value']): ?>
                                            <div class="small text-muted" style="font-size: 0.78rem;">
                                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($row['old_value'] ?: 'None') ?></span>
                                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($row['new_value']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-semibold small">
                                            <i class="fas fa-user-circle me-1 text-muted"></i>
                                            <?= htmlspecialchars($row['user_name'] ?? 'System') ?>
                                        </div>
                                        <?php if (!empty($row['user_role'])): ?>
                                            <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.7rem;"><?= htmlspecialchars($row['user_role']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= APP_URL ?>/tickets/view.php?id=<?= $row['ticket_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Ticket">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer bg-white border-top py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        if ($startPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a></li>
                            <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>"><?= $totalPages ?></a></li>
                        <?php endif; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

  </div>
</div>

<?php include '../includes/footer.php'; ?>
