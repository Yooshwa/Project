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

// Add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if ($quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
        exit;
    }
    
    // Check if product exists and has stock
    $check_query = "SELECT quantity FROM Products WHERE product_id = $product_id";
    $result = $conn->query($check_query);
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    $product = $result->fetch_assoc();
    
    if ($product['quantity'] < $quantity) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }
    
    // Check if product already in cart
    $cart_check = "SELECT cart_id, quantity FROM Cart WHERE user_id = $user_id AND product_id = $product_id";
    $cart_result = $conn->query($cart_check);
    
    if ($cart_result->num_rows > 0) {
        // Update quantity
        $cart_item = $cart_result->fetch_assoc();
        $new_quantity = $cart_item['quantity'] + $quantity;
        
        if ($new_quantity > $product['quantity']) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            exit;
        }
        
        $update_query = "UPDATE Cart SET quantity = $new_quantity WHERE cart_id = " . $cart_item['cart_id'];
        if ($conn->query($update_query)) {
            echo json_encode(['success' => true, 'message' => 'Cart updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
        }
    } else {
        // Insert new item
        $insert_query = "INSERT INTO Cart (user_id, product_id, quantity) VALUES ($user_id, $product_id, $quantity)";
        if ($conn->query($insert_query)) {
            echo json_encode(['success' => true, 'message' => 'Added to cart']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
        }
    }
}

$conn->close();
?>