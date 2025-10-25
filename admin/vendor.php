<?php
// Start session and check authentication
require_once '../config/auth_check.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../home.php");
    exit;
}

// Get user info
$user_name = $_SESSION['name'];

// Get database connection
require_once '../config/database.php';
$conn = getDBConnection();

// Get all vendors with their user information and shop details
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
    s.shop_id
FROM Vendors v
JOIN Users u ON v.user_id = u.user_id
LEFT JOIN Shops s ON v.vendor_id = s.vendor_id
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
    $vendors[] = $row;
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
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-badge {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .logout-btn {
            background: #5a3e36;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #7a5f57;
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
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
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
            background: #f44336;
            color: white;
        }

        .btn-reject:hover {
            background: #da190b;
            transform: translateY(-2px);
        }

        .btn-view {
            background: #2196f3;
            color: white;
        }

        .btn-view:hover {
            background: #0b7dda;
        }

        .custom-cake-badge {
            background: #ff6b9d;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🧁 Sweetkart Admin</div>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="vendors.php" class="active">Manage Vendors</a></li>
        </ul>
        <div class="navbar-user">
            <span class="user-badge">👨‍💼 <?php echo htmlspecialchars($user_name); ?></span>
            <a href="../auth/logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>👥 Vendor Management</h1>
            <p>Approve, reject, or manage vendor accounts</p>
        </div>

        <div class="vendors-table">
            <?php if (count($vendors) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Vendor Info</th>
                        <th>Shop Name</th>
                        <th>Email</th>
                        <th>Address</th>
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
                        <td><?php echo htmlspecialchars($vendor['shop_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($vendor['email']); ?></td>
                        <td><?php echo htmlspecialchars($vendor['address'] ?? 'N/A'); ?></td>
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
                                        ✗ Reject
                                    </button>
                                <?php elseif ($vendor['status'] === 'rejected'): ?>
                                    <button class="btn btn-approve" onclick="updateVendorStatus(<?php echo $vendor['vendor_id']; ?>, 'approved')">
                                        ✓ Approve
                                    </button>
                                <?php endif; ?>
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

    <script>
        function updateVendorStatus(vendorId, newStatus) {
            if (!confirm(`Are you sure you want to ${newStatus === 'approved' ? 'approve' : 'reject'} this vendor?`)) {
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
    </script>
</body>
</html>