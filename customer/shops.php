<?php
/*
 * BROWSE SHOPS PAGE
 * Purpose: Display all approved vendor shops with ratings and details
 * Features: Shop listings, ratings, search, view products link
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

// Get search parameter
$search = $_GET['search'] ?? '';

// Build shops query - only show shops from approved vendors
$shops_query = "SELECT 
    s.shop_id,
    s.shop_name,
    s.address,
    s.created_at,
    v.vendor_id,
    v.custom_cake_flag,
    u.name as vendor_name,
    COUNT(DISTINCT p.product_id) as product_count,
    COUNT(DISTINCT f.feedback_id) as review_count,
    IFNULL(AVG(f.rating), 0) as avg_rating
FROM Shops s
JOIN Vendors v ON s.vendor_id = v.vendor_id
JOIN Users u ON v.user_id = u.user_id
LEFT JOIN Products p ON s.shop_id = p.shop_id
LEFT JOIN Feedback f ON s.shop_id = f.shop_id
WHERE v.status = 'approved'";

// Apply search filter
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $shops_query .= " AND (s.shop_name LIKE '%$search_term%' OR s.address LIKE '%$search_term%')";
}

$shops_query .= " GROUP BY s.shop_id, s.shop_name, s.address, s.created_at, v.vendor_id, v.custom_cake_flag, u.name
                  ORDER BY s.shop_name ASC";

$result = $conn->query($shops_query);
$shops = [];

while ($shop = $result->fetch_assoc()) {
    $shops[] = $shop;
}

// Get cart count for badge
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
    <title>Browse Shops - Sweetkart</title>
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

        .page-header p {
            color: #7a5f57;
            margin-bottom: 1.5rem;
        }

        /* Search Section */
        .search-section {
            display: flex;
            gap: 1rem;
            max-width: 600px;
        }

        .search-box {
            flex: 1;
            display: flex;
            gap: 0.5rem;
        }

        .search-box input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .search-box button {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-box button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        /* Shops Grid */
        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .shop-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .shop-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .shop-header {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .shop-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .vendor-name {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .shop-body {
            padding: 1.5rem;
        }

        .shop-address {
            color: #7a5f57;
            margin-bottom: 1rem;
            line-height: 1.6;
            display: flex;
            align-items: start;
            gap: 0.5rem;
        }

        /* Rating Section */
        .shop-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #fff5f7;
            border-radius: 10px;
        }

        .stars {
            display: flex;
            gap: 0.2rem;
            font-size: 1.2rem;
        }

        .star {
            color: #ddd;
        }

        .star.filled {
            color: #ffa500;
        }

        .rating-text {
            color: #5a3e36;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .review-count {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .no-rating {
            color: #7a5f57;
            font-style: italic;
        }

        /* Shop Stats */
        .shop-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b9d;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            display: block;
            font-size: 0.85rem;
            color: #7a5f57;
        }

        /* View Products Button */
        .btn-view-products {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-view-products:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        /* Empty State */
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

        @media (max-width: 768px) {
            .shops-grid {
                grid-template-columns: 1fr;
            }

            .shop-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="products.php" class="navbar-brand"> Sweetkart</a>
        <ul class="navbar-menu">
            <li><a href="products.php"> Products</a></li>
            <li><a href="shops.php" class="active"> Shops</a></li>
            <li><a href="custom_cakes.php"> Custom Cakes</a></li>
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
            <h1>🪙 Browse Shops</h1>
            <p>Discover amazing local shops and their delicious treats</p>
            
            <div class="search-section">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search shops by name or location..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button onclick="searchShops()">🔍 Search</button>
                </div>
            </div>
        </div>

        <?php if (count($shops) > 0): ?>
        <div class="shops-grid">
            <?php foreach ($shops as $shop): ?>
            <div class="shop-card" onclick="viewShopProducts(<?php echo $shop['shop_id']; ?>)">
                <div class="shop-header">  
                    <div class="shop-name"><?php echo htmlspecialchars($shop['shop_name']); ?></div>
                    <div class="vendor-name">by <?php echo htmlspecialchars($shop['vendor_name']); ?></div>
                </div>
                
                <div class="shop-body">
                    <div class="shop-address">
                        📍 <?php echo nl2br(htmlspecialchars($shop['address'])); ?>
                    </div>

                    

                    <!-- Rating Section -->
                    <div class="shop-rating">
                        <?php if ($shop['review_count'] > 0): ?>
                            <div class="stars">
                                <?php 
                                $avg_rating = round($shop['avg_rating']);
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <span class="star <?php echo $i <= $avg_rating ? 'filled' : ''; ?>">⭐</span>
                                <?php endfor; ?>
                            </div>
                            <span class="rating-text"><?php echo number_format($shop['avg_rating'], 1); ?></span>
                            <span class="review-count">(<?php echo $shop['review_count']; ?> reviews)</span>
                        <?php else: ?>
                            <span class="no-rating">No ratings yet</span>
                        <?php endif; ?>
                    </div>

                    <!-- Shop Stats -->
                    <div class="shop-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $shop['product_count']; ?></span>
                            <span class="stat-label">Products</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $shop['review_count']; ?></span>
                            <span class="stat-label">Reviews</span>
                        </div>
                    </div>

                    <a href="products.php?shop=<?php echo $shop['shop_id']; ?>" class="btn-view-products" 
                       onclick="event.stopPropagation()">
                        👀 View Products
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-shops">
            <div class="no-shops-icon">🪙</div>
            <h3>No Shops Found</h3>
            <p>Try adjusting your search or check back later for new shops!</p>
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

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                button.classList.remove('active');
            }
        });

        // Search functionality
        function searchShops() {
            const search = document.getElementById('searchInput').value;
            window.location.href = `shops.php?search=${encodeURIComponent(search)}`;
        }

        // Allow Enter key to search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchShops();
            }
        });

        // View shop products
        function viewShopProducts(shopId) {
            window.location.href = `products.php?shop=${shopId}`;
        }
    </script>
</body>
</html>