<?php
/*
 * ORDER SUCCESS PAGE
 * Purpose: Show order confirmation and details after successful order placement
 * Accessed via: place_order.php redirects here with order_id
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

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id === 0) {
    header("Location: products.php");
    exit;
}

// Get database connection
require_once '../config/database.php';
$conn = getDBConnection();

// Get order details and verify it belongs to this user
$order_query = "SELECT 
    o.order_id,
    o.total_amount,
    o.status,
    o.order_date,
    p.method as payment_method,
    p.status as payment_status
FROM Orders o
LEFT JOIN Payments p ON o.order_id = p.order_id
WHERE o.order_id = $order_id AND o.user_id = $user_id";

$order_result = $conn->query($order_query);

if ($order_result->num_rows === 0) {
    // Order not found or doesn't belong to this user
    header("Location: products.php");
    exit;
}

$order = $order_result->fetch_assoc();

// Get order items
$items_query = "SELECT 
    oi.quantity,
    oi.price,
    p.product_name,
    p.image,
    s.shop_name
FROM Order_Items oi
JOIN Products p ON oi.product_id = p.product_id
JOIN Shops s ON p.shop_id = s.shop_id
WHERE oi.order_id = $order_id
ORDER BY s.shop_name, p.product_name";

$items_result = $conn->query($items_query);
$order_items = [];

while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - Sweetkart</title>
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
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        /* Success Animation */
        .success-animation {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
            color: white;
            font-size: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-animation h1 {
            color: #5a3e36;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .success-animation p {
            color: #7a5f57;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .order-number {
            display: inline-block;
            background: #fff5f7;
            color: #ff6b9d;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.2rem;
        }

        /* Order Details */
        .order-details {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section-title {
            color: #5a3e36;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #ffe8ec;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ffe8ec;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #7a5f57;
            font-weight: 500;
        }

        .detail-value {
            color: #5a3e36;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        /* Order Items */
        .order-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #ffe8ec;
            border-radius: 10px;
            margin-bottom: 1rem;
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
            font-size: 1.2rem;
            text-align: right;
        }

        .total-section {
            background: #fff5f7;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1.5rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 1.4rem;
            font-weight: bold;
            color: #5a3e36;
        }

        .total-amount {
            color: #ff6b9d;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #ff6b9d;
            border: 2px solid #ff6b9d;
        }

        .btn-secondary:hover {
            background: #fff5f7;
        }

        @media (max-width: 768px) {
            .action-buttons {
                grid-template-columns: 1fr;
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
            <li><a href="orders.php">📦 Orders</a></li>
            <li><a href="cart.php">🛒 Cart</a></li>
        </ul>
        <div class="navbar-user">
            <button class="user-profile-btn" onclick="toggleDropdown()">
                <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($user_name); ?></span>
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
        <!-- Success Message -->
        <div class="success-animation">
            <div class="success-icon">✓</div>
            <h1>Order Placed Successfully!</h1>
            <p>Thank you for your order. We'll start preparing it right away.</p>
            <div class="order-number">Order #<?php echo $order_id; ?></div>
        </div>

        <!-- Order Information -->
        <div class="order-details">
            <div class="section-title">📋 Order Information</div>
            <div class="detail-row">
                <span class="detail-label">Order Date</span>
                <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($order['order_date'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order Status</span>
                <span class="status-badge status-pending"><?php echo ucfirst($order['status']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method</span>
                <span class="detail-value"><?php echo ucfirst($order['payment_method']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Status</span>
                <span class="status-badge status-pending"><?php echo ucfirst($order['payment_status']); ?></span>
            </div>
        </div>

        <!-- Order Items -->
        <div class="order-details">
            <div class="section-title">📦 Order Items</div>
            <?php foreach ($order_items as $item): ?>
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
                    <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></div>
                </div>
                <div class="item-price">₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
            </div>
            <?php endforeach; ?>

            <div class="total-section">
                <div class="total-row">
                    <span>Total Amount</span>
                    <span class="total-amount">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="orders.php" class="btn btn-primary">
                📦 View All Orders
            </a>
            <a href="products.php" class="btn btn-secondary">
                🛒 Continue Shopping
            </a>
        </div>
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
    </script>
</body>
</html>