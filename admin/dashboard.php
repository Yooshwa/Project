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

// Count total shops
$result = $conn->query("SELECT COUNT(*) as count FROM Shops");
$total_shops = $result->fetch_assoc()['count'];

// Count total products
$result = $conn->query("SELECT COUNT(*) as count FROM Products");
$total_products = $result->fetch_assoc()['count'];

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
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-badge {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .logout-btn {
            background: #5a3e36;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #7a5f57;
            transform: translateY(-2px);
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
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1rem 1.5rem;
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
            <span class="user-badge">👨‍💼 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
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

        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="vendors.php" class="action-btn">
                    👥 Manage Vendors
                </a>
            </div>
        </div>
    </div>
</body>
</html>