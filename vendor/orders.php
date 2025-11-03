<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is vendor
if ($_SESSION['role'] !== 'vendor') {
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

// Get vendor information
$stmt = $conn->prepare("SELECT vendor_id, status FROM Vendors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();

if (!$vendor || $vendor['status'] !== 'approved') {
    header("Location: dashboard.php");
    exit;
}

$vendor_id = $vendor['vendor_id'];

// Get filter parameter
$filter_status = $_GET['status'] ?? 'all';

// Get all orders that contain products from vendor's shops
$orders_query = "SELECT DISTINCT
    o.order_id,
    o.user_id,
    o.total_amount,
    o.status,
    o.order_date,
    u.name as customer_name,
    u.email as customer_email,
    u.address as customer_address
FROM Orders o
JOIN Order_Items oi ON o.order_id = oi.order_id
JOIN Products p ON oi.product_id = p.product_id
JOIN Shops s ON p.shop_id = s.shop_id
JOIN Users u ON o.user_id = u.user_id
WHERE s.vendor_id = $vendor_id";

if ($filter_status !== 'all') {
    $orders_query .= " AND o.status = '" . $conn->real_escape_string($filter_status) . "'";
}

$orders_query .= " ORDER BY o.order_date DESC";

$result = $conn->query($orders_query);
$orders = [];
while ($row = $result->fetch_assoc()) {
    $order_id = $row['order_id'];
    
    // Get order items for this order (only vendor's products)
    $items_query = "SELECT 
        oi.quantity,
        oi.price,
        p.product_name,
        p.image,
        s.shop_name
    FROM Order_Items oi
    JOIN Products p ON oi.product_id = p.product_id
    JOIN Shops s ON p.shop_id = s.shop_id
    WHERE oi.order_id = $order_id AND s.vendor_id = $vendor_id";
    
    $items_result = $conn->query($items_query);
    $items = [];
    $vendor_total = 0;
    
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
        $vendor_total += $item['quantity'] * $item['price'];
    }
    
    $row['items'] = $items;
    $row['vendor_total'] = $vendor_total;
    $orders[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Sweetkart Vendor</title>
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

        .filter-section {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-section label {
            color: #5a3e36;
            font-weight: 600;
        }

        .filter-section select {
            padding: 0.5rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            color: #5a3e36;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-section select:focus {
            outline: none;
            border-color: #ff6b9d;
        }

        .orders-container {
            display: grid;
            gap: 1.5rem;
        }

        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .order-card:hover {
            transform: translateY(-3px);
        }

        .order-header {
            background: linear-gradient(135deg, #fff5f7 0%, #ffe8ec 100%);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #ffe8ec;
        }

        .order-info h3 {
            color: #5a3e36;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .order-date {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-body {
            padding: 1.5rem;
        }

        .customer-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .customer-info h4 {
            color: #5a3e36;
            margin-bottom: 0.5rem;
        }

        .customer-info p {
            color: #7a5f57;
            font-size: 0.9rem;
            margin: 0.25rem 0;
        }

        .order-items {
            margin-bottom: 1.5rem;
        }

        .order-items h4 {
            color: #5a3e36;
            margin-bottom: 1rem;
        }

        .item-row {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .no-image {
            width: 60px;
            height: 60px;
            background: #e0e0e0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
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
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .item-price {
            color: #ff6b9d;
            font-weight: 600;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: #f8f9fa;
            border-top: 2px solid #ffe8ec;
        }

        .order-total {
            font-size: 1.2rem;
            font-weight: 600;
            color: #5a3e36;
        }

        .order-total span {
            color: #ff6b9d;
            font-size: 1.4rem;
        }

        .status-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-process {
            background: #007bff;
            color: white;
        }

        .btn-process:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn-complete {
            background: #28a745;
            color: white;
        }

        .btn-complete:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }

        .no-orders {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-orders-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .no-orders h3 {
            color: #5a3e36;
            margin-bottom: 0.5rem;
        }

        .no-orders p {
            color: #7a5f57;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">🧁 Sweetkart Vendor</a>
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
                    <div class="user-badge">🪙 VENDOR</div>
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
            <h1>📦 Orders Management</h1>
            <div class="filter-section">
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" onchange="filterByStatus()">
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Orders</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $filter_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <div class="orders-container">
            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <h3>Order #<?php echo $order['order_id']; ?></h3>
                            <p class="order-date">📅 <?php echo date('M d, Y - h:i A', strtotime($order['order_date'])); ?></p>
                        </div>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>

                    <div class="order-body">
                        <div class="customer-info">
                            <h4>👤 Customer Information</h4>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                        </div>

                        <div class="order-items">
                            <h4>🛒 Order Items (Your Products)</h4>
                            <?php foreach ($order['items'] as $item): ?>
                            <div class="item-row">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" 
                                         alt="Product" class="item-image">
                                <?php else: ?>
                                    <div class="no-image">🧁</div>
                                <?php endif; ?>
                                <div class="item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="item-shop">Shop: <?php echo htmlspecialchars($item['shop_name']); ?></div>
                                    <div class="item-price">
                                        ₹<?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?> = 
                                        ₹<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="order-footer">
                        <div class="order-total">
                            Your Total: <span>₹<?php echo number_format($order['vendor_total'], 2); ?></span>
                        </div>
                        <div class="status-actions">
                            <?php if ($order['status'] === 'pending'): ?>
                                <button class="btn btn-process" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'processing')">
                                    ⏩ Start Processing
                                </button>
                            <?php elseif ($order['status'] === 'processing'): ?>
                                <button class="btn btn-complete" onclick="updateOrderStatus(<?php echo $order['order_id']; ?>, 'completed')">
                                    ✅ Mark Complete
                                </button>
                            <?php elseif ($order['status'] === 'completed'): ?>
                                <span style="color: #28a745; font-weight: 600;">✅ Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="no-orders">
                <div class="no-orders-icon">📦</div>
                <h3>No Orders Found</h3>
                <p>Orders containing your products will appear here</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            dropdown.classList.toggle('show');
            button.classList.toggle('active');
        }

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                button.classList.remove('active');
            }
        });

        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            window.location.href = `orders.php?status=${status}`;
        }

        function updateOrderStatus(orderId, newStatus) {
            const confirmMessage = newStatus === 'processing' 
                ? 'Start processing this order?' 
                : 'Mark this order as completed?';
            
            if (!confirm(confirmMessage)) {
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
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
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