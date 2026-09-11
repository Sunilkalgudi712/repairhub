<?php
/**
 * RepairHub — Inventory Search API
 * AJAX endpoint for parts search in invoice creation
 */
require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$search = trim($_GET['search'] ?? $_GET['q'] ?? '');

if (strlen($search) < 2) {
    jsonResponse([]);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, sku, category, quantity, selling_price, cost_price
        FROM inventory 
        WHERE is_active = 1 AND (name LIKE ? OR sku LIKE ? OR category LIKE ?)
        ORDER BY name ASC
        LIMIT 15
    ");
    $term = "%{$search}%";
    $stmt->execute([$term, $term, $term]);
    $items = $stmt->fetchAll();
    
    jsonResponse($items);
} catch (Exception $e) {
    jsonResponse(['error' => 'Search failed'], 500);
}
