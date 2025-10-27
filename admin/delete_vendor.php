<?php
// Prevent output before JSON
ob_start();

require_once '../config/auth_check.php';

// Clear any previous output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$vendor_id = $_POST['vendor_id'] ?? '';

// Validation
if (empty($vendor_id)) {
    echo json_encode(['success' => false, 'message' => 'Vendor ID is required']);
    exit;
}

require_once '../config/database.php';
$conn = getDBConnection();

// Get vendor and user information before deletion
$stmt = $conn->prepare("SELECT v.user_id, u.name FROM Vendors v JOIN Users u ON v.user_id = u.user_id WHERE v.vendor_id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Vendor not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$vendor = $result->fetch_assoc();
$user_id = $vendor['user_id'];
$vendor_name = $vendor['name'];
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Delete from Users table (cascade will handle Vendors, Shops, Products due to foreign keys)
    $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete vendor");
    }
    
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Vendor "' . $vendor_name . '" and all associated data have been permanently deleted'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();

ob_end_flush();
?>