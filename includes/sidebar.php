<?php
/**
 * RepairHub — Sidebar Navigation
 * Collapsible left sidebar with icon + label menu items
 */

// Determine active page for highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

if (!function_exists('isActive')) {
    function isActive($dir, $page = null) {
        global $currentDir, $currentPage;
        if ($page) {
            return ($currentPage === $page) ? 'active' : '';
        }
        return ($currentDir === $dir) ? 'active' : '';
    }
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>/index.php" class="text-decoration-none">
            <i class="fas fa-tools"></i>
            <span class="sidebar-brand-text"><?= APP_NAME ?></span>
        </a>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <!-- Dashboard -->
            <li class="sidebar-item <?= isActive('Reper_hub', 'index.php') ?>">
                <a href="<?= APP_URL ?>/index.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Section: Operations -->
            <li class="sidebar-section">OPERATIONS</li>
            
            <!-- Repair Tickets -->
            <li class="sidebar-item <?= (isActive('tickets') && $currentPage !== 'history.php') ? 'active' : '' ?>">
                <a href="<?= APP_URL ?>/tickets/index.php" class="sidebar-link">
                    <i class="fas fa-ticket-alt"></i>
                    <span>Repair Tickets</span>
                </a>
            </li>

            <!-- Ticket History -->
            <li class="sidebar-item <?= (isActive('tickets') && $currentPage === 'history.php') ? 'active' : '' ?>">
                <a href="<?= APP_URL ?>/tickets/history.php" class="sidebar-link">
                    <i class="fas fa-history"></i>
                    <span>Ticket History</span>
                </a>
            </li>
            
            <!-- Customers -->
            <li class="sidebar-item <?= isActive('customers') ?>">
                <a href="<?= APP_URL ?>/customers/index.php" class="sidebar-link">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
            </li>
            
            <!-- Inventory -->
            <li class="sidebar-item <?= (isActive('inventory') && $currentPage !== 'history.php') ? 'active' : '' ?>">
                <a href="<?= APP_URL ?>/inventory/index.php" class="sidebar-link">
                    <i class="fas fa-boxes-stacked"></i>
                    <span>Inventory</span>
                </a>
            </li>
            
            <!-- Stock History -->
            <li class="sidebar-item <?= (isActive('inventory') && $currentPage === 'history.php') ? 'active' : '' ?>">
                <a href="<?= APP_URL ?>/inventory/history.php" class="sidebar-link">
                    <i class="fas fa-history"></i>
                    <span>Stock History</span>
                </a>
            </li>

            <!-- Client Submissions -->
            <li class="sidebar-item <?= isActive('admin') ?>">
                <a href="<?= APP_URL ?>/admin/client_submissions.php" class="sidebar-link">
                    <i class="fas fa-inbox"></i>
                    <span>Client Submissions</span>
                </a>
            </li>
            
            <!-- Section: Finance -->
            <li class="sidebar-section">FINANCE</li>
            
            <!-- Invoices -->
            <li class="sidebar-item <?= isActive('invoices') ?>">
                <a href="<?= APP_URL ?>/invoices/index.php" class="sidebar-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
            </li>

            <!-- Quotations -->
            <li class="sidebar-item <?= isActive('quotations') ?>">
                <a href="<?= APP_URL ?>/quotations/index.php" class="sidebar-link">
                    <i class="fas fa-file-contract"></i>
                    <span>Quotations</span>
                </a>
            </li>
            
            <!-- Purchases -->
            <li class="sidebar-item <?= isActive('purchases') ?>">
                <a href="<?= APP_URL ?>/purchases/index.php" class="sidebar-link">
                    <i class="fas fa-cart-shopping"></i>
                    <span>Purchases</span>
                </a>
            </li>
            
            <!-- Expenses -->
            <li class="sidebar-item <?= isActive('expenses') ?>">
                <a href="<?= APP_URL ?>/expenses/index.php" class="sidebar-link">
                    <i class="fas fa-receipt"></i>
                    <span>Expenses</span>
                </a>
            </li>
            
            <!-- Section: CRM -->
            <li class="sidebar-section">CRM</li>
            
            <!-- Leads -->
            <li class="sidebar-item <?= isActive('leads') ?>">
                <a href="<?= APP_URL ?>/leads/index.php" class="sidebar-link">
                    <i class="fas fa-user-tag"></i>
                    <span>Leads</span>
                </a>
            </li>
            
            <!-- Tasks -->
            <li class="sidebar-item <?= isActive('tasks') ?>">
                <a href="<?= APP_URL ?>/tasks/index.php" class="sidebar-link">
                    <i class="fas fa-list-check"></i>
                    <span>Tasks</span>
                </a>
            </li>

            <!-- Feedback -->
            <li class="sidebar-item <?= isActive('feedback') ?>">
                <a href="<?= APP_URL ?>/admin/feedback.php" class="sidebar-link">
                    <i class="fas fa-star-half-alt"></i>
                    <span>Feedback</span>
                </a>
            </li>
            
            <!-- Section: Management -->
            <li class="sidebar-section">MANAGEMENT</li>
            
            <!-- Employees -->
            <li class="sidebar-item <?= isActive('employees') ?>">
                <a href="<?= APP_URL ?>/employees/index.php" class="sidebar-link">
                    <i class="fas fa-user-tie"></i>
                    <span>Employees</span>
                </a>
            </li>

            <!-- Worker Schedule -->
            <li class="sidebar-item <?= isActive('worker_schedule') ?>">
                <a href="<?= APP_URL ?>/admin/worker_schedule.php" class="sidebar-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Worker Schedule</span>
                </a>
            </li>
            
            <!-- Reports -->
            <li class="sidebar-item <?= isActive('reports') ?>">
                <a href="<?= APP_URL ?>/reports/index.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <!-- Settings -->
            <li class="sidebar-item <?= isActive('settings') ?>">
                <a href="<?= APP_URL ?>/settings/index.php" class="sidebar-link">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
    </div>
</aside>
