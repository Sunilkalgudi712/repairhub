<?php
/**
 * RepairHub — Header (Top Navbar)
 * Included on every authenticated page
 */
$pageTitle = $pageTitle ?? 'RepairHub';

// Get current user info
$currentUser = [
    'name' => $_SESSION['user_name'] ?? 'User',
    'email' => $_SESSION['user_email'] ?? '',
    'role' => $_SESSION['user_role'] ?? 'Technician',
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <!-- Custom CSS -->
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <script>window.APP_URL = <?= json_encode(APP_URL) ?>;</script>
</head>
<body>

<!-- Top Navbar -->
<nav class="top-navbar">
    <div class="d-flex align-items-center gap-3">
        <!-- Sidebar Toggle (Mobile) -->
        <button class="btn btn-link text-white d-lg-none p-0" id="sidebarToggleBtn" aria-label="Toggle menu">
            <i class="fas fa-bars fa-lg"></i>
        </button>
        
        <!-- Brand (Mobile) -->
        <a href="<?= APP_URL ?>/index.php" class="text-white text-decoration-none d-lg-none fw-bold">
            <i class="fas fa-tools me-1"></i> <?= APP_NAME ?>
        </a>
    </div>
    
    <!-- Search Bar -->
    <div class="top-search d-none d-md-block position-relative">
        <form action="<?= APP_URL ?>/tickets/index.php" method="GET" class="m-0" id="globalSearchForm">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 text-white-50">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" class="form-control" id="globalSearch" name="search"
                       placeholder="Search tickets, customers, devices..." autocomplete="off">
            </div>
        </form>
        <div id="searchResults" class="search-results-dropdown"></div>
    </div>
    
    <!-- Right Side -->
    <div class="d-flex align-items-center gap-3">
        <!-- Quick Add -->
        <div class="dropdown">
            <button class="btn btn-sm btn-light rounded-pill" data-bs-toggle="dropdown" aria-label="Quick add">
                <i class="fas fa-plus me-1"></i> <span class="d-none d-md-inline">New</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= APP_URL ?>/tickets/create.php">
                    <i class="fas fa-ticket-alt me-2 text-primary"></i> New Ticket</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/customers/create.php">
                    <i class="fas fa-user-plus me-2 text-success"></i> New Customer</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/invoices/create.php">
                    <i class="fas fa-file-invoice me-2 text-info"></i> New Invoice</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/inventory/create.php">
                    <i class="fas fa-box me-2 text-warning"></i> Add Inventory</a></li>
            </ul>
        </div>
        
        <!-- Theme Toggle -->
        <button class="btn btn-sm btn-outline-light rounded-circle" id="themeToggle" title="Toggle theme" aria-label="Toggle theme">
            <i class="fas fa-moon" id="themeIcon"></i>
        </button>
        
        <!-- User Menu -->
        <div class="dropdown">
            <button class="btn btn-link text-white text-decoration-none dropdown-toggle p-0" 
                    data-bs-toggle="dropdown" aria-label="User menu">
                <div class="user-avatar-sm">
                    <?= getInitials($currentUser['name']) ?>
                </div>
                <span class="d-none d-md-inline ms-2"><?= htmlspecialchars($currentUser['name']) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="dropdown-header">
                    <strong><?= htmlspecialchars($currentUser['name']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($currentUser['role']) ?></small>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/worker/dashboard.php">
                    <i class="fas fa-tools me-2 text-warning"></i> Technician Panel</a></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/client/dashboard.php">
                    <i class="fas fa-user me-2 text-success"></i> Customer Portal</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= APP_URL ?>/settings/index.php">
                    <i class="fas fa-cog me-2 text-muted"></i> Settings</a></li>
                <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
