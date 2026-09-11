<?php
session_start();
require_once '../config.php';
requireWorker();

$userId = $_SESSION['user_id'];
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch ticket
if (function_exists('isAdmin') && isAdmin()) {
    $stmt = $pdo->prepare("SELECT * FROM repair_tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM repair_tickets WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$ticketId, $userId]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    $_SESSION['flash_message'] = "Task not found or access denied.";
    $_SESSION['flash_type'] = "danger";
    header("Location: my_tasks.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCSRFToken();
    
    $completionSummary = $_POST['completion_summary'];
    $partsUsed = $_POST['parts_used_notes'] ?? '';
    $finalCost = (float)$_POST['final_cost'];
    $timeSpent = (int)$_POST['time_spent_total'];
    
    try {
        $pdo->beginTransaction();
        
        // Update ticket
        $upStmt = $pdo->prepare("
            UPDATE repair_tickets 
            SET status = 'Ready for Pickup', 
                completion_summary = ?, 
                parts_used_notes = ?, 
                final_cost = ?, 
                time_spent_total = ?, 
                completed_at = NOW() 
            WHERE id = ?
        ");
        $upStmt->execute([$completionSummary, $partsUsed, $finalCost, $timeSpent, $ticketId]);
        
        // Insert history
        $histStmt = $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, user_id, changed_by, status, old_status, new_status, created_at) VALUES (?, ?, ?, 'Ready for Pickup', ?, 'Ready for Pickup', NOW())");
        $histStmt->execute([$ticketId, $userId, $userId, $ticket['status']]);

        // Log to comprehensive ticket history
        $summarySnippet = strlen($completionSummary) > 100 ? substr($completionSummary, 0, 100) . '...' : $completionSummary;
        logTicketHistory(
            $pdo,
            $ticketId,
            $ticket['ticket_id'],
            'status_change',
            'status',
            $ticket['status'],
            'Ready for Pickup',
            "Technician submitted completion report: {$summarySnippet}. Final Cost: " . CURRENCY_SYMBOL . number_format($finalCost, 2),
            $userId,
            $_SESSION['user_name'] ?? 'Technician',
            $_SESSION['user_role'] ?? 'Technician'
        );

        if ($finalCost > 0) {
            $oldCostStr = CURRENCY_SYMBOL . number_format((float)($ticket['final_cost'] ?: $ticket['estimated_cost'] ?: 0), 2);
            $newCostStr = CURRENCY_SYMBOL . number_format($finalCost, 2);
            logTicketHistory(
                $pdo,
                $ticketId,
                $ticket['ticket_id'],
                'cost_update',
                'final_cost',
                $oldCostStr,
                $newCostStr,
                "Final repair cost recorded as {$newCostStr}",
                $userId,
                $_SESSION['user_name'] ?? 'Technician',
                $_SESSION['user_role'] ?? 'Technician'
            );
        }
        
        // Insert progress
        $progStmt = $pdo->prepare("INSERT INTO service_progress (ticket_id, user_id, status_update, description, created_at) VALUES (?, ?, 'Ready for Pickup', ?, NOW())");
        $progStmt->execute([$ticketId, $userId, "Repairs completed. " . $completionSummary]);
        
        // Handle images
        if (isset($_FILES['after_repair_photos'])) {
            $files = $_FILES['after_repair_photos'];
            $uploadDir = '../uploads/devices/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $fileName = 'after_' . $ticketId . '_' . time() . '_' . $i . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $uploadDir . $fileName)) {
                        $imagePath = 'uploads/devices/' . $fileName;
                        // For simplicity, we just add another progress entry for each image, 
                        // or in a more robust app, a separate attachments table.
                        $imgProgStmt = $pdo->prepare("INSERT INTO service_progress (ticket_id, user_id, status_update, description, image_path, created_at) VALUES (?, ?, 'Photo Upload', 'After repair photo', ?, NOW())");
                        $imgProgStmt->execute([$ticketId, $userId, $imagePath]);
                    }
                }
            }
        }
        
        logActivity($pdo, $userId, "Submitted completion report for ticket #{$ticket['ticket_id']}");
        
        $pdo->commit();
        $_SESSION['flash_message'] = "Completion report submitted successfully. Task is now Ready for Pickup.";
        $_SESSION['flash_type'] = "success";
        header("Location: task_detail.php?id=$ticketId");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_message'] = "Error submitting report: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

$pageTitle = 'Completion Report - ' . htmlspecialchars($ticket['ticket_id']);
include '../includes/worker_header.php';
include '../includes/worker_sidebar.php';
?>
<div class="main-content" id="mainContent">
  <div class="container-fluid py-4">
    <?php displayFlashMessage(); ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Completion Report</h2>
        <a href="task_detail.php?id=<?= $ticketId ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Task <?= htmlspecialchars($ticket['ticket_id']) ?></h5>
            <p class="text-muted">Fill out this report to mark the repair as finished and ready for pickup.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Completion Summary / Final Notes <span class="text-danger">*</span></label>
                    <textarea name="completion_summary" class="form-control" rows="4" required placeholder="Describe the final resolution and any notes for the customer..."><?= htmlspecialchars($ticket['completion_summary'] ?? '') ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Parts Used (Notes)</label>
                    <textarea name="parts_used_notes" class="form-control" rows="3" placeholder="List parts used during repair..."><?= htmlspecialchars($ticket['parts_used_notes'] ?? '') ?></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Total Time Spent (Minutes)</label>
                        <input type="number" name="time_spent_total" class="form-control" min="0" value="<?= htmlspecialchars($ticket['time_spent_total'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Final Cost (₹)</label>
                        <input type="number" step="0.01" name="final_cost" class="form-control" value="<?= htmlspecialchars($ticket['final_cost'] ?? $ticket['estimated_cost']) ?>">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">After Repair Photos</label>
                    <input type="file" name="after_repair_photos[]" class="form-control" accept="image/*" multiple>
                    <div class="form-text">You can upload multiple photos holding Ctrl/Cmd.</div>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-1"></i> Submit & Mark Ready</button>
                </div>
            </form>
        </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>
