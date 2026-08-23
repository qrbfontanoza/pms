<?php
session_start();
require 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Validate input
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid booking ID']));
}

$booking_id = (int)$_POST['id'];

try {
    // First, check if the booking is active
    $check = $conn->prepare("SELECT status FROM bookings WHERE id = ?");
    $check->bind_param('i', $booking_id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception('Booking not found');
    }
    
    if ($result['status'] === 'active') {
        throw new Exception('Cannot delete active bookings');
    }
    
    // Delete the booking
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->bind_param('i', $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to delete booking');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}