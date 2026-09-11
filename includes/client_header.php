<?php
/**
 * RepairHub — Client Portal Header
 * Simplified top navbar for customers
 */
$pageTitle = $pageTitle ?? 'My Account';
$isAdminView = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'Admin');
$clientName = ($_SESSION['user_role'] === 'Client') ? ($_SESSION['user_name'] ?? 'Customer') : 'Rahul Sharma';
$currentUser = [
    'name' => $clientName,
    'email' => $_SESSION['user_email'] ?? 'client@repairhub.com',
    'role' => 'Customer'
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

<!-- Client Top Navbar -->
<nav class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link text-white d-lg-none p-0" id="sidebarToggleBtn" aria-label="Toggle menu">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        <a href="<?= APP_URL ?>/client/dashboard.php" class="text-white text-decoration-none d-lg-none fw-bold">
            <i class="fas fa-tools me-1"></i> <?= APP_NAME ?>
        </a>
    </div>

    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-50">
            <i class="fas fa-user me-1"></i> Customer Portal
        </span>
    </div>

    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-light rounded-circle" id="themeToggle" title="Toggle theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        <div class="dropdown">
            <button class="btn btn-link text-white text-decoration-none dropdown-toggle p-0" data-bs-toggle="dropdown">
                <div class="user-avatar-sm"><?= getInitials($currentUser['name']) ?></div>
                <span class="d-none d-md-inline ms-2"><?= htmlspecialchars($currentUser['name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="dropdown-header">
                    <strong><?= htmlspecialchars($currentUser['name']) ?></strong><br>
                    <small class="text-muted">Customer</small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/client/profile.php"><i class="fas fa-user-edit me-2"></i> My Profile</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/client/my_repairs.php"><i class="fas fa-laptop me-2"></i> My Repairs</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/client/my_invoices.php"><i class="fas fa-file-invoice me-2"></i> My Invoices</a></li>
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
