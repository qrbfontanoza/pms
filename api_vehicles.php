<?php
// api_vehicles.php - JSON vehicle listing/detail for API consumers (e.g. the mobile app)
require 'db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if ($id) {
  $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = ? AND is_active = 1");
  $stmt->execute([$id]);
  $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$vehicle) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehicle not found']);
    exit;
  }

  $q = $pdo->prepare("
    SELECT COUNT(*) FROM bookings
    WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')
  ");
  $q->execute([$vehicle['id']]);
  $bookedCount = (int)$q->fetchColumn();
  $vehicle['available_units'] = max(0, (int)$vehicle['units_total'] - $bookedCount);

  echo json_encode(['success' => true, 'vehicle' => $vehicle]);
  exit;
}

$category = isset($_GET['category']) ? trim($_GET['category']) : null;

if ($category) {
  $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE is_active = 1 AND category = ? ORDER BY title ASC");
  $stmt->execute([$category]);
} else {
  $stmt = $pdo->query("SELECT * FROM vehicles WHERE is_active = 1 ORDER BY title ASC");
}
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("
  SELECT COUNT(*) FROM bookings
  WHERE vehicle_id = ? AND status IN ('pending','confirmed','completed')
");

foreach ($vehicles as &$v) {
  $countStmt->execute([$v['id']]);
  $bookedCount = (int)$countStmt->fetchColumn();
  $v['available_units'] = max(0, (int)$v['units_total'] - $bookedCount);
}
unset($v);

echo json_encode(['success' => true, 'vehicles' => $vehicles]);
