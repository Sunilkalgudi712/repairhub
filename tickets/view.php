<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . APP_URL . "/auth/login.php");
    exit;
}

if (!function_exists('csrfField')) {
    function csrfField() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token']) . '">';
    }
}
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch ticket
$sql = "SELECT t.*, c.name, c.phone, c.email, u.name as assignee_name 
        FROM repair_tickets t 
        LEFT JOIN customers c ON t.customer_id = c.id 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    header("Location: index.php");
    exit;
}

// Handle Status Change POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid token.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $new_status = $_POST['status'];
        $old_status = $ticket['status'];
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE repair_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            
            $note = "Status changed from {$old_status} to {$new_status}";
            $stmt = $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, status, user_id, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$id, $new_status, $_SESSION['user_id'], $note]);

            // Log detailed ticket history
            logTicketHistory($pdo, $id, $ticket['ticket_id'], 'status_change', 'status', $old_status, $new_status, $note);
            
            $pdo->commit();
            $_SESSION['flash_message'] = 'Status updated successfully.';
            $_SESSION['flash_type'] = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['flash_message'] = 'Error updating status: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }
        header("Location: view.php?id=$id");
        exit;
    }
}

// Handle Add Note POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_note') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Invalid token.';
        $_SESSION['flash_type'] = 'danger';
    } else {
        $note_text = trim($_POST['note_text']);
        if (!empty($note_text)) {
            $stmt = $pdo->prepare("INSERT INTO ticket_notes (ticket_id, user_id, note) VALUES (?, ?, ?)");
            $stmt->execute([$id, $_SESSION['user_id'], $note_text]);

            // Log ticket history
            $nDesc = "Added note: " . (strlen($note_text) > 80 ? substr($note_text, 0, 80) . '...' : $note_text);
            logTicketHistory($pdo, $id, $ticket['ticket_id'], 'note_added', 'notes', null, null, $nDesc);

            $_SESSION['flash_message'] = 'Note added successfully.';
            $_SESSION['flash_type'] = 'success';
        }
        header("Location: view.php?id=$id");
        exit;
    }
}

// Fetch complete ticket history
$stmt = $pdo->prepare("SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$id]);
$ticketHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($ticketHistory) && !empty($ticket)) {
    $custName = $ticket['name'] ?? 'Customer';
    $initDesc = "Ticket created with priority '{$ticket['priority']}' for customer {$custName}. Device: {$ticket['device_brand']} {$ticket['device_model']}. Problem: {$ticket['problem_description']}";
    logTicketHistory($pdo, $ticket['id'], $ticket['ticket_id'], 'created', 'all', null, 'Pending', $initDesc, 1, 'Admin', 'Admin');

    try {
        $oldHist = $pdo->prepare("SELECT tsh.*, u.name as user_name, u.role as user_role FROM ticket_status_history tsh LEFT JOIN users u ON tsh.user_id = u.id WHERE tsh.ticket_id = ? ORDER BY tsh.created_at ASC");
        $oldHist->execute([$id]);
        foreach ($oldHist->fetchAll() as $oh) {
            $desc = $oh['notes'] ?: "Status changed to {$oh['status']}";
            logTicketHistory($pdo, $id, $ticket['ticket_id'], 'status_change', 'status', $oh['old_status'] ?? null, $oh['status'], $desc, $oh['user_id'], $oh['user_name'] ?: 'Staff', $oh['user_role'] ?? 'Staff');
        }
    } catch (Exception $e) {}

    $stmt->execute([$id]);
    $ticketHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch notes
$stmt = $pdo->prepare("SELECT n.*, u.name FROM ticket_notes n LEFT JOIN users u ON n.user_id = u.id WHERE n.ticket_id = ? ORDER BY n.created_at DESC");
$stmt->execute([$id]);
$notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch images
$stmt = $pdo->prepare("SELECT * FROM device_images WHERE ticket_id = ?");
$stmt->execute([$id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$all_statuses = ['Pending', 'In Progress', 'Waiting for Parts', 'Ready for Pickup', 'Completed', 'Delivered', 'Cancelled'];


$pageTitle = 'View Ticket - ' . htmlspecialchars($ticket['ticket_id']);
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid py-4">
        
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= htmlspecialchars($_SESSION['flash_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['flash_message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Ticket #<?= htmlspecialchars($ticket['ticket_id'] ?? '') ?> 
                    <span class="fs-6 align-middle"><?= getStatusBadge($ticket['status'] ?? 'Pending') ?></span>
                </h2>
                <small class="text-muted">Created on <?= date('F d, Y h:i A', strtotime($ticket['created_at'])) ?></small>
            </div>
            <div>
                <a href="edit.php?id=<?= $ticket['id'] ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                <a href="print.php?id=<?= $ticket['id'] ?>" target="_blank" class="btn btn-outline-secondary"><i class="fas fa-print"></i> Print</a>
                <a href="../invoices/create.php?ticket_id=<?= $ticket['id'] ?>" class="btn btn-success"><i class="fas fa-file-invoice-dollar"></i> Create Invoice</a>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                
                <!-- Device Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Device Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-4 mb-3">
                                <span class="text-muted d-block">Device Type</span>
                                <span class="fw-bold"><?= htmlspecialchars($ticket['device_type'] ?? '') ?></span>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <span class="text-muted d-block">Brand & Model</span>
                                <span class="fw-bold"><?= htmlspecialchars(($ticket['device_brand'] ?? '') . ' ' . ($ticket['device_model'] ?? '')) ?></span>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <span class="text-muted d-block">Serial Number</span>
                                <span><?= htmlspecialchars($ticket['serial_number'] ?? '') ?: 'N/A' ?></span>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <span class="text-muted d-block">IMEI Number</span>
                                <span><?= htmlspecialchars($ticket['imei_number'] ?? '') ?: 'N/A' ?></span>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <span class="text-muted d-block">Password/PIN</span>
                                <span><?= htmlspecialchars($ticket['device_password'] ?? '') ?: 'N/A' ?></span>
                            </div>
                        </div>
                        <?php if ($ticket['device_condition']): ?>
                        <div class="mt-2">
                            <span class="text-muted d-block mb-1">Device Condition / Included Items</span>
                            <div class="p-3 bg-light rounded border">
                                <?= nl2br(htmlspecialchars($ticket['device_condition'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Problem Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Problem Description</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['problem_description'])) ?></p>
                    </div>
                </div>

                <!-- Images Card -->
                <?php if (count($images) > 0): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Device Images</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($images as $img): ?>
                                <div class="col-sm-3 col-6">
                                    <a href="../<?= htmlspecialchars($img['image_path']) ?>" target="_blank">
                                        <img src="../<?= htmlspecialchars($img['image_path']) ?>" class="img-fluid rounded border" alt="Device Image">
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notes Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Internal Notes</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="view.php?id=<?= $id ?>" class="mb-4">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add_note">
                            <div class="input-group">
                                <textarea class="form-control" name="note_text" rows="2" placeholder="Type a note..." required></textarea>
                                <button class="btn btn-primary" type="submit">Add Note</button>
                            </div>
                        </form>

                        <div class="list-group list-group-flush">
                            <?php if (count($notes) > 0): ?>
                                <?php foreach ($notes as $note): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between w-100 mb-1">
                                            <h6 class="mb-0"><?= htmlspecialchars($note['name']) ?></h6>
                                            <small class="text-muted"><?= date('M d, g:i A', strtotime($note['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center mb-0">No notes added yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                
                <!-- Status Update Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Update Status</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="view.php?id=<?= $id ?>">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <div class="input-group">
                                <select class="form-select" name="status">
                                    <?php foreach ($all_statuses as $s): ?>
                                        <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-primary" type="submit">Update</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Customer Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Customer Info</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-1"><?= htmlspecialchars($ticket['name']) ?></h6>
                        <p class="mb-1 text-muted"><i class="fas fa-phone fa-fw"></i> <?= htmlspecialchars($ticket['phone']) ?></p>
                        <?php if ($ticket['email']): ?>
                        <p class="mb-3 text-muted"><i class="fas fa-envelope fa-fw"></i> <?= htmlspecialchars($ticket['email']) ?></p>
                        <?php endif; ?>
                        <a href="../customers/view.php?id=<?= $ticket['customer_id'] ?>" class="btn btn-sm btn-outline-secondary w-100">View Profile</a>
                    </div>
                </div>

                <!-- Assignment Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Assigned To:</span>
                            <span class="fw-bold"><?= $ticket['assignee_name'] ? htmlspecialchars($ticket['assignee_name']) : 'Unassigned' ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Priority:</span>
                            <?= getPriorityBadge($ticket['priority'] ?? 'Medium') ?>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Due Date:</span>
                            <span class="fw-bold"><?= $ticket['due_date'] ? date('M d, Y', strtotime($ticket['due_date'])) : 'Not set' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Financial Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">Cost Estimation</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Estimated Cost:</span>
                            <span class="fs-5 fw-bold">₹<?= number_format((float)$ticket['estimated_cost'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history text-primary me-2"></i>Audit Timeline</h5>
                        <a href="history.php?id=<?= $ticket['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Full History Log">
                            <i class="fas fa-list-ul me-1"></i> All Logs
                        </a>
                    </div>
                    <div class="card-body" style="max-height: 480px; overflow-y: auto;">
                        <?php if (empty($ticketHistory)): ?>
                            <p class="text-muted small text-center mb-0">No history records yet.</p>
                        <?php else: ?>
                            <div class="timeline position-relative" style="border-left: 2px solid #e9ecef; margin-left: 10px; padding-left: 20px;">
                                <?php foreach ($ticketHistory as $h): 
                                    $markerColor = '#0d6efd';
                                    $badgeClass = 'bg-primary';
                                    $badgeIcon = 'fa-sync-alt';
                                    $actionLabel = ucwords(str_replace('_', ' ', $h['action_type']));

                                    switch ($h['action_type']) {
                                        case 'created':
                                            $markerColor = '#198754';
                                            $badgeClass = 'bg-success';
                                            $badgeIcon = 'fa-plus-circle';
                                            $actionLabel = 'Ticket Created';
                                            break;
                                        case 'status_change':
                                            $markerColor = '#0d6efd';
                                            $badgeClass = 'bg-primary';
                                            $badgeIcon = 'fa-arrows-rotate';
                                            $actionLabel = 'Status Changed';
                                            break;
                                        case 'priority_change':
                                            $markerColor = '#ffc107';
                                            $badgeClass = 'bg-warning text-dark';
                                            $badgeIcon = 'fa-flag';
                                            $actionLabel = 'Priority Updated';
                                            break;
                                        case 'assigned':
                                            $markerColor = '#0dcaf0';
                                            $badgeClass = 'bg-info text-dark';
                                            $badgeIcon = 'fa-user-tag';
                                            $actionLabel = 'Assignment';
                                            break;
                                        case 'cost_update':
                                            $markerColor = '#6f42c1';
                                            $badgeClass = 'bg-purple text-white';
                                            $badgeIcon = 'fa-coins';
                                            $actionLabel = 'Cost Updated';
                                            break;
                                        case 'device_update':
                                            $markerColor = '#212529';
                                            $badgeClass = 'bg-dark';
                                            $badgeIcon = 'fa-laptop';
                                            $actionLabel = 'Device Updated';
                                            break;
                                        case 'note_added':
                                            $markerColor = '#6c757d';
                                            $badgeClass = 'bg-secondary';
                                            $badgeIcon = 'fa-comment-dots';
                                            $actionLabel = 'Note Added';
                                            break;
                                    }
                                ?>
                                    <div class="timeline-item mb-3 position-relative">
                                        <div class="timeline-marker position-absolute" style="left: -27px; top: 2px; width: 12px; height: 12px; border-radius: 50%; background-color: <?= $markerColor ?>; border: 2px solid white; box-shadow: 0 0 0 2px rgba(0,0,0,0.08);"></div>
                                        <div class="d-flex align-items-center gap-1 mb-1">
                                            <span class="badge <?= $badgeClass ?> font-monospace" style="font-size: 0.72rem;">
                                                <i class="fas <?= $badgeIcon ?> me-1"></i><?= $actionLabel ?>
                                            </span>
                                            <?php if ($h['field_name'] && $h['field_name'] !== 'all' && $h['field_name'] !== 'status' && $h['field_name'] !== 'notes'): ?>
                                                <span class="badge bg-light text-muted border" style="font-size: 0.7rem;"><?= htmlspecialchars($h['field_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="mb-1 small text-break"><?= htmlspecialchars($h['change_description']) ?></p>
                                        <?php if ($h['old_value'] !== null && $h['new_value'] !== null && $h['old_value'] !== $h['new_value']): ?>
                                            <div class="small mb-1 text-muted" style="font-size: 0.75rem;">
                                                <span class="badge bg-light text-secondary border"><?= htmlspecialchars($h['old_value'] ?: 'None') ?></span>
                                                <i class="fas fa-arrow-right mx-1 text-muted" style="font-size: 0.65rem;"></i>
                                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($h['new_value']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">
                                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($h['user_name'] ?? 'System') ?>
                                            <?php if (!empty($h['user_role'])): ?>
                                                <span class="badge bg-light text-secondary border px-1"><?= htmlspecialchars($h['user_role']) ?></span>
                                            <?php endif; ?>
                                            &bull; <?= date('M d, Y h:i A', strtotime($h['created_at'])) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>
