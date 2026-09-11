<?php
session_start();
require_once '../config.php';
requireRole(['Admin', 'Receptionist']);

// Current week start and end
$weekOffset = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;
$startOfWeek = new DateTime();
$startOfWeek->setISODate((int)$startOfWeek->format('o'), (int)$startOfWeek->format('W') + $weekOffset);
$endOfWeek = clone $startOfWeek;
$endOfWeek->modify('+6 days');

$daysOfWeek = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $startOfWeek;
    $d->modify("+$i days");
    $daysOfWeek[] = [
        'date' => $d->format('Y-m-d'),
        'day' => $d->format('l'),
        'day_num' => (int)$d->format('w') // 0 = Sunday, 6 = Saturday
    ];
}

// Fetch Technicians
$techStmt = $pdo->query("SELECT id, name FROM users WHERE role = 'Technician' AND is_active = 1");
$technicians = $techStmt->fetchAll();

// Prepare grid
$grid = [];
foreach ($technicians as $tech) {
    $grid[$tech['id']] = [
        'name' => $tech['name'],
        'days' => []
    ];
    
    // Fetch base schedule
    $schedStmt = $pdo->prepare("SELECT day_of_week, is_working, start_time, end_time, max_jobs FROM worker_schedule WHERE user_id = ?");
    $schedStmt->execute([$tech['id']]);
    $schedule = [];
    while ($row = $schedStmt->fetch()) {
        $schedule[$row['day_of_week']] = $row;
    }
    
    // Fetch overrides (availability/leave) for this week
    $availStmt = $pdo->prepare("SELECT available_date, is_leave, notes FROM worker_availability WHERE user_id = ? AND available_date BETWEEN ? AND ?");
    $availStmt->execute([$tech['id'], $startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
    $overrides = [];
    while ($row = $availStmt->fetch()) {
        $overrides[$row['available_date']] = $row;
    }
    
    // Fetch ticket counts for this week
    $tickStmt = $pdo->prepare("SELECT DATE(created_at) as c_date, COUNT(*) as cnt FROM repair_tickets WHERE assigned_to = ? AND DATE(created_at) BETWEEN ? AND ? AND status NOT IN ('Completed', 'Delivered', 'Cancelled') GROUP BY DATE(created_at)");
    $tickStmt->execute([$tech['id'], $startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
    $tickets = [];
    while ($row = $tickStmt->fetch()) {
        $tickets[$row['c_date']] = $row['cnt'];
    }
    
    foreach ($daysOfWeek as $d) {
        $dateStr = $d['date'];
        $dayNum = $d['day_num'];
        
        $isWorking = true;
        $status = 'Available';
        $badgeClass = 'bg-success';
        $maxJobs = 0;
        
        // Check base schedule
        if (isset($schedule[$dayNum])) {
            if (!$schedule[$dayNum]['is_working']) {
                $isWorking = false;
                $status = 'Off';
                $badgeClass = 'bg-secondary';
            }
            $maxJobs = $schedule[$dayNum]['max_jobs'] ?? 0;
        }
        
        // Check overrides
        if (isset($overrides[$dateStr])) {
            if ($overrides[$dateStr]['is_leave']) {
                $isWorking = false;
                $status = 'On Leave';
                $badgeClass = 'bg-danger';
            }
        }
        
        $assigned = $tickets[$dateStr] ?? 0;
        
        $grid[$tech['id']]['days'][$dateStr] = [
            'is_working' => $isWorking,
            'status' => $status,
            'badge_class' => $badgeClass,
            'assigned' => $assigned,
            'max_jobs' => $maxJobs
        ];
    }
}

$pageTitle = 'Worker Schedule';
include '../includes/header.php';
include '../includes/sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Worker Schedule</h2>
        <div class="d-flex align-items-center gap-3">
            <a href="?week_offset=<?= $weekOffset - 1 ?>" class="btn btn-outline-secondary"><i class="fas fa-chevron-left"></i> Prev Week</a>
            <h5 class="mb-0 mx-2"><?= $startOfWeek->format('M d') ?> - <?= $endOfWeek->format('M d, Y') ?></h5>
            <a href="?week_offset=<?= $weekOffset + 1 ?>" class="btn btn-outline-secondary">Next Week <i class="fas fa-chevron-right"></i></a>
            <?php if ($weekOffset !== 0): ?>
                <a href="?week_offset=0" class="btn btn-primary">Current Week</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start" width="16%">Technician</th>
                            <?php foreach ($daysOfWeek as $d): ?>
                            <th width="12%">
                                <div><?= $d['day'] ?></div>
                                <div class="small text-muted"><?= date('M d', strtotime($d['date'])) ?></div>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($technicians)): ?>
                        <tr><td colspan="8" class="text-center py-4">No active technicians found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($grid as $techId => $data): ?>
                            <tr>
                                <td class="text-start fw-bold"><?= htmlspecialchars($data['name']) ?></td>
                                <?php foreach ($daysOfWeek as $d): 
                                    $cell = $data['days'][$d['date']];
                                ?>
                                <td>
                                    <span class="badge <?= $cell['badge_class'] ?> mb-2 d-block"><?= $cell['status'] ?></span>
                                    <?php if ($cell['is_working']): ?>
                                        <div class="small">
                                            Assigned: <strong><?= $cell['assigned'] ?></strong>
                                            <?php if ($cell['max_jobs'] > 0): ?>
                                                / <?= $cell['max_jobs'] ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
