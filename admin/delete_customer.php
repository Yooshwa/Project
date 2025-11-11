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
$customer_id = $_POST['customer_id'] ?? '';

// Validation
if (empty($customer_id)) {
    echo json_encode(['success' => false, 'message' => 'Customer ID is required']);
    exit;
}

require_once '../config/database.php';
$conn = getDBConnection();

// Get customer information before deletion
$stmt = $conn->prepare("SELECT name, role FROM Users WHERE user_id = ? AND role = 'customer'");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$customer = $result->fetch_assoc();
$customer_name = $customer['name'];
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Delete from Users table (cascade will handle related records due to foreign keys)
    $stmt = $conn->prepare("DELETE FROM Users WHERE user_id = ? AND role = 'customer'");
    $stmt->bind_param("i", $customer_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete customer");
    }
    
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Customer "' . $customer_name . '" and all associated data have been permanently deleted'
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();

ob_end_flush();
?>