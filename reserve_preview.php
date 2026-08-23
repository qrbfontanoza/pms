<?php
// reserve_preview.php
require 'db.php';
header('Content-Type: application/json');


$data = json_decode(file_get_contents('php://input'), true);
$vehicle_id   = (int)($data['vehicle_id'] ?? 0);
$rental_date  = $data['rental_date'] ?? '';
$return_date  = $data['return_date'] ?? '';
$voucher_code = trim($data['voucher_code'] ?? '');


if (!$vehicle_id || !$rental_date || !$return_date) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields']);
  exit;
}


// 1. Fetch vehicle info
$stmt = $pdo->prepare('SELECT price_per_day FROM vehicles WHERE id = ? AND is_active = 1');
$stmt->execute([$vehicle_id]);
$veh = $stmt->fetch();
if (!$veh) {
  http_response_code(400);
  echo json_encode(['error' => 'Vehicle not found']);
  exit;
}


// 2. Calculate days
$d1 = new DateTime($rental_date);
$d2 = new DateTime($return_date);
$days = max(1, (int)$d2->diff($d1)->format('%a'));


// 3. Compute subtotal
$rate     = $veh['price_per_day'];
$subtotal = $rate * $days;


// 4. Optional voucher
$discount = 0.0;
if ($voucher_code) {
  $v = $pdo->prepare('SELECT * FROM vouchers WHERE code = ? AND is_active = 1');
  $v->execute([$voucher_code]);
  $voucher = $v->fetch();
  if ($voucher) {
    if ($voucher['discount_pct']) {
      $discount = $subtotal * ($voucher['discount_pct'] / 100);
    } elseif ($voucher['discount_amount']) {
      $discount = (float)$voucher['discount_amount'];
    }
  } else {
    echo json_encode(['error' => 'Invalid voucher']);
    exit;
  }
}


// 5. Total
$total = max(0, $subtotal - $discount);


// 6. Send response
echo json_encode([
  'days'      => $days,
  'rate'      => $rate,
  'subtotal'  => $subtotal,
  'discount'  => $discount,
  'total'     => $total
]);





