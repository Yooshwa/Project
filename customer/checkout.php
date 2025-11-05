<?php
/*
 * CHECKOUT PAGE
 * Purpose: Allow customer to review order, confirm address, select payment method
 * Flow: Cart → Checkout → Place Order → Order Success
 */

// Start session and check authentication
require_once '../config/auth_check.php';

// Only customers can access checkout
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

// Get user info
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

// Database connection
require_once '../config/database.php';
$conn = getDBConnection();

// Get cart items - we need to verify cart is not empty
$cart_query = "SELECT 
    c.cart_id,
    c.product_id,
    c.quantity as cart_quantity,
    p.product_name,
    p.price,
    p.image,
    p.quantity as stock_quantity,
    s.shop_name,
    s.shop_id,
    v.vendor_id
FROM Cart c
JOIN Products p ON c.product_id = p.product_id
JOIN Shops s ON p.shop_id = s.shop_id
JOIN Vendors v ON s.vendor_id = v.vendor_id
WHERE c.user_id = $user_id AND v.status = 'approved'
ORDER BY s.shop_name, p.product_name";

$result = $conn->query($cart_query);
$cart_items = [];
$subtotal = 0;
$shops = []; // Group items by shop

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $subtotal += $row['price'] * $row['cart_quantity'];
    
    // Group by shop for better display
    if (!isset($shops[$row['shop_id']])) {
        $shops[$row['shop_id']] = [
            'shop_name' => $row['shop_name'],
            'items' => []
        ];
    }
    $shops[$row['shop_id']]['items'][] = $row;
}

// If cart is empty, redirect to cart page
if (count($cart_items) === 0) {
    header("Location: cart.php");
    exit;
}

// Get user's delivery address from Users table
$user_query = "SELECT address FROM Users WHERE user_id = $user_id";
$user_result = $conn->query($user_query);
$user_data = $user_result->fetch_assoc();
$delivery_address = $user_data['address'];

// Calculate totals
$delivery_fee = 0; // You can add delivery fee logic later
$total = $subtotal + $delivery_fee;

// Get cart count for badge
$cart_count = count($cart_items);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sweetkart</title>
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

        /* Navbar styles (same as other pages) */
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

        /* Progress indicator */
        .checkout-progress {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid #ffe8ec;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #7a5f57;
            font-weight: 500;
        }

        .progress-step.active {
            color: #ff6b9d;
            font-weight: 600;
        }

        .progress-step.completed {
            color: #4caf50;
        }

        .step-number {
            width: 32px;
            height: 32px;
            background: #e0e0e0;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .progress-step.active .step-number {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
        }

        .progress-step.completed .step-number {
            background: #4caf50;
        }

        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .checkout-main {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .checkout-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: #5a3e36;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Delivery Address Section */
        .address-box {
            background: #fff5f7;
            padding: 1.5rem;
            border-radius: 10px;
            border-left: 4px solid #ff6b9d;
        }

        .address-text {
            color: #5a3e36;
            line-height: 1.8;
            margin-bottom: 1rem;
        }

        .btn-edit-address {
            background: white;
            color: #ff6b9d;
            border: 2px solid #ff6b9d;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-edit-address:hover {
            background: #ff6b9d;
            color: white;
        }

        /* Order Items Section */
        .shop-group {
            margin-bottom: 2rem;
        }

        .shop-group:last-child {
            margin-bottom: 0;
        }

        .shop-header {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #ffe8ec;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-quantity {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .item-price {
            color: #ff6b9d;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Payment Method Section */
        .payment-options {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .payment-option {
            border: 2px solid #e0e0e0;
            padding: 1.25rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-option:hover {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .payment-option.selected {
            border-color: #ff6b9d;
            background: #fff5f7;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .payment-option input[type="radio"] {
            cursor: pointer;
        }

        .payment-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: #5a3e36;
            cursor: pointer;
        }

        .payment-icon {
            font-size: 1.5rem;
        }

        /* Order Summary Sidebar */
        .order-summary {
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
            font-size: 1.4rem;
            font-weight: bold;
            padding-top: 1rem;
            border-top: 2px solid #ffe8ec;
            margin-top: 1.5rem;
        }

        .total-price {
            color: #ff6b9d;
        }

        .btn-place-order {
            width: 100%;
            padding: 1.25rem;
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

        .btn-place-order:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .btn-place-order:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .security-note {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Address Edit Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            color: #5a3e36;
            font-size: 1.5rem;
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
        }

        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            min-height: 100px;
            resize: vertical;
            transition: all 0.3s;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .btn-save {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 968px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
            }

            .payment-options {
                grid-template-columns: 1fr;
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
            <h1>💳 Checkout</h1>
            <p>Review your order and complete purchase</p>
            
            <div class="checkout-progress">
                <div class="progress-step completed">
                    <div class="step-number">✓</div>
                    <span>Cart</span>
                </div>
                <span style="color: #e0e0e0;">→</span>
                <div class="progress-step active">
                    <div class="step-number">2</div>
                    <span>Checkout</span>
                </div>
                <span style="color: #e0e0e0;">→</span>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <span>Confirm</span>
                </div>
            </div>
        </div>

        <div class="checkout-content">
            <div class="checkout-main">
                <!-- Delivery Address Section -->
                <div class="checkout-section">
                    <div class="section-title">📍 Delivery Address</div>
                    <div class="address-box">
                        <div class="address-text" id="deliveryAddress">
                            <?php echo nl2br(htmlspecialchars($delivery_address)); ?>
                        </div>
                        <button class="btn-edit-address" onclick="openAddressModal()">
                            ✏️ Edit Address
                        </button>
                    </div>
                </div>

                <!-- Order Items Section -->
                <div class="checkout-section">
                    <div class="section-title">📦 Order Items (<?php echo $cart_count; ?>)</div>
                    <?php foreach ($shops as $shop_id => $shop_data): ?>
                    <div class="shop-group">
                        <div class="shop-header">
                            🪙 <?php echo htmlspecialchars($shop_data['shop_name']); ?>
                        </div>
                        <?php foreach ($shop_data['items'] as $item): ?>
                        <div class="order-item">
                            <div class="item-info">
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="item-quantity">Quantity: <?php echo $item['cart_quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <div class="item-price">₹<?php echo number_format($item['price'] * $item['cart_quantity'], 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Payment Method Section -->
                <div class="checkout-section">
                    <div class="section-title">💳 Payment Method</div>
                    <form id="checkoutForm">
                        <div class="payment-options">
                            <label class="payment-option" onclick="selectPayment('cash')">
                                <input type="radio" name="payment_method" value="cash" checked>
                                <div class="payment-label">
                                    <span class="payment-icon">💵</span>
                                    <span>Cash on Delivery</span>
                                </div>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment('card')">
                                <input type="radio" name="payment_method" value="card">
                                <div class="payment-label">
                                    <span class="payment-icon">💳</span>
                                    <span>Debit/Credit Card</span>
                                </div>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment('upi')">
                                <input type="radio" name="payment_method" value="upi">
                                <div class="payment-label">
                                    <span class="payment-icon">📱</span>
                                    <span>UPI</span>
                                </div>
                            </label>
                            
                            <label class="payment-option" onclick="selectPayment('wallet')">
                                <input type="radio" name="payment_method" value="wallet">
                                <div class="payment-label">
                                    <span class="payment-icon">👛</span>
                                    <span>Digital Wallet</span>
                                </div>
                            </label>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="order-summary">
                <div class="summary-title">Order Summary</div>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₹<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>Delivery Fee</span>
                    <span>₹<?php echo number_format($delivery_fee, 2); ?></span>
                </div>
                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span class="total-price">₹<?php echo number_format($total, 2); ?></span>
                </div>
                
                <button class="btn-place-order" onclick="placeOrder()">
                    🎉 Place Order
                </button>

                <div class="security-note">
                    🔒 Your payment information is secure
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Address Modal -->
    <div id="addressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Edit Delivery Address</div>
            <form id="addressForm">
                <div class="form-group">
                    <label for="newAddress">Delivery Address *</label>
                    <textarea id="newAddress" required><?php echo htmlspecialchars($delivery_address); ?></textarea>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancel" onclick="closeAddressModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Address</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // User dropdown toggle
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

        // Payment method selection
        function selectPayment(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');
        }

        // Initialize first payment option as selected
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.payment-option').classList.add('selected');
        });

        // Address Modal functions
        function openAddressModal() {
            document.getElementById('addressModal').style.display = 'block';
        }

        function closeAddressModal() {
            document.getElementById('addressModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addressModal');
            if (event.target == modal) {
                closeAddressModal();
            }
        }

        // Handle address update
        document.getElementById('addressForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newAddress = document.getElementById('newAddress').value;
            
            if (!newAddress.trim()) {
                alert('Please enter a valid address');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_address');
            formData.append('address', newAddress);
            
            fetch('update_address.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update displayed address
                    document.getElementById('deliveryAddress').innerHTML = newAddress.replace(/\n/g, '<br>');
                    closeAddressModal();
                    alert('Address updated successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });

        // Place Order function
        function placeOrder() {
            // Get selected payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            // Confirm order
            if (!confirm('Confirm order placement?')) {
                return;
            }
            
            // Disable button to prevent double submission
            const btn = document.querySelector('.btn-place-order');
            btn.disabled = true;
            btn.textContent = 'Processing...';
            
            const formData = new FormData();
            formData.append('payment_method', paymentMethod);
            
            fetch('place_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to success page with order ID
                    window.location.href = 'order_success.php?order_id=' + data.order_id;
                } else {
                    alert('Error: ' + data.message);
                    btn.disabled = false;
                    btn.textContent = '🎉 Place Order';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = '🎉 Place Order';
            });
        }
    </script>
</body>
</html>