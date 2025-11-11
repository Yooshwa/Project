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
$stmt = $conn->prepare("SELECT vendor_id, custom_cake_flag, status FROM Vendors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();

if (!$vendor) {
    echo "Vendor profile not found!";
    exit;
}

$vendor_id = $vendor['vendor_id'];
$vendor_status = $vendor['status'];
$custom_cake_enabled = $vendor['custom_cake_flag'];

// Get statistics (only if approved)
$total_shops = 0;
$total_products = 0;
$pending_orders = 0;
$total_feedback = 0;

if ($vendor_status === 'approved') {
    // Count shops
    $result = $conn->query("SELECT COUNT(*) as count FROM Shops WHERE vendor_id = $vendor_id");
    $total_shops = $result->fetch_assoc()['count'];
    
    // Count products
    $result = $conn->query("SELECT COUNT(*) as count FROM Products p 
                           JOIN Shops s ON p.shop_id = s.shop_id 
                           WHERE s.vendor_id = $vendor_id");
    $total_products = $result->fetch_assoc()['count'];
    
    // Count pending orders (orders with items from vendor's shops)
    $result = $conn->query("SELECT COUNT(DISTINCT o.order_id) as count 
                           FROM Orders o 
                           JOIN Order_Items oi ON o.order_id = oi.order_id 
                           JOIN Products p ON oi.product_id = p.product_id 
                           JOIN Shops s ON p.shop_id = s.shop_id 
                           WHERE s.vendor_id = $vendor_id AND o.status = 'pending'");
    $pending_orders = $result->fetch_assoc()['count'];
    
    // Count total feedback
    $result = $conn->query("SELECT COUNT(*) as count FROM Feedback f 
                           JOIN Shops s ON f.shop_id = s.shop_id 
                           WHERE s.vendor_id = $vendor_id");
    $total_feedback = $result->fetch_assoc()['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Dashboard - Sweetkart</title>
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
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .welcome-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .welcome-section h1 {
            color: #5a3e36;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: #7a5f57;
            font-size: 1.1rem;
        }

        .status-alert {
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .status-alert.pending {
            background: #fff3cd;
            border-left: 4px solid #ffa500;
        }

        .status-alert.rejected {
            background: #f8d7da;
            border-left: 4px solid #f44336;
        }

        .status-alert h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .status-alert.pending h2 {
            color: #856404;
        }

        .status-alert.rejected h2 {
            color: #721c24;
        }

        .status-alert p {
            color: #5a3e36;
            margin-top: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3rem;
        }

        .stat-info h3 {
            color: #7a5f57;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-info .stat-number {
            color: #5a3e36;
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-card.shops {
            border-left: 4px solid #9c27b0;
        }

        .stat-card.products {
            border-left: 4px solid #ff6b9d;
        }

        .stat-card.orders {
            border-left: 4px solid #ffa500;
        }

        .stat-card.feedback {
            border-left: 4px solid #4caf50;
        }

        .quick-actions {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .quick-actions h2 {
            color: #5a3e36;
            margin-bottom: 1.5rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .action-btn-icon {
            font-size: 2rem;
        }

        .custom-cake-badge {
            display: inline-block;
            background: #4caf50;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">Sweetkart Vendor</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <?php if ($vendor_status === 'approved'): ?>
            <li><a href="shops.php">My Shops</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="orders.php">Orders</a></li>
            <?php if ($custom_cake_enabled): ?>
            <li><a href="custom_cakes.php">Custom Cakes</a></li>
            <?php endif; ?>
            <li><a href="feedback.php">Feedback</a></li>
            <?php endif; ?>
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
        <?php if ($vendor_status === 'pending'): ?>
            <div class="status-alert pending">
                <h2>⏳ Account Pending Approval</h2>
                <p>Your vendor account is currently under review by our admin team.</p>
                <p>You will receive access to all vendor features once approved.</p>
                <p style="margin-top: 1rem; font-weight: 600;">Please check back later or contact support for updates.</p>
            </div>
        <?php elseif ($vendor_status === 'rejected'): ?>
            <div class="status-alert rejected">
                <h2>❌ Account Rejected</h2>
                <p>Unfortunately, your vendor account has been rejected.</p>
                <p style="margin-top: 1rem;">Please contact our admin team for more information:</p>
                <p style="font-weight: 600;">Email: admin@sweetkart.com</p>
            </div>
        <?php else: ?>
            <div class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <p>Manage your shops, products, and orders from your dashboard
                <?php if ($custom_cake_enabled): ?>
                    <span class="custom-cake-badge">🎂 Custom Cakes Enabled</span>
                <?php endif; ?>
                </p>
            </div>

            <div class="stats-grid">
                <div class="stat-card shops">
                    <div class="stat-icon">🏪</div>
                    <div class="stat-info">
                        <h3>Total Shops</h3>
                        <div class="stat-number"><?php echo $total_shops; ?></div>
                    </div>
                </div>

                <div class="stat-card products">
                    <div class="stat-icon">🧁</div>
                    <div class="stat-info">
                        <h3>Total Products</h3>
                        <div class="stat-number"><?php echo $total_products; ?></div>
                    </div>
                </div>

                <div class="stat-card orders">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3>Pending Orders</h3>
                        <div class="stat-number"><?php echo $pending_orders; ?></div>
                    </div>
                </div>

                <div class="stat-card feedback">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-info">
                        <h3>Total Feedback</h3>
                        <div class="stat-number"><?php echo $total_feedback; ?></div>
                    </div>
                </div>
            </div>

            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <a href="shops.php" class="action-btn">
                        <span class="action-btn-icon">🏪</span>
                        <span>Manage Shops</span>
                    </a>
                    <a href="products.php" class="action-btn">
                        <span class="action-btn-icon">🧁</span>
                        <span>Manage Products</span>
                    </a>
                    <a href="orders.php" class="action-btn">
                        <span class="action-btn-icon">📦</span>
                        <span>View Orders</span>
                    </a>
                    <?php if ($custom_cake_enabled): ?>
                    <a href="custom_cakes.php" class="action-btn">
                        <span class="action-btn-icon">🎂</span>
                        <span>Custom Cakes</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            dropdown.classList.toggle('show');
            button.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                button.classList.remove('active');
            }
        });
    </script>
</body>
</html>