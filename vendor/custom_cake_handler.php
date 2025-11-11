<?php
/*
 * CUSTOM CAKES HANDLER - UPDATED
 * Purpose: Handle vendor actions on custom cake requests
 * Actions: accept_request, reject_request, update_status
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$vendor_user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get vendor_id
$vendor_query = "SELECT vendor_id FROM Vendors WHERE user_id = $vendor_user_id";
$vendor_result = $conn->query($vendor_query);

if ($vendor_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    $conn->close();
    exit;
}

$vendor = $vendor_result->fetch_assoc();
$vendor_id = $vendor['vendor_id'];

$action = $_POST['action'] ?? '';

// Accept request
if ($action === 'accept_request' && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    
    // Verify request belongs to vendor's shop
    $verify_query = "SELECT cco.custom_order_id, cco.status
                     FROM Custom_Cake_Orders cco
                     JOIN Shops s ON cco.shop_id = s.shop_id
                     WHERE cco.custom_order_id = $request_id 
                     AND s.vendor_id = $vendor_id";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        $conn->close();
        exit;
    }
    
    $request = $verify_result->fetch_assoc();
    
    if ($request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Can only accept pending requests']);
        $conn->close();
        exit;
    }
    
    // Update status to confirmed
    $update_query = "UPDATE Custom_Cake_Orders 
                     SET status = 'confirmed' 
                     WHERE custom_order_id = $request_id";
    
    if ($conn->query($update_query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Order accepted successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to accept order']);
    }
}

// Reject request
elseif ($action === 'reject_request' && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    
    // Verify request belongs to vendor
    $verify_query = "SELECT cco.custom_order_id, cco.status
                     FROM Custom_Cake_Orders cco
                     JOIN Shops s ON cco.shop_id = s.shop_id
                     WHERE cco.custom_order_id = $request_id 
                     AND s.vendor_id = $vendor_id";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        $conn->close();
        exit;
    }
    
    $request = $verify_result->fetch_assoc();
    
    if ($request['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Can only reject pending requests']);
        $conn->close();
        exit;
    }
    
    $update_query = "UPDATE Custom_Cake_Orders 
                     SET status = 'cancelled' 
                     WHERE custom_order_id = $request_id";
    
    if ($conn->query($update_query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Request rejected successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject request']);
    }
}

// Update status (for in_progress and completed)
elseif ($action === 'update_status' && isset($_POST['request_id']) && isset($_POST['status'])) {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['status'];
    
    $allowed_statuses = ['in_progress', 'completed'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        $conn->close();
        exit;
    }
    
    // Verify request belongs to vendor
    $verify_query = "SELECT cco.custom_order_id, cco.status
                     FROM Custom_Cake_Orders cco
                     JOIN Shops s ON cco.shop_id = s.shop_id
                     WHERE cco.custom_order_id = $request_id 
                     AND s.vendor_id = $vendor_id";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        $conn->close();
        exit;
    }
    
    $request = $verify_result->fetch_assoc();
    
    // Validate transitions
    $valid_transitions = [
        'confirmed' => ['in_progress'],
        'in_progress' => ['completed']
    ];
    
    if (!isset($valid_transitions[$request['status']]) || 
        !in_array($new_status, $valid_transitions[$request['status']])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
        $conn->close();
        exit;
    }
    
    $update_query = "UPDATE Custom_Cake_Orders 
                     SET status = '$new_status' 
                     WHERE custom_order_id = $request_id";
    
    if ($conn->query($update_query)) {
        $messages = [
            'in_progress' => 'Status updated to In Progress',
            'completed' => 'Custom cake order marked as completed'
        ];
        
        echo json_encode([
            'success' => true,
            'message' => $messages[$new_status]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>