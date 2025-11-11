<?php
/*
 * SUBMIT CUSTOM CAKE REQUEST HANDLER - UPDATED
 * Purpose: Process custom cake request with fixed pricing
 */

require_once '../config/auth_check.php';
require_once '../config/database.php';

header('Content-Type: application/json');

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
$phone_no = trim($_POST['phone_no'] ?? '');
$flavour = trim($_POST['flavour'] ?? '');
$size = trim($_POST['size'] ?? '');
$shape = trim($_POST['shape'] ?? '');
$layers = isset($_POST['layers']) ? intval($_POST['layers']) : 0;
$occasion = trim($_POST['occasion'] ?? '');
$description = trim($_POST['description'] ?? '');
$delivery_date = $_POST['delivery_date'] ?? '';
$final_price = isset($_POST['final_price']) ? floatval($_POST['final_price']) : 0;

// Validation
if (empty($shop_id) || empty($phone_no) || empty($flavour) || empty($size) || 
    empty($shape) || empty($layers) || empty($delivery_date) || $final_price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Validate phone number
if (strlen($phone_no) < 10) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid phone number']);
    exit;
}

// Validate delivery date (must be at least 2 days from now)
$min_date = date('Y-m-d', strtotime('+2 days'));
if ($delivery_date < $min_date) {
    echo json_encode(['success' => false, 'message' => 'Delivery date must be at least 2 days from now']);
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

// Update user phone number if provided
if (!empty($phone_no)) {
    $phone_escaped = $conn->real_escape_string($phone_no);
    $update_phone = "UPDATE Users SET phone_no = '$phone_escaped' WHERE user_id = $user_id";
    $conn->query($update_phone);
}

// Handle image upload
$image_filename = null;

// Debug: Check if file was uploaded
if (isset($_FILES['reference_image'])) {
    $upload_error = $_FILES['reference_image']['error'];
    
    // Check for upload errors
    if ($upload_error === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/custom_cakes/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
                $conn->close();
                exit;
            }
        }
        
        $file_tmp = $_FILES['reference_image']['tmp_name'];
        $file_name = $_FILES['reference_image']['name'];
        $file_size = $_FILES['reference_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image format. Use JPG, PNG, or GIF']);
            $conn->close();
            exit;
        }
        
        if ($file_size > 62914560) { // 60MB limit
            echo json_encode(['success' => false, 'message' => 'Image size must be less than 60MB']);
            $conn->close();
            exit;
        }
        
        $image_filename = 'cake_' . uniqid() . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $image_filename;
        
        if (!move_uploaded_file($file_tmp, $upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image. Check directory permissions.']);
            $conn->close();
            exit;
        }
        
        // Verify file was actually uploaded
        if (!file_exists($upload_path)) {
            echo json_encode(['success' => false, 'message' => 'Image upload verification failed']);
            $conn->close();
            exit;
        }
    } 
    // File input exists but no file selected - this is OK since it's optional
    elseif ($upload_error !== UPLOAD_ERR_NO_FILE) {
        // There was an actual error
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        $error_msg = $error_messages[$upload_error] ?? 'Unknown upload error';
        echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error_msg]);
        $conn->close();
        exit;
    }
}

// Escape strings for SQL
$flavour = $conn->real_escape_string($flavour);
$size = $conn->real_escape_string($size);
$shape = $conn->real_escape_string($shape);
$occasion_value = !empty($occasion) ? "'" . $conn->real_escape_string($occasion) . "'" : 'NULL';
$description_value = !empty($description) ? "'" . $conn->real_escape_string($description) . "'" : 'NULL';
$image_value = $image_filename ? "'" . $conn->real_escape_string($image_filename) . "'" : 'NULL';

// Calculate weight based on size (extract kg value)
$weight = floatval($size);

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
    delivery_date,
    final_price,
    status,
    created_at
) VALUES (
    $user_id,
    $shop_id,
    '$flavour',
    '$size',
    '$shape',
    $weight,
    $layers,
    $description_value,
    $image_value,
    '$delivery_date',
    $final_price,
    'pending',
    NOW()
)";

if ($conn->query($insert_query)) {
    echo json_encode([
        'success' => true,
        'message' => 'Custom cake request submitted successfully',
        'debug' => [
            'image_uploaded' => $image_filename ? true : false,
            'image_filename' => $image_filename
        ]
    ]);
} else {
    // If database insert fails, delete uploaded image
    if ($image_filename && file_exists($upload_dir . $image_filename)) {
        unlink($upload_dir . $image_filename);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit request. Please try again.',
        'error' => $conn->error
    ]);
}

$conn->close();
?>