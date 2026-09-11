<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ticket_id = $_GET['ticket_id'] ?? null;
    if (!$ticket_id) {
        jsonResponse(['error' => 'ticket_id is required'], 400);
    }

    // Get service progress
    $stmt1 = $pdo->prepare("
        SELECT sp.id, sp.status_update AS title, sp.description, sp.image_path, sp.time_spent_minutes, 
               sp.created_at, u.name AS user_name, 'progress' AS type
        FROM service_progress sp
        LEFT JOIN users u ON sp.user_id = u.id
        WHERE sp.ticket_id = ?
    ");
    $stmt1->execute([$ticket_id]);
    $progress = $stmt1->fetchAll();

    // Get status history
    $stmt2 = $pdo->prepare("
        SELECT tsh.id, tsh.new_status AS title, tsh.notes AS description, NULL AS image_path, NULL AS time_spent_minutes,
               tsh.created_at, u.name AS user_name, 'status_change' AS type
        FROM ticket_status_history tsh
        LEFT JOIN users u ON tsh.changed_by = u.id
        WHERE tsh.ticket_id = ?
    ");
    $stmt2->execute([$ticket_id]);
    $history = $stmt2->fetchAll();

    // Merge and sort by created_at DESC
    $timeline = array_merge($progress, $history);
    usort($timeline, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    jsonResponse($timeline);

} elseif ($method === 'POST') {
    if (!in_array($_SESSION['user_role'], ['Technician', 'Admin'])) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $ticket_id = $_POST['ticket_id'] ?? null;
    $status_update = $_POST['status_update'] ?? '';
    $description = $_POST['description'] ?? '';
    $time_spent_minutes = isset($_POST['time_spent_minutes']) ? (int)$_POST['time_spent_minutes'] : 0;
    
    if (!$ticket_id || empty($status_update)) {
        jsonResponse(['error' => 'ticket_id and status_update are required'], 400);
    }

    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadResult = uploadFile($_FILES['image'], 'devices');
        if (isset($uploadResult['success']) && $uploadResult['success']) {
            $image_path = $uploadResult['path'];
        } else {
            jsonResponse(['error' => $uploadResult['error'] ?? 'Upload failed'], 400);
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO service_progress (ticket_id, user_id, status_update, description, image_path, time_spent_minutes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $_SESSION['user_id'], $status_update, $description, $image_path, $time_spent_minutes]);
        
        if ($time_spent_minutes > 0) {
            $updateTicket = $pdo->prepare("UPDATE repair_tickets SET time_spent_total = time_spent_total + ? WHERE id = ?");
            $updateTicket->execute([$time_spent_minutes, $ticket_id]);
        }

        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Invalid method'], 405);
