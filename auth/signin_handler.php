<?php
// Prevent any output before JSON
ob_start();

require_once '../config/database.php';

// Clear any previous output
ob_clean();

// Set JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$role = $_POST['role'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$secret_key = $_POST['secretKey'] ?? '';

// Validation
if (empty($role) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

$conn = getDBConnection();

// Query user by email and role
$stmt = $conn->prepare("SELECT user_id, name, email, password, role FROM Users WHERE email = ? AND role = ?");
$stmt->bind_param("ss", $email, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials or role']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    $conn->close();
    exit;
}

// Admin secret key verification
if ($role === 'admin') {
    if (empty($secret_key)) {
        echo json_encode(['success' => false, 'message' => 'Secret key required for admin login']);
        $conn->close();
        exit;
    }
    
    $stmt = $conn->prepare("SELECT admin_id FROM Admins WHERE user_id = ? AND secret_key = ?");
    $stmt->bind_param("is", $user['user_id'], $secret_key);
    $stmt->execute();
    $admin_result = $stmt->get_result();
    
    if ($admin_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid admin secret key']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $stmt->close();
}

// Vendor approval check
if ($role === 'vendor') {
    $stmt = $conn->prepare("SELECT status FROM Vendors WHERE user_id = ?");
    $stmt->bind_param("i", $user['user_id']);
    $stmt->execute();
    $vendor_result = $stmt->get_result();
    
    if ($vendor_result->num_rows > 0) {
        $vendor = $vendor_result->fetch_assoc();
        
        if ($vendor['status'] === 'pending') {
            echo json_encode(['success' => false, 'message' => 'Your vendor account is pending admin approval']);
            $stmt->close();
            $conn->close();
            exit;
        } else if ($vendor['status'] === 'rejected') {
            echo json_encode(['success' => false, 'message' => 'Your vendor account has been rejected']);
            $stmt->close();
            $conn->close();
            exit;
        }
    }
    $stmt->close();
}

// Set session variables
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['name'] = $user['name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];

// Redirect URLs based on role
$redirect_urls = [
    'customer' => '../customer/dashboard.php',
    'vendor' => '../vendor/dashboard.php',
    'admin' => '../admin/dashboard.php'
];

echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'role' => $role,
    'redirect' => $redirect_urls[$role],
    'user' => [
        'id' => $user['user_id'],
        'name' => $user['name'],
        'email' => $user['email']
    ]
]);

$conn->close();

// End output buffering and send
ob_end_flush();
?>