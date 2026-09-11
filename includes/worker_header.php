<?php
/**
 * RepairHub — Worker Panel Header
 */
$pageTitle = $pageTitle ?? 'Worker Panel';
$isAdminView = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin');
$workerName = ($_SESSION['user_role'] === 'Technician') ? ($_SESSION['user_name'] ?? 'Alex Technician') : 'Alex Technician';
$currentUser = [
    'name' => $workerName,
    'email' => $_SESSION['user_email'] ?? 'tech@repairhub.com',
    'role' => 'Technician'
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="top-navbar" style="background: #1a4731;">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link text-white d-lg-none p-0" id="sidebarToggleBtn" aria-label="Toggle menu">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <a href="<?= APP_URL ?>/worker/dashboard.php" class="text-white text-decoration-none d-lg-none fw-bold">
            <i class="fas fa-tools me-1"></i> <?= APP_NAME ?>
        </a>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-warning text-dark border border-warning fw-semibold">
            <i class="fas fa-wrench me-1"></i> Technician Panel
        </span>
    </div>

    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-light rounded-circle" id="themeToggle" title="Toggle theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <div class="dropdown">
            <button class="btn btn-link text-white text-decoration-none dropdown-toggle p-0" data-bs-toggle="dropdown">
                <div class="user-avatar-sm" style="background:#f59e0b;"><?= getInitials($currentUser['name']) ?></div>
                <span class="d-none d-md-inline ms-2"><?= htmlspecialchars($currentUser['name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="dropdown-header">
                    <strong><?= htmlspecialchars($currentUser['name']) ?></strong><br>
                    <small class="text-muted">Assigned Technician</small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/worker/my_tasks.php"><i class="fas fa-clipboard-list me-2"></i> My Tasks</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/worker/availability.php"><i class="fas fa-calendar-check me-2"></i> My Availability</a></li>
                <?php if ($isAdminView): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-primary" href="<?= APP_URL ?>/index.php"><i class="fas fa-user-shield me-2"></i> Return to Admin Panel</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
