<?php
/**
 * RepairHub — Helper Functions
 * Reusable utilities used throughout the application
 */

// ─── Authentication Helpers ─────────────────────────────────

/**
 * Check if user is logged in, redirect to login if not
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        setFlashMessage('Please log in to continue.', 'warning');
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

/**
 * Check if user has admin role
 */
function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'Admin') {
        setFlashMessage('Access denied. Admin privileges required.', 'danger');
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

/**
 * Get current logged-in user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged-in user name
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'Guest';
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? '';
}

/**
 * Check if current user has one of the allowed roles
 */
function requireRole($allowedRoles) {
    requireLogin();
    if (!is_array($allowedRoles)) $allowedRoles = [$allowedRoles];
    if (!in_array($_SESSION['user_role'], $allowedRoles)) {
        setFlashMessage('Access denied. You don\'t have permission to view this page.', 'danger');
        // Redirect to appropriate dashboard based on role
        switch ($_SESSION['user_role']) {
            case 'Client':
                header('Location: ' . APP_URL . '/client/dashboard.php');
                break;
            case 'Technician':
                header('Location: ' . APP_URL . '/worker/dashboard.php');
                break;
            default:
                header('Location: ' . APP_URL . '/index.php');
                break;
        }
        exit;
    }
}

/**
 * Require client role (allows Client and Admin)
 */
function requireClient() {
    requireRole(['Client', 'Admin']);
}

/**
 * Require worker/technician role (allows Technician and Admin)
 */
function requireWorker() {
    requireRole(['Technician', 'Admin']);
}

/**
 * Get current customer for client portal (handles Client and Admin testing)
 */
function getCurrentClientCustomer($pdo) {
    if (!isset($_SESSION['user_id'])) return null;
    
    // If real Client user, fetch their linked customer record
    if (isClient()) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) return $customer;
    }

    // If Admin/Staff previewing client portal, show primary demo customer (Rahul Sharma)
    $stmt = $pdo->query("SELECT c.* FROM customers c JOIN users u ON c.user_id = u.id WHERE u.role = 'Client' ORDER BY c.id ASC LIMIT 1");
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) return $customer;

    // Fallback: any first customer
    $customer = $pdo->query("SELECT * FROM customers ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return $customer;
}

/**
 * Check role helpers
 */
function isAdmin() { return ($_SESSION['user_role'] ?? '') === 'Admin'; }
function isWorker() { return ($_SESSION['user_role'] ?? '') === 'Technician'; }
function isClient() { return ($_SESSION['user_role'] ?? '') === 'Client'; }
function isReceptionist() { return ($_SESSION['user_role'] ?? '') === 'Receptionist'; }
function isStaff() { return in_array($_SESSION['user_role'] ?? '', ['Admin', 'Technician', 'Receptionist']); }

/**
 * Get quotation status badge
 */
function getQuotationStatusBadge($status) {
    $statuses = QUOTATION_STATUSES;
    $badge = $statuses[$status]['badge'] ?? 'bg-secondary';
    return '<span class="badge ' . $badge . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Generate quotation number
 */
function generateQuotationNumber($pdo) {
    $prefix = 'QT';
    $date = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM quotations WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch()['count'] + 1;
    return $prefix . '-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// ─── Flash Messages ─────────────────────────────────────────

/**
 * Set a flash message
 */
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Display and clear flash message
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = htmlspecialchars($_SESSION['flash_message']);
        $type = $_SESSION['flash_type'] ?? 'success';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    }
}

// ─── Input Sanitization ─────────────────────────────────────

/**
 * Sanitize string input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize email
 */
function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ─── Formatting Helpers ─────────────────────────────────────

/**
 * Format currency amount
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return '—';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'd M Y, h:i A') {
    if (empty($datetime)) return '—';
    return date($format, strtotime($datetime));
}

/**
 * Time ago format
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return formatDate($datetime);
}

// ─── Ticket ID Generator ────────────────────────────────────

/**
 * Generate unique ticket ID (e.g., RH-20260908-001)
 */
function generateTicketId($pdo) {
    $prefix = 'RH';
    $date = date('Ymd');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM repair_tickets WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch()['count'] + 1;
    
    return $prefix . '-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate invoice number (e.g., INV-20260908-001)
 */
function generateInvoiceNumber($pdo) {
    $prefix = 'INV';
    $date = date('Ymd');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM invoices WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $count = $stmt->fetch()['count'] + 1;
    
    return $prefix . '-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
}

// ─── Pagination ─────────────────────────────────────────────

/**
 * Get pagination data
 */
function getPagination($totalItems, $currentPage, $perPage = ITEMS_PER_PAGE) {
    $totalPages = max(1, ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total_items'  => $totalItems,
        'total_pages'  => $totalPages,
        'current_page' => $currentPage,
        'per_page'     => $perPage,
        'offset'       => $offset,
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center mb-0">';
    
    // Previous
    $prevDisabled = $pagination['current_page'] <= 1 ? 'disabled' : '';
    $prevPage = $pagination['current_page'] - 1;
    $html .= '<li class="page-item ' . $prevDisabled . '">
                <a class="page-link" href="' . $baseUrl . '&page=' . $prevPage . '">&laquo;</a>
              </li>';
    
    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $pagination['current_page'] ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">
                    <a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>
                  </li>';
    }
    
    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $pagination['total_pages'] . '">' . $pagination['total_pages'] . '</a></li>';
    }
    
    // Next
    $nextDisabled = $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '';
    $nextPage = $pagination['current_page'] + 1;
    $html .= '<li class="page-item ' . $nextDisabled . '">
                <a class="page-link" href="' . $baseUrl . '&page=' . $nextPage . '">&raquo;</a>
              </li>';
    
    $html .= '</ul></nav>';
    return $html;
}

// ─── Status Badge ───────────────────────────────────────────

/**
 * Get status badge HTML for tickets
 */
function getStatusBadge($status) {
    $statuses = TICKET_STATUSES;
    $badge = $statuses[$status]['badge'] ?? 'bg-secondary';
    return '<span class="badge ' . $badge . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Get invoice status badge
 */
function getInvoiceStatusBadge($status) {
    $statuses = INVOICE_STATUSES;
    $badge = $statuses[$status]['badge'] ?? 'bg-secondary';
    return '<span class="badge ' . $badge . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Get priority badge
 */
function getPriorityBadge($priority) {
    $priorities = PRIORITIES;
    $badge = $priorities[$priority] ?? 'bg-secondary';
    return '<span class="badge ' . $badge . '">' . htmlspecialchars($priority) . '</span>';
}

// ─── File Upload ────────────────────────────────────────────

/**
 * Handle file upload
 */
function uploadFile($file, $subdir = 'devices') {
    $uploadDir = UPLOAD_DIR . $subdir . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF, WebP allowed.'];
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $ext;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $subdir . '/' . $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

// ─── Activity Logging ───────────────────────────────────────

/**
 * Log an activity
 */
function logActivity($pdo, $action, $details = '', $relatedType = null, $relatedId = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, related_type, related_id) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([getCurrentUserId(), $action, $details, $relatedType, $relatedId]);
    } catch (Exception $e) {
        // Silently fail — logging shouldn't break the app
    }
}

/**
 * Log an inventory change in inventory_history
 */
function logInventoryHistory($pdo, $inventoryId, $itemName, $actionType, $fieldName = null, $oldValue = null, $newValue = null, $description = '', $userId = null, $userName = null) {
    try {
        static $tableChecked = false;
        if (!$tableChecked) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_history` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `inventory_id` INT NOT NULL,
                `item_name` VARCHAR(200) NOT NULL,
                `user_id` INT DEFAULT NULL,
                `user_name` VARCHAR(100) DEFAULT NULL,
                `action_type` VARCHAR(50) NOT NULL,
                `field_name` VARCHAR(50) DEFAULT NULL,
                `old_value` TEXT DEFAULT NULL,
                `new_value` TEXT DEFAULT NULL,
                `change_description` TEXT NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY `idx_inventory_id` (`inventory_id`),
                KEY `idx_user_id` (`user_id`),
                KEY `idx_action_type` (`action_type`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $tableChecked = true;
        }

        if (empty($userId)) {
            $userId = $_SESSION['user_id'] ?? null;
        }
        if (empty($userName)) {
            $userName = $_SESSION['user_name'] ?? 'System';
        }
        if (empty($itemName) && $inventoryId) {
            $st = $pdo->prepare("SELECT name FROM inventory WHERE id = ?");
            $st->execute([$inventoryId]);
            $itemName = $st->fetchColumn() ?: 'Item #' . $inventoryId;
        }

        $stmt = $pdo->prepare("INSERT INTO inventory_history (inventory_id, item_name, user_id, user_name, action_type, field_name, old_value, new_value, change_description, created_at)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $inventoryId,
            $itemName,
            $userId,
            $userName,
            $actionType,
            $fieldName,
            $oldValue !== null ? (string)$oldValue : null,
            $newValue !== null ? (string)$newValue : null,
            $description
        ]);

        // Also log to global activity log
        logActivity($pdo, "Inventory: " . ucfirst(str_replace('_', ' ', $actionType)), $description, 'inventory', $inventoryId);
    } catch (Exception $e) {
        // Silently fail — logging shouldn't break the app
    }
}

// ─── CSRF Protection ────────────────────────────────────────

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF hidden input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

/**
 * Verify CSRF token (accepts parameter or auto-reads from $_POST/$_GET)
 */
function verifyCsrfToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token = null) {
        return verifyCsrfToken($token);
    }
}

// ─── Misc Helpers ───────────────────────────────────────────

/**
 * Redirect with flash message
 */
function redirectWith($url, $message, $type = 'success') {
    setFlashMessage($message, $type);
    header('Location: ' . $url);
    exit;
}

/**
 * Get initials from name
 */
function getInitials($name) {
    $cleanName = preg_replace('/[^a-zA-Z\s]/', '', $name);
    $words = array_values(array_filter(explode(' ', trim($cleanName))));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials ?: strtoupper(substr(trim($name), 0, 1)) ?: '?';
}

/**
 * JSON response helper for AJAX
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
