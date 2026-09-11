<?php
/**
 * RepairHub — Global Search API
 * Searches across tickets, customers, and inventory
 */
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    jsonResponse([]);
}

$results = [];
$term = "%{$query}%";

try {
    // Search tickets
    $stmt = $pdo->prepare("
        SELECT t.id, t.ticket_id, t.device_type, t.device_brand, t.device_model, t.status, c.name as customer_name
        FROM repair_tickets t
        JOIN customers c ON t.customer_id = c.id
        WHERE t.ticket_id LIKE ? 
           OR t.device_brand LIKE ? 
           OR t.device_model LIKE ? 
           OR t.device_type LIKE ?
           OR t.serial_number LIKE ?
           OR t.problem_description LIKE ?
           OR c.name LIKE ? 
           OR c.phone LIKE ?
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$term, $term, $term, $term, $term, $term, $term, $term]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $brandModel = trim(($row['device_brand'] ?? '') . ' ' . ($row['device_model'] ?? ''));
        $results[] = [
            'title' => $row['ticket_id'] . ' — ' . $row['customer_name'],
            'subtitle' => ($brandModel ? $brandModel . ' • ' : '') . $row['status'],
            'icon' => 'fa-ticket-alt',
            'url' => APP_URL . '/tickets/view.php?id=' . $row['id'],
        ];
    }
    
    // Search customers
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email 
        FROM customers 
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$term, $term, $term]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'title' => $row['name'],
            'subtitle' => $row['phone'] . ($row['email'] ? ' • ' . $row['email'] : ''),
            'icon' => 'fa-user',
            'url' => APP_URL . '/customers/view.php?id=' . $row['id'],
        ];
    }
    
    // Search inventory
    $stmt = $pdo->prepare("
        SELECT id, name, sku, quantity, category 
        FROM inventory 
        WHERE name LIKE ? OR sku LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$term, $term]);
    foreach ($stmt->fetchAll() as $row) {
        $results[] = [
            'title' => $row['name'],
            'subtitle' => ($row['sku'] ? $row['sku'] . ' • ' : '') . 'Stock: ' . $row['quantity'],
            'icon' => 'fa-box',
            'url' => APP_URL . '/inventory/edit.php?id=' . $row['id'],
        ];
    }
    
} catch (Exception $e) {
    // Return whatever we have
}

jsonResponse($results);
