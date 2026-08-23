<?php
// reserve.php (debug-friendly, PDO)
error_reporting(E_ALL);


session_start();
require 'db.php';
header('Content-Type: application/json');


// require login
if (!isset($_SESSION['user']['id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Please log in first']);
  exit;
}
$user_id = (int)$_SESSION['user']['id'];


// read JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);


// if JSON parse error, return raw body for debugging
if ($data === null) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid JSON', 'raw' => $raw]);
  exit;
}


// extract fields
$vehicle_id    = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : null;
$rental_date   = isset($data['rental_date']) ? trim($data['rental_date']) : null;
$return_date   = isset($data['return_date']) ? trim($data['return_date']) : null;
$pickup_time   = isset($data['pickup_time']) ? trim($data['pickup_time']) : null;
$dropoff_time  = isset($data['dropoff_time']) ? trim($data['dropoff_time']) : null;
$contact_number= isset($data['contact_number']) ? trim($data['contact_number']) : null;
$age           = isset($data['age']) ? (int)$data['age'] : null;
$voucher_code  = isset($data['voucher_code']) ? trim($data['voucher_code']) : null;
$amount_paid   = isset($data['amount_paid']) ? (float)$data['amount_paid'] : null;


// Add this right after the field extraction section, before the missing fields check
$is_preview = isset($data['is_preview']) && $data['is_preview'];

// Validate dates
// Allow same-day bookings but disallow past dates.
$today = new DateTime();
$today->setTime(0, 0); // start of today
$rental = new DateTime($rental_date);
$rental->setTime(0, 0);
$return = new DateTime($return_date);
$return->setTime(0, 0);

// Rental date cannot be in the past (allow equal to today)
if ($rental < $today) {
  http_response_code(400);
  echo json_encode(['error' => 'Rental date cannot be in the past']);
  exit;
}

// Check if return date is before rental date
if ($return < $rental) {
  http_response_code(400);
  echo json_encode(['error' => 'Return date must be on or after rental date']);
  exit;
}

/* collect missing required fields
$missing = [];
if (!$vehicle_id)    $missing[] = 'vehicle_id';
if (!$rental_date)   $missing[] = 'rental_date';
if (!$return_date)   $missing[] = 'return_date';
if (!$contact_number)$missing[] = 'contact_number';
if (!$age && $age !== 0) $missing[] = 'age';
if ($amount_paid === null) $missing[] = 'amount_paid';
*/


// Modify the existing missing fields check section
$missing = [];
if (!$vehicle_id)    $missing[] = 'vehicle_id';
if (!$rental_date)   $missing[] = 'rental_date';
if (!$return_date)   $missing[] = 'return_date';
if (!$contact_number)$missing[] = 'contact_number';
if (!$age && $age !== 0) $missing[] = 'age';
// Only check amount_paid if not preview
if (!$is_preview && $amount_paid === null) $missing[] = 'amount_paid';


if (!empty($missing)) {
  http_response_code(400);
  echo json_encode([
    'error' => 'Missing fields',
    'missing' => $missing,
    'received' => $data
  ]);
  exit;
}


// basic age validation
if ($age < 18) {
  http_response_code(400);
  echo json_encode(['error' => 'Driver must be at least 18 years old']);
  exit;
}


// fetch vehicle and price
$sth = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND is_active = 1");
$sth->execute([$vehicle_id]);
$vehicle = $sth->fetch(PDO::FETCH_ASSOC);
if (!$vehicle) {
  http_response_code(404);
  echo json_encode(['error' => 'Vehicle not found or not active']);
  exit;
}


// compute days
try {
  $d1 = new DateTime($rental_date);
  $d2 = new DateTime($return_date);
  $diff = (int)$d2->diff($d1)->format('%a');
  $days = max(1, $diff);
} catch (Exception $e) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid date format', 'details' => $e->getMessage()]);
  exit;
}


// compute totals (re-using preview logic)
$rate = (float)$vehicle['price_per_day'];
$subtotal = $rate * $days;
$discount = 0.0;
$voucher_id = null;


if ($voucher_code) {
  $v = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? AND is_active = 1');
  $v->execute([$voucher_code]);
  $voucher = $v->fetch(PDO::FETCH_ASSOC);
  if ($voucher) {
    $voucher_id = $voucher['id'];
    if (!empty($voucher['discount_pct'])) {
      $discount = $subtotal * ($voucher['discount_pct'] / 100);
    } elseif (!empty($voucher['discount_amount'])) {
      $discount = (float)$voucher['discount_amount'];
    }
  } else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid voucher code']);
    exit;
  }
}


$total = max(0, $subtotal - $discount);




// Add this after computing totals but before the payment check
if ($is_preview) {
  echo json_encode([
    'success' => true,
    'subtotal' => $subtotal,
    'discount' => $discount,
    'total' => $total,
    'days' => $days,
    'rate' => $rate,
    'vehicle_name' => $vehicle['title'],
    'rental_date' => $rental_date,
    'return_date' => $return_date,
    'pickup_time' => $pickup_time,
    'dropoff_time' => $dropoff_time,
    'voucher_code' => $voucher_code
  ]);
  exit;
}




// check payment
if ($amount_paid < $total) {
  http_response_code(400);
  echo json_encode(['error' => 'Insufficient payment', 'total' => $total, 'amount_paid' => $amount_paid]);
  exit;
}


// check availability (units_total)
$q = $pdo->prepare("
  SELECT COUNT(*) FROM bookings
  WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')
  AND NOT (return_date < ? OR rental_date > ?)
");
$q->execute([$vehicle_id, $rental_date, $return_date]);
$occupied = (int)$q->fetchColumn();
$units_total = (int)$vehicle['units_total'];
if ($occupied >= $units_total) {
  http_response_code(409);
  echo json_encode(['error' => 'Vehicle unavailable on selected dates']);
  exit;
}


// everything OK -> insert booking
try {
  $booking_ref = 'B' . time() . rand(1000,9999);


  // Start transaction
  $pdo->beginTransaction();
 
  try {
    // Define variables consistent with DB schema
    // New bookings default to 'pending' until admin confirms
    $status = 'pending';
    $total_amount = $total;

    // Optional license upload: the client may send a base64-encoded license image
    // in the JSON payload as 'license_base64' and original filename as 'license_name'.
    $license_file = null;
    if (!empty($data['license_base64']) && !empty($data['license_name'])) {
      $uploadsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'licenses';
        // Create uploads directory if it doesn't exist
        if (!is_dir($uploadsDir)) {
          mkdir($uploadsDir, 0755, true);
        }

        // Add debug logging
        error_log("Processing license upload: " . $data['license_name']);

      $raw = $data['license_base64'];
        // Validate and clean the base64 data
        if (preg_match('/^data:image\/(jpeg|png|jpg);base64,(.*)$/i', $raw, $m)) {
          $raw = $m[2];
          $ext = $m[1];
        } else {
          error_log("Invalid license file format. Expected JPEG/PNG image.");
          $raw = null;
      }

        if ($raw !== null) {
          $decoded = base64_decode($raw);
          if ($decoded === false) {
            error_log("Failed to decode base64 license data");
          } else {
        // sanitize filename
          $safeBase = preg_replace('/[^a-zA-Z0-9-_]/', '_', $booking_ref);
          $filename = $safeBase . '_license.' . $ext;
        $target = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
        
        if (file_put_contents($target, $decoded) !== false) {
          $license_file = 'uploads/licenses/' . $filename;
            error_log("License file saved successfully: " . $license_file);
          } else {
            error_log("Failed to save license file to: " . $target);
        }
      }
        } else {
          error_log("No valid base64 data found for license file");
        }
    }

    // Insert booking: if DB doesn't have license_file column yet, fall back to inserting without it
    if ($license_file !== null) {
      $ins = $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, vehicle_id, rental_date, return_date, pickup_time, dropoff_time, days, rate, voucher_id, discount, total_amount, paid, contact_number, age, license_file, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())");
      $params = [
        $booking_ref,
        $user_id,
        $vehicle_id,
        $rental_date,
        $return_date,
        $pickup_time,
        $dropoff_time,
        $days,
        $rate,
        $voucher_id,
        $discount,
        $total_amount,
        $amount_paid,
        $contact_number,
        $age,
        $license_file,
        $status
      ];
    } else {
      $ins = $pdo->prepare("INSERT INTO bookings (booking_ref, user_id, vehicle_id, rental_date, return_date, pickup_time, dropoff_time, days, rate, voucher_id, discount, total_amount, paid, contact_number, age, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())");
      $params = [
        $booking_ref,
        $user_id,
        $vehicle_id,
        $rental_date,
        $return_date,
        $pickup_time,
        $dropoff_time,
        $days,
        $rate,
        $voucher_id,
        $discount,
        $total_amount,
        $amount_paid,
        $contact_number,
        $age,
        $status
      ];
    }

    $ins->execute($params);


    // Get the booking ID
    $booking_id = $pdo->lastInsertId();
   
    if (!$booking_id) {
      throw new Exception('Failed to create booking');
    }

    // Commit booking immediately so optional steps don't fail the booking
    $pdo->commit();

    // Update voucher usage count if voucher was used
    if (!empty($voucher_code) && isset($voucher['usage_limit'])) {
      $new_usage_count = (int)($voucher['usage_count'] ?? 0) + 1;


      // Mark as used by this user and timestamp
      $updateVoucher = $pdo->prepare("
          UPDATE vouchers
          SET used_by = ?, used_at = NOW(), usage_count = ?
          WHERE id = ?
      ");
      $updateVoucher->execute([$user_id, $new_usage_count, $voucher_id]);


      // If reached usage limit, deactivate it
      if ($new_usage_count >= (int)$voucher['usage_limit']) {
          $deactivate = $pdo->prepare("UPDATE vouchers SET is_active = 0 WHERE id = ?");
          $deactivate->execute([$voucher_id]);
      }
    }


    // NOTE: Do not insert transaction or decrement vehicle units here.
    // Booking remains 'pending' until an admin confirms it. Inventory and transaction
    // creation are handled when admin confirms the booking.
   
    // Return success response with receipt data
    echo json_encode([
      'success' => true,
      'booking_ref' => $booking_ref,
      'total' => $total,
      'amount_paid' => $amount_paid,
      'change' => $amount_paid - $total,
      'days' => $days,
      'rate' => $rate,
      'subtotal' => $subtotal,
      'discount' => $discount,
      'rental_date' => $rental_date,
      'return_date' => $return_date,
      'pickup_time' => $pickup_time,
      'dropoff_time' => $dropoff_time,
      'vehicle_name' => $vehicle['title'],
      'voucher_code' => $voucher_code,
      'booking_id' => $booking_id
    ]);
   
  } catch (Exception $e) {
    // Rollback the transaction on error
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
  }


} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
  exit;
}



