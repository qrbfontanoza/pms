<?php
session_start();
require 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking id']);
    exit;
}

$bookingId = intval($_POST['id']);

// Check booking exists
// We'll atomically confirm the booking: check availability, decrement units, create transaction
$conn->begin_transaction();
try {
    // Lock booking row
    $stmt = $conn->prepare("SELECT id, vehicle_id, total_amount, contact_number, status FROM bookings WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    $booking = $res->fetch_assoc();

    if (strtolower($booking['status']) === 'confirmed') {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Already confirmed']);
        exit;
    }

    $vehicle_id = intval($booking['vehicle_id']);
    $amount = (float)$booking['total_amount'];
    $payer = $booking['contact_number'] ?? null;

    // Lock vehicle row and check units
    $vstmt = $conn->prepare("SELECT units_total FROM vehicles WHERE id = ? FOR UPDATE");
    $vstmt->bind_param('i', $vehicle_id);
    $vstmt->execute();
    $vres = $vstmt->get_result();
    if ($vres->num_rows === 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Vehicle not found']);
        exit;
    }
    $vrow = $vres->fetch_assoc();
    $units = intval($vrow['units_total']);
    if ($units <= 0) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'No available units to confirm this booking']);
        exit;
    }

    // Decrement units and possibly flip is_active
    $uupdate = $conn->prepare(
        "UPDATE vehicles SET units_total = GREATEST(0, units_total - 1), is_active = IF(GREATEST(0, units_total - 1) = 0, 0, is_active) WHERE id = ?"
    );
    $uupdate->bind_param('i', $vehicle_id);
    if (!$uupdate->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to update vehicle availability']);
        exit;
    }

    // Update booking status to confirmed
    $update = $conn->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? LIMIT 1");
    $update->bind_param('i', $bookingId);
    if (!$update->execute()) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Failed to update booking']);
        exit;
    }

    // Insert transaction record if none exists for this booking
    $tchk = $conn->prepare("SELECT COUNT(*) AS cnt FROM transactions WHERE booking_id = ?");
    $tchk->bind_param('i', $bookingId);
    $tchk->execute();
    $tcres = $tchk->get_result();
    $tc = $tcres->fetch_assoc()['cnt'] ?? 0;
    if (intval($tc) === 0) {
        $txref = 'TX' . time() . rand(1000,9999);
        // Insert only into existing transaction columns (avoid unknown columns like payer_contact)
        $tins = $conn->prepare("INSERT INTO transactions (booking_id, transaction_ref, amount) VALUES (?, ?, ?)");
        $tins->bind_param('isd', $bookingId, $txref, $amount);
        @$tins->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
    exit;
}
?>