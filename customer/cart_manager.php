<?php
require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$action = $_POST['action'] ?? '';

// Update cart item quantity
if ($action === 'update' && isset($_POST['cart_id']) && isset($_POST['quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $quantity = intval($_POST['quantity']);
    
    if ($quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        exit;
    }
    
    // Verify cart item belongs to user and check stock
    $check_query = "SELECT c.product_id, p.quantity as stock 
                    FROM Cart c 
                    JOIN Products p ON c.product_id = p.product_id 
                    WHERE c.cart_id = $cart_id AND c.user_id = $user_id";
    $result = $conn->query($check_query);
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Cart item not found']);
        exit;
    }
    
    $item = $result->fetch_assoc();
    
    if ($quantity > $item['stock']) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }
    
    $update_query = "UPDATE Cart SET quantity = $quantity WHERE cart_id = $cart_id";
    
    if ($conn->query($update_query)) {
        echo json_encode(['success' => true, 'message' => 'Cart updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
    }
}

// Remove item from cart
elseif ($action === 'remove' && isset($_POST['cart_id'])) {
    $cart_id = intval($_POST['cart_id']);
    
    $delete_query = "DELETE FROM Cart WHERE cart_id = $cart_id AND user_id = $user_id";
    
    if ($conn->query($delete_query)) {
        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
    }
}

// Clear entire cart
elseif ($action === 'clear') {
    $delete_query = "DELETE FROM Cart WHERE user_id = $user_id";
    
    if ($conn->query($delete_query)) {
        echo json_encode(['success' => true, 'message' => 'Cart cleared']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear cart']);
    }
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>