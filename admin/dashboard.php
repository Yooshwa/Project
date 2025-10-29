<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../home.php");
    exit;
}

// Get user info
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];

// Get statistics from database
require_once '../config/database.php';
$conn = getDBConnection();

// Count total vendors
$result = $conn->query("SELECT COUNT(*) as count FROM Vendors");
$total_vendors = $result->fetch_assoc()['count'];

// Count pending vendors
$result = $conn->query("SELECT COUNT(*) as count FROM Vendors WHERE status = 'pending'");
$pending_vendors = $result->fetch_assoc()['count'];

// Count approved vendors
$result = $conn->query("SELECT COUNT(*) as count FROM Vendors WHERE status = 'approved'");
$approved_vendors = $result->fetch_assoc()['count'];

// Count total customers
$result = $conn->query("SELECT COUNT(*) as count FROM Users WHERE role = 'customer'");
$total_customers = $result->fetch_assoc()['count'];

/* Count total shops
$result = $conn->query("SELECT COUNT(*) as count FROM Shops");
$total_shops = $result->fetch_assoc()['count'];
*/

// Count total shops (ONLY from approved vendors)
$result = $conn->query("SELECT COUNT(*) as count FROM Shops s 
                        JOIN Vendors v ON s.vendor_id = v.vendor_id 
                        WHERE v.status = 'approved'");
$total_shops = $result->fetch_assoc()['count'];

// Count total products (ONLY from approved vendors' shops)
$result = $conn->query("SELECT COUNT(*) as count FROM Products p 
                        JOIN Shops s ON p.shop_id = s.shop_id 
                        JOIN Vendors v ON s.vendor_id = v.vendor_id 
                        WHERE v.status = 'approved'");
$total_products = $result->fetch_assoc()['count'];

/* Count total products
$result = $conn->query("SELECT COUNT(*) as count FROM Products");
$total_products = $result->fetch_assoc()['count'];
*/

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sweetkart</title>
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

        .stat-card.pending {
            border-left: 4px solid #ffa500;
        }

        .stat-card.approved {
            border-left: 4px solid #4caf50;
        }

        .stat-card.customers {
            border-left: 4px solid #2196f3;
        }

        .stat-card.shops {
            border-left: 4px solid #9c27b0;
        }

        .stat-card.products {
            border-left: 4px solid #ff6b9d;
        }

        .alert {
            background: #fff3cd;
            border-left: 4px solid #ffa500;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .alert-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🧁 Sweetkart Admin</div>
        <ul class="navbar-menu">
            <li><a href="dashboard.php" class="active">Dashboard</a></li>
            <li><a href="vendors.php">Manage Vendors</a></li>
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
                    <div class="user-badge">👨‍💼 ADMIN</div>
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
        <div class="welcome-section">
            <h1>👋 Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
            <p>Here's what's happening with your platform today</p>
        </div>

        <?php if ($pending_vendors > 0): ?>
        <div class="alert">
            <span class="alert-icon">⚠️</span>
            <strong>Action Required:</strong> You have <?php echo $pending_vendors; ?> vendor(s) waiting for approval.
            <a href="vendors.php" style="color: #ff6b9d; font-weight: 600; margin-left: 1rem;">Review Now →</a>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <h3>Pending Vendors</h3>
                    <div class="stat-number"><?php echo $pending_vendors; ?></div>
                </div>
            </div>

            <div class="stat-card approved">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3>Approved Vendors</h3>
                    <div class="stat-number"><?php echo $approved_vendors; ?></div>
                </div>
            </div>

            <div class="stat-card customers">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3>Total Customers</h3>
                    <div class="stat-number"><?php echo $total_customers; ?></div>
                </div>
            </div>

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
        </div>
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