<?php
session_start();
require_once '../config.php';
requireWorker();

$userId = $_SESSION['user_id'];
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch ticket
if (function_exists('isAdmin') && isAdmin()) {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email 
        FROM repair_tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$ticketId]);
} else {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email 
        FROM repair_tickets t
        LEFT JOIN customers c ON t.customer_id = c.id
        WHERE t.id = ? AND t.assigned_to = ?
    ");
    $stmt->execute([$ticketId, $userId]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_message'] = "Task not found or access denied.";
    $_SESSION['flash_type'] = "danger";
    header("Location: my_tasks.php");
    exit;
}

// Handle Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verifyCSRFToken();
    $newStatus = $_POST['status'];
    $validStatuses = ['In Progress', 'Waiting for Parts', 'Ready for Pickup', 'Completed'];
    
    if (in_array($newStatus, $validStatuses)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE repair_tickets SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $ticketId]);
            
            // Insert status history
            $histStmt = $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, changed_by, status, old_status, new_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $histStmt->execute([$ticketId, $userId, $userId, $newStatus, $ticket['status'], $newStatus]);
            
            // Log to comprehensive ticket history
            logTicketHistory(
                $pdo,
                $ticketId,
                $ticket['ticket_id'],
                'status_change',
                'status',
                $ticket['status'],
                $newStatus,
                "Technician changed status from '{$ticket['status']}' to '{$newStatus}'",
                $userId,
                $_SESSION['user_name'] ?? 'Technician',
                $_SESSION['user_role'] ?? 'Technician'
            );
            
            logActivity($pdo, $userId, "Updated ticket #{$ticket['ticket_id']} status to $newStatus");
            
            $pdo->commit();
            $_SESSION['flash_message'] = "Status updated successfully.";
            $_SESSION['flash_type'] = "success";
            header("Location: task_detail.php?id=$ticketId");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = "Error updating status: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Handle Progress Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_progress'])) {
    verifyCSRFToken();
    $statusUpdate = $_POST['status_update'];
    $description = $_POST['description'];
    $timeSpent = (int)$_POST['time_spent_minutes'];
    
    $imagePath = null;
    if (isset($_FILES['progress_image']) && $_FILES['progress_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/devices/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = pathinfo($_FILES['progress_image']['name'], PATHINFO_EXTENSION);
        $fileName = 'prog_' . $ticketId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['progress_image']['tmp_name'], $uploadDir . $fileName)) {
            $imagePath = 'uploads/devices/' . $fileName;
        }
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO service_progress (ticket_id, user_id, status_update, description, image_path, time_spent_minutes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$ticketId, $userId, $statusUpdate, $description, $imagePath, $timeSpent]);
        
        $pDesc = "Technician progress update: " . (strlen($description) > 80 ? substr($description, 0, 80) . '...' : $description) . " (Status: $statusUpdate, Time: {$timeSpent}m)";
        logTicketHistory($pdo, $ticketId, $ticket['ticket_id'], 'note_added', 'progress', null, null, $pDesc, $userId, $_SESSION['user_name'] ?? 'Technician', $_SESSION['user_role'] ?? 'Technician');
        logActivity($pdo, $userId, "Added progress update to ticket #{$ticket['ticket_id']}");
        
        $_SESSION['flash_message'] = "Progress updated successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: task_detail.php?id=$ticketId");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "Error adding progress: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

// Handle Notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    verifyCSRFToken();
    $note = trim($_POST['note']);
    if (!empty($note)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO ticket_notes (ticket_id, user_id, note, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$ticketId, $userId, $note]);
            
            $nDesc = "Technician added note: " . (strlen($note) > 80 ? substr($note, 0, 80) . '...' : $note);
            logTicketHistory($pdo, $ticketId, $ticket['ticket_id'], 'note_added', 'notes', null, null, $nDesc, $userId, $_SESSION['user_name'] ?? 'Technician', $_SESSION['user_role'] ?? 'Technician');

            $_SESSION['flash_message'] = "Note added successfully.";
            $_SESSION['flash_type'] = "success";
            header("Location: task_detail.php?id=$ticketId");
            exit;
        } catch (Exception $e) {
            $_SESSION['flash_message'] = "Error adding note: " . $e->getMessage();
            $_SESSION['flash_type'] = "danger";
        }
    }
}

// Update Final Cost
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cost'])) {
    verifyCSRFToken();
    $finalCost = (float)$_POST['final_cost'];
    try {
        $stmt = $pdo->prepare("UPDATE repair_tickets SET final_cost = ? WHERE id = ?");
        $stmt->execute([$finalCost, $ticketId]);
        
        $oldCost = CURRENCY_SYMBOL . number_format((float)($ticket['final_cost'] ?? 0), 2);
        $newCost = CURRENCY_SYMBOL . number_format($finalCost, 2);
        logTicketHistory($pdo, $ticketId, $ticket['ticket_id'], 'cost_update', 'final_cost', $oldCost, $newCost, "Final cost updated to {$newCost}", $userId, $_SESSION['user_name'] ?? 'Technician', $_SESSION['user_role'] ?? 'Technician');

        $_SESSION['flash_message'] = "Cost updated successfully.";
        $_SESSION['flash_type'] = "success";
        header("Location: task_detail.php?id=$ticketId");
        exit;
    } catch (Exception $e) {
        $_SESSION['flash_message'] = "Error updating cost.";
        $_SESSION['flash_type'] = "danger";
    }
}

// Fetch Progress
$progStmt = $pdo->prepare("SELECT sp.*, u.name as user_name FROM service_progress sp LEFT JOIN users u ON sp.user_id = u.id WHERE sp.ticket_id = ? ORDER BY sp.created_at DESC");
$progStmt->execute([$ticketId]);
$progressList = $progStmt->fetchAll();

// Fetch Notes
$noteStmt = $pdo->prepare("SELECT tn.*, u.name as user_name FROM ticket_notes tn LEFT JOIN users u ON tn.user_id = u.id WHERE tn.ticket_id = ? ORDER BY tn.created_at DESC");
$noteStmt->execute([$ticketId]);
$notes = $noteStmt->fetchAll();

$pageTitle = 'Task Detail - ' . htmlspecialchars($ticket['ticket_id']);
include '../includes/worker_header.php';
include '../includes/worker_sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <?php displayFlashMessage(); ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Task <?= htmlspecialchars($ticket['ticket_id']) ?></h2>
        <div>
            <?php if ($ticket['status'] !== 'Completed' && $ticket['status'] !== 'Delivered' && $ticket['status'] !== 'Cancelled'): ?>
                <a href="completion_report.php?id=<?= $ticketId ?>" class="btn btn-success">Submit Completion Report</a>
            <?php endif; ?>
            <a href="my_tasks.php" class="btn btn-outline-secondary">Back to Tasks</a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8 mb-4">
            <!-- Device Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Device Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-2"><strong>Type:</strong> <?= htmlspecialchars($ticket['device_type'] ?? 'N/A') ?></div>
                        <div class="col-sm-6 mb-2"><strong>Brand:</strong> <?= htmlspecialchars($ticket['device_brand'] ?? 'N/A') ?></div>
                        <div class="col-sm-6 mb-2"><strong>Model:</strong> <?= htmlspecialchars($ticket['device_model'] ?? 'N/A') ?></div>
                        <div class="col-sm-6 mb-2"><strong>Serial Number:</strong> <?= htmlspecialchars($ticket['serial_number'] ?? 'N/A') ?></div>
                        <div class="col-sm-12"><strong>Condition:</strong> <?= nl2br(htmlspecialchars($ticket['device_condition'] ?? 'N/A')) ?></div>
                    </div>
                </div>
            </div>

            <!-- Problem Description -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Problem Description</h5>
                </div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($ticket['problem_description'] ?? 'No description provided.')) ?>
                </div>
            </div>

            <!-- Service Progress -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Service Progress</h5>
                </div>
                <div class="card-body">
                    <!-- Add Progress Form -->
                    <form method="POST" enctype="multipart/form-data" class="mb-4 bg-light p-3 rounded">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_progress" value="1">
                        <h6>Add Progress Update</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <select name="status_update" class="form-select" required>
                                    <option value="">Select Update Type</option>
                                    <?php if (defined('PROGRESS_TYPES')): ?>
                                        <?php foreach (PROGRESS_TYPES as $type): ?>
                                            <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="Diagnostics Started">Diagnostics Started</option>
                                        <option value="Issue Identified">Issue Identified</option>
                                        <option value="Repairing">Repairing</option>
                                        <option value="Parts Ordered">Parts Ordered</option>
                                        <option value="Testing">Testing</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="number" name="time_spent_minutes" class="form-control" placeholder="Time Spent (minutes)" min="0">
                            </div>
                            <div class="col-12">
                                <textarea name="description" class="form-control" rows="2" placeholder="Description of work done" required></textarea>
                            </div>
                            <div class="col-12">
                                <input type="file" name="progress_image" class="form-control" accept="image/*">
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary btn-sm">Add Update</button>
                            </div>
                        </div>
                    </form>

                    <!-- Timeline -->
                    <div class="progress-timeline ms-3" style="border-left: 2px solid #e9ecef; padding-left: 1rem;">
                        <?php if (count($progressList) > 0): ?>
                            <?php foreach ($progressList as $prog): ?>
                                <div class="progress-entry mb-3 position-relative">
                                    <div class="progress-dot position-absolute bg-primary rounded-circle" style="width: 10px; height: 10px; left: -1.4rem; top: 0.4rem;"></div>
                                    <div class="progress-header">
                                        <strong class="progress-status"><?= htmlspecialchars($prog['status_update']) ?></strong>
                                        <span class="progress-time text-muted small ms-2"><?= date('M d, Y h:i A', strtotime($prog['created_at'])) ?></span>
                                    </div>
                                    <div class="progress-desc mt-1">
                                        <?= nl2br(htmlspecialchars($prog['description'])) ?>
                                        <?php if ($prog['time_spent_minutes']): ?>
                                            <div class="text-muted small">Time spent: <?= $prog['time_spent_minutes'] ?> mins</div>
                                        <?php endif; ?>
                                        <?php if ($prog['image_path']): ?>
                                            <div class="mt-2">
                                                <a href="../<?= htmlspecialchars($prog['image_path']) ?>" target="_blank">
                                                    <img src="../<?= htmlspecialchars($prog['image_path']) ?>" class="img-thumbnail" style="max-height: 100px;">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No progress updates yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Notes</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="add_note" value="1">
                        <div class="input-group">
                            <input type="text" name="note" class="form-control" placeholder="Add a note..." required>
                            <button type="submit" class="btn btn-primary">Add</button>
                        </div>
                    </form>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($notes as $note): ?>
                            <li class="list-group-item px-0">
                                <strong><?= htmlspecialchars($note['user_name']) ?></strong> 
                                <span class="text-muted small ms-2"><?= date('M d, g:i a', strtotime($note['created_at'])) ?></span>
                                <div><?= nl2br(htmlspecialchars($note['note'])) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4 mb-4">
            <!-- Status Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Task Status</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?= getStatusBadge($ticket['status'] ?? 'Pending') ?>
                    </div>
                    <?php if (!in_array($ticket['status'], ['Delivered', 'Cancelled'])): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="update_status" value="1">
                        <div class="input-group">
                            <select name="status" class="form-select">
                                <option value="In Progress" <?= $ticket['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Waiting for Parts" <?= $ticket['status'] === 'Waiting for Parts' ? 'selected' : '' ?>>Waiting for Parts</option>
                                <option value="Ready for Pickup" <?= $ticket['status'] === 'Ready for Pickup' ? 'selected' : '' ?>>Ready for Pickup</option>
                                <option value="Completed" <?= $ticket['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary">Update</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Customer Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Customer Info</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($ticket['customer_name'] ?? 'N/A') ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($ticket['customer_phone'] ?? '') ?>"><?= htmlspecialchars($ticket['customer_phone'] ?? 'N/A') ?></a></p>
                    <p class="mb-0"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($ticket['customer_email'] ?? '') ?>"><?= htmlspecialchars($ticket['customer_email'] ?? 'N/A') ?></a></p>
                </div>
            </div>

            <!-- Priority and Dates -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Details</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Priority:</strong> <?= getPriorityBadge($ticket['priority'] ?? 'Normal') ?></p>
                    <p class="mb-1"><strong>Created:</strong> <?= date('M d, Y', strtotime($ticket['created_at'])) ?></p>
                    <p class="mb-0"><strong>Due Date:</strong> <?= $ticket['due_date'] ? date('M d, Y', strtotime($ticket['due_date'])) : 'Not Set' ?></p>
                </div>
            </div>

            <!-- Cost Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Cost</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Estimated:</strong> ₹<?= number_format($ticket['estimated_cost'] ?? 0, 2) ?></p>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="update_cost" value="1">
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" name="final_cost" class="form-control" placeholder="Final Cost" value="<?= htmlspecialchars($ticket['final_cost'] ?? '') ?>">
                            <button type="submit" class="btn btn-outline-success">Save</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
