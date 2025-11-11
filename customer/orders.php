<?php
/*
 * CUSTOMER ORDERS PAGE - WITH FEEDBACK FEATURE
 * Purpose: Display customer's order history with tracking and feedback option
 * Features: Order list, status filtering, order details, leave feedback for completed orders
 */

require_once '../config/auth_check.php';

if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

require_once '../config/database.php';
$conn = getDBConnection();

$status_filter = $_GET['status'] ?? 'all';

// Build orders query with filter
$orders_query = "SELECT 
    o.order_id,
    o.total_amount,
    o.status,
    o.order_date,
    p.method as payment_method,
    p.status as payment_status,
    COUNT(oi.order_item_id) as item_count
FROM Orders o
LEFT JOIN Payments p ON o.order_id = p.order_id
LEFT JOIN Order_Items oi ON o.order_id = oi.order_id
WHERE o.user_id = $user_id";

if ($status_filter !== 'all') {
    $status_filter_escaped = $conn->real_escape_string($status_filter);
    $orders_query .= " AND o.status = '$status_filter_escaped'";
}

$orders_query .= " GROUP BY o.order_id, o.total_amount, o.status, o.order_date, p.method, p.status
                   ORDER BY o.order_date DESC";

$orders_result = $conn->query($orders_query);
$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    $items_query = "SELECT 
        oi.quantity,
        oi.price,
        p.product_name,
        p.image,
        s.shop_name,
        s.shop_id
    FROM Order_Items oi
    JOIN Products p ON oi.product_id = p.product_id
    JOIN Shops s ON p.shop_id = s.shop_id
    WHERE oi.order_id = " . $order['order_id'];
    
    $items_result = $conn->query($items_query);
    $order['items'] = [];
    $order['shops'] = []; // Track unique shops in this order
    
    while ($item = $items_result->fetch_assoc()) {
        $order['items'][] = $item;
        if (!in_array($item['shop_id'], array_column($order['shops'], 'shop_id'))) {
            $order['shops'][] = [
                'shop_id' => $item['shop_id'],
                'shop_name' => $item['shop_name']
            ];
        }
    }
    
    // Check if feedback already given for shops in this order
    if ($order['status'] === 'completed' && !empty($order['shops'])) {
        $shop_ids = implode(',', array_column($order['shops'], 'shop_id'));
        $feedback_check = "SELECT shop_id FROM Feedback 
                          WHERE user_id = $user_id 
                          AND shop_id IN ($shop_ids)";
        $feedback_result = $conn->query($feedback_check);
        
        $feedback_given = [];
        while ($fb = $feedback_result->fetch_assoc()) {
            $feedback_given[] = $fb['shop_id'];
        }
        
        $order['feedback_given'] = $feedback_given;
    }
    
    $orders[] = $order;
}

// Get cart count
$cart_query = "SELECT IFNULL(SUM(quantity), 0) as cart_count FROM Cart WHERE user_id = $user_id";
$cart_result = $conn->query($cart_query);
$cart_count = $cart_result->fetch_assoc()['cart_count'];

// Get order statistics
$stats_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
FROM Orders 
WHERE user_id = $user_id";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Sweetkart</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #fff5f7 0%, #ffe8ec 100%);
            min-height: 100vh;
        }

        .navbar {
            background: white;
            padding: 1rem 5%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b9d;
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .navbar-menu a {
            text-decoration: none;
            color: #5a3e36;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
            position: relative;
        }

        .navbar-menu a:hover {
            background: #fff5f7;
            color: #ff6b9d;
        }

        .navbar-menu a.active {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #f44336;
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
        }

        .navbar-user {
            position: relative;
        }

        .user-profile-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: white;
            border: 2px solid #ff6b9d;
            color: #5a3e36;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .dropdown-arrow {
            font-size: 0.7rem;
            transition: transform 0.3s;
        }

        .user-profile-btn.active .dropdown-arrow {
            transform: rotate(180deg);
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            display: none;
        }

        .user-dropdown.show {
            display: block;
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #ffe8ec;
        }

        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .dropdown-menu {
            padding: 0.5rem 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #5a3e36;
            text-decoration: none;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background: #fff5f7;
        }

        .dropdown-item.logout {
            color: #dc3545;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #5a3e36;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            color: #5a3e36;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            gap: 0.5rem;
        }

        .filter-tab:hover {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-color: #ff6b9d;
        }

        .tab-count {
            background: rgba(255, 255, 255, 0.3);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .order-header {
            background: #fff5f7;
            padding: 1.5rem;
            border-bottom: 2px solid #ffe8ec;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-info {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cfe2ff;
            color: #084298;
        }

        .status-completed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #842029;
        }

        .order-progress {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 2px solid #ffe8ec;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e0e0e0;
        }

        .progress-line-fill {
            height: 100%;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            transition: width 0.5s;
        }

        .progress-step {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .progress-step.active .step-circle,
        .progress-step.completed .step-circle {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-color: #ff6b9d;
        }

        .step-label {
            color: #7a5f57;
            font-size: 0.85rem;
        }

        .order-items {
            padding: 1.5rem;
        }

        .order-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #ffe8ec;
        }

        .item-image-container {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-image {
            font-size: 2.5rem;
        }

        .item-price {
            color: #ff6b9d;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .order-footer {
            padding: 1.5rem;
            background: #fff5f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-amount {
            color: #ff6b9d;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .btn-feedback {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #ffa500 0%, #ffb733 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-feedback:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 165, 0, 0.3);
        }

        .feedback-given {
            color: #0f5132;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Feedback Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: #fff5f7;
            padding: 1.5rem;
            border-bottom: 2px solid #ffe8ec;
            text-align: center;
        }

        .modal-title {
            color: #5a3e36;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-body {
            padding: 2rem;
        }

        .shop-selection {
            margin-bottom: 2rem;
        }

        .shop-option {
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .shop-option:hover {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .shop-option.selected {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .star-rating {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            font-size: 2.5rem;
            margin: 1.5rem 0;
        }

        .star {
            cursor: pointer;
            transition: all 0.2s;
            color: #ddd;
        }

        .star.active {
            color: #ffa500;
        }

        .star:hover {
            transform: scale(1.2);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b9d;
        }

        .modal-footer {
            padding: 1.5rem;
            background: #f8f9fa;
            display: flex;
            gap: 1rem;
            border-radius: 0 0 15px 15px;
        }

        .btn-cancel {
            flex: 1;
            padding: 1rem;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit {
            flex: 2;
            padding: 1rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .no-orders {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .order-item {
                grid-template-columns: 60px 1fr;
            }
            .item-price {
                grid-column: 2;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="products.php" class="navbar-brand"> Sweetkart</a>
        <ul class="navbar-menu">
            <li><a href="products.php"> Products</a></li>
            <li><a href="shops.php"> Shops</a></li>
            <li><a href="custom_cakes.php"> Custom Cakes</a></li>
            <li><a href="orders.php" class="active"> Orders</a></li>
            <li>
                <a href="cart.php">
                    Cart
                    <?php if ($cart_count > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
        </ul>
        <div class="navbar-user">
            <button class="user-profile-btn" onclick="toggleDropdown()">
                <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($user_name); ?></span>
                <span class="dropdown-arrow">▼</span>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <p><?php echo htmlspecialchars($user_name); ?></p>
                    <span><?php echo htmlspecialchars($user_email); ?></span>
                    <div class="user-badge">⚪ CUSTOMER</div>
                </div>
                <div class="dropdown-menu">
                    <a href="../auth/logout.php" class="dropdown-item logout">
                        <span>➜</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>📦 My Orders</h1>
            <p>Track and manage your orders</p>
            
            <div class="filter-tabs">
                <a href="orders.php?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Orders <span class="tab-count"><?php echo $stats['total_orders']; ?></span>
                </a>
                <a href="orders.php?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="tab-count"><?php echo $stats['pending_count']; ?></span>
                </a>
                <a href="orders.php?status=processing" class="filter-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
                    Processing <span class="tab-count"><?php echo $stats['processing_count']; ?></span>
                </a>
                <a href="orders.php?status=completed" class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    Completed <span class="tab-count"><?php echo $stats['completed_count']; ?></span>
                </a>
                <a href="orders.php?status=cancelled" class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    Cancelled <span class="tab-count"><?php echo $stats['cancelled_count']; ?></span>
                </a>
            </div>
        </div>

        <?php if (count($orders) > 0): ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): 
                $progress_width = 0;
                if ($order['status'] === 'pending') $progress_width = 0;
                elseif ($order['status'] === 'processing') $progress_width = 50;
                elseif ($order['status'] === 'completed') $progress_width = 100;
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-info">
                        <div style="font-weight: 600; color: #5a3e36;">Order #<?php echo $order['order_id']; ?></div>
                        <div style="color: #7a5f57;">📅 <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
                    </div>
                    <div class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </div>
                </div>

                <?php if ($order['status'] !== 'cancelled'): ?>
                <div class="order-progress">
                    <div class="progress-steps">
                        <div class="progress-line">
                            <div class="progress-line-fill" style="width: <?php echo $progress_width; ?>%;"></div>
                        </div>
                        <div class="progress-step <?php echo ($order['status'] === 'pending' || $order['status'] === 'processing' || $order['status'] === 'completed') ? 'completed' : ''; ?>">
                            <div class="step-circle">✓</div>
                            <div class="step-label">Placed</div>
                        </div>
                        <div class="progress-step <?php echo ($order['status'] === 'processing') ? 'active' : ($order['status'] === 'completed' ? 'completed' : ''); ?>">
                            <div class="step-circle"><?php echo ($order['status'] === 'completed') ? '✓' : '2'; ?></div>
                            <div class="step-label">Processing</div>
                        </div>
                        <div class="progress-step <?php echo ($order['status'] === 'completed') ? 'completed' : ''; ?>">
                            <div class="step-circle"><?php echo ($order['status'] === 'completed') ? '✓' : '3'; ?></div>
                            <div class="step-label">Completed</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Cancellation Message for Cancelled Orders -->
                <div style="padding: 1.5rem; background: #fff3cd; border-left: 4px solid #ffa500;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <span style="font-size: 1.5rem;">⚠️</span>
                        <strong style="color: #856404; font-size: 1.1rem;">Order Cancelled</strong>
                    </div>
                    <?php if ($order['payment_method'] === 'card' && $order['payment_status'] === 'completed'): ?>
                        <p style="color: #856404; line-height: 1.6; margin: 0;">
                            Your amount will be refunded within 5-7 business days. Sorry, we couldn't take your order at the moment.
                        </p>
                    <?php else: ?>
                        <p style="color: #856404; line-height: 1.6; margin: 0;">
                            Sorry, we couldn't take your order at the moment.
                        </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="order-items">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="order-item">
                        <div class="item-image-container">
                            <?php if (!empty($item['image'])): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                     class="item-image">
                            <?php else: ?>
                                <div class="no-image">🧁</div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: #5a3e36; margin-bottom: 0.25rem;">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </div>
                            <div style="color: #7a5f57; font-size: 0.9rem;">
                                🪙 <?php echo htmlspecialchars($item['shop_name']); ?>
                            </div>
                            <div style="color: #7a5f57; font-size: 0.9rem;">
                                Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?>
                            </div>
                        </div>
                        <div class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-footer">
                    <div>
                        <div style="color: #7a5f57; margin-bottom: 0.5rem;">Total Amount</div>
                        <div class="total-amount">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                    </div>
                    
                    <?php if ($order['status'] === 'completed'): ?>
                        <?php 
                        // Check if any shop in this order hasn't been reviewed
                        $can_review = false;
                        foreach ($order['shops'] as $shop) {
                            if (!in_array($shop['shop_id'], $order['feedback_given'] ?? [])) {
                                $can_review = true;
                                break;
                            }
                        }
                        ?>
                        
                        <?php if ($can_review): ?>
                            <button class="btn-feedback" onclick='openFeedbackModal(<?php echo json_encode($order); ?>)'>
                                ⭐ Leave Feedback
                            </button>
                        <?php else: ?>
                            <div class="feedback-given">
                                ✅ Feedback Submitted
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-orders">
            <div style="font-size: 5rem; margin-bottom: 1rem;">📦</div>
            <h3 style="color: #5a3e36; margin-bottom: 0.5rem;">No Orders Found</h3>
            <p style="color: #7a5f57;">Start shopping to see your orders here!</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">⭐ Rate Your Experience</div>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <input type="hidden" id="orderId" name="order_id">
                    
                    <div class="shop-selection" id="shopSelection">
                        <label style="display: block; color: #5a3e36; font-weight: 600; margin-bottom: 1rem;">
                            Select Shop to Review:
                        </label>
                    </div>
                    
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <label style="color: #5a3e36; font-weight: 600;">Your Rating:</label>
                    </div>
                    <div class="star-rating" id="starRating">
                        <span class="star" data-rating="1" onclick="setRating(1)">⭐</span>
                        <span class="star" data-rating="2" onclick="setRating(2)">⭐</span>
                        <span class="star" data-rating="3" onclick="setRating(3)">⭐</span>
                        <span class="star" data-rating="4" onclick="setRating(4)">⭐</span>
                        <span class="star" data-rating="5" onclick="setRating(5)">⭐</span>
                    </div>
                    <input type="hidden" id="rating" name="rating" required>
                    <input type="hidden" id="shopId" name="shop_id" required>
                    
                    <div class="form-group">
                        <label for="comment">Your Review (Optional):</label>
                        <textarea id="comment" name="comment" placeholder="Share your experience with this shop..." maxlength="500"></textarea>
                        <div style="text-align: right; color: #7a5f57; font-size: 0.85rem; margin-top: 0.25rem;">
                            <span id="charCount">0</span>/500 characters
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeFeedbackModal()">Cancel</button>
                <button type="button" class="btn-submit" onclick="submitFeedback()">Submit Feedback</button>
            </div>
        </div>
    </div>

    <script>
        let selectedRating = 0;
        let selectedShopId = null;
        let feedbackGivenShops = [];

        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        function openFeedbackModal(orderData) {
            const order = typeof orderData === 'string' ? JSON.parse(orderData) : orderData;
            
            document.getElementById('orderId').value = order.order_id;
            feedbackGivenShops = order.feedback_given || [];
            
            // Populate shop selection
            const shopSelection = document.getElementById('shopSelection');
            shopSelection.innerHTML = '<label style="display: block; color: #5a3e36; font-weight: 600; margin-bottom: 1rem;">Select Shop to Review:</label>';
            
            order.shops.forEach(shop => {
                if (!feedbackGivenShops.includes(shop.shop_id)) {
                    const shopOption = document.createElement('div');
                    shopOption.className = 'shop-option';
                    shopOption.innerHTML = `
                        <input type="radio" name="shop_select" value="${shop.shop_id}" style="margin-right: 0.5rem;">
                        <strong>🪙 ${shop.shop_name}</strong>
                    `;
                    shopOption.onclick = function() {
                        document.querySelectorAll('.shop-option').forEach(o => o.classList.remove('selected'));
                        shopOption.classList.add('selected');
                        shopOption.querySelector('input').checked = true;
                        selectedShopId = shop.shop_id;
                        document.getElementById('shopId').value = shop.shop_id;
                    };
                    shopSelection.appendChild(shopOption);
                }
            });
            
            // Reset form
            selectedRating = 0;
            selectedShopId = null;
            document.getElementById('rating').value = '';
            document.getElementById('shopId').value = '';
            document.getElementById('comment').value = '';
            document.getElementById('charCount').textContent = '0';
            document.querySelectorAll('.star').forEach(star => star.classList.remove('active'));
            
            document.getElementById('feedbackModal').style.display = 'block';
        }

        function closeFeedbackModal() {
            document.getElementById('feedbackModal').style.display = 'none';
        }

        function setRating(rating) {
            selectedRating = rating;
            document.getElementById('rating').value = rating;
            
            document.querySelectorAll('.star').forEach((star, index) => {
                if (index < rating) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
        }

        // Character counter for comment
        document.getElementById('comment').addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });

        function submitFeedback() {
            if (!selectedShopId) {
                alert('Please select a shop to review');
                return;
            }
            
            if (selectedRating === 0) {
                alert('Please select a rating (1-5 stars)');
                return;
            }

            const formData = new FormData(document.getElementById('feedbackForm'));
            
            const btn = document.querySelector('.btn-submit');
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            fetch('submit_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    closeFeedbackModal();
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = 'Submit Feedback';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Submit Feedback';
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('feedbackModal');
            if (event.target == modal) {
                closeFeedbackModal();
            }
        }
    </script>
</body>
</html>