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

// Get cart items
$cart_query = "SELECT 
    c.cart_id,
    c.product_id,
    c.quantity as cart_quantity,
    p.product_name,
    p.price,
    p.image,
    p.quantity as stock_quantity,
    s.shop_name,
    s.shop_id
FROM Cart c
JOIN Products p ON c.product_id = p.product_id
JOIN Shops s ON p.shop_id = s.shop_id
WHERE c.user_id = $user_id
ORDER BY c.added_at DESC";

$result = $conn->query($cart_query);
$cart_items = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total += $row['price'] * $row['cart_quantity'];
}

// Get cart count for badge
$cart_count = count($cart_items);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Sweetkart</title>
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
            max-width: 1200px;
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
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7a5f57;
        }

        .cart-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .cart-items {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border: 2px solid #ffe8ec;
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .cart-item:hover {
            border-color: #ff6b9d;
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.1);
        }

        .item-image-container {
            width: 100px;
            height: 100px;
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
            font-size: 3rem;
            color: #ddd;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            color: #5a3e36;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .item-shop {
            color: #7a5f57;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .item-price {
            color: #ff6b9d;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            align-items: flex-end;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fff5f7;
            padding: 0.5rem;
            border-radius: 8px;
        }

        .qty-btn {
            width: 32px;
            height: 32px;
            background: white;
            border: 2px solid #ff6b9d;
            color: #ff6b9d;
            border-radius: 6px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .qty-btn:hover {
            background: #ff6b9d;
            color: white;
        }

        .qty-value {
            min-width: 40px;
            text-align: center;
            font-weight: 600;
            color: #5a3e36;
        }

        .btn-remove {
            background: #f44336;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-remove:hover {
            background: #da190b;
            transform: translateY(-2px);
        }

        .cart-summary {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-title {
            color: #5a3e36;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ffe8ec;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #5a3e36;
        }

        .summary-row.total {
            font-size: 1.3rem;
            font-weight: bold;
            padding-top: 1rem;
            border-top: 2px solid #ffe8ec;
            margin-top: 1.5rem;
        }

        .total-price {
            color: #ff6b9d;
        }

        .btn-checkout {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1.5rem;
        }

        .btn-checkout:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .btn-continue {
            width: 100%;
            padding: 1rem;
            background: white;
            color: #ff6b9d;
            border: 2px solid #ff6b9d;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .btn-continue:hover {
            background: #fff5f7;
        }

        .empty-cart {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .empty-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
        }

        .empty-cart h3 {
            color: #5a3e36;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-cart p {
            color: #7a5f57;
            margin-bottom: 2rem;
        }

        @media (max-width: 968px) {
            .cart-content {
                grid-template-columns: 1fr;
            }

            .cart-summary {
                position: static;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
            }

            .item-actions {
                grid-column: 2;
                flex-direction: row;
                justify-content: space-between;
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
            <li>
                <a href="cart.php" class="active">
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
            <h1>🛒 Shopping Cart</h1>
            <p>Review your items before checkout</p>
        </div>

        <?php if (count($cart_items) > 0): ?>
        <div class="cart-content">
            <div class="cart-items">
                <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
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
                        <div class="item-price">₹<?php echo number_format($item['price'], 2); ?> × <?php echo $item['cart_quantity']; ?> = 
                            ₹<?php echo number_format($item['price'] * $item['cart_quantity'], 2); ?>
                        </div>
                    </div>
                    <div class="item-actions">
                        <div class="quantity-controls">
                            <button class="qty-btn" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, -1, <?php echo $item['cart_quantity']; ?>)">−</button>
                            <span class="qty-value" id="qty-<?php echo $item['cart_id']; ?>"><?php echo $item['cart_quantity']; ?></span>
                            <button class="qty-btn" onclick="updateQuantity(<?php echo $item['cart_id']; ?>, 1, <?php echo $item['cart_quantity']; ?>, <?php echo $item['stock_quantity']; ?>)">+</button>
                        </div>
                        <button class="btn-remove" onclick="removeItem(<?php echo $item['cart_id']; ?>, '<?php echo htmlspecialchars($item['product_name'], ENT_QUOTES); ?>')">
                            🗑️ Remove
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="cart-summary">
                <div class="summary-title">Order Summary</div>
                <div class="summary-row">
                    <span>Subtotal (<?php echo $cart_count; ?> items)</span>
                    <span>₹<?php echo number_format($total, 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total</span>
                    <span class="total-price">₹<?php echo number_format($total, 2); ?></span>
                </div>
                <button class="btn-checkout" onclick="window.location.href='checkout.php'">
                    💳 Proceed to Checkout
                </button>
                <a href="products.php" class="btn-continue">← Continue Shopping</a>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-cart">
            <div class="empty-icon">🛒</div>
            <h3>Your Cart is Empty</h3>
            <p>Add some delicious treats to get started!</p>
            <a href="products.php" class="btn-checkout" style="display: inline-block; text-decoration: none; max-width: 300px; margin: 0 auto;">
                Browse Products
            </a>
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

        function updateQuantity(cartId, change, currentQty, maxStock = 999) {
            const newQty = currentQty + change;
            
            if (newQty < 1) {
                alert('Quantity cannot be less than 1');
                return;
            }
            
            if (newQty > maxStock) {
                alert('Not enough stock available');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('cart_id', cartId);
            formData.append('quantity', newQty);
            
            fetch('cart_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
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

        function removeItem(cartId, productName) {
            if (!confirm(`Remove "${productName}" from cart?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('cart_id', cartId);
            
            fetch('cart_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
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