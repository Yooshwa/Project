<?php
/**
 * Include this file at the top of protected pages to check authentication
 * Usage: require_once '../config/auth_check.php';
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../auth/signin.php");
    exit;
}

// Function to check if user has specific role
function checkRole($required_role) {
    if ($_SESSION['role'] !== $required_role) {
        header("Location: ../index.php");
        exit;
    }
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to check if user is vendor
function isVendor() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'vendor';
}

// Function to check if user is customer
function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

// Get current user info
function getCurrentUser() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}
?>