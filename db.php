<?php
$host = 'localhost';
$db   = 'pms_connection';    // database  name pre
$user = 'root';          // Hostinger MySQL username
$pass = '';              // Hostinger MySQL password DO NOT forget this ah 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  // If the DB connection fails, show a simple message and stop
  echo "Database connection failed: " . htmlspecialchars($e->getMessage());
  exit;
}
