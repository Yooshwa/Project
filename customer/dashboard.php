<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is customer
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

// Get user info
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Sweetkart</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #5a3e36;
            font-size: 2rem;
        }

        .header p {
            color: #7a5f57;
            margin-top: 0.5rem;
        }

        .user-info {
            text-align: right;
        }

        .user-info .role-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .user-info p {
            color: #5a3e36;
            font-size: 0.95rem;
        }

        .content {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .content h2 {
            color: #5a3e36;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .content p {
            color: #7a5f57;
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }

        .emoji {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .logout-btn {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .info-box {
            background: #fff5f7;
            border-left: 4px solid #ff6b9d;
            padding: 1.5rem;
            margin-top: 2rem;
            border-radius: 8px;
        }

        .info-box h3 {
            color: #5a3e36;
            margin-bottom: 1rem;
        }

        .info-box p {
            color: #7a5f57;
            font-size: 1rem;
            text-align: left;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🛒 Sweetkart</h1>
                <p>Customer Dashboard</p>
            </div>
            <div class="user-info">
                <span class="role-badge">🛒 CUSTOMER</span>
                <p><strong><?php echo htmlspecialchars($user_name); ?></strong></p>
                <p><?php echo htmlspecialchars($user_email); ?></p>
            </div>
        </div>

        <div class="content">
            <div class="emoji">👋</div>
            <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
            <p>You are logged in as <strong>Customer</strong></p>
            
            <a href="../auth/logout.php" class="logout-btn">Logout</a>

            <div class="info-box">
                <h3>🚀 Customer Dashboard - Coming Soon</h3>
                <p>✅ Authentication is working perfectly!</p>
                <p>🛒 Full customer dashboard coming in Phase 11</p>
                <p>🎯 Features to be added:</p>
                <ul style="text-align: left; margin-left: 2rem; color: #7a5f57;">
                    <li>Browse shops and products</li>
                    <li>Add items to cart and checkout</li>
                    <li>Place and track orders</li>
                    <li>Order custom cakes</li>
                    <li>Leave feedback and reviews</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>