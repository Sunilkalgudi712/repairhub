<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ticket_id = $_GET['ticket_id'] ?? null;
    $customer_id = $_GET['customer_id'] ?? null;
    
    $query = "
        SELECT f.id, f.ticket_id, f.customer_id, f.rating, f.review, f.created_at, c.name AS customer_name
        FROM feedback f
        JOIN customers c ON f.customer_id = c.id
        WHERE f.is_visible = 1
    ";
    
    $params = [];
    if ($ticket_id) {
        $query .= " AND f.ticket_id = ?";
        $params[] = $ticket_id;
    } elseif ($customer_id) {
        $query .= " AND f.customer_id = ?";
        $params[] = $customer_id;
    } else {
        jsonResponse(['error' => 'ticket_id or customer_id required'], 400);
    }
    
    $query .= " ORDER BY f.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        jsonResponse($stmt->fetchAll());
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }

} elseif ($method === 'POST') {
    if ($_SESSION['user_role'] !== 'Client') {
        jsonResponse(['error' => 'Only clients can submit feedback'], 403);
    }
    
    $ticket_id = $_POST['ticket_id'] ?? null;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review = $_POST['review'] ?? '';
    
    if (!$ticket_id || $rating < 1 || $rating > 5) {
        jsonResponse(['error' => 'Invalid ticket_id or rating (must be 1-5)'], 400);
    }
    
    try {
        // Get customer_id for this client
        $stmtCust = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
        $stmtCust->execute([$_SESSION['user_id']]);
        $customer = $stmtCust->fetch();
        
        if (!$customer) {
            jsonResponse(['error' => 'Customer profile not found'], 404);
        }
        
        $customer_id = $customer['id'];
        
        // Validate ticket belongs to this customer & status
        $stmtTick = $pdo->prepare("SELECT id, status, assigned_to FROM repair_tickets WHERE id = ? AND customer_id = ?");
        $stmtTick->execute([$ticket_id, $customer_id]);
        $ticket = $stmtTick->fetch();
        
        if (!$ticket) {
            jsonResponse(['error' => 'Ticket not found or does not belong to you'], 404);
        }
        
        if (!in_array($ticket['status'], ['Completed', 'Delivered'])) {
            jsonResponse(['error' => 'Feedback can only be submitted for completed or delivered tickets'], 400);
        }
        
        // Check for duplicate feedback
        $stmtDup = $pdo->prepare("SELECT id FROM feedback WHERE ticket_id = ?");
        $stmtDup->execute([$ticket_id]);
        if ($stmtDup->fetch()) {
            jsonResponse(['error' => 'Feedback already submitted for this ticket'], 400);
        }
        
        $technician_id = $ticket['assigned_to'];
        
        $stmt = $pdo->prepare("
            INSERT INTO feedback (ticket_id, customer_id, rating, review, technician_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $customer_id, $rating, $review, $technician_id]);
        
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Invalid method'], 405);
