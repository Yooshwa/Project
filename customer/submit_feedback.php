<?php
/*
 * SUBMIT FEEDBACK HANDLER
 * Purpose: Process customer feedback/reviews for shops
 * Validation: Order completed, customer owns order, no duplicate feedback
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Only customers can submit feedback
if ($_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = trim($_POST['comment'] ?? '');

// Validation
if ($order_id === 0 || $shop_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Missing required information']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars']);
    exit;
}

$conn = getDBConnection();

// Verify order belongs to customer and is completed
$order_check = "SELECT o.order_id, o.status 
                FROM Orders o
                WHERE o.order_id = $order_id AND o.user_id = $user_id";
$order_result = $conn->query($order_check);

if ($order_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    $conn->close();
    exit;
}

$order = $order_result->fetch_assoc();

if ($order['status'] !== 'completed') {
    echo json_encode(['success' => false, 'message' => 'Can only review completed orders']);
    $conn->close();
    exit;
}

// Verify shop was part of this order
$shop_check = "SELECT DISTINCT s.shop_id 
               FROM Order_Items oi
               JOIN Products p ON oi.product_id = p.product_id
               JOIN Shops s ON p.shop_id = s.shop_id
               WHERE oi.order_id = $order_id AND s.shop_id = $shop_id";
$shop_result = $conn->query($shop_check);

if ($shop_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Shop not found in this order']);
    $conn->close();
    exit;
}

// Check if feedback already exists for this shop from this user
$duplicate_check = "SELECT feedback_id 
                    FROM Feedback 
                    WHERE user_id = $user_id AND shop_id = $shop_id";
$duplicate_result = $conn->query($duplicate_check);

if ($duplicate_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this shop']);
    $conn->close();
    exit;
}

// Escape comment for SQL
$comment_escaped = $conn->real_escape_string($comment);

// Insert feedback
$insert_query = "INSERT INTO Feedback (user_id, shop_id, rating, comment, created_at) 
                 VALUES ($user_id, $shop_id, $rating, '$comment_escaped', NOW())";

if ($conn->query($insert_query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback!'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit feedback. Please try again.'
    ]);
}

$conn->close();
?>