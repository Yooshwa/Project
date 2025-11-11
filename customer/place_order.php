<?php
/*
 * PLACE ORDER HANDLER - UPDATED
 * Purpose: Process customer order with payment handling
 * Payment Logic:
 *   - Cash: Payment status = 'pending' (to be paid on delivery)
 *   - Card: Payment status = 'completed' (paid immediately)
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Only customers can place orders
if ($_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_method = $_POST['payment_method'] ?? 'cash';

// Validate payment method
$valid_methods = ['cash', 'card'];
if (!in_array($payment_method, $valid_methods)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

$conn = getDBConnection();

// Start transaction
$conn->begin_transaction();

try {
    // Step 1: Get cart items and validate stock
    $cart_query = "SELECT 
        c.cart_id,
        c.product_id,
        c.quantity as cart_quantity,
        p.product_name,
        p.price,
        p.quantity as stock_quantity
    FROM Cart c
    JOIN Products p ON c.product_id = p.product_id
    WHERE c.user_id = $user_id";
    
    $cart_result = $conn->query($cart_query);
    
    if ($cart_result->num_rows === 0) {
        throw new Exception('Your cart is empty');
    }
    
    $cart_items = [];
    $total_amount = 0;
    
    while ($item = $cart_result->fetch_assoc()) {
        if ($item['stock_quantity'] < $item['cart_quantity']) {
            throw new Exception("Not enough stock for {$item['product_name']}. Only {$item['stock_quantity']} available.");
        }
        
        $cart_items[] = $item;
        $total_amount += $item['price'] * $item['cart_quantity'];
    }
    
    // Step 2: Create order
    $order_query = "INSERT INTO Orders (user_id, total_amount, status, order_date) 
                    VALUES ($user_id, $total_amount, 'pending', NOW())";
    
    if (!$conn->query($order_query)) {
        throw new Exception('Failed to create order');
    }
    
    $order_id = $conn->insert_id;
    
    // Step 3: Create order items and reduce stock
    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['cart_quantity'];
        $price = $item['price'];
        
        // Insert order item
        $order_item_query = "INSERT INTO Order_Items (order_id, product_id, quantity, price) 
                            VALUES ($order_id, $product_id, $quantity, $price)";
        
        if (!$conn->query($order_item_query)) {
            throw new Exception('Failed to create order items');
        }
        
        // Reduce stock
        $update_stock_query = "UPDATE Products 
                              SET quantity = quantity - $quantity 
                              WHERE product_id = $product_id";
        
        if (!$conn->query($update_stock_query)) {
            throw new Exception('Failed to update product stock');
        }
    }
    // Step 4: Create payment record
    // PAYMENT STATUS LOGIC:
    // - Card payment: 'completed' (payment received immediately)
    // - Cash payment: 'pending' (to be collected on delivery)
    $payment_status = ($payment_method === 'card') ? 'completed' : 'pending';
    
    $payment_query = "INSERT INTO Payments (order_id, amount, method, status, payment_date) 
                     VALUES ($order_id, $total_amount, '$payment_method', '$payment_status', NOW())";
    
    if (!$conn->query($payment_query)) {
        throw new Exception('Failed to create payment record');
    }
    
    // Step 5: Clear cart
    $clear_cart_query = "DELETE FROM Cart WHERE user_id = $user_id";
    
    if (!$conn->query($clear_cart_query)) {
        throw new Exception('Failed to clear cart');
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id,
        'payment_method' => $payment_method,
        'payment_status' => $payment_status
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
$conn->close();
?>