<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Logout the user
$auth->logout();

// Redirect to login page
header('Location: ' . SITE_URL . '/login.php');
exit;
?>
