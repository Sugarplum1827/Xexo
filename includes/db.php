<?php
define('DB_HOST', 'sql102.infinityfree.com');
define('DB_USER', 'if0_41212959');
define('DB_PASS', 'n6YstrO9R9c');
define('DB_NAME', 'if0_41212959_xexo');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

function Connection(){
global $conn;
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}else {
    echo "Connection Established";
}
}

$conn->set_charset('utf8mb4');

// Set PHP and MySQL to the same timezone (Asia/Manila = UTC+8)
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");
