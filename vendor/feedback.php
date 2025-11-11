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

// Get all shops for this vendor (for filter dropdown and stats)
$shops_query = "SELECT 
    s.shop_id, 
    s.shop_name,
    COUNT(f.feedback_id) as feedback_count,
    AVG(f.rating) as avg_rating
FROM Shops s
LEFT JOIN Feedback f ON s.shop_id = f.shop_id
WHERE s.vendor_id = $vendor_id
GROUP BY s.shop_id, s.shop_name
ORDER BY s.shop_name";

$shops_result = $conn->query($shops_query);
$shops = [];
while ($row = $shops_result->fetch_assoc()) {
    $shops[] = $row;
}

// Get all feedback for vendor's shops
$feedback_query = "SELECT 
    f.feedback_id,
    f.rating,
    f.comment,
    f.created_at,
    u.name as customer_name,
    s.shop_id,
    s.shop_name
FROM Feedback f
JOIN Users u ON f.user_id = u.user_id
JOIN Shops s ON f.shop_id = s.shop_id
WHERE s.vendor_id = $vendor_id";

if ($filter_shop !== 'all') {
    $feedback_query .= " AND s.shop_id = " . intval($filter_shop);
}

$feedback_query .= " ORDER BY f.created_at DESC";

$result = $conn->query($feedback_query);
$feedbacks = [];
while ($row = $result->fetch_assoc()) {
    $feedbacks[] = $row;
}

$conn->close();

// Helper function to generate star display
function getStarDisplay($rating) {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $stars .= '⭐';
        } else {
            $stars .= '☆';
        }
    }
    return $stars;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - Sweetkart Vendor</title>
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
            margin-bottom: 1rem;
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

        .shop-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card h3 {
            color: #5a3e36;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .stars {
            font-size: 1.5rem;
        }

        .rating-number {
            color: #ff6b9d;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .feedback-count {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .feedback-container {
            display: grid;
            gap: 1.5rem;
        }

        .feedback-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.3s;
        }

        .feedback-card:hover {
            transform: translateY(-3px);
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ffe8ec;
        }

        .customer-info h4 {
            color: #5a3e36;
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
        }

        .shop-tag {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.85rem;
        }

        .rating-stars {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .feedback-comment {
            color: #5a3e36;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .feedback-date {
            color: #7a5f57;
            font-size: 0.85rem;
        }

        .no-feedback {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .no-feedback-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .no-feedback h3 {
            color: #5a3e36;
            margin-bottom: 0.5rem;
        }

        .no-feedback p {
            color: #7a5f57;
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
            <li><a href="feedback.php" class="active">Feedback</a></li>
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
            <h1>⭐ Customer Feedback</h1>
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

        <?php if (count($shops) > 0): ?>
        <div class="shop-stats">
            <?php foreach ($shops as $shop): ?>
            <div class="stat-card">
                <h3>🏪 <?php echo htmlspecialchars($shop['shop_name']); ?></h3>
                <div class="rating-display">
                    <span class="stars"><?php echo getStarDisplay($shop['avg_rating'] ? round($shop['avg_rating']) : 0); ?></span>
                    <span class="rating-number"><?php echo $shop['avg_rating'] ? number_format($shop['avg_rating'], 1) : 'No ratings'; ?></span>
                </div>
                <div class="feedback-count">
                    <?php echo $shop['feedback_count']; ?> review<?php echo $shop['feedback_count'] != 1 ? 's' : ''; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="feedback-container">
            <?php if (count($feedbacks) > 0): ?>
                <?php foreach ($feedbacks as $feedback): ?>
                <div class="feedback-card">
                    <div class="feedback-header">
                        <div class="customer-info">
                            <h4>👤 <?php echo htmlspecialchars($feedback['customer_name']); ?></h4>
                            <span class="shop-tag">🏪 <?php echo htmlspecialchars($feedback['shop_name']); ?></span>
                        </div>
                        <div class="rating-stars"><?php echo getStarDisplay($feedback['rating']); ?></div>
                    </div>
                    <?php if (!empty($feedback['comment'])): ?>
                    <div class="feedback-comment">
                        "<?php echo htmlspecialchars($feedback['comment']); ?>"
                    </div>
                    <?php endif; ?>
                    <div class="feedback-date">
                        📅 <?php echo date('M d, Y - h:i A', strtotime($feedback['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="no-feedback">
                <div class="no-feedback-icon">⭐</div>
                <h3>No Feedback Yet</h3>
                <p>Customer reviews and ratings will appear here</p>
            </div>
            <?php endif; ?>
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

        function filterByShop() {
            const shopId = document.getElementById('shopFilter').value;
            window.location.href = `feedback.php?shop=${shopId}`;
        }
    </script>
</body>
</html>