<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $dayOfWeek = date('w', strtotime($date));

    // Get technicians and their availability/schedule
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, 
               wa.slot_morning AS wa_morning, 
               wa.slot_afternoon AS wa_afternoon, 
               wa.is_leave AS wa_leave,
               ws.is_working AS ws_working,
               ws.max_jobs
        FROM users u
        LEFT JOIN worker_availability wa ON u.id = wa.user_id AND wa.available_date = ?
        LEFT JOIN worker_schedule ws ON u.id = ws.user_id AND ws.day_of_week = ?
        WHERE u.role = 'Technician' AND u.is_active = 1
    ");
    $stmt->execute([$date, $dayOfWeek]);
    $technicians = $stmt->fetchAll();

    // Count assigned active tickets for that date
    $stmtCount = $pdo->prepare("
        SELECT assigned_to, COUNT(*) as count 
        FROM repair_tickets 
        WHERE status NOT IN ('Completed', 'Delivered', 'Cancelled') 
          AND (due_date = ? OR (due_date IS NULL AND DATE(created_at) = ?))
        GROUP BY assigned_to
    ");
    $stmtCount->execute([$date, $date]);
    $counts = [];
    foreach ($stmtCount->fetchAll() as $row) {
        $counts[$row['assigned_to']] = $row['count'];
    }

    $result = [];
    foreach ($technicians as $tech) {
        // Determine availability from worker_availability or fallback to worker_schedule
        if (isset($tech['wa_leave'])) {
            $is_leave = (bool)$tech['wa_leave'];
            $slot_morning = (bool)$tech['wa_morning'];
            $slot_afternoon = (bool)$tech['wa_afternoon'];
        } else {
            $is_working = isset($tech['ws_working']) ? (bool)$tech['ws_working'] : true;
            $slot_morning = $is_working;
            $slot_afternoon = $is_working;
            $is_leave = !$is_working;
        }

        $assigned_count = $counts[$tech['id']] ?? 0;
        $max_jobs = $tech['max_jobs'] ?? 5;
        $is_available = !$is_leave && ($slot_morning || $slot_afternoon) && ($assigned_count < $max_jobs);

        $result[] = [
            'id' => $tech['id'],
            'name' => $tech['name'],
            'is_available' => $is_available,
            'slot_morning' => $slot_morning,
            'slot_afternoon' => $slot_afternoon,
            'is_leave' => $is_leave,
            'assigned_count' => $assigned_count,
            'max_jobs' => $max_jobs
        ];
    }
    
    jsonResponse($result);

} elseif ($method === 'POST') {
    if (!in_array($_SESSION['user_role'], ['Technician', 'Admin'])) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
    
    $user_id = (isset($_POST['user_id']) && $_SESSION['user_role'] === 'Admin') ? $_POST['user_id'] : $_SESSION['user_id'];
    $date = $_POST['date'] ?? '';
    $slot_morning = isset($_POST['slot_morning']) ? (int)$_POST['slot_morning'] : 0;
    $slot_afternoon = isset($_POST['slot_afternoon']) ? (int)$_POST['slot_afternoon'] : 0;
    $is_leave = isset($_POST['is_leave']) ? (int)$_POST['is_leave'] : 0;
    $notes = $_POST['notes'] ?? '';

    if (empty($date)) {
        jsonResponse(['error' => 'Date is required'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO worker_availability (user_id, available_date, slot_morning, slot_afternoon, is_leave, notes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                slot_morning = VALUES(slot_morning),
                slot_afternoon = VALUES(slot_afternoon),
                is_leave = VALUES(is_leave),
                notes = VALUES(notes)
        ");
        $stmt->execute([$user_id, $date, $slot_morning, $slot_afternoon, $is_leave, $notes]);
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Invalid method'], 405);
