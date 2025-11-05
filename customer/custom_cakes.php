<?php
/*
 * CUSTOM CAKE REQUEST PAGE
 * Purpose: Allow customers to request custom cakes from vendors
 * Features: Request form, image upload, request history, status tracking
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

// Get shops that offer custom cakes (from approved vendors only)
$shops_query = "SELECT 
    s.shop_id,
    s.shop_name,
    s.address,
    u.name as vendor_name
FROM Shops s
JOIN Vendors v ON s.vendor_id = v.vendor_id
JOIN Users u ON v.user_id = u.user_id
WHERE v.status = 'approved' AND v.custom_cake_flag = 1
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
    cco.reference_image,
    cco.special_instructions,
    cco.delivery_date,
    cco.estimated_price,
    cco.final_price,
    cco.status,
    cco.created_at,
    s.shop_name,
    s.shop_id
FROM Custom_Cake_Orders cco
JOIN Shops s ON cco.shop_id = s.shop_id
WHERE cco.user_id = $user_id
ORDER BY cco.created_at DESC";

$requests_result = $conn->query($requests_query);
$requests = [];

while ($request = $requests_result->fetch_assoc()) {
    $requests[] = $request;
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
            margin-bottom: 0.5rem;
        }
        .page-header p {
            color: #7a5f57;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        /* Request Form Section */
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: #5a3e36;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .required {
            color: #f44336;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .file-upload {
            position: relative;
        }

        .file-upload input[type="file"] {
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
            border-color: #ff8fab;
        }

        .image-preview {
            margin-top: 1rem;
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            display: none;
        }

        .no-shops-message {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ffa500;
            margin-bottom: 1rem;
        }

        .btn-submit {
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
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Requests List Section */
        .requests-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            max-height: 800px;
            overflow-y: auto;
        }

        .request-card {
            border: 2px solid #ffe8ec;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }

        .request-card:hover {
            border-color: #ff6b9d;
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.1);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ffe8ec;
        }

        .request-id {
            color: #5a3e36;
            font-weight: 600;
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
        }

        .status-quoted {
            background: #cfe2ff;
            color: #084298;
        }

        .status-confirmed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-in_progress {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-completed {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #842029;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            color: #7a5f57;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: #5a3e36;
            font-weight: 600;
        }

        .request-description {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ffe8ec;
        }

        .request-description p {
            color: #5a3e36;
            line-height: 1.6;
        }

        .price-section {
            margin-top: 1rem;
            padding: 1rem;
            background: #fff5f7;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price-label {
            color: #7a5f57;
            font-weight: 500;
        }

        .price-value {
            color: #ff6b9d;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .no-requests {
            text-align: center;
            padding: 2rem;
            color: #7a5f57;
        }

        .no-requests-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .requests-section {
                max-height: none;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .request-details {
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
            <li><a href="custom_cakes.php" class="active">🎂 Custom Cakes</a></li>
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
            <h1>🎂 Custom Cake Requests</h1>
            <p>Design your dream cake with our talented vendors</p>
        </div>

        <div class="content-grid">
            <!-- Request Form -->
            <div class="form-section">
                <div class="section-title">📝 New Request</div>

                <?php if (count($custom_shops) > 0): ?>
                <form id="customCakeForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="shopSelect">Select Shop <span class="required">*</span></label>
                        <select id="shopSelect" name="shop_id" required>
                            <option value="">Choose a shop...</option>
                            <?php foreach ($custom_shops as $shop): ?>
                            <option value="<?php echo $shop['shop_id']; ?>">
                                <?php echo htmlspecialchars($shop['shop_name']); ?> 
                                (by <?php echo htmlspecialchars($shop['vendor_name']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="flavour">Flavour <span class="required">*</span></label>
                            <input type="text" id="flavour" name="flavour" required 
                                   placeholder="e.g., Chocolate, Vanilla">
                        </div>
                        <div class="form-group">
                            <label for="size">Size <span class="required">*</span></label>
                            <input type="text" id="size" name="size" required 
                                   placeholder="e.g., 8 inch, 10 inch">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="shape">Shape</label>
                            <input type="text" id="shape" name="shape" 
                                   placeholder="e.g., Round, Square, Heart">
                        </div>
                        <div class="form-group">
                            <label for="weight">Weight (kg)</label>
                            <input type="number" id="weight" name="weight" step="0.1" min="0.5" 
                                   placeholder="e.g., 1.5">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="layers">Number of Layers</label>
                        <input type="number" id="layers" name="layers" min="1" max="10" 
                               placeholder="e.g., 2">
                    </div>

                    <div class="form-group">
                        <label for="description">Description <span class="required">*</span></label>
                        <textarea id="description" name="description" required 
                                  placeholder="Describe your dream cake..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="specialInstructions">Special Instructions</label>
                        <textarea id="specialInstructions" name="special_instructions" 
                                  placeholder="Any dietary requirements, allergies, or special requests..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="deliveryDate">Delivery Date <span class="required">*</span></label>
                        <input type="date" id="deliveryDate" name="delivery_date" required 
                               min="<?php echo date('Y-m-d', strtotime('+3 days')); ?>">
                    </div>

                    <div class="form-group file-upload">
                        <label>Reference Image (Optional)</label>
                        <input type="file" id="referenceImage" name="reference_image" 
                               accept="image/*" onchange="previewImage(this)">
                        <label for="referenceImage" class="file-upload-label">
                            📷 Choose Image
                        </label>
                        <img id="imagePreview" class="image-preview" alt="Preview">
                    </div>

                    <button type="submit" class="btn-submit">🎂 Submit Request</button>
                </form>
                <?php else: ?>
                <div class="no-shops-message">
                    ⚠️ No shops currently offer custom cake services. Please check back later!
                </div>
                <?php endif; ?>
            </div>

            <!-- Requests List -->
            <div class="requests-section">
                <div class="section-title">📋 My Requests</div>

                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $request): ?>
                    <div class="request-card">
                        <div class="request-header">
                            <div class="request-id">Request #<?php echo $request['custom_order_id']; ?></div>
                            <div class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                            </div>
                        </div>

                        <div class="request-details">
                            <div class="detail-item">
                                <span class="detail-label">Shop</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['shop_name']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Delivery Date</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($request['delivery_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Flavour</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['flavour']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Size</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['size']); ?></span>
                            </div>
                            <?php if ($request['shape']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Shape</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['shape']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($request['layers']): ?>
                            <div class="detail-item">
                                <span class="detail-label">Layers</span>
                                <span class="detail-value"><?php echo $request['layers']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="request-description">
                            <div class="detail-label">Description</div>
                            <p><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>
                        </div>

                        <?php if ($request['estimated_price'] || $request['final_price']): ?>
                        <div class="price-section">
                            <span class="price-label">
                                <?php echo $request['final_price'] ? 'Final Price' : 'Estimated Price'; ?>
                            </span>
                            <span class="price-value">
                                ₹<?php echo number_format($request['final_price'] ?: $request['estimated_price'], 2); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="no-requests">
                    <div class="no-requests-icon">🎂</div>
                    <h3>No Requests Yet</h3>
                    <p>Submit your first custom cake request!</p>
                </div>
                <?php endif; ?>
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

        // Image preview
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const label = input.nextElementSibling;
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    label.textContent = '✓ Image Selected';
                    label.style.background = '#d1e7dd';
                    label.style.borderColor = '#4caf50';
                    label.style.color = '#0f5132';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Form submission
        document.getElementById('customCakeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('.btn-submit');
            
            // Disable button
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
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = '🎂 Submit Request';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.textContent = '🎂 Submit Request';
            });
        });
    </script>
</body>
</html>