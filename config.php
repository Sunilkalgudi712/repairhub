<?php
/**
 * RepairHub — Configuration File
 * Database connection, app constants, session configuration
 */

// Error reporting (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load local override configuration if exists (for production / Rocky Linux)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ─── App Constants ──────────────────────────────────────────
if (!defined('APP_NAME')) define('APP_NAME', 'RepairHub');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) {
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $modules = ['auth', 'worker', 'client', 'admin', 'tickets', 'customers', 'invoices', 'inventory', 'reports', 'settings', 'tasks', 'leads', 'expenses', 'purchases', 'employees', 'api', 'quotations'];
    if ($scriptDir === '/' || $scriptDir === '\\' || $scriptDir === '' || $scriptDir === '.') {
        define('APP_URL', '');
    } else {
        $base = basename($scriptDir);
        if (in_array($base, $modules)) {
            $parent = dirname($scriptDir);
            define('APP_URL', ($parent === '/' || $parent === '\\' || $parent === '' || $parent === '.') ? '' : rtrim(str_replace('\\', '/', $parent), '/'));
        } else {
            define('APP_URL', rtrim(str_replace('\\', '/', $scriptDir), '/'));
        }
    }
}
if (!defined('APP_ROOT')) define('APP_ROOT', __DIR__);
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', '₹');
if (!defined('CURRENCY_CODE')) define('CURRENCY_CODE', 'INR');
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 15);
if (!defined('UPLOAD_DIR')) define('UPLOAD_DIR', APP_ROOT . '/uploads/');
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// ─── Database Configuration ─────────────────────────────────
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'repairhub');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// ─── PDO Connection ─────────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // If database doesn't exist yet, show friendly message
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        die('<div style="font-family:system-ui;padding:40px;text-align:center;">
            <h2>⚠️ Database Not Found</h2>
            <p>Please import <code>database.sql</code> into MySQL to create the <strong>repairhub</strong> database.</p>
            <p style="color:#6c757d;">Open phpMyAdmin → Import → Select database.sql</p>
        </div>');
    }
    die('Database connection failed: ' . $e->getMessage());
}

// ─── Include Helper Functions ───────────────────────────────
require_once APP_ROOT . '/includes/functions.php';

// ─── Ticket Status Constants ────────────────────────────────
define('TICKET_STATUSES', [
    'Pending'           => ['badge' => 'bg-warning text-dark', 'icon' => 'fa-clock'],
    'In Progress'       => ['badge' => 'bg-primary',          'icon' => 'fa-wrench'],
    'Waiting for Parts' => ['badge' => 'bg-info',             'icon' => 'fa-hourglass-half'],
    'Ready for Pickup'  => ['badge' => 'bg-success',          'icon' => 'fa-check-circle'],
    'Completed'         => ['badge' => 'bg-success',          'icon' => 'fa-check-double'],
    'Delivered'         => ['badge' => 'bg-secondary',        'icon' => 'fa-truck'],
    'Cancelled'         => ['badge' => 'bg-danger',           'icon' => 'fa-times-circle'],
]);

// ─── Invoice Status Constants ───────────────────────────────
define('INVOICE_STATUSES', [
    'Draft'     => ['badge' => 'bg-secondary',      'icon' => 'fa-file'],
    'Sent'      => ['badge' => 'bg-info',            'icon' => 'fa-paper-plane'],
    'Paid'      => ['badge' => 'bg-success',         'icon' => 'fa-check-circle'],
    'Overdue'   => ['badge' => 'bg-danger',          'icon' => 'fa-exclamation-circle'],
    'Cancelled' => ['badge' => 'bg-dark',            'icon' => 'fa-ban'],
]);

// ─── Payment Methods ────────────────────────────────────────
define('PAYMENT_METHODS', ['Cash', 'UPI', 'Card', 'Bank Transfer', 'Other']);

// ─── Priority Levels ────────────────────────────────────────
define('PRIORITIES', [
    'Low'      => 'bg-secondary',
    'Normal'   => 'bg-primary',
    'High'     => 'bg-warning text-dark',
    'Urgent'   => 'bg-danger',
]);

// ─── Device Types ───────────────────────────────────────────
define('DEVICE_TYPES', [
    'Laptop', 'Desktop', 'Printer', 'Monitor', 'Tablet',
    'Phone', 'Hard Drive', 'SSD', 'RAM', 'Motherboard',
    'Power Supply', 'UPS', 'Router', 'Other'
]);

// ─── Quotation Status Constants ─────────────────────────────
define('QUOTATION_STATUSES', [
    'Draft'     => ['badge' => 'bg-secondary',  'icon' => 'fa-file'],
    'Sent'      => ['badge' => 'bg-info',       'icon' => 'fa-paper-plane'],
    'Approved'  => ['badge' => 'bg-success',    'icon' => 'fa-check-circle'],
    'Rejected'  => ['badge' => 'bg-danger',     'icon' => 'fa-times-circle'],
    'Expired'   => ['badge' => 'bg-dark',       'icon' => 'fa-clock'],
    'Converted' => ['badge' => 'bg-primary',    'icon' => 'fa-file-invoice'],
]);

// ─── Service Progress Types ─────────────────────────────────
define('PROGRESS_TYPES', [
    'Received',
    'Diagnosis Started',
    'Diagnosis Complete',
    'Waiting for Approval',
    'Parts Ordered',
    'Parts Received',
    'Repair In Progress',
    'Testing',
    'Quality Check',
    'Ready for Pickup',
    'Other'
]);

// ─── User Roles ─────────────────────────────────────────────
define('USER_ROLES', ['Admin', 'Technician', 'Receptionist', 'Client']);
