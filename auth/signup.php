<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
$conn = getDBConnection();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$role = $_POST['role'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$name = $_POST['name'] ?? '';
$address = $_POST['address'] ?? '';

// Validation
if (empty($role) || empty($email) || empty($password) || empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Password validation (minimum 8 characters)
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

$conn = getDBConnection();

// Check if email already exists
$stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already registered']);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Start transaction
$conn->begin_transaction();

try {
    // Insert into Users table
    $stmt = $conn->prepare("INSERT INTO Users (name, email, password, address, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $email, $hashed_password, $address, $role);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create user");
    }
    
    $user_id = $conn->insert_id;
    $stmt->close();
    
    // Role-specific insertions
    if ($role === 'vendor') {
        $shop_name = $_POST['shopName'] ?? '';
        $custom_cake = $_POST['customCake'] ?? 'no';
        $custom_cake_flag = ($custom_cake === 'yes') ? 1 : 0;
        
        if (empty($shop_name)) {
            throw new Exception("Shop name is required for vendors");
        }
        
        // Insert into Vendors table
        $stmt = $conn->prepare("INSERT INTO Vendors (user_id, custom_cake_flag, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $user_id, $custom_cake_flag);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create vendor profile");
        }
        
        $vendor_id = $conn->insert_id;
        $stmt->close();
        
        // Insert into Shops table
        $stmt = $conn->prepare("INSERT INTO Shops (shop_name, address, vendor_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $shop_name, $address, $vendor_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create shop");
        }
        $stmt->close();
        
        $message = "Vendor registration successful! Your account is pending admin approval.";
    } else {
        // Customer
        $message = "Registration successful! You can now sign in.";
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'role' => $role
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>