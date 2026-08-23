<?php
session_start();
require 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Validate input
if (!isset($_POST['transaction_id']) || !isset($_POST['pickup_time']) || !isset($_POST['dropoff_time'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing required fields']));
}

$booking_id = (int)$_POST['transaction_id'];
$pickup_time = $_POST['pickup_time'];
$dropoff_time = $_POST['dropoff_time'];

try {
    // Update the booking times
    $stmt = $conn->prepare("
        UPDATE bookings 
        SET pickup_time = ?, 
            dropoff_time = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param('ssi', $pickup_time, $dropoff_time, $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update booking');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}