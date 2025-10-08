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

try {
    // Get user settings
    $stmt = $conn->prepare("SELECT refresh_interval, dark_mode FROM user_settings WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'refresh_interval' => intval($settings['refresh_interval']),
            'dark_mode' => intval($settings['dark_mode'])
        ]);
    } else {
        // Return defaults if no settings found
        echo json_encode([
            'success' => true,
            'refresh_interval' => 6,
            'dark_mode' => 0
        ]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>