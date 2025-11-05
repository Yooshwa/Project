<?php
/*
 * PLACE ORDER HANDLER
 * Purpose: Process customer order - creates order, order items, payment record, reduces stock, clears cart
 * Flow: Checkout → place_order.php → Order Success
 * 
 * This file handles the complete order placement transaction:
 * 1. Validate cart and stock availability
 * 2. Create order in Orders table
 * 3. Create order items in Order_Items table
 * 4. Reduce product stock quantities
 * 5. Create payment record in Payments table
 * 6. Clear customer's cart
 * 7. Return order ID for confirmation page
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
$valid_methods = ['cash', 'card', 'upi', 'wallet'];
if (!in_array($payment_method, $valid_methods)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

$conn = getDBConnection();

// Start transaction - ensures all operations succeed or all fail together
$conn->begin_transaction();

try {
    // Step 1: Get cart items and validate stock availability
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
    
    // Check if cart is empty
    if ($cart_result->num_rows === 0) {
        throw new Exception('Your cart is empty');
    }
    
    $cart_items = [];
    $total_amount = 0;
    
    // Validate each item's stock availability
    while ($item = $cart_result->fetch_assoc()) {
        // Check if product has enough stock
        if ($item['stock_quantity'] < $item['cart_quantity']) {
            throw new Exception("Not enough stock for {$item['product_name']}. Only {$item['stock_quantity']} available.");
        }
        
        $cart_items[] = $item;
        $total_amount += $item['price'] * $item['cart_quantity'];
    }
    
    // Step 2: Create order in Orders table
    $order_query = "INSERT INTO Orders (user_id, total_amount, status, order_date) 
                    VALUES ($user_id, $total_amount, 'pending', NOW())";
    
    if (!$conn->query($order_query)) {
        throw new Exception('Failed to create order');
    }
    
    // Get the order ID that was just created
    $order_id = $conn->insert_id;
    
    // Step 3: Create order items and reduce stock
    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $quantity = $item['cart_quantity'];
        $price = $item['price'];
        
        // Insert into Order_Items table
        $order_item_query = "INSERT INTO Order_Items (order_id, product_id, quantity, price) 
                            VALUES ($order_id, $product_id, $quantity, $price)";
        
        if (!$conn->query($order_item_query)) {
            throw new Exception('Failed to create order items');
        }
        
        // Step 4: Reduce product stock (IMPORTANT!)
        $update_stock_query = "UPDATE Products 
                              SET quantity = quantity - $quantity 
                              WHERE product_id = $product_id";
        
        if (!$conn->query($update_stock_query)) {
            throw new Exception('Failed to update product stock');
        }
    }
    
    // Step 5: Create payment record in Payments table
    // Status is 'pending' for cash/card/upi/wallet (no actual payment processing yet)
    $payment_query = "INSERT INTO Payments (order_id, amount, method, status, payment_date) 
                     VALUES ($order_id, $total_amount, '$payment_method', 'pending', NOW())";
    
    if (!$conn->query($payment_query)) {
        throw new Exception('Failed to create payment record');
    }
    
    // Step 6: Clear the cart
    $clear_cart_query = "DELETE FROM Cart WHERE user_id = $user_id";
    
    if (!$conn->query($clear_cart_query)) {
        throw new Exception('Failed to clear cart');
    }
    
    // Commit the transaction - all operations successful!
    $conn->commit();
    
    // Return success with order ID
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction if any step fails
    // This ensures database consistency - either all changes happen or none
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>