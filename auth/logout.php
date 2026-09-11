<?php
session_start();
session_unset();
session_destroy();

session_start();
$_SESSION['flash_message'] = "You have been logged out.";
$_SESSION['flash_type'] = "success";

header("Location: " . APP_URL . "/auth/login.php");
exit;
