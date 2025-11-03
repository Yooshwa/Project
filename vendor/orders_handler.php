<?php
ob_start();

require_once '../config/auth_check.php';

ob_clean();
header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['role'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get action type
$action = $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

require_once '../config/database.php';
$conn = getDBConnection();

// Get vendor information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT vendor_id, status FROM Vendors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();

if (!$vendor || $vendor['status'] !== 'approved') {
    echo json_encode(['success' => false, 'message' => 'Vendor not approved']);
    $conn->close();
    exit;
}

$vendor_id = $vendor['vendor_id'];

// Handle different actions
switch ($action) {
    case 'update_status':
        updateOrderStatus($conn, $vendor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
ob_end_flush();

// ============================================
// UPDATE ORDER STATUS FUNCTION
// ============================================
function updateOrderStatus($conn, $vendor_id) {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    // Validate inputs
    if (empty($order_id) || empty($new_status)) {
        echo json_encode(['success' => false, 'message' => 'Order ID and status are required']);
        return;
    }
    
    // Validate status values
    $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    // Verify order contains vendor's products
    $stmt = $conn->prepare("SELECT DISTINCT o.order_id 
                           FROM Orders o
                           JOIN Order_Items oi ON o.order_id = oi.order_id
                           JOIN Products p ON oi.product_id = p.product_id
                           JOIN Shops s ON p.shop_id = s.shop_id
                           WHERE o.order_id = ? AND s.vendor_id = ?");
    $stmt->bind_param("ii", $order_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or unauthorized']);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Update order status
    $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE order_id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    
    if ($stmt->execute()) {
        $status_messages = [
            'processing' => 'Order is now being processed!',
            'completed' => 'Order marked as completed!',
            'cancelled' => 'Order has been cancelled!'
        ];
        
        $message = $status_messages[$new_status] ?? 'Order status updated!';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
    }
    
    $stmt->close();
}
?>