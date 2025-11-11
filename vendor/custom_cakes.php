<?php
/*
 * VENDOR CUSTOM CAKE MANAGEMENT - UPDATED
 * Purpose: View and manage custom cake requests (Accept/Reject only)
 */

require_once '../config/auth_check.php';

if ($_SESSION['role'] !== 'vendor') {
    header("Location: ../index.php");
    exit;
}

$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$vendor_user_id = $_SESSION['user_id'];

require_once '../config/database.php';
$conn = getDBConnection();

// Get vendor info
$vendor_query = "SELECT vendor_id, custom_cake_flag FROM Vendors WHERE user_id = $vendor_user_id";
$vendor_result = $conn->query($vendor_query);
$vendor = $vendor_result->fetch_assoc();
$vendor_id = $vendor['vendor_id'];
$custom_cake_flag = $vendor['custom_cake_flag'];

// If vendor doesn't offer custom cakes, redirect
if (!$custom_cake_flag) {
    header("Location: dashboard.php");
    exit;
}

// Get filter
$status_filter = $_GET['status'] ?? 'all';

// Get custom cake requests
$requests_query = "SELECT 
    cco.*,
    u.name as customer_name,
    u.email as customer_email,
    u.phone_no as customer_phone,
    u.address as customer_address,
    s.shop_name
FROM Custom_Cake_Orders cco
JOIN Shops s ON cco.shop_id = s.shop_id
JOIN Users u ON cco.user_id = u.user_id
WHERE s.vendor_id = $vendor_id";

if ($status_filter !== 'all') {
    $status_escaped = $conn->real_escape_string($status_filter);
    $requests_query .= " AND cco.status = '$status_escaped'";
}

$requests_query .= " ORDER BY cco.created_at DESC";

$requests_result = $conn->query($requests_query);
$requests = [];
while ($row = $requests_result->fetch_assoc()) {
    $requests[] = $row;
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN cco.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN cco.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
    SUM(CASE WHEN cco.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
    SUM(CASE WHEN cco.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN cco.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
FROM Custom_Cake_Orders cco
JOIN Shops s ON cco.shop_id = s.shop_id
WHERE s.vendor_id = $vendor_id";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custom Cake Orders - Vendor Dashboard</title>
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
        }

        .dropdown-arrow {
            font-size: 0.7rem;
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
        }

        .user-dropdown.show {
            display: block;
        }

        .dropdown-header {
            padding: 1rem;
            border-bottom: 1px solid #ffe8ec;
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

        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.75rem 1.5rem;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            color: #5a3e36;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .filter-tab:hover {
            border-color: #ff6b9d;
            background: #fff5f7;
        }

        .filter-tab.active {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-color: #ff6b9d;
        }

        .tab-count {
            background: rgba(255, 255, 255, 0.3);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .requests-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .request-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .request-header {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .request-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
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

        .request-body {
            padding: 1.5rem;
        }

        .request-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .request-section {
            background: #fff5f7;
            padding: 1.5rem;
            border-radius: 10px;
        }

        .section-title {
            color: #5a3e36;
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #ffe8ec;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #7a5f57;
        }

        .detail-value {
            color: #5a3e36;
            font-weight: 600;
        }

        .description-box {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ff6b9d;
            margin-top: 0.5rem;
            line-height: 1.6;
            color: #5a3e36;
        }

        .image-preview {
            width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 10px;
            margin-top: 1rem;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .image-preview:hover {
            transform: scale(1.02);
        }

        .no-image {
            text-align: center;
            padding: 2rem;
            color: #7a5f57;
            font-style: italic;
        }

        .request-actions {
            padding: 1.5rem;
            background: #f8f9fa;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-accept {
            background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
            color: white;
        }

        .btn-accept:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-progress {
            background: linear-gradient(135deg, #2196f3 0%, #42a5f5 100%);
            color: white;
        }

        .btn-complete {
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
        }

        .btn-reject {
            background: #f44336;
            color: white;
        }

        .btn-reject:hover {
            background: #da190b;
        }

        .no-requests {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .request-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand"> Sweetkart Vendor</a>
        <ul class="navbar-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="shops.php">My Shops</a></li>
            <li><a href="products.php">Products</a></li>
            <li><a href="orders.php">Orders</a></li>
            <li><a href="custom_cakes.php" class="active">Custom Cakes</a></li>
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
            <h1>🎂 Custom Cake Orders</h1>
            <p>Manage custom cake requests from customers</p>
            
            <div class="filter-tabs">
                <a href="custom_cakes.php?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All <span class="tab-count"><?php echo $stats['total_requests']; ?></span>
                </a>
                <a href="custom_cakes.php?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="tab-count"><?php echo $stats['pending_count']; ?></span>
                </a>
                <a href="custom_cakes.php?status=confirmed" class="filter-tab <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>">
                    Confirmed <span class="tab-count"><?php echo $stats['confirmed_count']; ?></span>
                </a>
                <a href="custom_cakes.php?status=in_progress" class="filter-tab <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>">
                    In Progress <span class="tab-count"><?php echo $stats['in_progress_count']; ?></span>
                </a>
                <a href="custom_cakes.php?status=completed" class="filter-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    Completed <span class="tab-count"><?php echo $stats['completed_count']; ?></span>
                </a>
            </div>
        </div>

        <?php if (count($requests) > 0): ?>
        <div class="requests-list">
            <?php foreach ($requests as $request): ?>
            <div class="request-card">
                <div class="request-header">
                    <div class="request-title">
                        Request #<?php echo $request['custom_order_id']; ?> - <?php echo htmlspecialchars($request['shop_name']); ?>
                    </div>
                    <div class="status-badge status-<?php echo $request['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                    </div>
                </div>

                <div class="request-body">
                    <div class="request-grid">
                        <!-- Customer Details -->
                        <div class="request-section">
                            <div class="section-title">👤 Customer Details</div>
                            <div class="detail-row">
                                <span class="detail-label">Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['customer_name']); ?></span>
                            </div>
                            <?php if (!empty($request['customer_phone'])): ?>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['customer_phone']); ?></span>
                            </div>
                            <?php else: ?>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value" style="color: #999; font-style: italic;">Not provided</span>
                            </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value" style="font-size: 0.85rem;"><?php echo htmlspecialchars($request['customer_email']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Delivery Date:</span>
                                <span class="detail-value" style="color: #ff6b9d;">
                                    <?php echo date('M d, Y', strtotime($request['delivery_date'])); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Cake Details -->
                        <div class="request-section">
                            <div class="section-title">🎂 Cake Details</div>
                            <div class="detail-row">
                                <span class="detail-label">Flavour:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['flavour']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Size:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['size']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Shape:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($request['shape']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Layers:</span>
                                <span class="detail-value"><?php echo $request['layers']; ?></span>
                            </div>
                            <?php if ($request['weight']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Weight:</span>
                                <span class="detail-value"><?php echo $request['weight']; ?> kg</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pricing -->
                        <div class="request-section">
                            <div class="section-title">💰 Price</div>
                            <?php if ($request['final_price']): ?>
                            <div style="text-align: center; padding: 1rem 0;">
                                <div style="color: #7a5f57; font-size: 0.9rem; margin-bottom: 0.5rem;">Total Amount</div>
                                <div style="color: #ff6b9d; font-size: 2rem; font-weight: bold;">
                                    ₹<?php echo number_format($request['final_price'], 2); ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div style="text-align: center; padding: 1rem; color: #7a5f57; font-style: italic;">
                                No price available
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if ($request['description']): ?>
                    <div style="margin-top: 1.5rem;">
                        <div class="section-title">📝 Design Description</div>
                        <div class="description-box">
                            <?php echo nl2br(htmlspecialchars($request['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Reference Image -->
                    <?php if ($request['reference_image']): ?>
                    <div style="margin-top: 1.5rem;">
                        <div class="section-title">📷 Reference Image</div>
                        <img src="../uploads/custom_cakes/<?php echo htmlspecialchars($request['reference_image']); ?>" 
                             alt="Reference" 
                             class="image-preview"
                             onclick="window.open(this.src, '_blank')">
                    </div>
                    <?php else: ?>
                    <div class="no-image">No reference image provided</div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="request-actions">
                    <?php if ($request['status'] === 'pending'): ?>
                        <button class="btn btn-accept" onclick="acceptRequest(<?php echo $request['custom_order_id']; ?>)">
                            ✓ Accept Order
                        </button>
                        <button class="btn btn-reject" onclick="rejectRequest(<?php echo $request['custom_order_id']; ?>)">
                            ✗ Reject Order
                        </button>
                    <?php elseif ($request['status'] === 'confirmed'): ?>
                        <button class="btn btn-progress" onclick="updateStatus(<?php echo $request['custom_order_id']; ?>, 'in_progress')">
                            ⚡ Start Making
                        </button>
                    <?php elseif ($request['status'] === 'in_progress'): ?>
                        <button class="btn btn-complete" onclick="updateStatus(<?php echo $request['custom_order_id']; ?>, 'completed')">
                            ✅ Mark Completed
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-requests">
            <div style="font-size: 5rem; margin-bottom: 1rem;">🎂</div>
            <h3 style="color: #5a3e36; margin-bottom: 0.5rem;">No Custom Cake Requests</h3>
            <p style="color: #7a5f57;">Requests matching your filter will appear here</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
        }

        window.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.querySelector('.user-profile-btn');
            
            if (!button.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        function acceptRequest(requestId) {
            if (!confirm('Are you sure you want to accept this custom cake order?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'accept_request');
            formData.append('request_id', requestId);

            fetch('custom_cake_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Order accepted successfully!');
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

        function rejectRequest(requestId) {
            if (!confirm('Are you sure you want to reject this custom cake order? This action cannot be undone.')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'reject_request');
            formData.append('request_id', requestId);

            fetch('custom_cake_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Order rejected successfully');
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

        function updateStatus(requestId, newStatus) {
            const confirmMessages = {
                'in_progress': 'Start making this custom cake?',
                'completed': 'Mark this order as completed?'
            };

            if (!confirm(confirmMessages[newStatus])) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('request_id', requestId);
            formData.append('status', newStatus);

            fetch('custom_cake_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ ' + data.message);
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