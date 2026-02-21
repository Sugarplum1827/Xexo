<?php
session_start();
require_once '../includes/db.php';
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES ($uid, 'LOGOUT', 'User logged out', '$ip', NOW())");
}
session_destroy();
header('Location: ../index.php');
exit;
