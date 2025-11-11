<?php
/*
 * VENDOR ORDERS HANDLER - Complete Version
 * Purpose: Update order status and handle cancellations with stock restoration
 * Actions: update_status, cancel_order
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Only vendors can manage orders
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

// Update order status (processing, completed)
if ($action === 'update_status' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $_POST['status'];
    
    // Validate status
    $allowed_statuses = ['processing', 'completed'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        $conn->close();
        exit;
    }
    
    // Verify order belongs to vendor
    $verify_query = "SELECT o.order_id, o.status as current_status
                     FROM Orders o
                     JOIN Order_Items oi ON o.order_id = oi.order_id
                     JOIN Products p ON oi.product_id = p.product_id
                     JOIN Shops s ON p.shop_id = s.shop_id
                     WHERE o.order_id = $order_id AND s.vendor_id = $vendor_id
                     LIMIT 1";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
        $conn->close();
        exit;
    }
    
    $order_info = $verify_result->fetch_assoc();
    
    // Validate status transition
    $valid_transitions = [
        'pending' => ['processing'],
        'processing' => ['completed']
    ];
    
    if (!isset($valid_transitions[$order_info['current_status']]) || 
        !in_array($new_status, $valid_transitions[$order_info['current_status']])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
        $conn->close();
        exit;
    }
    
    // Update order status
    $update_query = "UPDATE Orders SET status = '$new_status' WHERE order_id = $order_id";
    
    if ($conn->query($update_query)) {
        $status_messages = [
            'processing' => 'Order status updated to Processing',
            'completed' => 'Order marked as completed'
        ];
        
        echo json_encode([
            'success' => true,
            'message' => $status_messages[$new_status]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
    }
}

// Cancel order with stock restoration
elseif ($action === 'cancel_order' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Cancelled by vendor';
    
    // Verify order belongs to vendor and get current status
    $verify_query = "SELECT o.order_id, o.status
                     FROM Orders o
                     JOIN Order_Items oi ON o.order_id = oi.order_id
                     JOIN Products p ON oi.product_id = p.product_id
                     JOIN Shops s ON p.shop_id = s.shop_id
                     WHERE o.order_id = $order_id AND s.vendor_id = $vendor_id
                     LIMIT 1";
    
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
        $conn->close();
        exit;
    }
    
    $order_info = $verify_result->fetch_assoc();
    
    // Can't cancel already completed or cancelled orders
    if ($order_info['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Cannot cancel completed orders']);
        $conn->close();
        exit;
    }
    
    if ($order_info['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'Order is already cancelled']);
        $conn->close();
        exit;
    }
    
    // Start transaction for cancellation
    $conn->begin_transaction();
    
    try {
        // Get all order items for this vendor's products
        $items_query = "SELECT oi.product_id, oi.quantity
                        FROM Order_Items oi
                        JOIN Products p ON oi.product_id = p.product_id
                        JOIN Shops s ON p.shop_id = s.shop_id
                        WHERE oi.order_id = $order_id AND s.vendor_id = $vendor_id";
        
        $items_result = $conn->query($items_query);
        
        // Restore stock for each product
        while ($item = $items_result->fetch_assoc()) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];
            
            $restore_stock = "UPDATE Products 
                             SET quantity = quantity + $quantity 
                             WHERE product_id = $product_id";
            
            if (!$conn->query($restore_stock)) {
                throw new Exception('Failed to restore product stock');
            }
        }
        
        // Update order status to cancelled
        $cancel_query = "UPDATE Orders 
                        SET status = 'cancelled' 
                        WHERE order_id = $order_id";
        
        if (!$conn->query($cancel_query)) {
            throw new Exception('Failed to cancel order');
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Order cancelled successfully. Stock has been restored.'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>