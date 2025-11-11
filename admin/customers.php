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

// Get all customers with their information
$query = "SELECT 
    u.user_id,
    u.name,
    u.email,
    u.address,
    u.created_at
FROM Users u
WHERE u.role = 'customer'
ORDER BY u.created_at DESC";

$result = $conn->query($query);
$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Sweetkart Admin</title>
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

        .customers-table {
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

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #da190b;
            transform: translateY(-2px);
        }

        .no-customers {
            text-align: center;
            padding: 3rem;
            color: #7a5f57;
        }

        .no-customers-icon {
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
        <a href="dashboard.php" class="navbar-brand">Sweetkart Admin</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="vendors.php">Manage Vendors</a></li>
            <li><a href="customers.php" class="active">Manage Customers</a></li>
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
                    <div class="user-badge">⚪ ADMIN</div>
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
            <h1>Customer Management</h1>
            <p>View and manage customer accounts</p>
        </div>

        <div class="customers-table">
            <?php if (count($customers) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Customer Info</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
                            <br>
                            <small style="color: #7a5f57;">ID: <?php echo $customer['user_id']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo htmlspecialchars($customer['address'] ?? 'Not provided'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-delete" onclick="showDeleteModal(<?php echo $customer['user_id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>')">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-customers">
                <div class="no-customers-icon">👥</div>
                <h3>No Customers Yet</h3>
                <p>Customers will appear here once they register</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">⚠️ Confirm Deletion</div>
            <div class="modal-body">
                <p>Are you sure you want to delete customer <strong id="customerName"></strong>?</p>
                <div class="modal-warning">
                    <strong>⚠️ Warning:</strong> This action cannot be undone!
                    <br><br>
                    This will permanently delete:
                    <ul style="margin: 0.5rem 0 0 1.5rem;">
                        <li>Customer account</li>
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
        let deleteCustomerId = null;

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

        function showDeleteModal(customerId, customerName) {
            deleteCustomerId = customerId;
            document.getElementById('customerName').textContent = customerName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteCustomerId = null;
        }

        function confirmDelete() {
            if (!deleteCustomerId) return;

            fetch('delete_customer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `customer_id=${deleteCustomerId}`
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