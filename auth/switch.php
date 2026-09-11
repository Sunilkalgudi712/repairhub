<?php
session_start();
require_once __DIR__ . '/../config.php';

$role = strtolower(trim($_GET['role'] ?? ''));

switch ($role) {
    case 'tech':
    case 'worker':
    case 'technician':
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'Technician' AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: ' . APP_URL . '/worker/dashboard.php');
            exit;
        }
        break;

    case 'client':
    case 'customer':
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'Client' AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: ' . APP_URL . '/client/dashboard.php');
            exit;
        }
        break;

    case 'admin':
    default:
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'Admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: ' . APP_URL . '/index.php');
            exit;
        }
        break;
}

header('Location: ' . APP_URL . '/index.php');
exit;
