<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Get user info
$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];

// Get database connection
require_once '../config/database.php';
$conn = getDBConnection();

// Get all vendors with their user information, shop details, and product counts
$query = "SELECT 
    v.vendor_id,
    v.user_id,
    v.status,
    v.custom_cake_flag,
    v.created_at as vendor_created,
    u.name,
    u.email,
    u.address,
    s.shop_name,
    s.shop_id,
    COUNT(DISTINCT s2.shop_id) as total_shops,
    COUNT(p.product_id) as total_products
FROM Vendors v
JOIN Users u ON v.user_id = u.user_id
LEFT JOIN Shops s ON v.vendor_id = s.vendor_id
LEFT JOIN Shops s2 ON v.vendor_id = s2.vendor_id
LEFT JOIN Products p ON s2.shop_id = p.shop_id
GROUP BY v.vendor_id, v.user_id, v.status, v.custom_cake_flag, v.created_at, u.name, u.email, u.address, s.shop_name, s.shop_id
ORDER BY 
    CASE v.status
        WHEN 'pending' THEN 1
        WHEN 'approved' THEN 2
        WHEN 'rejected' THEN 3
    END,
    v.created_at DESC";

$result = $conn->query($query);
$vendors = [];
while ($row = $result->fetch_assoc()) {
    $vendor_id = $row['vendor_id'];
    if (!isset($vendors[$vendor_id])) {
        $vendors[$vendor_id] = $row;
        $vendors[$vendor_id]['shop_names'] = [];
    }
    if ($row['shop_name']) {
        $vendors[$vendor_id]['shop_names'][] = $row['shop_name'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vendors - Sweetkart Admin</title>
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

        .page-header h1 {
            color: #5a3e36;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #7a5f57;
        }

        .vendors-table {
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

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }

        .btn-approve {
            background: #4caf50;
            color: white;
        }

        .btn-approve:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #ff9800;
            color: white;
        }

        .btn-reject:hover {
            background: #e68900;
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

        .custom-cake-badge {
            background: #ff6b9d;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .product-count {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .shop-count {
            display: inline-block;
            background: #f3e5f5;
            color: #7b1fa2;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .shop-names {
            font-size: 0.85rem;
            color: #5a3e36;
        }

        .shop-names-small {
            color: #7a5f57;
            font-size: 0.8rem;
        }

        .no-vendors {
            text-align: center;
            padding: 3rem;
            color: #7a5f57;
        }

        .no-vendors-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* Confirmation Modal */
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
            margin: 10% auto;
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
            margin-bottom: 1rem;
        }

        .modal-body {
            color: #7a5f57;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .modal-warning {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #ffa500;
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

        .btn-confirm-delete {
            background: #f44336;
            color: white;
        }

        .btn-confirm-delete:hover {
            background: #da190b;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">🧁 Sweetkart Admin</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="vendors.php" class="active">Manage Vendors</a></li>
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
                    <div class="user-badge">👨‍💼 ADMIN</div>
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
            <h1>Vendor Management</h1>
            <p>Approve, reject, or manage vendor accounts</p>
        </div>

        <div class="vendors-table">
            <?php if (count($vendors) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Vendor Info</th>
                        <th>Shops</th>
                        <th>Products</th>
                        <th>Email</th>
                        <th>Custom Cakes</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($vendor['name']); ?></strong>
                            <br>
                            <small style="color: #7a5f57;">ID: <?php echo $vendor['vendor_id']; ?></small>
                        </td>
                        <td>
                            <span class="shop-count">🪙 <?php echo $vendor['total_shops']; ?> Shop(s)</span>
                            <?php if (count($vendor['shop_names']) > 0): ?>
                                <br>
                                <span class="shop-names-small">
                                    <?php echo htmlspecialchars(implode(', ', array_slice($vendor['shop_names'], 0, 2))); ?>
                                    <?php if (count($vendor['shop_names']) > 2): ?>
                                        <br>+ <?php echo count($vendor['shop_names']) - 2; ?> more
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="product-count">🧁 <?php echo $vendor['total_products']; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                        <td>
                            <?php if ($vendor['custom_cake_flag']): ?>
                                <span class="custom-cake-badge">🎂 Available</span>
                            <?php else: ?>
                                <span style="color: #7a5f57;">Not offered</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $vendor['status']; ?>">
                                <?php echo ucfirst($vendor['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($vendor['vendor_created'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($vendor['status'] === 'pending'): ?>
                                    <button class="btn btn-approve" onclick="updateVendorStatus(<?php echo $vendor['vendor_id']; ?>, 'approved')">
                                        ✓ Approve
                                    </button>
                                    <button class="btn btn-reject" onclick="updateVendorStatus(<?php echo $vendor['vendor_id']; ?>, 'rejected')">
                                        ✗ Reject
                                    </button>
                                <?php elseif ($vendor['status'] === 'approved'): ?>
                                    <button class="btn btn-reject" onclick="updateVendorStatus(<?php echo $vendor['vendor_id']; ?>, 'rejected')">
                                        ✗ Suspend
                                    </button>
                                <?php elseif ($vendor['status'] === 'rejected'): ?>
                                    <button class="btn btn-approve" onclick="updateVendorStatus(<?php echo $vendor['vendor_id']; ?>, 'approved')">
                                        ✓ Approve
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-delete" onclick="showDeleteModal(<?php echo $vendor['vendor_id']; ?>, '<?php echo htmlspecialchars($vendor['name'], ENT_QUOTES); ?>', <?php echo $vendor['total_shops']; ?>, <?php echo $vendor['total_products']; ?>)">
                                    🗑️ Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-vendors">
                <div class="no-vendors-icon">👥</div>
                <h3>No Vendors Yet</h3>
                <p>Vendors will appear here once they register</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">⚠️ Confirm Deletion</div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="vendorName"></strong>?</p>
                <div class="modal-warning">
                    <strong>⚠️ Warning:</strong> This action cannot be undone!
                    <br><br>
                    This will permanently delete:
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <li><span id="deleteShops"></span> shop(s)</li>
                        <li><span id="deleteProducts"></span> product(s)</li>
                        <li>All associated data</li>
                    </ul>
                </div>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="btn btn-confirm-delete" onclick="confirmDelete()">Delete Permanently</button>
            </div>
        </div>
    </div>

    <script>
        let deleteVendorId = null;

        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            dropdown.classList.toggle('show');
            button.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
                button.classList.remove('active');
            }
        });

        function updateVendorStatus(vendorId, newStatus) {
            const action = newStatus === 'approved' ? 'approve' : (newStatus === 'rejected' ? 'reject' : 'update');
            if (!confirm(`Are you sure you want to ${action} this vendor?`)) {
                return;
            }

            fetch('update_vendor_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `vendor_id=${vendorId}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
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

        function showDeleteModal(vendorId, vendorName, shopCount, productCount) {
            deleteVendorId = vendorId;
            document.getElementById('vendorName').textContent = vendorName;
            document.getElementById('deleteShops').textContent = shopCount;
            document.getElementById('deleteProducts').textContent = productCount;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteVendorId = null;
        }

        function confirmDelete() {
            if (!deleteVendorId) return;

            fetch('delete_vendor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `vendor_id=${deleteVendorId}`
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
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>