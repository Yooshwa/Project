<?php
/*
 * SUBMIT CUSTOM CAKE REQUEST HANDLER
 * Purpose: Process custom cake request form submission
 * Features: Validate input, upload image, insert into database
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

// Only customers can submit requests
if ($_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get form data
$shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : 0;
$flavour = trim($_POST['flavour'] ?? '');
$size = trim($_POST['size'] ?? '');
$shape = trim($_POST['shape'] ?? '');
$weight = isset($_POST['weight']) ? floatval($_POST['weight']) : null;
$layers = isset($_POST['layers']) ? intval($_POST['layers']) : null;
$description = trim($_POST['description'] ?? '');
$special_instructions = trim($_POST['special_instructions'] ?? '');
$delivery_date = $_POST['delivery_date'] ?? '';

// Validation
if (empty($shop_id) || empty($flavour) || empty($size) || empty($description) || empty($delivery_date)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Validate delivery date (must be at least 3 days from now)
$min_date = date('Y-m-d', strtotime('+3 days'));
if ($delivery_date < $min_date) {
    echo json_encode(['success' => false, 'message' => 'Delivery date must be at least 3 days from now']);
    exit;
}

$conn = getDBConnection();

// Verify shop exists and offers custom cakes
$shop_check = "SELECT s.shop_id 
               FROM Shops s 
               JOIN Vendors v ON s.vendor_id = v.vendor_id 
               WHERE s.shop_id = $shop_id 
               AND v.custom_cake_flag = 1 
               AND v.status = 'approved'";
$shop_result = $conn->query($shop_check);

if ($shop_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid shop selection']);
    $conn->close();
    exit;
}

// Handle image upload
$image_filename = null;

if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/custom_cakes/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_tmp = $_FILES['reference_image']['tmp_name'];
    $file_name = $_FILES['reference_image']['name'];
    $file_size = $_FILES['reference_image']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed extensions
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    
    // Validate file
    if (!in_array($file_ext, $allowed_ext)) {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Use JPG, PNG, or GIF']);
        $conn->close();
        exit;
    }
    
    if ($file_size > 5242880) { // 5MB limit
        echo json_encode(['success' => false, 'message' => 'Image size must be less than 5MB']);
        $conn->close();
        exit;
    }
    
    // Generate unique filename
    $image_filename = 'cake_' . uniqid() . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $image_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $upload_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
        $conn->close();
        exit;
    }
}

// Escape strings for SQL
$flavour = $conn->real_escape_string($flavour);
$size = $conn->real_escape_string($size);
$shape = !empty($shape) ? "'" . $conn->real_escape_string($shape) . "'" : 'NULL';
$weight_value = $weight ? $weight : 'NULL';
$layers_value = $layers ? $layers : 'NULL';
$description = $conn->real_escape_string($description);
$special_instructions = !empty($special_instructions) ? "'" . $conn->real_escape_string($special_instructions) . "'" : 'NULL';
$image_value = $image_filename ? "'" . $conn->real_escape_string($image_filename) . "'" : 'NULL';

// Insert into Custom_Cake_Orders table
$insert_query = "INSERT INTO Custom_Cake_Orders (
    user_id,
    shop_id,
    flavour,
    size,
    shape,
    weight,
    layers,
    description,
    reference_image,
    special_instructions,
    delivery_date,
    status,
    created_at
) VALUES (
    $user_id,
    $shop_id,
    '$flavour',
    '$size',
    $shape,
    $weight_value,
    $layers_value,
    '$description',
    $image_value,
    $special_instructions,
    '$delivery_date',
    'pending',
    NOW()
)";

if ($conn->query($insert_query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Custom cake request submitted successfully'
    ]);
} else {
    // If database insert fails, delete uploaded image
    if ($image_filename && file_exists($upload_dir . $image_filename)) {
        unlink($upload_dir . $image_filename);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit request. Please try again.'
    ]);
}

$conn->close();
?>