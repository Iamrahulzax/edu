<?php
// Common admin authentication and header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?message=' . urlencode('Please login to access admin panel') . '&type=error');
    exit;
}

// Get user details and check admin role
$user = getUserById($_SESSION['user_id']);
if (!$user) {
    session_destroy();
    header('Location: ../login.php?message=' . urlencode('User not found') . '&type=error');
    exit;
}

if ($user['role'] !== 'admin') {
    header('Location: ../login.php?message=' . urlencode('Admin access required') . '&type=error');
    exit;
}

// User is authenticated as admin - continue with page
?>
