<?php
session_start();
header('Content-Type: application/json');

// Database connection
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['dark_mode'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Check if user_settings record exists
    $check = $conn->prepare("SELECT user_id FROM user_settings WHERE user_id = ?");
    $check->bind_param("i", $user_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    $dark_mode = intval($input['dark_mode']);

    if ($exists) {
        // Update existing settings
        $stmt = $conn->prepare("UPDATE user_settings SET dark_mode = ? WHERE user_id = ?");
        $stmt->bind_param("ii", $dark_mode, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new settings record with default refresh_interval
        $stmt = $conn->prepare("INSERT INTO user_settings (user_id, dark_mode, refresh_interval) VALUES (?, ?, 6)");
        $stmt->bind_param("ii", $user_id, $dark_mode);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => true, 'message' => 'Dark mode updated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>