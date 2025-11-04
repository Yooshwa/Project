<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is customer
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

// Redirect customers directly to products page (no dashboard needed)
header("Location: products.php");
exit;
?>