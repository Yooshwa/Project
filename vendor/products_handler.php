<?php
ob_start();
require_once '../config/auth_check.php';
ob_clean();
header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['role'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get action type
$action = $_POST['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Action is required']);
    exit;
}

require_once '../config/database.php';
$conn = getDBConnection();
// Get vendor information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT vendor_id, status FROM Vendors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$vendor = $result->fetch_assoc();
$stmt->close();

if (!$vendor || $vendor['status'] !== 'approved') {
    echo json_encode(['success' => false, 'message' => 'Vendor not approved']);
    $conn->close();
    exit;
}
$vendor_id = $vendor['vendor_id'];
// Handle different actions
switch ($action) {
    case 'add':
        addProduct($conn, $vendor_id);
        break;
    
    case 'edit':
        editProduct($conn, $vendor_id);
        break;
    
    case 'delete':
        deleteProduct($conn, $vendor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
$conn->close();
ob_end_flush();
// ============================================
// ADD PRODUCT FUNCTION
// ============================================
function addProduct($conn, $vendor_id) {
    $shop_id = $_POST['shop_id'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $description = $_POST['description'] ?? '';
    $customizable = isset($_POST['customizable']) ? 1 : 0;
    
    // Validate required fields
    if (empty($shop_id) || empty($product_name) || empty($category_id) || empty($price) || empty($quantity)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        return;
    }
    // Verify shop belongs to this vendor
    $stmt = $conn->prepare("SELECT shop_id FROM Shops WHERE shop_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $shop_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Shop not found or unauthorized']);
        $stmt->close();
        return;
    }
    $stmt->close();
    // Handle image upload
    $image_name = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $image_name = handleImageUpload($_FILES['product_image']);
        if ($image_name === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            return;
        }
    }
    // Insert product
    $stmt = $conn->prepare("INSERT INTO Products (shop_id, category_id, product_name, price, description, image, customizable, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisdssis", $shop_id, $category_id, $product_name, $price, $description, $image_name, $customizable, $quantity);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add product']);
    }
    
    $stmt->close();
}
// ============================================
// EDIT PRODUCT FUNCTION
// ============================================
function editProduct($conn, $vendor_id) {
    $product_id = $_POST['product_id'] ?? '';
    $shop_id = $_POST['shop_id'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $description = $_POST['description'] ?? '';
    $current_image = $_POST['current_image'] ?? '';
    $customizable = isset($_POST['customizable']) ? 1 : 0; 
    // Validate required fields
    if (empty($product_id) || empty($shop_id) || empty($product_name) || empty($category_id) || empty($price) || empty($quantity)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        return;
    } 
    // Verify product belongs to vendor's shop
    $stmt = $conn->prepare("SELECT p.product_id FROM Products p 
                           JOIN Shops s ON p.shop_id = s.shop_id 
                           WHERE p.product_id = ? AND s.vendor_id = ?");
    $stmt->bind_param("ii", $product_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
        $stmt->close();
        return;
    }
    $stmt->close();  
    // Verify new shop belongs to this vendor
    $stmt = $conn->prepare("SELECT shop_id FROM Shops WHERE shop_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $shop_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Shop not found or unauthorized']);
        $stmt->close();
        return;
    }
    $stmt->close();   
    // Handle image upload
    $image_name = $current_image;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $new_image = handleImageUpload($_FILES['product_image']);
        if ($new_image !== false) {
            // Delete old image if exists
            if (!empty($current_image) && file_exists("../uploads/products/" . $current_image)) {
                unlink("../uploads/products/" . $current_image);
            }
            $image_name = $new_image;
        }
    }
    // Update product
    $stmt = $conn->prepare("UPDATE Products SET shop_id = ?, category_id = ?, product_name = ?, price = ?, description = ?, image = ?, customizable = ?, quantity = ? WHERE product_id = ?");
    $stmt->bind_param("iisdsssii", $shop_id, $category_id, $product_name, $price, $description, $image_name, $customizable, $quantity, $product_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update product']);
    }
    $stmt->close();
}
// ============================================
// DELETE PRODUCT FUNCTION
// ============================================
function deleteProduct($conn, $vendor_id) {
    $product_id = $_POST['product_id'] ?? '';
    
    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        return;
    }
    
    // Get product image and verify ownership
    $stmt = $conn->prepare("SELECT p.image FROM Products p 
                           JOIN Shops s ON p.shop_id = s.shop_id 
                           WHERE p.product_id = ? AND s.vendor_id = ?");
    $stmt->bind_param("ii", $product_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
        $stmt->close();
        return;
    }
    
    $product = $result->fetch_assoc();
    $stmt->close();
    
    // Delete product
    $stmt = $conn->prepare("DELETE FROM Products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    
    if ($stmt->execute()) {
        // Delete image file if exists
        if (!empty($product['image']) && file_exists("../uploads/products/" . $product['image'])) {
            unlink("../uploads/products/" . $product['image']);
        }
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
    }
    
    $stmt->close();
}
// ============================================
// IMAGE UPLOAD HELPER FUNCTION
// ============================================
function handleImageUpload($file) {
    $upload_dir = "../uploads/products/";
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    return false;
}
?>