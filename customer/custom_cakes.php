<?php
/*
 * CUSTOM CAKE REQUEST PAGE - UPDATED
 * Purpose: Display shops offering custom cakes with fixed pricing
 * Features: Shop cards, fixed-price order form, request history
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

// Get customer phone number
$user_query = "SELECT phone_no FROM Users WHERE user_id = $user_id";
$user_result = $conn->query($user_query);
$user_data = $user_result->fetch_assoc();
$user_phone = $user_data['phone_no'] ?? '';

// Get ONLY shops that offer custom cakes
$shops_query = "SELECT 
    s.shop_id,
    s.shop_name,
    s.address,
    u.name as vendor_name,
    COUNT(DISTINCT f.feedback_id) as review_count,
    IFNULL(AVG(f.rating), 0) as avg_rating
FROM Shops s
JOIN Vendors v ON s.vendor_id = v.vendor_id
JOIN Users u ON v.user_id = u.user_id
LEFT JOIN Feedback f ON s.shop_id = f.shop_id
WHERE v.status = 'approved' AND v.custom_cake_flag = 1
GROUP BY s.shop_id, s.shop_name, s.address, u.name
ORDER BY s.shop_name ASC";

$shops_result = $conn->query($shops_query);
$custom_shops = [];
while ($shop = $shops_result->fetch_assoc()) {
    $custom_shops[] = $shop;
}

// Get customer's custom cake requests
$requests_query = "SELECT 
    cco.custom_order_id,
    cco.flavour,
    cco.size,
    cco.shape,
    cco.weight,
    cco.layers,
    cco.description,
    cco.delivery_date,
    cco.final_price,
    cco.status,
    cco.created_at,
    s.shop_name
FROM Custom_Cake_Orders cco
JOIN Shops s ON cco.shop_id = s.shop_id
WHERE cco.user_id = $user_id
ORDER BY cco.created_at DESC";

$requests_result = $conn->query($requests_query);
$requests = [];
while ($request = $requests_result->fetch_assoc()) {
    $requests[] = $request;
}

// Get cart count
$cart_query = "SELECT IFNULL(SUM(quantity), 0) as cart_count FROM Cart WHERE user_id = $user_id";
$cart_result = $conn->query($cart_query);
$cart_count = $cart_result->fetch_assoc()['cart_count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Cakes - Sweetkart</title>
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
            text-align: center;
        }

        .page-header h1 {
            color: #5a3e36;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7a5f57;
            font-size: 1.1rem;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .tabs-header {
            display: flex;
            border-bottom: 2px solid #ffe8ec;
            background: #fff5f7;
        }

        .tab-button {
            flex: 1;
            padding: 1.5rem 2rem;
            background: transparent;
            border: none;
            color: #7a5f57;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-button:hover {
            background: rgba(255, 107, 157, 0.05);
            color: #ff6b9d;
        }

        .tab-button.active {
            color: #ff6b9d;
            background: white;
            border-bottom-color: #ff6b9d;
        }

        .tab-badge {
            background: #ff6b9d;
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            min-width: 24px;
            text-align: center;
        }

        .tab-button.active .tab-badge {
            background: #ff8fab;
        }

        .tab-content {
            display: none;
            padding: 2rem;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .shop-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .shop-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .shop-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .shop-body {
            padding: 1.5rem;
        }

        .shop-name {
            color: #5a3e36;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .shop-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stars {
            display: flex;
            gap: 0.1rem;
        }

        .star {
            color: #ffa500;
            font-size: 1rem;
        }

        .star.empty {
            color: #ddd;
        }

        .rating-text {
            color: #5a3e36;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .shop-description {
            color: #7a5f57;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            min-height: 48px;
        }

        .shop-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
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

        .btn-view {
            background: white;
            color: #ff6b9d;
            border: 2px solid #ff6b9d;
        }

        .btn-view:hover {
            background: #fff5f7;
        }

        .btn-order {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .btn-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .no-shops {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-shops-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .no-shops h3 {
            color: #5a3e36;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .no-shops p {
            color: #7a5f57;
        }

        /* Modal */
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
            padding: 2rem 0;
        }

        .modal-content {
            background: white;
            margin: 0 auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
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
            padding: 2rem;
            border-bottom: 2px solid #ffe8ec;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
        }

        .modal-title {
            color: #5a3e36;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .modal-subtitle {
            color: #7a5f57;
            font-size: 1rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-wrapper input[type="file"] {
            display: none;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            background: #fff5f7;
            border: 2px dashed #ff6b9d;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: #5a3e36;
            font-weight: 500;
        }

        .file-upload-label:hover {
            background: #ffe8ec;
        }

        .image-preview {
            margin-top: 1rem;
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            display: none;
        }

        .price-display {
            background: #fff5f7;
            padding: 1.5rem;
            border-radius: 10px;
            border: 2px solid #ff6b9d;
            text-align: center;
            margin-top: 1rem;
        }

        .price-label {
            color: #7a5f57;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .price-value {
            color: #ff6b9d;
            font-size: 2rem;
            font-weight: bold;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-top: 2px solid #ffe8ec;
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
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #5a6268;
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
            transition: all 0.3s;
            font-size: 1.1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .requests-section {
            margin-top: 3rem;
        }

        .section-title {
            color: #5a3e36;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        table {
            width: 100%;
        }

        @media (max-width: 768px) {
            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 0.5rem !important;
            }
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
            display: inline-block;
        }

        .status-confirmed {
            background: #d1e7dd;
            color: #0f5132;
            display: inline-block;
        }

        .status-in_progress {
            background: #e3f2fd;
            color: #1976d2;
            display: inline-block;
        }

        .status-completed {
            background: #d1e7dd;
            color: #0f5132;
            display: inline-block;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #842029;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .shops-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
            }

            .tabs-header {
                flex-direction: column;
            }

            .tab-button {
                padding: 1rem;
                font-size: 1rem;
            }

            .tab-content {
                padding: 1rem;
            }

            table, thead, tbody, th, td, tr {
                display: block;
            }

            thead tr {
                display: none;
            }

            tr {
                margin-bottom: 1rem;
                border: 1px solid #ffe8ec !important;
                border-radius: 8px;
                padding: 0.5rem !important;
            }

            td {
                text-align: right;
                padding-left: 50%;
                position: relative;
            }

            td:before {
                content: attr(data-label);
                position: absolute;
                left: 0.5rem;
                font-weight: bold;
                color: #5a3e36;
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
            <li><a href="custom_cakes.php" class="active"> Custom Cakes</a></li>
            <li><a href="orders.php"> Orders</a></li>
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
            <h1>🎂 Custom Cakes</h1>
            <p>Order personalized cakes from talented vendors</p>
        </div>

        <!-- Tabs Container -->
        <div class="tabs-container">
            <div class="tabs-header">
                <button class="tab-button active" onclick="switchTab('vendors')">
                    🏪 Browse Vendors
                </button>
                <button class="tab-button" onclick="switchTab('requests')">
                    📋 My Requests
                    <?php if (count($requests) > 0): ?>
                    <span class="tab-badge"><?php echo count($requests); ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Vendors Tab Content -->
            <div id="vendorsTab" class="tab-content active">
                <?php if (count($custom_shops) > 0): ?>
                <div class="shops-grid">
                    <?php foreach ($custom_shops as $shop): ?>
                    <div class="shop-card">
                        <div class="shop-image">
                            <div class="shop-logo">🎂</div>
                        </div>
                        <div class="shop-body">
                            <h3 class="shop-name"><?php echo htmlspecialchars($shop['shop_name']); ?></h3>
                            <div class="shop-rating">
                                <div class="stars">
                                    <?php 
                                    $avg_rating = round($shop['avg_rating']);
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                        <span class="star <?php echo $i > $avg_rating ? 'empty' : ''; ?>">⭐</span>
                                    <?php endfor; ?>
                                </div>
                                <?php if ($shop['review_count'] > 0): ?>
                                <span class="rating-text"><?php echo number_format($shop['avg_rating'], 1); ?></span>
                                <span style="color: #7a5f57; font-size: 0.85rem;">(<?php echo $shop['review_count']; ?>)</span>
                                <?php else: ?>
                                <span style="color: #7a5f57; font-size: 0.85rem; font-style: italic;">No ratings yet</span>
                                <?php endif; ?>
                            </div>
                            <p class="shop-description">
                                Exquisite custom cakes for every celebration, crafted with the finest ingredients and artistic flair.
                            </p>
                            <div class="shop-actions">
                                <a href="products.php?shop=<?php echo $shop['shop_id']; ?>" class="btn btn-view">
                                    View Products
                                </a>
                                <button class="btn btn-order" onclick="openOrderModal(<?php echo $shop['shop_id']; ?>, '<?php echo htmlspecialchars($shop['shop_name'], ENT_QUOTES); ?>')">
                                    🎂 Order Custom Cake
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-shops">
                    <div class="no-shops-icon">🎂</div>
                    <h3>No Custom Cake Vendors Available</h3>
                    <p>No shops currently offer custom cake services. Please check back later!</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- My Requests Tab Content -->
            <div id="requestsTab" class="tab-content">
                <?php if (count($requests) > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #fff5f7; border-bottom: 2px solid #ffe8ec;">
                                <th style="padding: 1rem; text-align: left; color: #5a3e36; font-weight: 600;">Request #</th>
                                <th style="padding: 1rem; text-align: left; color: #5a3e36; font-weight: 600;">Shop</th>
                                <th style="padding: 1rem; text-align: left; color: #5a3e36; font-weight: 600;">Details</th>
                                <th style="padding: 1rem; text-align: left; color: #5a3e36; font-weight: 600;">Delivery Date</th>
                                <th style="padding: 1rem; text-align: left; color: #5a3e36; font-weight: 600;">Price</th>
                                <th style="padding: 1rem; text-align: center; color: #5a3e36; font-weight: 600;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr style="border-bottom: 1px solid #ffe8ec;">
                                <td style="padding: 1rem; color: #5a3e36; font-weight: 600;" data-label="Request #">#<?php echo $request['custom_order_id']; ?></td>
                                <td style="padding: 1rem; color: #5a3e36;" data-label="Shop"><?php echo htmlspecialchars($request['shop_name']); ?></td>
                                <td style="padding: 1rem; color: #7a5f57; font-size: 0.9rem;" data-label="Details">
                                    <?php echo htmlspecialchars($request['flavour']); ?> | 
                                    <?php echo htmlspecialchars($request['size']); ?> | 
                                    <?php echo htmlspecialchars($request['shape']); ?>
                                </td>
                                <td style="padding: 1rem; color: #ff6b9d; font-weight: 600;" data-label="Delivery">
                                    <?php echo date('M d, Y', strtotime($request['delivery_date'])); ?>
                                </td>
                                <td style="padding: 1rem; color: #ff6b9d; font-weight: 700; font-size: 1.1rem;" data-label="Price">
                                    ₹<?php echo number_format($request['final_price'], 2); ?>
                                </td>
                                <td style="padding: 1rem; text-align: center;" data-label="Status">
                                    <div class="status-badge status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="no-shops">
                    <div class="no-shops-icon">📋</div>
                    <h3>No Custom Cake Requests Yet</h3>
                    <p>You haven't placed any custom cake orders. Browse vendors to get started!</p>
                    <button class="btn btn-order" onclick="switchTab('vendors')" style="margin-top: 1.5rem;">
                        🎂 Browse Vendors
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">🎂</div>
                <h2 class="modal-title">Design Your Custom Cake</h2>
                <p class="modal-subtitle" id="modalShopName">Create a one-of-a-kind cake</p>
            </div>
            <div class="modal-body">
                <form id="customCakeForm" enctype="multipart/form-data">
                    <input type="hidden" id="selectedShopId" name="shop_id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="customerName">Your Name *</label>
                            <input type="text" id="customerName" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="customerPhone">Phone Number *</label>
                            <input type="tel" id="customerPhone" name="phone_no" required 
                                   value="<?php echo htmlspecialchars($user_phone); ?>"
                                   placeholder="Enter your phone number">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="cakeSize">Cake Size *</label>
                            <select id="cakeSize" name="size" required onchange="calculatePrice()">
                                <option value="">Select size</option>
                                <option value="0.5kg" data-price="400">0.5 kg - ₹400</option>
                                <option value="1kg" data-price="700">1 kg - ₹700</option>
                                <option value="1.5kg" data-price="1000">1.5 kg - ₹1000</option>
                                <option value="2kg" data-price="1300">2 kg - ₹1300</option>
                                <option value="2.5kg" data-price="1600">2.5 kg - ₹1600</option>
                                <option value="3kg" data-price="1900">3 kg - ₹1900</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="flavour">Flavor *</label>
                            <select id="flavour" name="flavour" required onchange="calculatePrice()">
                                <option value="">Select flavor</option>
                                <option value="Chocolate" data-price="0">Chocolate - ₹0</option>
                                <option value="Vanilla" data-price="0">Vanilla - ₹0</option>
                                <option value="Strawberry" data-price="50">Strawberry - +₹50</option>
                                <option value="Red Velvet" data-price="100">Red Velvet - +₹100</option>
                                <option value="Black Forest" data-price="80">Black Forest - +₹80</option>
                                <option value="Butterscotch" data-price="60">Butterscotch - +₹60</option>
                                <option value="Pineapple" data-price="40">Pineapple - +₹40</option>
                                <option value="Mixed Fruit" data-price="70">Mixed Fruit - +₹70</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="shape">Shape *</label>
                            <select id="shape" name="shape" required onchange="calculatePrice()">
                                <option value="">Select shape</option>
                                <option value="Round" data-price="0">Round - ₹0</option>
                                <option value="Square" data-price="0">Square - ₹0</option>
                                <option value="Rectangle" data-price="0">Rectangle - ₹0</option>
                                <option value="Heart" data-price="150">Heart - +₹150</option>
                                <option value="Number" data-price="200">Number - +₹200</option>
                                <option value="Custom" data-price="300">Custom - +₹300</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="layers">Number of Layers *</label>
                            <select id="layers" name="layers" required onchange="calculatePrice()">
                                <option value="">Select layers</option>
                                <option value="1" data-price="0">1 Layer - ₹0</option>
                                <option value="2" data-price="100">2 Layers - +₹100</option>
                                <option value="3" data-price="200">3 Layers - +₹200</option>
                                <option value="4" data-price="300">4 Layers - +₹300</option>
                                <option value="5" data-price="400">5+ Layers - +₹400</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="occasion">Occasion</label>
                            <select id="occasion" name="occasion">
                                <option value="">Select occasion</option>
                                <option value="Birthday">Birthday</option>
                                <option value="Anniversary">Anniversary</option>
                                <option value="Wedding">Wedding</option>
                                <option value="Graduation">Graduation</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="deliveryDate">Delivery Date *</label>
                            <input type="date" id="deliveryDate" name="delivery_date" required 
                                   min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Design Details <span style="color: #7a5f57; font-weight: normal;">(Optional)</span></label>
                        <textarea id="description" name="description" 
                                  placeholder="Describe your cake design - colors, decorations, text, themes, etc. (Optional)"></textarea>
                    </div>

                    <div class="form-group file-upload-wrapper">
                        <label>Reference Image (Optional)</label>
                        <input type="file" id="referenceImage" name="reference_image" 
                               accept="image/*" onchange="previewImage(this)">
                        <label for="referenceImage" class="file-upload-label">
                            📷 Choose File
                        </label>
                        <img id="imagePreview" class="image-preview" alt="Preview">
                    </div>

                    <div class="price-display">
                        <div class="price-label">Total Price</div>
                        <div class="price-value" id="totalPrice">₹0.00</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeOrderModal()">Cancel</button>
                <button type="button" class="btn-submit" onclick="submitOrder()">Submit Custom Cake Request</button>
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

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                button.classList.remove('active');
            }
        });

        function openOrderModal(shopId, shopName) {
            document.getElementById('selectedShopId').value = shopId;
            document.getElementById('modalShopName').textContent = 'Create a one-of-a-kind cake with ' + shopName;
            document.getElementById('orderModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
            document.getElementById('customCakeForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('totalPrice').textContent = '₹0.00';
            document.body.style.overflow = 'auto';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target == modal) {
                closeOrderModal();
            }
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const label = input.nextElementSibling;
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    label.innerHTML = '✓ Image Selected';
                    label.style.background = '#d1e7dd';
                    label.style.borderColor = '#4caf50';
                    label.style.color = '#0f5132';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function calculatePrice() {
            const size = document.getElementById('cakeSize');
            const flavour = document.getElementById('flavour');
            const shape = document.getElementById('shape');
            const layers = document.getElementById('layers');

            let total = 0;

            if (size.value) {
                total += parseFloat(size.options[size.selectedIndex].dataset.price || 0);
            }
            if (flavour.value) {
                total += parseFloat(flavour.options[flavour.selectedIndex].dataset.price || 0);
            }
            if (shape.value) {
                total += parseFloat(shape.options[shape.selectedIndex].dataset.price || 0);
            }
            if (layers.value) {
                total += parseFloat(layers.options[layers.selectedIndex].dataset.price || 0);
            }

            document.getElementById('totalPrice').textContent = '₹' + total.toFixed(2);
        }

        function submitOrder() {
            const form = document.getElementById('customCakeForm');
            
            // Validate required fields (description is now optional)
            if (!form.shop_id.value || !form.phone_no.value || !form.delivery_date.value || 
                !form.size.value || !form.flavour.value || !form.shape.value || !form.layers.value) {
                alert('Please fill all required fields');
                return;
            }

            // Validate phone number
            const phone = form.phone_no.value.trim();
            if (phone.length < 10) {
                alert('Please enter a valid phone number');
                return;
            }
            
            const formData = new FormData(form);
            
            // Add final price to form data
            const totalPrice = document.getElementById('totalPrice').textContent.replace('₹', '').replace(',', '');
            formData.append('final_price', totalPrice);
            
            const submitBtn = document.querySelector('.btn-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
            
            fetch('submit_custom_cake.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Custom cake request submitted successfully!');
                    closeOrderModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Custom Cake Request';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Custom Cake Request';
            });
        }

        // Tab switching function
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            if (tabName === 'vendors') {
                document.getElementById('vendorsTab').classList.add('active');
                document.querySelectorAll('.tab-button')[0].classList.add('active');
            } else if (tabName === 'requests') {
                document.getElementById('requestsTab').classList.add('active');
                document.querySelectorAll('.tab-button')[1].classList.add('active');
            }
        }
    
    </script>
</body>
</html>