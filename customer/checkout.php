<?php
/*
 * CHECKOUT PAGE
 * Purpose: Review cart items, select payment method, place order
 * Features: Address editing, payment selection (Cash/Card), card payment form
 */

require_once '../config/auth_check.php';

// Only customers can checkout
if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

require_once '../config/database.php';
$conn = getDBConnection();

// Get user's current address
$user_query = "SELECT address FROM Users WHERE user_id = $user_id";
$user_result = $conn->query($user_query);
$user_data = $user_result->fetch_assoc();
$user_address = $user_data['address'] ?? '';

// Get cart items
$cart_query = "SELECT 
    c.cart_id,
    c.product_id,
    c.quantity as cart_quantity,
    p.product_name,
    p.price,
    p.image,
    p.quantity as stock_quantity,
    s.shop_name
FROM Cart c
JOIN Products p ON c.product_id = p.product_id
JOIN Shops s ON p.shop_id = s.shop_id
WHERE c.user_id = $user_id
ORDER BY s.shop_name, p.product_name";

$result = $conn->query($cart_query);
$cart_items = [];
$total = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total += $row['price'] * $row['cart_quantity'];
}

// If cart is empty, redirect to products
if (count($cart_items) === 0) {
    header("Location: products.php");
    exit;
}

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
            text-align: center;
        }

        .page-header h1 {
            color: #5a3e36;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7a5f57;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 2rem;
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
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #ffe8ec;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Address Section */
        .address-display {
            background: #fff5f7;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 2px solid #ffe8ec;
        }

        .address-text {
            color: #5a3e36;
            line-height: 1.6;
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
            background: #fff5f7;
        }

        .address-edit-form {
            display: none;
        }

        .address-edit-form.active {
            display: block;
        }

        .address-edit-form textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 1rem;
        }

        .address-edit-form textarea:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .address-actions {
            display: flex;
            gap: 1rem;
        }

        .btn-save-address {
            flex: 1;
            padding: 0.75rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-save-address:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .btn-cancel {
            flex: 1;
            padding: 0.75rem;
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

        /* Order Items */
        .order-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 1rem;
            padding: 1rem;
            border: 2px solid #ffe8ec;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .item-image-container {
            width: 80px;
            height: 80px;
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
            font-size: 2.5rem;
            color: #ddd;
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
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-quantity {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .item-price {
            color: #ff6b9d;
            font-weight: 600;
            font-size: 1.2rem;
            text-align: right;
        }

        /* Payment Section */
        .payment-methods {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-option:hover {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .payment-option.selected {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .payment-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .payment-label {
            flex: 1;
        }

        .payment-title {
            color: #5a3e36;
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .payment-description {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .payment-icon {
            font-size: 2rem;
        }

        /* Card Payment Form */
        .card-payment-form {
            display: none;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 2px solid #ff6b9d;
            animation: slideDown 0.3s ease;
        }

        .card-payment-form.active {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .form-header-icon {
            font-size: 1.5rem;
        }

        .form-header-title {
            color: #5a3e36;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .card-icon {
            font-size: 1.5rem;
        }

        /* Order Summary */
        .order-summary {
            position: sticky;
            top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #ffe8ec;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: #7a5f57;
        }

        .summary-value {
            color: #5a3e36;
            font-weight: 600;
        }

        .summary-total {
            font-size: 1.5rem;
            padding-top: 1rem;
            border-top: 2px solid #ffe8ec;
            margin-top: 1rem;
        }

        .summary-total .summary-value {
            color: #ff6b9d;
            font-weight: bold;
        }

        .btn-place-order {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.2rem;
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

        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            color: #4caf50;
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        @media (max-width: 968px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .order-summary {
                position: static;
            }

            .order-item {
                grid-template-columns: 60px 1fr;
            }

            .item-price {
                grid-column: 2;
                text-align: left;
                margin-top: 0.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="products.php" class="navbar-brand">🧁 Sweetkart</a>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>🛒 Checkout</h1>
            <p>Review your order and complete payment</p>
        </div>

        <div class="checkout-grid">
            <!-- Left Column: Delivery & Items -->
            <div>
                <!-- Delivery Address -->
                <div class="checkout-section">
                    <div class="section-title">📍 Delivery Address</div>
                    
                    <div class="address-display" id="addressDisplay">
                        <div class="address-text" id="addressText">
                            <?php echo nl2br(htmlspecialchars($user_address)); ?>
                        </div>
                        <button class="btn-edit-address" onclick="editAddress()">✏️ Edit Address</button>
                    </div>

                    <div class="address-edit-form" id="addressEditForm">
                        <textarea id="newAddress" placeholder="Enter your delivery address..."><?php echo htmlspecialchars($user_address); ?></textarea>
                        <div class="address-actions">
                            <button class="btn-save-address" onclick="saveAddress()">💾 Save Address</button>
                            <button class="btn-cancel" onclick="cancelEdit()">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="checkout-section" style="margin-top: 2rem;">
                    <div class="section-title">📦 Order Items (<?php echo count($cart_items); ?>)</div>
                    
                    <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
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
                            <div class="item-quantity">Quantity: <?php echo $item['cart_quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></div>
                        </div>
                        <div class="item-price">₹<?php echo number_format($item['price'] * $item['cart_quantity'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column: Payment & Summary -->
            <div>
                <!-- Payment Method -->
                <div class="checkout-section order-summary">
                    <div class="section-title">💳 Payment Method</div>
                    
                    <div class="payment-methods">
                        <label class="payment-option" onclick="selectPayment('cash')">
                            <input type="radio" name="payment" value="cash" checked>
                            <div class="payment-label">
                                <div class="payment-title">Cash on Delivery</div>
                                <div class="payment-description">Pay when you receive your order</div>
                            </div>
                            <div class="payment-icon">💵</div>
                        </label>

                        <label class="payment-option" onclick="selectPayment('card')">
                            <input type="radio" name="payment" value="card">
                            <div class="payment-label">
                                <div class="payment-title">Card Payment</div>
                                <div class="payment-description">Pay securely with your card</div>
                            </div>
                            <div class="payment-icon">💳</div>
                        </label>
                    </div>

                    <!-- Card Payment Form -->
                    <div class="card-payment-form" id="cardPaymentForm">
                        <div class="form-header">
                            <span class="form-header-icon">💳</span>
                            <span class="form-header-title">Enter Card Details</span>
                        </div>

                        <div class="form-group">
                            <label for="cardholderName">Cardholder Name</label>
                            <input type="text" id="cardholderName" placeholder="JOHN DOE" maxlength="50">
                        </div>

                        <div class="form-group">
                            <label for="cardNumber">Card Number</label>
                            <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" 
                                   maxlength="19" oninput="formatCardNumber(this)">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="expiryDate">Expiry Date</label>
                                <input type="text" id="expiryDate" placeholder="MM/YY" 
                                       maxlength="5" oninput="formatExpiry(this)">
                            </div>
                            <div class="form-group">
                                <label for="cvv">CVV</label>
                                <input type="text" id="cvv" placeholder="123" 
                                       maxlength="3" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">₹<?php echo number_format($total, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Delivery Fee</span>
                        <span class="summary-value">FREE</span>
                    </div>
                    <div class="summary-row summary-total">
                        <span class="summary-label">Total Amount</span>
                        <span class="summary-value">₹<?php echo number_format($total, 2); ?></span>
                    </div>

                    <button class="btn-place-order" onclick="placeOrder()">
                        🎉 Place Order
                    </button>

                    <div class="secure-badge">
                        🔒 Secure Checkout
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedPayment = 'cash';

        // Payment selection
        function selectPayment(method) {
            selectedPayment = method;
            
            // Update radio buttons
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Show/hide card form
            const cardForm = document.getElementById('cardPaymentForm');
            if (method === 'card') {
                cardForm.classList.add('active');
            } else {
                cardForm.classList.remove('active');
            }
        }

        // Address editing
        function editAddress() {
            document.getElementById('addressDisplay').style.display = 'none';
            document.getElementById('addressEditForm').classList.add('active');
        }

        function cancelEdit() {
            document.getElementById('addressDisplay').style.display = 'block';
            document.getElementById('addressEditForm').classList.remove('active');
        }

        function saveAddress() {
            const newAddress = document.getElementById('newAddress').value.trim();
            
            if (!newAddress) {
                alert('Please enter a delivery address');
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
                    document.getElementById('addressText').innerHTML = newAddress.replace(/\n/g, '<br>');
                    cancelEdit();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Card number formatting
        function formatCardNumber(input) {
            let value = input.value.replace(/\s/g, '').replace(/[^0-9]/g, '');
            let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
            input.value = formatted;
        }

        // Expiry date formatting
        function formatExpiry(input) {
            let value = input.value.replace(/[^0-9]/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            input.value = value;
        }

        // Validate card details
        function validateCardDetails() {
            const name = document.getElementById('cardholderName').value.trim();
            const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
            const expiry = document.getElementById('expiryDate').value;
            const cvv = document.getElementById('cvv').value;

            if (!name) {
                alert('Please enter cardholder name');
                return false;
            }

            if (cardNumber.length !== 16) {
                alert('Please enter a valid 16-digit card number');
                return false;
            }

            if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                alert('Please enter expiry date in MM/YY format');
                return false;
            }

            // Validate expiry date
            const [month, year] = expiry.split('/').map(Number);
            const currentYear = new Date().getFullYear() % 100;
            const currentMonth = new Date().getMonth() + 1;

            if (month < 1 || month > 12) {
                alert('Invalid expiry month');
                return false;
            }

            if (year < currentYear || (year === currentYear && month < currentMonth)) {
                alert('Card has expired');
                return false;
            }

            if (cvv.length !== 3) {
                alert('Please enter a valid 3-digit CVV');
                return false;
            }

            return true;
        }

        // Place order
        function placeOrder() {
            // Validate card details if card payment selected
            if (selectedPayment === 'card') {
                if (!validateCardDetails()) {
                    return;
                }
            }

            // Disable button
            const btn = document.querySelector('.btn-place-order');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            const formData = new FormData();
            formData.append('payment_method', selectedPayment);

            fetch('place_order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('✅ Payment Successful! Your order has been placed.');
                    
                    // Redirect to success page
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