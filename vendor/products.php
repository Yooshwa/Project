<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is vendor
if ($_SESSION['role'] !== 'vendor') {
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

// Get vendor information
$stmt = $conn->prepare("SELECT vendor_id, status FROM Vendors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();

if (!$vendor || $vendor['status'] !== 'approved') {
    header("Location: dashboard.php");
    exit;
}

$vendor_id = $vendor['vendor_id'];

// Get filter parameter
$filter_shop = $_GET['shop'] ?? 'all';

// Get all shops for this vendor (for filter dropdown)
$shops_query = "SELECT shop_id, shop_name FROM Shops WHERE vendor_id = $vendor_id ORDER BY shop_name";
$shops_result = $conn->query($shops_query);
$shops = [];
while ($row = $shops_result->fetch_assoc()) {
    $shops[] = $row;
}

// Get all products for this vendor
$products_query = "SELECT 
    p.product_id,
    p.product_name,
    p.price,
    p.description,
    p.image,
    p.customizable,
    p.quantity,
    p.created_at,
    s.shop_id,
    s.shop_name,
    c.category_name
FROM Products p
JOIN Shops s ON p.shop_id = s.shop_id
JOIN Categories c ON p.category_id = c.category_id
WHERE s.vendor_id = $vendor_id";

if ($filter_shop !== 'all') {
    $products_query .= " AND s.shop_id = " . intval($filter_shop);
}

$products_query .= " ORDER BY p.created_at DESC";

$result = $conn->query($products_query);
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Get all categories for the add/edit form
$categories_query = "SELECT category_id, category_name FROM Categories ORDER BY CASE WHEN category_name = 'Others' THEN 1 ELSE 0 END, category_name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products - Sweetkart Vendor</title>
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

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .page-header h1 {
            color: #5a3e36;
            font-size: 2rem;
        }

        .page-header p {
            color: #7a5f57;
            margin-top: 0.5rem;
        }

        .add-product-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        .add-product-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .filter-section {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-section label {
            color: #5a3e36;
            font-weight: 600;
        }

        .filter-section select {
            padding: 0.5rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            color: #5a3e36;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-section select:focus {
            outline: none;
            border-color: #ff6b9d;
        }

        .products-table {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #fff5f7;
        }

        th {
            padding: 1rem;
            text-align: left;
            color: #5a3e36;
            font-weight: 600;
            border-bottom: 2px solid #ffe8ec;
            white-space: nowrap;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #ffe8ec;
            color: #5a3e36;
        }

        tr:hover {
            background: #fff5f7;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .no-image {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .price-tag {
            color: #ff6b9d;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .stock-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .in-stock {
            background: #d4edda;
            color: #155724;
        }

        .low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .out-of-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .category-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-edit {
            background: #4caf50;
            color: white;
        }

        .btn-edit:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #da190b;
            transform: translateY(-2px);
        }

        .no-products {
            text-align: center;
            padding: 3rem;
            color: #7a5f57;
        }

        .no-products-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Modal Styles */
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
        }

        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .btn-submit {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 0.5rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand"> Sweetkart Vendor</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="shops.php">My Shops</a></li>
            <li><a href="products.php" class="active">Products</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="feedback.php">Feedback</a></li>
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
                    <div class="user-badge">⚪ VENDOR</div>
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
            <div class="header-top">
                <div>
                    <h1>🧁 My Products</h1>
                    <p>Manage your product inventory</p>
                </div>
                <button class="add-product-btn" onclick="openAddProductModal()">
                    <span>➕</span>
                    <span>Add New Product</span>
                </button>
            </div>
            <div class="filter-section">
                <label for="shopFilter">Filter by Shop:</label>
                <select id="shopFilter" onchange="filterByShop()">
                    <option value="all" <?php echo $filter_shop === 'all' ? 'selected' : ''; ?>>All Shops</option>
                    <?php foreach ($shops as $shop): ?>
                    <option value="<?php echo $shop['shop_id']; ?>" <?php echo $filter_shop == $shop['shop_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($shop['shop_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="products-table">
            <?php if (count($products) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Product Name</th>
                        <th>Shop</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <?php if (!empty($product['image'])): ?>
                                <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                     alt="Product" class="product-image">
                            <?php else: ?>
                                <div class="no-image">🧁</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($product['shop_name']); ?></td>
                        <td><span class="category-badge"><?php echo htmlspecialchars($product['category_name']); ?></span></td>
                        <td><span class="price-tag">₹<?php echo number_format($product['price'], 2); ?></span></td>
                        <td><?php echo $product['quantity']; ?></td>
                        <td>
                            <?php if ($product['quantity'] > 10): ?>
                                <span class="stock-badge in-stock">In Stock</span>
                            <?php elseif ($product['quantity'] > 0): ?>
                                <span class="stock-badge low-stock">Low Stock</span>
                            <?php else: ?>
                                <span class="stock-badge out-of-stock">Out of Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-edit" onclick='openEditProductModal(<?php echo json_encode($product); ?>)'>
                                    ✏️ Edit
                                </button>
                                <button class="btn btn-delete" onclick="openDeleteModal(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>')">
                                    🗑️ Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-products">
                <div class="no-products-icon">🧁</div>
                <h3>No Products Yet</h3>
                <p>Click "Add New Product" to start adding products to your shops</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">➕ Add New Product</div>
            <form id="addProductForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="productShop">Select Shop *</label>
                    <select id="productShop" name="shop_id" required>
                        <option value="">Choose a shop...</option>
                        <?php foreach ($shops as $shop): ?>
                        <option value="<?php echo $shop['shop_id']; ?>"><?php echo htmlspecialchars($shop['shop_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="productName">Product Name *</label>
                    <input type="text" id="productName" name="product_name" required>
                </div>
                <div class="form-group">
                    <label for="productCategory">Category *</label>
                    <select id="productCategory" name="category_id" required>
                        <option value="">Choose a category...</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="productPrice">Price (₹) *</label>
                        <input type="number" id="productPrice" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="productQuantity">Stock Quantity *</label>
                        <input type="number" id="productQuantity" name="quantity" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="productDescription">Description</label>
                    <textarea id="productDescription" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="productImage">Product Image</label>
                    <input type="file" id="productImage" name="product_image" accept="image/*" onchange="previewImage(this, 'addImagePreview')">
                    <img id="addImagePreview" class="image-preview" style="display:none;">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeAddProductModal()">Cancel</button>
                    <button type="submit" class="btn btn-submit">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Edit Product</div>
            <form id="editProductForm" enctype="multipart/form-data">
                <input type="hidden" id="editProductId" name="product_id">
                <input type="hidden" id="editCurrentImage" name="current_image">
                <div class="form-group">
                    <label for="editProductShop">Select Shop *</label>
                    <select id="editProductShop" name="shop_id" required>
                        <?php foreach ($shops as $shop): ?>
                        <option value="<?php echo $shop['shop_id']; ?>"><?php echo htmlspecialchars($shop['shop_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editProductName">Product Name *</label>
                    <input type="text" id="editProductName" name="product_name" required>
                </div>
                <div class="form-group">
                    <label for="editProductCategory">Category *</label>
                    <select id="editProductCategory" name="category_id" required>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="editProductPrice">Price (₹) *</label>
                        <input type="number" id="editProductPrice" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="editProductQuantity">Stock Quantity *</label>
                        <input type="number" id="editProductQuantity" name="quantity" min="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editProductDescription">Description</label>
                    <textarea id="editProductDescription" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="editProductImage">Product Image (leave empty to keep current)</label>
                    <input type="file" id="editProductImage" name="product_image" accept="image/*" onchange="previewImage(this, 'editImagePreview')">
                    <img id="editImagePreview" class="image-preview" style="display:none;">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeEditProductModal()">Cancel</button>
                    <button type="submit" class="btn btn-submit">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">⚠️ Confirm Deletion</div>
            <div style="margin-bottom: 1.5rem;">
                <p style="color: #5a3e36; margin-bottom: 1rem;">Are you sure you want to delete <strong id="deleteProductName"></strong>?</p>
                <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; border-left: 4px solid #ffa500;">
                    <strong>⚠️ Warning:</strong> This action cannot be undone!
                    <br><br>
                    This will permanently delete this product and all its data.
                </div>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-delete" onclick="confirmDelete()">Delete Permanently</button>
            </div>
        </div>
    </div>

    <script>
        let deleteProductId = null;

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

        // Filter by shop
        function filterByShop() {
            const shopId = document.getElementById('shopFilter').value;
            window.location.href = `products.php?shop=${shopId}`;
        }

        // Image preview
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Add Product Modal
        function openAddProductModal() {
            document.getElementById('addProductModal').style.display = 'block';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
            document.getElementById('addProductForm').reset();
            document.getElementById('addImagePreview').style.display = 'none';
        }

        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add');
            
            fetch('products_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeAddProductModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });

        // Edit Product Modal
        function openEditProductModal(product) {
            document.getElementById('editProductId').value = product.product_id;
            document.getElementById('editProductShop').value = product.shop_id;
            document.getElementById('editProductName').value = product.product_name;
            document.getElementById('editProductCategory').value = product.category_id;
            document.getElementById('editProductPrice').value = product.price;
            document.getElementById('editProductQuantity').value = product.quantity;
            document.getElementById('editProductDescription').value = product.description || '';
            document.getElementById('editCurrentImage').value = product.image || '';
           // document.getElementById('editProductCustomizable').checked = product.customizable == 1;
            
            // Show current image if exists
            if (product.image) {
                const preview = document.getElementById('editImagePreview');
                preview.src = '../uploads/products/' + product.image;
                preview.style.display = 'block';
            }
            
            document.getElementById('editProductModal').style.display = 'block';
        }

        function closeEditProductModal() {
            document.getElementById('editProductModal').style.display = 'none';
            document.getElementById('editProductForm').reset();
            document.getElementById('editImagePreview').style.display = 'none';
        }

        document.getElementById('editProductForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'edit');
            
            fetch('products_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeEditProductModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });

        // Delete Product Modal
        function openDeleteModal(productId, productName) {
            deleteProductId = productId;
            document.getElementById('deleteProductName').textContent = productName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteProductId = null;
        }

        function confirmDelete() {
            if (!deleteProductId) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('product_id', deleteProductId);

            fetch('products_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeDeleteModal();
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

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addProductModal');
            const editModal = document.getElementById('editProductModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == addModal) {
                closeAddProductModal();
            }
            if (event.target == editModal) {
                closeEditProductModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>