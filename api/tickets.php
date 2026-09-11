<?php
/**
 * RepairHub — Ticket API
 * Quick status update and ticket search
 */
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// Handle quick status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = intval($_POST['ticket_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    
    if (!$ticketId || !isset(TICKET_STATUSES[$newStatus])) {
        jsonResponse(['error' => 'Invalid parameters'], 400);
    }
    
    try {
        // Get current status
        $stmt = $pdo->prepare("SELECT status, ticket_id FROM repair_tickets WHERE id = ?");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();
        
        if (!$ticket) {
            jsonResponse(['error' => 'Ticket not found'], 404);
        }
        
        $oldStatus = $ticket['status'];
        
        // Update status
        $updateFields = ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')];
        if ($newStatus === 'Completed') {
            $updateFields['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($newStatus === 'Delivered') {
            $updateFields['delivered_at'] = date('Y-m-d H:i:s');
        }
        
        $sets = [];
        $vals = [];
        foreach ($updateFields as $key => $val) {
            $sets[] = "`$key` = ?";
            $vals[] = $val;
        }
        $vals[] = $ticketId;
        
        $stmt = $pdo->prepare("UPDATE repair_tickets SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($vals);
        
        // Log status change
        $stmt = $pdo->prepare("INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticketId, $oldStatus, $newStatus, getCurrentUserId()]);

        // Log to comprehensive ticket history
        logTicketHistory(
            $pdo,
            $ticketId,
            $ticket['ticket_id'] ?? null,
            'status_change',
            'status',
            $oldStatus,
            $newStatus,
            "Quick status changed from '{$oldStatus}' to '{$newStatus}'"
        );
        
        logActivity($pdo, 'Ticket Status Changed', "Ticket #{$ticketId}: {$oldStatus} → {$newStatus}", 'ticket', $ticketId);
        
        jsonResponse(['success' => true, 'message' => 'Status updated']);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Update failed'], 500);
    }
}

// Handle search
$search = trim($_GET['q'] ?? '');
if (strlen($search) < 2) {
    jsonResponse([]);
}

try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.ticket_id, t.status, t.device_type, t.device_brand, t.device_model,
               c.name as customer_name, c.phone as customer_phone
        FROM repair_tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.ticket_id LIKE ? 
           OR c.name LIKE ? 
           OR c.phone LIKE ? 
           OR t.device_brand LIKE ? 
           OR t.device_model LIKE ? 
           OR t.problem_description LIKE ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $term = "%{$search}%";
    $stmt->execute([$term, $term, $term, $term, $term, $term]);
    $tickets = $stmt->fetchAll();
    
    jsonResponse($tickets);
} catch (Exception $e) {
    jsonResponse(['error' => 'Search failed'], 500);
}
