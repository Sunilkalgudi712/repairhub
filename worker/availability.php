<?php
session_start();
require_once '../config.php';
requireWorker();

$userId = $_SESSION['user_id'];
if (function_exists('isAdmin') && isAdmin()) {
    $techStmt = $pdo->query("SELECT id FROM users WHERE role = 'Technician' LIMIT 1");
    $firstTech = $techStmt->fetchColumn();
    if ($firstTech) {
        $userId = $firstTech;
    }
}

// Get current week date range
$weekStart = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' + 6 days'));
$prevWeek = date('Y-m-d', strtotime($weekStart . ' - 7 days'));
$nextWeek = date('Y-m-d', strtotime($weekStart . ' + 7 days'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    try {
        $pdo->beginTransaction();
        
        $dates = $_POST['dates']; // array of dates
        foreach ($dates as $date) {
            $morning = isset($_POST['morning'][$date]) ? 1 : 0;
            $afternoon = isset($_POST['afternoon'][$date]) ? 1 : 0;
            $leave = isset($_POST['leave'][$date]) ? 1 : 0;
            $notes = $_POST['notes'][$date] ?? '';
            
            $stmt = $pdo->prepare("
                INSERT INTO worker_availability (user_id, available_date, slot_morning, slot_afternoon, is_leave, notes)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                slot_morning = VALUES(slot_morning),
                slot_afternoon = VALUES(slot_afternoon),
                is_leave = VALUES(is_leave),
                notes = VALUES(notes)
            ");
            $stmt->execute([$userId, $date, $morning, $afternoon, $leave, $notes]);
        }
        
        $pdo->commit();
        $_SESSION['flash_message'] = "Availability updated successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: availability.php?date=$weekStart");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Error updating availability: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

// Fetch availability for current week
$availStmt = $pdo->prepare("SELECT * FROM worker_availability WHERE user_id = ? AND available_date BETWEEN ? AND ?");
$availStmt->execute([$userId, $weekStart, $weekEnd]);
$availability = [];
while ($row = $availStmt->fetch()) {
    $availability[$row['available_date']] = $row;
}

// Fetch base schedule
$schedStmt = $pdo->prepare("SELECT * FROM worker_schedule WHERE user_id = ?");
$schedStmt->execute([$userId]);
$schedule = [];
while ($row = $schedStmt->fetch()) {
    $schedule[$row['day_of_week']] = $row;
}

// Fetch assigned tickets count per day
$ticketStmt = $pdo->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM repair_tickets 
    WHERE assigned_to = ? AND status NOT IN ('Completed', 'Delivered', 'Cancelled') 
    AND DATE(created_at) BETWEEN ? AND ? 
    GROUP BY DATE(created_at)
");
$ticketStmt->execute([$userId, $weekStart, $weekEnd]);
$ticketCounts = [];
while ($row = $ticketStmt->fetch()) {
    $ticketCounts[$row['date']] = $row['count'];
}

$pageTitle = 'My Availability';
include '../includes/worker_header.php';
include '../includes/worker_sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <?php displayFlashMessage(); ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Availability</h2>
        <div class="btn-group">
            <a href="availability.php?date=<?= $prevWeek ?>" class="btn btn-outline-secondary"><i class="fas fa-chevron-left"></i> Prev Week</a>
            <span class="btn btn-light disabled text-dark border-secondary">
                <?= date('M d', strtotime($weekStart)) ?> - <?= date('M d, Y', strtotime($weekEnd)) ?>
            </span>
            <a href="availability.php?date=<?= $nextWeek ?>" class="btn btn-outline-secondary">Next Week <i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="availability-grid row g-3">
                    <?php
                    $currentDate = $weekStart;
                    for ($i = 0; $i < 7; $i++):
                        $dayOfWeek = date('w', strtotime($currentDate)); // 0 = Sunday
                        
                        // Defaults from schedule
                        $isWorkingDefault = isset($schedule[$dayOfWeek]) ? $schedule[$dayOfWeek]['is_working'] : 1;
                        
                        // Overrides from availability
                        $dayAvail = $availability[$currentDate] ?? null;
                        
                        $isLeave = $dayAvail ? $dayAvail['is_leave'] : !$isWorkingDefault;
                        $morning = $dayAvail ? $dayAvail['slot_morning'] : ($isLeave ? 0 : 1);
                        $afternoon = $dayAvail ? $dayAvail['slot_afternoon'] : ($isLeave ? 0 : 1);
                        $notes = $dayAvail ? $dayAvail['notes'] : '';
                        
                        $taskCount = $ticketCounts[$currentDate] ?? 0;
                    ?>
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="card h-100 avail-day <?= $isLeave ? 'bg-light' : 'border-primary' ?>">
                            <div class="card-header <?= $isLeave ? 'bg-secondary text-white' : 'bg-primary text-white' ?> d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><?= date('l', strtotime($currentDate)) ?></h6>
                                <small><?= date('M d', strtotime($currentDate)) ?></small>
                            </div>
                            <div class="card-body">
                                <input type="hidden" name="dates[]" value="<?= $currentDate ?>">
                                
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input leave-toggle" type="checkbox" name="leave[<?= $currentDate ?>]" value="1" id="leave_<?= $currentDate ?>" <?= $isLeave ? 'checked' : '' ?> onchange="toggleDay(this, '<?= $currentDate ?>')">
                                    <label class="form-check-label text-danger" for="leave_<?= $currentDate ?>">On Leave / Unavailable</label>
                                </div>
                                
                                <hr>
                                
                                <div class="slot-container" id="slots_<?= $currentDate ?>">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="morning[<?= $currentDate ?>]" value="1" id="morn_<?= $currentDate ?>" <?= $morning ? 'checked' : '' ?> <?= $isLeave ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="morn_<?= $currentDate ?>">Morning Slot</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="afternoon[<?= $currentDate ?>]" value="1" id="aft_<?= $currentDate ?>" <?= $afternoon ? 'checked' : '' ?> <?= $isLeave ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="aft_<?= $currentDate ?>">Afternoon Slot</label>
                                    </div>
                                    
                                    <input type="text" name="notes[<?= $currentDate ?>]" class="form-control form-control-sm" placeholder="Notes (e.g. late by 1hr)" value="<?= htmlspecialchars($notes) ?>">
                                    
                                    <?php if ($taskCount > 0): ?>
                                    <div class="mt-3">
                                        <span class="badge bg-warning text-dark"><i class="fas fa-tasks"></i> <?= $taskCount ?> active task(s)</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                        $currentDate = date('Y-m-d', strtotime($currentDate . ' + 1 day'));
                    endfor;
                    ?>
                </div>
                
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Availability</button>
                </div>
            </form>
        </div>
    </div>
  </div>
</div>

<script>
function toggleDay(checkbox, dateStr) {
    const isLeave = checkbox.checked;
    const slots = document.getElementById('slots_' + dateStr);
    const inputs = slots.querySelectorAll('input[type="checkbox"]');
    const card = checkbox.closest('.card');
    const header = card.querySelector('.card-header');
    
    if (isLeave) {
        inputs.forEach(input => {
            input.checked = false;
            input.disabled = true;
        });
        card.classList.remove('border-primary');
        card.classList.add('bg-light');
        header.classList.remove('bg-primary');
        header.classList.add('bg-secondary');
    } else {
        inputs.forEach(input => {
            input.disabled = false;
        });
        card.classList.add('border-primary');
        card.classList.remove('bg-light');
        header.classList.add('bg-primary');
        header.classList.remove('bg-secondary');
    }
}
</script>
<?php include '../includes/footer.php'; ?>
