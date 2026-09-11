<?php
/**
 * RepairHub — Customer Search API
 * AJAX endpoint for customer autocomplete
 */
require_once '../config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$search = trim($_GET['search'] ?? $_GET['q'] ?? '');

if (strlen($search) < 2) {
    jsonResponse([]);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, phone, email, city 
        FROM customers 
        WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?
        ORDER BY name ASC
        LIMIT 10
    ");
    $term = "%{$search}%";
    $stmt->execute([$term, $term, $term]);
    $customers = $stmt->fetchAll();
    
    jsonResponse($customers);
} catch (Exception $e) {
    jsonResponse(['error' => 'Search failed'], 500);
}
