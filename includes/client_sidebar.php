<?php
/**
 * RepairHub — Client Portal Sidebar
 */
$currentPage = basename($_SERVER['PHP_SELF']);
if (!function_exists('isClientActive')) {
    function isClientActive($page) {
        global $currentPage;
        return ($currentPage === $page) ? 'active' : '';
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>/client/dashboard.php" class="text-decoration-none">
            <i class="fas fa-tools"></i>
            <span class="sidebar-brand-text"><?= APP_NAME ?></span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="sidebar-item <?= isClientActive('dashboard.php') ?>">
                <a href="<?= APP_URL ?>/client/dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-section">SERVICES</li>
            <li class="sidebar-item <?= isClientActive('submit_device.php') ?>">
                <a href="<?= APP_URL ?>/client/submit_device.php" class="sidebar-link">
                    <i class="fas fa-laptop-medical"></i><span>Submit Device</span>
                </a>
            </li>
            <li class="sidebar-item <?= isClientActive('my_repairs.php') || isClientActive('repair_detail.php') ?>">
                <a href="<?= APP_URL ?>/client/my_repairs.php" class="sidebar-link">
                    <i class="fas fa-wrench"></i><span>My Repairs</span>
                </a>
            </li>
            <li class="sidebar-section">BILLING</li>
            <li class="sidebar-item <?= isClientActive('my_invoices.php') ?>">
                <a href="<?= APP_URL ?>/client/my_invoices.php" class="sidebar-link">
                    <i class="fas fa-file-invoice-dollar"></i><span>My Invoices</span>
                </a>
            </li>
            <li class="sidebar-section">FEEDBACK</li>
            <li class="sidebar-item <?= isClientActive('feedback.php') ?>">
                <a href="<?= APP_URL ?>/client/feedback.php" class="sidebar-link">
                    <i class="fas fa-star"></i><span>Feedback</span>
                </a>
            </li>
            <li class="sidebar-section">ACCOUNT</li>
            <li class="sidebar-item <?= isClientActive('profile.php') ?>">
                <a href="<?= APP_URL ?>/client/profile.php" class="sidebar-link">
                    <i class="fas fa-user-edit"></i><span>My Profile</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
    </div>
</aside>
