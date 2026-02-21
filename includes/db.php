<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'laraveluser');
define('DB_PASS', 'StrongPassword123');
define('DB_NAME', 'xexo_val');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

function Connection(){
if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}else {
    echo "Connection Established";
}
}

$conn->set_charset('utf8mb4');
