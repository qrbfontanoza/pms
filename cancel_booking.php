<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in first']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$booking_id = $data['booking_id'] ?? null;

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Booking ID is required']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get booking details (allow pending or confirmed cancellations)
    $stmt = $pdo->prepare(
        "SELECT b.*, v.id as vehicle_id, v.title as vehicle_title
         FROM bookings b
         JOIN vehicles v ON b.vehicle_id = v.id
         WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending','confirmed')"
    );
    $stmt->execute([$booking_id, $_SESSION['user']['id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found or already cancelled');
    }

    // Check if rental date is in the future
    $rental_date = new DateTime($booking['rental_date']);
    $now = new DateTime();

    if ($rental_date <= $now) {
        throw new Exception('Cannot cancel bookings on or after the rental date');
    }

    // Calculate refund amount based on cancellation policy
    $interval = $now->diff($rental_date);
    $hours_until_rental = ($interval->days * 24) + $interval->h;
    $refund_percentage = $hours_until_rental >= 24 ? 100 : 50;
    $refund_amount = ($booking['total_amount'] * $refund_percentage) / 100;

    // Update booking status (do not write columns that may not exist)
    $updateBooking = $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled' WHERE id = ?"
    );
    $updateBooking->execute([$booking_id]);

    // If the booking was confirmed, increment vehicle units (pending bookings did not decrement units)
    if (strtolower($booking['status']) === 'confirmed') {
        $updateVehicle = $pdo->prepare(
            "UPDATE vehicles 
             SET units_total = units_total + 1,
                 is_active = IF(units_total + 1 > 0, 1, is_active)
             WHERE id = ?"
        );
        $updateVehicle->execute([$booking['vehicle_id']]);
    }

    // Commit transaction
    $pdo->commit();

    // Success response
    echo json_encode([
        'success' => true,
        'message' => "Booking cancelled successfully. Refund amount: ₱" . number_format($refund_amount, 2) . " ({$refund_percentage}% of total)",
        'refund_amount' => $refund_amount,
        'refund_percentage' => $refund_percentage
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}