<?php
/*
 * UPDATE ADDRESS HANDLER
 * Purpose: Allow customer to update delivery address during checkout
 * Called from: checkout.php via AJAX
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Only customers can update address
if ($_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'update_address' && isset($_POST['address'])) {
    $new_address = trim($_POST['address']);
    
    // Validate address
    if (empty($new_address)) {
        echo json_encode(['success' => false, 'message' => 'Address cannot be empty']);
        exit;
    }
    
    $conn = getDBConnection();
    
    // Escape special characters to prevent SQL injection
    $new_address = $conn->real_escape_string($new_address);
    
    // Update address in Users table
    $update_query = "UPDATE Users SET address = '$new_address' WHERE user_id = $user_id";
    
    if ($conn->query($update_query)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Address updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update address'
        ]);
    }
    
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>