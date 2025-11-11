<?php
/*
 * VENDOR ORDERS PAGE - WITH PAYMENT MANAGEMENT
 * Purpose: View and manage orders, update order status, mark payments as completed
 * Features: Order list, status updates, payment status tracking, mark as paid button
 */

require_once '../config/auth_check.php';

if ($_SESSION['role'] !== 'vendor') {
    header("Location: ../index.php");
    exit;
}

$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$vendor_user_id = $_SESSION['user_id'];

require_once '../config/database.php';
$conn = getDBConnection();

// Get vendor_id
$vendor_query = "SELECT vendor_id, custom_cake_flag FROM Vendors WHERE user_id = $vendor_user_id";
$vendor_result = $conn->query($vendor_query);
$vendor = $vendor_result->fetch_assoc();
$vendor_id = $vendor['vendor_id'];
$custom_cake_flag = $vendor['custom_cake_flag'];

// Get filter parameter
$status_filter = $_GET['status'] ?? 'all';

// Build orders query - only orders containing vendor's products
$orders_query = "SELECT DISTINCT
    o.order_id,
    o.user_id,
    o.total_amount,
    o.status as order_status,
    o.order_date,
    u.name as customer_name,
    u.email as customer_email,
    u.address as delivery_address,
    p.method as payment_method,
    p.status as payment_status,
    p.amount as payment_amount
FROM Orders o
JOIN Order_Items oi ON o.order_id = oi.order_id
JOIN Products pr ON oi.product_id = pr.product_id
JOIN Shops s ON pr.shop_id = s.shop_id
JOIN Users u ON o.user_id = u.user_id
LEFT JOIN Payments p ON o.order_id = p.order_id
WHERE s.vendor_id = $vendor_id";

// Apply status filter
if ($status_filter !== 'all') {
    $status_filter_escaped = $conn->real_escape_string($status_filter);
    $orders_query .= " AND o.status = '$status_filter_escaped'";
}

$orders_query .= " ORDER BY o.order_date DESC";

$orders_result = $conn->query($orders_query);
$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    // Get items for this order (only vendor's products)
    $items_query = "SELECT 
        oi.order_item_id,
        oi.quantity,
        oi.price,
        pr.product_name,
        pr.image,
        s.shop_name
    FROM Order_Items oi
    JOIN Products pr ON oi.product_id = pr.product_id
    JOIN Shops s ON pr.shop_id = s.shop_id
    WHERE oi.order_id = " . $order['order_id'] . " 
    AND s.vendor_id = $vendor_id";
    
    $items_result = $conn->query($items_query);
    $order['items'] = [];
    $order['vendor_total'] = 0;
    
    while ($item = $items_result->fetch_assoc()) {
        $order['items'][] = $item;
        $order['vendor_total'] += $item['price'] * $item['quantity'];
    }
    
    $orders[] = $order;
}

// Get order statistics
$stats_query = "SELECT 
    COUNT(DISTINCT o.order_id) as total_orders,
    SUM(CASE WHEN o.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN o.status = 'processing' THEN 1 ELSE 0 END) as processing_count,
    SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
FROM Orders o
JOIN Order_Items oi ON o.order_id = oi.order_id
JOIN Products pr ON oi.product_id = pr.product_id
JOIN Shops s ON pr.shop_id = s.shop_id
WHERE s.vendor_id = $vendor_id";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Vendor Dashboard</title>
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
        }

        .navbar-menu a:hover {
            background: #fff5f7;
            color: #ff6b9d;
        }

        .navbar-menu a.active {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
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
            max-width: 1400px;
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
            margin-bottom: 1rem;
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

        /* Orders List */
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
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: center;
        }

        .order-info h3 {
            color: #5a3e36;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #7a5f57;
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

        /* Payment Section */
        .payment-section {
            padding: 1.5rem;
            background: #f8f9fa;
            border-bottom: 2px solid #ffe8ec;
        }

        .payment-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .payment-detail {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .payment-label {
            color: #7a5f57;
            font-size: 0.85rem;
        }

        .payment-value {
            color: #5a3e36;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .payment-actions {
            margin-top: 1rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-mark-paid {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
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

        .btn-mark-paid:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .payment-completed-badge {
            padding: 0.75rem 1.5rem;
            background: #d1e7dd;
            color: #0f5132;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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

        /* Order Actions */
        .order-actions {
            padding: 1.5rem;
            background: #fff5f7;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

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

        @media (max-width: 768px) {
            .order-header {
                grid-template-columns: 1fr;
            }

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
        <a href="dashboard.php" class="navbar-brand"> Sweetkart Vendor</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="shops.php">My Shops</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="orders.php" class="active">Orders</a></li>
            <li><a href="feedback.php">Feedback</a></li>
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
                    <div class="user-badge">⚪ VENDOR</div>
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
            <h1>📦 Orders Management</h1>
            <p>View and manage your orders</p>
            
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
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <!-- Order Header -->
                <div class="order-header">
                    <div class="order-info">
                        <h3>Order #<?php echo $order['order_id']; ?></h3>
                        <div class="order-meta">
                            <span>👤 <?php echo htmlspecialchars($order['customer_name']); ?></span>
                            <span>📅 <?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></span>
                        </div>
                    </div>
                    <div class="status-badge status-<?php echo $order['order_status']; ?>">
                        <?php echo ucfirst($order['order_status']); ?>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="payment-section">
                    <div class="payment-info">
                        <div class="payment-detail">
                            <span class="payment-label">Payment Method</span>
                            <span class="payment-value">
                                <?php echo $order['payment_method'] === 'cash' ? '💵 Cash on Delivery' : '💳 Card Payment'; ?>
                            </span>
                        </div>
                        <div class="payment-detail">
                            <span class="payment-label">Payment Status</span>
                            <span class="payment-value">
                                <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                    <?php 
                                    if ($order['payment_status'] === 'completed') {
                                        echo '✅ Paid';
                                    } else {
                                        echo ($order['payment_method'] === 'cash') ? '🟡 Cash on Delivery' : '🟡 Pending';
                                    }
                                    ?>
                                </span>
                            </span>
                        </div>
                        <div class="payment-detail">
                            <span class="payment-label">Your Earnings</span>
                            <span class="payment-value" style="color: #ff6b9d;">₹<?php echo number_format($order['vendor_total'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Payment Actions -->
                    <div class="payment-actions">
                        <?php if ($order['payment_method'] === 'cash' && $order['payment_status'] === 'pending' && $order['order_status'] !== 'cancelled'): ?>
                            <button class="btn-mark-paid" onclick="markAsPaid(<?php echo $order['order_id']; ?>)">
                                💰 Mark as Paid
                            </button>
                            <span style="color: #7a5f57; font-size: 0.9rem; display: flex; align-items: center;">
                                (Click after receiving cash payment)
                            </span>
                        <?php elseif ($order['payment_status'] === 'completed'): ?>
                            <div class="payment-completed-badge">
                                ✅ Payment Received
                            </div>
                        <?php elseif ($order['order_status'] === 'cancelled'): ?>
                            <div style="padding: 0.75rem 1.5rem; background: #f8d7da; color: #842029; border-radius: 8px; font-weight: 600;">
                                ❌ Order Cancelled - No Payment Required
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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

                <!-- Order Actions -->
                <div class="order-actions">
                    <?php if ($order['order_status'] === 'pending'): ?>
                        <button class="btn btn-primary" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'processing')">
                            ⚡ Start Processing
                        </button>
                        <button class="btn btn-danger" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                            ❌ Cancel Order
                        </button>
                    <?php elseif ($order['order_status'] === 'processing'): ?>
                        <button class="btn btn-success" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'completed')">
                            ✅ Mark as Completed
                        </button>
                        <button class="btn btn-danger" onclick="cancelOrder(<?php echo $order['order_id']; ?>)">
                            ❌ Cancel Order
                        </button>
                    <?php elseif ($order['order_status'] === 'completed'): ?>
                        <div style="padding: 1rem; color: #0f5132; background: #d1e7dd; border-radius: 8px; font-weight: 600;">
                            ✅ Order Completed - Customer can now leave feedback
                        </div>
                    <?php elseif ($order['order_status'] === 'cancelled'): ?>
                        <div style="padding: 1rem; color: #842029; background: #f8d7da; border-radius: 8px; font-weight: 600;">
                            ❌ Order was cancelled
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-orders">
            <div class="no-orders-icon">📦</div>
            <h3>No Orders Found</h3>
            <p>Orders matching your filter will appear here</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
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

        // Mark payment as paid
        function markAsPaid(orderId) {
            if (!confirm('Confirm that you have received the cash payment for this order?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'mark_paid');
            formData.append('order_id', orderId);

            fetch('payment_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Update order status
        function updateOrderStatus(orderId, newStatus) {
            const statusMessages = {
                'processing': 'Start processing this order?',
                'completed': 'Mark this order as completed?'
            };

            if (!confirm(statusMessages[newStatus])) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('order_id', orderId);
            formData.append('status', newStatus);

            fetch('orders_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Cancel order without reason
        function cancelOrder(orderId) {
            if (!confirm('Do you want to cancel this order and restore stock?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'cancel_order');
            formData.append('order_id', orderId);

            fetch('orders_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>
    </script>
</body>
</html>