<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin($redirect = '../index.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

function requireRole($roles, $redirect = '../index.php') {
    requireLogin($redirect);
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header("Location: $redirect");
        exit;
    }
}

function currentUser() {
    return $_SESSION ?? [];
}

function logActivity($conn, $action, $description) {
    if (!isLoggedIn()) return;
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isss", $user_id, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . 'd ago';
    if ($diff->h > 0) return $diff->h . 'h ago';
    if ($diff->i > 0) return $diff->i . 'm ago';
    return 'Just now';
}
