<?php
/**
 * RepairHub — Dashboard API
 * Chart data endpoints for dashboard
 */
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'revenue_weekly':
        // Revenue for last 7 days
        try {
            $stmt = $pdo->prepare("
                SELECT DATE(payment_date) as date, SUM(paid_amount) as total
                FROM invoices 
                WHERE status = 'Paid' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(payment_date)
                ORDER BY date ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            jsonResponse($data);
        } catch (Exception $e) {
            jsonResponse([]);
        }
        break;
        
    case 'tickets_by_status':
        try {
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as count 
                FROM repair_tickets 
                GROUP BY status 
                ORDER BY FIELD(status, 'Pending','In Progress','Waiting for Parts','Ready for Pickup','Completed','Delivered','Cancelled')
            ");
            $data = $stmt->fetchAll();
            jsonResponse($data);
        } catch (Exception $e) {
            jsonResponse([]);
        }
        break;
        
    case 'monthly_revenue':
        try {
            $stmt = $pdo->prepare("
                SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(paid_amount) as total
                FROM invoices 
                WHERE status = 'Paid' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            jsonResponse($data);
        } catch (Exception $e) {
            jsonResponse([]);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Invalid type'], 400);
}
