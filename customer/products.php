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
$user_id = $_SESSION['user_id'];

// Get database connection
require_once '../config/database.php';
$conn = getDBConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? 'all';
$shop = $_GET['shop'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';

// Get all categories for filter
$categories_query = "SELECT category_id, category_name FROM Categories ORDER BY category_name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get all approved vendor shops for filter
$shops_query = "SELECT DISTINCT s.shop_id, s.shop_name 
                FROM Shops s 
                JOIN Vendors v ON s.vendor_id = v.vendor_id 
                WHERE v.status = 'approved' 
                ORDER BY s.shop_name";
$shops_result = $conn->query($shops_query);
$shops = [];
while ($row = $shops_result->fetch_assoc()) {
    $shops[] = $row;
}

// Build products query with filters
$products_query = "SELECT 
    p.product_id,
    p.product_name,
    p.price,
    p.description,
    p.image,
    p.quantity,
    s.shop_id,
    s.shop_name,
    c.category_name,
    v.vendor_id
FROM Products p
JOIN Shops s ON p.shop_id = s.shop_id
JOIN Vendors v ON s.vendor_id = v.vendor_id
JOIN Categories c ON p.category_id = c.category_id
WHERE v.status = 'approved' AND p.quantity > 0";

// Apply search filter
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $products_query .= " AND (p.product_name LIKE '%$search_term%' OR p.description LIKE '%$search_term%')";
}

// Apply category filter
if ($category !== 'all') {
    $products_query .= " AND c.category_id = " . intval($category);
}

// Apply shop filter
if ($shop !== 'all') {
    $products_query .= " AND s.shop_id = " . intval($shop);
}

// Apply sorting
switch ($sort) {
    case 'price_low':
        $products_query .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $products_query .= " ORDER BY p.price DESC";
        break;
    case 'name':
        $products_query .= " ORDER BY p.product_name ASC";
        break;
    default: // newest
        $products_query .= " ORDER BY p.created_at DESC";
}

$result = $conn->query($products_query);
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Get cart item count
$cart_query = "SELECT IFNULL(SUM(quantity), 0) as cart_count 
               FROM Cart 
               WHERE user_id = $user_id";
$cart_result = $conn->query($cart_query);
$cart_count = $cart_result->fetch_assoc()['cart_count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Products - Sweetkart</title>
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

        .filters-section {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .search-box {
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

        .filter-select {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: #ff6b9d;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .product-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .no-image {
            font-size: 4rem;
            color: #ddd;
        }

        .product-info {
            padding: 1.25rem;
        }

        .product-name {
            color: #5a3e36;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            /*-webkit-line-clamp: 0;*/
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-shop {
            color: #7a5f57;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .product-category {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .product-price {
            color: #ff6b9d;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-add-cart {
            flex: 1;
            padding: 0.75rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .btn-add-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .stock-status {
            font-size: 0.85rem;
            padding: 0.5rem;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .in-stock {
            background: #d4edda;
            color: #155724;
        }

        .low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .no-products {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-products-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .no-products h3 {
            color: #5a3e36;
            margin-bottom: 0.5rem;
        }

        .no-products p {
            color: #7a5f57;
        }

        .success-message {
            position: fixed;
            top: 100px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: none;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .filters-section {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="products.php" class="navbar-brand">🧁 Sweetkart</a>
        <ul class="navbar-menu">
            <li><a href="products.php" class="active">🧁 Products</a></li>
            <li><a href="shops.php">🪙 Shops</a></li>
            <li><a href="custom_cakes.php">🎂 Custom Cakes</a></li>
            <li><a href="orders.php">📦 Orders</a></li>
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
            <h1>🧁 Browse Products</h1>
            <p>Discover delicious treats from local vendors</p>
            
            <div class="filters-section">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search products..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button onclick="applyFilters()">🔍 Search</button>
                </div>
                
                <select class="filter-select" id="categoryFilter" onchange="applyFilters()">
                    <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>" 
                            <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select class="filter-select" id="shopFilter" onchange="applyFilters()">
                    <option value="all" <?php echo $shop === 'all' ? 'selected' : ''; ?>>All Shops</option>
                    <?php foreach ($shops as $s): ?>
                    <option value="<?php echo $s['shop_id']; ?>" 
                            <?php echo $shop == $s['shop_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($s['shop_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <select class="filter-select" id="sortFilter" onchange="applyFilters()">
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
                </select>
            </div>
        </div>

        <?php if (count($products) > 0): ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image-container">
                    <?php if (!empty($product['image'])): ?>
                        <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                             class="product-image">
                    <?php else: ?>
                        <div class="no-image">🧁</div>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                    <div class="product-shop">🪙 <?php echo htmlspecialchars($product['shop_name']); ?></div>
                    <span class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    
                    <?php if ($product['quantity'] > 10): ?>
                        <div class="stock-status in-stock">✓ In Stock (<?php echo $product['quantity']; ?>)</div>
                    <?php elseif ($product['quantity'] > 0): ?>
                        <div class="stock-status low-stock">⚠️ Only <?php echo $product['quantity']; ?> left!</div>
                    <?php endif; ?>
                    
                    <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                    
                    <div class="product-actions">
                        <button class="btn-add-cart" 
                                onclick="addToCart(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>')">
                            🛒 Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-products">
            <div class="no-products-icon">🧁</div>
            <h3>No Products Found</h3>
            <p>Try adjusting your filters or search terms</p>
        </div>
        <?php endif; ?>
    </div>

    <div class="success-message" id="successMessage">
        ✓ Product added to cart!
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

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const category = document.getElementById('categoryFilter').value;
            const shop = document.getElementById('shopFilter').value;
            const sort = document.getElementById('sortFilter').value;
            
            let url = 'products.php?';
            if (search) url += `search=${encodeURIComponent(search)}&`;
            if (category !== 'all') url += `category=${category}&`;
            if (shop !== 'all') url += `shop=${shop}&`;
            url += `sort=${sort}`;
            
            window.location.href = url;
        }

        // Allow Enter key to search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        function addToCart(productId, productName) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);
            
            fetch('cart_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage();
                    // Update cart badge
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

        function showSuccessMessage() {
            const message = document.getElementById('successMessage');
            message.style.display = 'block';
            setTimeout(() => {
                message.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>