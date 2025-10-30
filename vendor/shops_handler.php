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

if (!$vendor) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    $conn->close();
    exit;
}

$vendor_id = $vendor['vendor_id'];

// Handle different actions
switch ($action) {
    case 'add':
        addShop($conn, $vendor_id, $vendor['status']);
        break;
    
    case 'edit':
        editShop($conn, $vendor_id);
        break;
    
    case 'delete':
        deleteShop($conn, $vendor_id);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
ob_end_flush();

// ============================================
// ADD SHOP FUNCTION
// ============================================
function addShop($conn, $vendor_id, $vendor_status) {
    // Check if vendor is approved
    if ($vendor_status !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Vendor not approved']);
        return;
    }
    
    $shop_name = $_POST['shopName'] ?? '';
    $address = $_POST['shopAddress'] ?? '';
    
    if (empty($shop_name) || empty($address)) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        return;
    }
    
    // Insert shop
    $stmt = $conn->prepare("INSERT INTO Shops (shop_name, address, vendor_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $shop_name, $address, $vendor_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Shop added successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add shop']);
    }
    
    $stmt->close();
}

// ============================================
// EDIT SHOP FUNCTION
// ============================================
function editShop($conn, $vendor_id) {
    $shop_id = $_POST['shopId'] ?? '';
    $shop_name = $_POST['shopName'] ?? '';
    $address = $_POST['shopAddress'] ?? '';
    
    if (empty($shop_id) || empty($shop_name) || empty($address)) {
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
    
    // Update shop
    $stmt = $conn->prepare("UPDATE Shops SET shop_name = ?, address = ? WHERE shop_id = ?");
    $stmt->bind_param("ssi", $shop_name, $address, $shop_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Shop updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update shop']);
    }
    
    $stmt->close();
}

// ============================================
// DELETE SHOP FUNCTION
// ============================================
function deleteShop($conn, $vendor_id) {
    $shop_id = $_POST['shop_id'] ?? '';
    
    if (empty($shop_id)) {
        echo json_encode(['success' => false, 'message' => 'Shop ID is required']);
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
    
    // Delete shop (CASCADE will delete products automatically)
    $stmt = $conn->prepare("DELETE FROM Shops WHERE shop_id = ?");
    $stmt->bind_param("i", $shop_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Shop deleted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete shop']);
    }
    
    $stmt->close();
}
?>