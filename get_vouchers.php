<?php
session_start();
include 'db_connect.php';


header('Content-Type: application/json');


// Fetch vouchers that haven't reached their usage limit
$sql = "SELECT * FROM vouchers
        WHERE usage_count < usage_limit
        ORDER BY created_at DESC";


$result = $conn->query($sql);
$vouchers = [];


while($row = $result->fetch_assoc()) {
    $vouchers[] = $row;
}


echo json_encode($vouchers);

