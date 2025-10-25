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
$new_status = $_POST['status'] ?? '';

// Validation
if (empty($vendor_id) || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status value
if (!in_array($new_status, ['pending', 'approved', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

require_once '../config/database.php';
$conn = getDBConnection();

// Update vendor status
$stmt = $conn->prepare("UPDATE Vendors SET status = ? WHERE vendor_id = ?");
$stmt->bind_param("si", $new_status, $vendor_id);

if ($stmt->execute()) {
    // Get vendor name for response message
    $stmt2 = $conn->prepare("SELECT u.name FROM Vendors v JOIN Users u ON v.user_id = u.user_id WHERE v.vendor_id = ?");
    $stmt2->bind_param("i", $vendor_id);
    $stmt2->execute();
    $result = $stmt2->get_result();
    $vendor = $result->fetch_assoc();
    $vendor_name = $vendor['name'] ?? 'Vendor';
    $stmt2->close();
    
    $message = $vendor_name . ' has been ' . $new_status;
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'vendor_id' => $vendor_id,
        'new_status' => $new_status
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update vendor status']);
}

$stmt->close();
$conn->close();

ob_end_flush();
?>