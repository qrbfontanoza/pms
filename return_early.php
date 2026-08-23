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

    // Get booking details with lock and include vehicle information
    $stmt = $pdo->prepare("
        SELECT b.*, v.id as vehicle_id, v.units_total, v.title as vehicle_name,
               b.rental_date, b.return_date, b.pickup_time, b.dropoff_time
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        WHERE b.id = ? AND b.user_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$booking_id, $_SESSION['user']['id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    if ($booking['status'] !== 'confirmed') {
        throw new Exception('Booking must be confirmed to be returned');
    }

    // Calculate actual rental duration
    $rentalStart = new DateTime($booking['rental_date'] . ' ' . $booking['pickup_time']);
    $actualReturn = new DateTime('now');
    $originalReturn = new DateTime($booking['return_date'] . ' ' . $booking['dropoff_time']);
    
    // Check if the columns exist in the bookings table
    $columnsExist = $pdo->query("
        SELECT COUNT(*) as count 
        FROM information_schema.COLUMNS 
        WHERE TABLE_NAME = 'bookings' 
        AND COLUMN_NAME IN ('actual_return_date', 'early_return')
        AND TABLE_SCHEMA = DATABASE()
    ")->fetch(PDO::FETCH_ASSOC);
    
    $hasColumns = $columnsExist['count'] == 2;
    $isEarlyReturn = $actualReturn < $originalReturn ? 1 : 0;
    
    // Prepare the update SQL based on column existence
    $sql = "UPDATE bookings SET status = 'completed'";
    if ($hasColumns) {
        $sql .= ", actual_return_date = CURRENT_TIMESTAMP, early_return = ?";
    }
    $sql .= " WHERE id = ? AND status = 'confirmed' LIMIT 1";
    
    $updateBooking = $pdo->prepare($sql);
    $result = $hasColumns ? 
        $updateBooking->execute([$isEarlyReturn, $booking_id]) : 
        $updateBooking->execute([$booking_id]);
    
    if ($updateBooking->rowCount() === 0) {
        throw new Exception('Failed to update booking status');
    }

    // Increase available units for the vehicle
    $updateVehicle = $pdo->prepare("
        UPDATE vehicles 
        SET units_total = units_total + 1,
            is_active = CASE 
                WHEN units_total + 1 > 0 THEN 1
                ELSE is_active
            END
        WHERE id = ? 
        LIMIT 1
    ");
    $result = $updateVehicle->execute([$booking['vehicle_id']]);
    
    if ($updateVehicle->rowCount() === 0) {
        throw new Exception('Failed to update vehicle availability');
    }

    // Record the transaction in a separate table for tracking
    $transaction_ref = 'RET' . date('Ymd') . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
    // Record transaction without assuming an amount column exists in transactions.
    // Use payment_status to indicate the return event.
    $recordTransaction = $pdo->prepare("INSERT INTO transactions (booking_id, transaction_ref, payment_status) VALUES (?, ?, ?)");
    $recordTransaction->execute([
        $booking_id,
        $transaction_ref,
        'returned'
    ]);

    // Commit transaction
    $pdo->commit();

    // Prepare response data with conditional fields
    $responseData = [
        'success' => true,
        'message' => 'Vehicle returned successfully',
        'booking_details' => [
            'booking_ref' => $booking_id,
            'vehicle_name' => $booking['vehicle_name'],
            'rental_date' => $booking['rental_date'],
            'return_date' => $booking['return_date'],
            'status' => 'completed'
        ]
    ];
    
    // Add optional fields only if the columns exist
    if ($hasColumns) {
        $responseData['booking_details']['actual_return_date'] = $actualReturn->format('Y-m-d H:i:s');
        $responseData['booking_details']['early_return'] = $isEarlyReturn;
    }

    echo json_encode($responseData);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to process return: ' . $e->getMessage()
    ]);
}