<?php
/*
 * ORDERS PAGE
 * Purpose: Display customer's order history with tracking and status
 * Features: Order list, status filtering, order details, tracking timeline
 */

require_once '../config/auth_check.php';

// Only customers can access
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

// Get user info
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

// Get database connection
require_once '../config/database.php';
$conn = getDBConnection();

// Get filter parameter
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

// Apply status filter
if ($status_filter !== 'all') {
    $status_filter_escaped = $conn->real_escape_string($status_filter);
    $orders_query .= " AND o.status = '$status_filter_escaped'";
}

$orders_query .= " GROUP BY o.order_id, o.total_amount, o.status, o.order_date, p.method, p.status
                   ORDER BY o.order_date DESC";

$orders_result = $conn->query($orders_query);
$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    // Get items for this order
    $items_query = "SELECT 
        oi.quantity,
        oi.price,
        p.product_name,
        p.image,
        s.shop_name
    FROM Order_Items oi
    JOIN Products p ON oi.product_id = p.product_id
    JOIN Shops s ON p.shop_id = s.shop_id
    WHERE oi.order_id = " . $order['order_id'];
    
    $items_result = $conn->query($items_query);
    $order['items'] = [];
    
    while ($item = $items_result->fetch_assoc()) {
        $order['items'][] = $item;
    }
    
    $orders[] = $order;
}

// Get cart count for badge
$cart_query = "SELECT IFNULL(SUM(quantity), 0) as cart_count FROM Cart WHERE user_id = $user_id";
$cart_result = $conn->query($cart_query);
$cart_count = $cart_result->fetch_assoc()['cart_count'];

// Get order statistics for filter badges
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

        /* Navbar styles */
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
            min-width: 18px;
            text-align: center;
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
            transition: all 0.3s;
        }

        .user-profile-btn:hover {
            background: #fff5f7;
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
            font-size: 0.9rem;
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
            z-index: 1000;
        }

        .user-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #ffe8ec;
        }

        .dropdown-header p {
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .dropdown-header span {
            color: #7a5f57;
            font-size: 0.85rem;
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

        .dropdown-item.logout:hover {
            background: #ffe8ec;
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

        .page-header p {
            color: #7a5f57;
        }

        /* Filter Tabs */
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
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
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
            font-size: 0.85rem;
        }

        .filter-tab.active .tab-count {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Order Cards */
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
            transition: all 0.3s;
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .order-header {
            background: #fff5f7;
            padding: 1.5rem;
            border-bottom: 2px solid #ffe8ec;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .order-id {
            color: #5a3e36;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .order-date {
            color: #7a5f57;
            font-size: 0.9rem;
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

        /* Order Progress Timeline */
        .order-progress {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 2px solid #ffe8ec;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .progress-line {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e0e0e0;
            z-index: 0;
        }

        .progress-line-fill {
            height: 100%;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            transition: width 0.5s ease;
        }

        .progress-step {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
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
            transition: all 0.3s;
        }

        .progress-step.active .step-circle {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-color: #ff6b9d;
        }

        .progress-step.completed .step-circle {
            background: #4caf50;
            color: white;
            border-color: #4caf50;
        }

        .step-label {
            color: #7a5f57;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
        }

        .progress-step.active .step-label,
        .progress-step.completed .step-label {
            color: #5a3e36;
            font-weight: 600;
        }

        /* Order Items */
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

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image-container {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
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
            color: #ddd;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-shop {
            color: #7a5f57;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-quantity {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .item-price {
            color: #ff6b9d;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: right;
        }

        /* Order Footer */
        .order-footer {
            padding: 1.5rem;
            background: #fff5f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-total {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .total-label {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .total-amount {
            color: #ff6b9d;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .payment-info {
            text-align: right;
        }

        .payment-method {
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .payment-status {
            font-size: 0.85rem;
        }

        /* Empty State */
        .no-orders {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-orders-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .no-orders h3 {
            color: #5a3e36;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .no-orders p {
            color: #7a5f57;
            margin-bottom: 2rem;
        }

        .btn-shop-now {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-shop-now:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-footer {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .payment-info {
                text-align: left;
            }

            .progress-steps {
                flex-wrap: wrap;
                gap: 1rem;
            }

            .progress-line {
                display: none;
            }

            .order-item {
                grid-template-columns: 60px 1fr;
            }

            .item-price {
                grid-column: 2;
                text-align: left;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="products.php" class="navbar-brand">🧁 Sweetkart</a>
        <ul class="navbar-menu">
            <li><a href="products.php">🧁 Products</a></li>
            <li><a href="shops.php">🪙 Shops</a></li>
            <li><a href="custom_cakes.php">🎂 Custom Cakes</a></li>
            <li><a href="orders.php" class="active">📦 Orders</a></li>
            <li>
                <a href="cart.php">
                    🛒 Cart
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
                    <div class="user-badge">🛒 CUSTOMER</div>
                </div>
                <div class="dropdown-menu">
                    <a href="../auth/logout.php" class="dropdown-item logout">
                        <span>🚪</span> Logout
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
                    All Orders
                    <span class="tab-count"><?php echo $stats['total_orders']; ?></span>
                </a>
                <a href="orders.php?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending
                    <span class="tab-count"><?php echo $stats['pending_count']; ?></span>
                </a>
                <a href="orders.php?status=processing" class="filter-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
                    Processing
                    <span class="tab-count"><?php echo $stats['processing_count']; ?></span>
                </a>
                <a href="orders.php?status=completed" class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    Completed
                    <span class="tab-count"><?php echo $stats['completed_count']; ?></span>
                </a>
                <a href="orders.php?status=cancelled" class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    Cancelled
                    <span class="tab-count"><?php echo $stats['cancelled_count']; ?></span>
                </a>
            </div>
        </div>

        <?php if (count($orders) > 0): ?>
        <div class="orders-list">
            <?php foreach ($orders as $order): 
                // Calculate progress percentage for timeline
                $progress_width = 0;
                if ($order['status'] === 'pending') $progress_width = 0;
                elseif ($order['status'] === 'processing') $progress_width = 50;
                elseif ($order['status'] === 'completed') $progress_width = 100;
                elseif ($order['status'] === 'cancelled') $progress_width = 0;
            ?>
            <div class="order-card">
                <div class="order-header">
                    <div class="order-info">
                        <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                        <div class="order-date">📅 <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></div>
                    </div>
                    <div class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </div>
                </div>

                <!-- Order Progress Timeline -->
                <?php if ($order['status'] !== 'cancelled'): ?>
                <div class="order-progress">
                    <div class="progress-steps">
                        <div class="progress-line">
                            <div class="progress-line-fill" style="width: <?php echo $progress_width; ?>%;"></div>
                        </div>
                        
                        <div class="progress-step <?php echo ($order['status'] === 'pending' || $order['status'] === 'processing' || $order['status'] === 'completed') ? 'completed' : ''; ?>">
                            <div class="step-circle">✓</div>
                            <div class="step-label">Order Placed</div>
                        </div>
                        
                        <div class="progress-step <?php echo ($order['status'] === 'processing') ? 'active' : ''; ?> <?php echo ($order['status'] === 'completed') ? 'completed' : ''; ?>">
                            <div class="step-circle"><?php echo ($order['status'] === 'completed') ? '✓' : '2'; ?></div>
                            <div class="step-label">Processing</div>
                        </div>
                        
                        <div class="progress-step <?php echo ($order['status'] === 'completed') ? 'completed' : ''; ?>">
                            <div class="step-circle"><?php echo ($order['status'] === 'completed') ? '✓' : '3'; ?></div>
                            <div class="step-label">Completed</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Order Items -->
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
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="item-shop">🪙 <?php echo htmlspecialchars($item['shop_name']); ?></div>
                            <div class="item-quantity">Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></div>
                        </div>
                        <div class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Order Footer -->
                <div class="order-footer">
                    <div class="order-total">
                        <div class="total-label">Total Amount</div>
                        <div class="total-amount">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                    </div>
                    <div class="payment-info">
                        <div class="payment-method">💳 <?php echo ucfirst($order['payment_method']); ?></div>
                        <div class="payment-status status-badge status-<?php echo $order['payment_status']; ?>">
                            Payment: <?php echo ucfirst($order['payment_status']); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-orders">
            <div class="no-orders-icon">📦</div>
            <h3>No Orders Found</h3>
            <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
            <a href="products.php" class="btn-shop-now">🛒 Start Shopping</a>
        </div>
        <?php endif;