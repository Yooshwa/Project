<?php
/*
 * VENDOR PAYMENT STATUS HANDLER
 * Purpose: Update payment status for cash on delivery orders
 * Action: Vendor marks payment as 'completed' after receiving cash
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Only vendors can manage payments
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

// Mark payment as completed (for cash on delivery)
if ($action === 'mark_paid' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    
    // Verify order belongs to vendor's shop
    $verify_query = "SELECT o.order_id, p.method, p.status
                     FROM Orders o
                     JOIN Order_Items oi ON o.order_id = oi.order_id
                     JOIN Products pr ON oi.product_id = pr.product_id
                     JOIN Shops s ON pr.shop_id = s.shop_id
                     JOIN Payments p ON o.order_id = p.order_id
                     WHERE o.order_id = $order_id AND s.vendor_id = $vendor_id
                     LIMIT 1";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
        $conn->close();
        exit;
    }
    
    $payment_info = $verify_result->fetch_assoc();
    
    // Only allow marking pending payments as completed
    if ($payment_info['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Payment is already ' . $payment_info['status']]);
        $conn->close();
        exit;
    }
    
    // Update payment status
    $update_query = "UPDATE Payments 
                     SET status = 'completed', 
                         payment_date = NOW() 
                     WHERE order_id = $order_id";
    
    if ($conn->query($update_query)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Payment marked as completed'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update payment status'
        ]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>