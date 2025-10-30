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

// Get all shops for this vendor
$query = "SELECT 
    s.shop_id,
    s.shop_name,
    s.address,
    s.created_at,
    COUNT(p.product_id) as product_count
FROM Shops s
LEFT JOIN Products p ON s.shop_id = p.shop_id
WHERE s.vendor_id = $vendor_id
GROUP BY s.shop_id, s.shop_name, s.address, s.created_at
ORDER BY s.created_at DESC";

$result = $conn->query($query);
$shops = [];
while ($row = $result->fetch_assoc()) {
    $shops[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Shops - Sweetkart Vendor</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: #5a3e36;
            font-size: 2rem;
        }

        .page-header p {
            color: #7a5f57;
            margin-top: 0.5rem;
        }

        .add-shop-btn {
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

        .add-shop-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
        }

        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .shop-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .shop-card:hover {
            transform: translateY(-5px);
        }

        .shop-header {
            display: flex;
            align-items: start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .shop-icon {
            font-size: 2.5rem;
        }

        .shop-info {
            flex: 1;
        }

        .shop-name {
            color: #5a3e36;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .shop-id {
            color: #7a5f57;
            font-size: 0.85rem;
        }

        .shop-address {
            color: #7a5f57;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .shop-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #fff5f7;
            border-radius: 10px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b9d;
        }

        .stat-label {
            display: block;
            font-size: 0.85rem;
            color: #7a5f57;
            margin-top: 0.25rem;
        }

        .shop-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
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

        .no-shops {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-shops-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .no-shops h3 {
            color: #5a3e36;
            margin-bottom: 0.5rem;
        }

        .no-shops p {
            color: #7a5f57;
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
            animation: fadeIn 0.3s;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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

        .created-date {
            color: #7a5f57;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">🧁 Sweetkart Vendor</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="shops.php" class="active">My Shops</a></li>
            <li><a href="products.php">Products</a></li>
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
                    <div class="user-badge">🪙 VENDOR</div>
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
            <div>
                <h1>🏪 My Shops</h1>
                <p>Manage your shop locations and details</p>
            </div>
            <button class="add-shop-btn" onclick="openAddShopModal()">
                <span>➕</span>
                <span>Add New Shop</span>
            </button>
        </div>

        <?php if (count($shops) > 0): ?>
        <div class="shops-grid">
            <?php foreach ($shops as $shop): ?>
            <div class="shop-card">
                <div class="shop-header">
                    <div class="shop-icon">🏪</div>
                    <div class="shop-info">
                        <div class="shop-name"><?php echo htmlspecialchars($shop['shop_name']); ?></div>
                        <div class="shop-id">ID: <?php echo $shop['shop_id']; ?></div>
                    </div>
                </div>
                <div class="shop-address">
                    📍 <?php echo htmlspecialchars($shop['address']); ?>
                </div>
                <div class="shop-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $shop['product_count']; ?></span>
                        <span class="stat-label">Products</span>
                    </div>
                </div>
                <div class="created-date">
                    Created: <?php echo date('M d, Y', strtotime($shop['created_at'])); ?>
                </div>
                <div class="shop-actions">
                    <button class="btn btn-edit" onclick="openEditShopModal(<?php echo htmlspecialchars(json_encode($shop)); ?>)">
                        ✏️ Edit
                    </button>
                    <button class="btn btn-delete" onclick="openDeleteModal(<?php echo $shop['shop_id']; ?>, '<?php echo htmlspecialchars($shop['shop_name'], ENT_QUOTES); ?>', <?php echo $shop['product_count']; ?>)">
                        🗑️ Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-shops">
            <div class="no-shops-icon">🏪</div>
            <h3>No Shops Yet</h3>
            <p>Click "Add New Shop" to create your first shop</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Shop Modal -->
    <div id="addShopModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">➕ Add New Shop</div>
            <form id="addShopForm">
                <div class="form-group">
                    <label for="shopName">Shop Name *</label>
                    <input type="text" id="shopName" name="shopName" required>
                </div>
                <div class="form-group">
                    <label for="shopAddress">Address *</label>
                    <textarea id="shopAddress" name="shopAddress" required></textarea>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeAddShopModal()">Cancel</button>
                    <button type="submit" class="btn btn-submit">Add Shop</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Shop Modal -->
    <div id="editShopModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✏️ Edit Shop</div>
            <form id="editShopForm">
                <input type="hidden" id="editShopId" name="shopId">
                <div class="form-group">
                    <label for="editShopName">Shop Name *</label>
                    <input type="text" id="editShopName" name="shopName" required>
                </div>
                <div class="form-group">
                    <label for="editShopAddress">Address *</label>
                    <textarea id="editShopAddress" name="shopAddress" required></textarea>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-cancel" onclick="closeEditShopModal()">Cancel</button>
                    <button type="submit" class="btn btn-submit">Update Shop</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">⚠️ Confirm Deletion</div>
            <div style="margin-bottom: 1.5rem;">
                <p style="color: #5a3e36; margin-bottom: 1rem;">Are you sure you want to delete <strong id="deleteShopName"></strong>?</p>
                <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; border-left: 4px solid #ffa500;">
                    <strong>⚠️ Warning:</strong> This action cannot be undone!
                    <br><br>
                    This will permanently delete:
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <li><span id="deleteProductCount"></span> product(s)</li>
                        <li>All associated data</li>
                    </ul>
                </div>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-delete" onclick="confirmDelete()">Delete Permanently</button>
            </div>
        </div>
    </div>

    <script>
        let deleteShopId = null;

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

        // Add Shop Modal
        function openAddShopModal() {
            document.getElementById('addShopModal').style.display = 'block';
        }

        function closeAddShopModal() {
            document.getElementById('addShopModal').style.display = 'none';
            document.getElementById('addShopForm').reset();
        }

        document.getElementById('addShopForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'add');
            
            fetch('shops_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeAddShopModal();
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

        // Edit Shop Modal
        function openEditShopModal(shop) {
            document.getElementById('editShopId').value = shop.shop_id;
            document.getElementById('editShopName').value = shop.shop_name;
            document.getElementById('editShopAddress').value = shop.address;
            document.getElementById('editShopModal').style.display = 'block';
        }

        function closeEditShopModal() {
            document.getElementById('editShopModal').style.display = 'none';
            document.getElementById('editShopForm').reset();
        }

        document.getElementById('editShopForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'edit');
            
            fetch('shops_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeEditShopModal();
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

        // Delete Shop Modal
        function openDeleteModal(shopId, shopName, productCount) {
            deleteShopId = shopId;
            document.getElementById('deleteShopName').textContent = shopName;
            document.getElementById('deleteProductCount').textContent = productCount;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteShopId = null;
        }

        function confirmDelete() {
            if (!deleteShopId) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('shop_id', deleteShopId);

            fetch('shops_handler.php', {
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
            const addModal = document.getElementById('addShopModal');
            const editModal = document.getElementById('editShopModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == addModal) {
                closeAddShopModal();
            }
            if (event.target == editModal) {
                closeEditShopModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>