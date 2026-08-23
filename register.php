<?php
// register.php
require 'db.php';
header('Content-Type: application/json');
// Prevent PHP warnings/notices from rendering HTML in JSON responses
ini_set('display_errors', 0);


// Accept JSON body or multipart/form-data (with file)
$data = [];
if (!empty($_FILES) || $_SERVER['CONTENT_TYPE'] && stripos($_SERVER['CONTENT_TYPE'], 'multipart/') === 0) {
  // form-data submission
  $data = $_POST;
} else {
  $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
}


$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$privacyConsent = $data['privacy_consent'] ?? false;


if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
  http_response_code(400);
  echo json_encode(['error' => 'Please provide a valid name, email, and a password (min 6 chars).']);
  exit;
}


// System Enhancements initiative, Step 7 (Decision D8: non-persisted —
// validated here, not written to the database). The client-side checkbox
// gate (js/app.js's #signupForm submit handler) is not sufficient on its
// own for a compliance requirement — an unrecognized JSON key is otherwise
// silently ignored by this endpoint, so a direct POST bypassing the UI
// would sail through without this check.
if (empty($privacyConsent)) {
  http_response_code(400);
  echo json_encode(['error' => 'You must agree to the Privacy Policy to create an account.']);
  exit;
}


$licensePath = null;
// Handle uploaded license image if present
if (!empty($_FILES['license']) && $_FILES['license']['error'] !== UPLOAD_ERR_NO_FILE) {
  $file = $_FILES['license'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Error uploading file.']);
    exit;
  }


  // Validate size (max 3MB)
  $maxSize = 3 * 1024 * 1024;
  if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'License image must be 3MB or smaller.']);
    exit;
  }


  // Validate image type
  $tmp = $file['tmp_name'];
  $detected = @exif_imagetype($tmp);
  $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
  if (!$detected || !isset($allowed[$detected])) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPG and PNG images are accepted for license.']);
    exit;
  }


  // Ensure upload directory exists
  $uploadDir = __DIR__ . '/assets/licenses/';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
  }


  // Generate safe unique filename
  try {
    $basename = bin2hex(random_bytes(8));
  } catch (Exception $ex) {
    $basename = uniqid('lic_', true);
  }
  $ext = $allowed[$detected];
  $targetName = $basename . '.' . $ext;
  $targetPath = $uploadDir . $targetName;


  if (!move_uploaded_file($tmp, $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to store uploaded file.']);
    exit;
  }


  // Relative path to store in DB
  $licensePath = 'assets/licenses/' . $targetName;
}


try {
  // check if email exists
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
  $stmt->execute([$email]);
  if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already registered.']);
    exit;
  }


  $hash = password_hash($password, PASSWORD_DEFAULT);


  // If we have a license path, try inserting with that column. If the column doesn't exist, attempt to add it and retry.
  if ($licensePath) {
    try {
      $ins = $pdo->prepare("INSERT INTO users (name,email,password,license_path) VALUES (?,?,?,?)");
      $ins->execute([$name, $email, $hash, $licensePath]);
    } catch (PDOException $e) {
      // If column doesn't exist (SQLSTATE 42S22 / error code 1054), try adding the column and retrying once
      $msg = $e->getMessage();
      $sqlstate = $e->getCode();
      if (stripos($msg, 'Unknown column') !== false || $sqlstate === '42S22' || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1054)) {
        // Try to add column (MySQL 8 supports IF NOT EXISTS; fallback to try/catch)
        try {
          $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS license_path VARCHAR(255) DEFAULT NULL");
        } catch (PDOException $ex) {
          // Some MySQL versions may not support IF NOT EXISTS in ALTER; try without it
          try {
            $pdo->exec("ALTER TABLE users ADD COLUMN license_path VARCHAR(255) DEFAULT NULL");
          } catch (PDOException $inner) {
            // If this fails, rethrow original
            throw $e;
          }
        }


        // Retry insert using `password` column
        $ins = $pdo->prepare("INSERT INTO users (name,email,password,license_path) VALUES (?,?,?,?)");
        $ins->execute([$name, $email, $hash, $licensePath]);
      } else {
        throw $e;
      }
    }
  } else {
    $ins = $pdo->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
    $ins->execute([$name, $email, $hash]);
  }


  echo json_encode(['success' => true, 'message' => 'User registered. You can now log in.']);
} catch (PDOException $e) {
  // If we stored a file but registration failed, attempt to remove the file to avoid orphaned uploads
  if (!empty($targetPath) && file_exists($targetPath)) {
    @unlink($targetPath);
  }
  http_response_code(500);
  echo json_encode(['error' => 'Registration failed', 'details' => $e->getMessage()]);
}



