<?php
/**
 * RepairHub — Worker Panel Sidebar
 */
$currentPage = basename($_SERVER['PHP_SELF']);
if (!function_exists('isWorkerActive')) {
    function isWorkerActive($page) {
        global $currentPage;
        return ($currentPage === $page) ? 'active' : '';
    }
}
?>
<aside class="sidebar" id="sidebar" style="background: #0c2918;">
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>/worker/dashboard.php" class="text-decoration-none">
            <i class="fas fa-tools" style="color:#10b981;"></i>
            <span class="sidebar-brand-text"><?= APP_NAME ?></span>
        </a>
    </div>
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="sidebar-item <?= isWorkerActive('dashboard.php') ?>">
                <a href="<?= APP_URL ?>/worker/dashboard.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-section">WORK</li>
            <li class="sidebar-item <?= isWorkerActive('my_tasks.php') || isWorkerActive('task_detail.php') ?>">
                <a href="<?= APP_URL ?>/worker/my_tasks.php" class="sidebar-link">
                    <i class="fas fa-clipboard-list"></i><span>My Tasks</span>
                </a>
            </li>
            <li class="sidebar-item <?= isWorkerActive('completion_report.php') ?>">
                <a href="<?= APP_URL ?>/worker/my_tasks.php?status=Completed" class="sidebar-link">
                    <i class="fas fa-check-double"></i><span>Completed</span>
                </a>
            </li>
            <li class="sidebar-section">SCHEDULE</li>
            <li class="sidebar-item <?= isWorkerActive('availability.php') ?>">
                <a href="<?= APP_URL ?>/worker/availability.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i><span>My Availability</span>
                </a>
            </li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
    </div>
</aside>
