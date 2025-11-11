<?php
/*
 * CUSTOMER FEEDBACK PAGE
 * Purpose: Allow customers to leave reviews for shops after completed orders
 * Features: Rate shops (1-5 stars), write comments, view submitted reviews
 */

require_once '../config/auth_check.php';

if ($_SESSION['role'] !== 'customer') {
    header("Location: ../index.php");
    exit;
}

$user_name = $_SESSION['name'];
$user_email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];

require_once '../config/database.php';
$conn = getDBConnection();

// Get shops from completed orders that haven't been reviewed yet
$pending_reviews_query = "SELECT DISTINCT
    s.shop_id,
    s.shop_name,
    s.address,
    COUNT(DISTINCT o.order_id) as order_count,
    MAX(o.order_date) as last_order_date
FROM Orders o
JOIN Order_Items oi ON o.order_id = oi.order_id
JOIN Products p ON oi.product_id = p.product_id
JOIN Shops s ON p.shop_id = s.shop_id
WHERE o.user_id = $user_id 
AND o.status = 'completed'
AND NOT EXISTS (
    SELECT 1 FROM Feedback f 
    WHERE f.shop_id = s.shop_id 
    AND f.user_id = $user_id
)
GROUP BY s.shop_id, s.shop_name, s.address
ORDER BY MAX(o.order_date) DESC";

$pending_result = $conn->query($pending_reviews_query);
$pending_reviews = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_reviews[] = $row;
}

// Get customer's submitted reviews
$submitted_reviews_query = "SELECT 
    f.feedback_id,
    f.rating,
    f.comment,
    f.created_at,
    s.shop_name,
    s.address
FROM Feedback f
JOIN Shops s ON f.shop_id = s.shop_id
WHERE f.user_id = $user_id
ORDER BY f.created_at DESC";

$submitted_result = $conn->query($submitted_reviews_query);
$submitted_reviews = [];
while ($row = $submitted_result->fetch_assoc()) {
    $submitted_reviews[] = $row;
}

// Get cart count
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
    <title>My Reviews - Sweetkart</title>
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

        .user-dropdown.show { display: block; }

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
            margin-top: 0.5rem;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #5a3e36;
            text-decoration: none;
        }

        .dropdown-item:hover { background: #fff5f7; }
        .dropdown-item.logout { color: #dc3545; }

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

        .section-title {
            color: #5a3e36;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Pending Reviews */
        .review-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .review-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .review-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .shop-info {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ffe8ec;
        }

        .shop-name {
            color: #5a3e36;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .shop-details {
            color: #7a5f57;
            font-size: 0.9rem;
        }

        .btn-write-review {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-write-review:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }

        /* Submitted Reviews */
        .submitted-review {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .review-stars {
            display: flex;
            gap: 0.2rem;
            font-size: 1.2rem;
            color: #ffa500;
        }

        .review-date {
            color: #7a5f57;
            font-size: 0.85rem;
        }

        .review-comment {
            color: #5a3e36;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        /* Review Modal */
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
            margin: 3rem auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: #fff5f7;
            padding: 2rem;
            border-bottom: 2px solid #ffe8ec;
            text-align: center;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1rem;
        }

        .modal-body {
            padding: 2rem;
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

        /* Star Rating */
        .star-rating {
            display: flex;
            gap: 0.5rem;
            font-size: 2.5rem;
            cursor: pointer;
        }

        .star-rating .star {
            color: #ddd;
            transition: all 0.2s;
        }

        .star-rating .star:hover,
        .star-rating .star.active {
            color: #ffa500;
            transform: scale(1.1);
        }

        textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
        }

        textarea:focus {
            outline: none;
            border-color: #ff6b9d;
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-top: 2px solid #ffe8ec;
            display: flex;
            gap: 1rem;
        }

        .btn-cancel {
            flex: 1;
            padding: 1rem;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit {
            flex: 2;
            padding: 1rem;
            background: linear-gradient(135deg, #ff6b9d 0%, #ff8fab 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .empty-state {
            background: white;
            padding: 3rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .review-cards {
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
            </button>
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <p><?php echo htmlspecialchars($user_name); ?></p>
                    <span><?php echo htmlspecialchars($user_email); ?></span>
                    <div class="user-badge">🛒 CUSTOMER</div>
                </div>
                <div class="dropdown-menu">
                    <a href="feedback.php" class="dropdown-item">
                        <span>⭐</span> My Reviews
                    </a>
                    <a href="../auth/logout.php" class="dropdown-item logout">
                        <span>🚪</span> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>⭐ My Reviews & Feedback</h1>
            <p>Share your experience with the shops you've ordered from</p>
        </div>

        <!-- Pending Reviews -->
        <?php if (count($pending_reviews) > 0): ?>
        <h2 class="section-title">📝 Write Reviews</h2>
        <div class="review-cards">
            <?php foreach ($pending_reviews as $shop): ?>
            <div class="review-card">
                <div class="shop-info">
                    <div class="shop-name">🪙 <?php echo htmlspecialchars($shop['shop_name']); ?></div>
                    <div class="shop-details">
                        <?php echo $shop['order_count']; ?> completed order(s)<br>
                        Last ordered: <?php echo date('M d, Y', strtotime($shop['last_order_date'])); ?>
                    </div>
                </div>
                <button class="btn-write-review" onclick="openReviewModal(<?php echo $shop['shop_id']; ?>, '<?php echo htmlspecialchars($shop['shop_name'], ENT_QUOTES); ?>')">
                    ⭐ Write Review
                </button>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Submitted Reviews -->
        <h2 class="section-title">✅ My Submitted Reviews</h2>
        <?php if (count($submitted_reviews) > 0): ?>
            <?php foreach ($submitted_reviews as $review): ?>
            <div class="submitted-review">
                <div class="review-header">
                    <div>
                        <div class="shop-name">🪙 <?php echo htmlspecialchars($review['shop_name']); ?></div>
                        <div class="review-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span><?php echo $i <= $review['rating'] ? '⭐' : '☆'; ?></span>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="review-date">
                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                    </div>
                </div>
                <?php if (!empty($review['comment'])): ?>
                <div class="review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">⭐</div>
            <h3>No Reviews Yet</h3>
            <p>Complete orders to leave reviews for shops</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">⭐</div>
                <h2 id="modalShopName">Write Your Review</h2>
            </div>
            <div class="modal-body">
                <form id="reviewForm">
                    <input type="hidden" id="shopId" name="shop_id">
                    
                    <div class="form-group">
                        <label>Rating *</label>
                        <div class="star-rating" id="starRating">
                            <span class="star" data-rating="1">★</span>
                            <span class="star" data-rating="2">★</span>
                            <span class="star" data-rating="3">★</span>
                            <span class="star" data-rating="4">★</span>
                            <span class="star" data-rating="5">★</span>
                        </div>
                        <input type="hidden" id="ratingValue" name="rating" required>
                    </div>

                    <div class="form-group">
                        <label>Your Review</label>
                        <textarea name="comment" placeholder="Share your experience with this shop..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeReviewModal()">Cancel</button>
                <button class="btn-submit" onclick="submitReview()">Submit Review</button>
            </div>
        </div>
    </div>

    <script>
        function toggleDropdown() {
            document.getElementById('userDropdown').classList.toggle('show');
        }

        window.onclick = function(e) {
            if (!e.target.matches('.user-profile-btn') && !e.target.closest('.user-profile-btn')) {
                document.getElementById('userDropdown').classList.remove('show');
            }
            if (e.target.id === 'reviewModal') {
                closeReviewModal();
            }
        }

        let selectedRating = 0;

        // Star rating functionality
        document.querySelectorAll('.star-rating .star').forEach(star => {
            star.addEventListener('click', function() {
                selectedRating = parseInt(this.dataset.rating);
                document.getElementById('ratingValue').value = selectedRating;
                updateStars();
            });

            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                document.querySelectorAll('.star-rating .star').forEach((s, idx) => {
                    s.classList.toggle('active', idx < rating);
                });
            });
        });

        document.querySelector('.star-rating').addEventListener('mouseleave', updateStars);

        function updateStars() {
            document.querySelectorAll('.star-rating .star').forEach((s, idx) => {
                s.classList.toggle('active', idx < selectedRating);
            });
        }

        function openReviewModal(shopId, shopName) {
            document.getElementById('shopId').value = shopId;
            document.getElementById('modalShopName').textContent = 'Review: ' + shopName;
            document.getElementById('reviewModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            selectedRating = 0;
            document.getElementById('reviewForm').reset();
            updateStars();
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function submitReview() {
            if (selectedRating === 0) {
                alert('Please select a rating');
                return;
            }

            const formData = new FormData(document.getElementById('reviewForm'));
            
            fetch('submit_feedback.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✓ Review submitted successfully!');
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