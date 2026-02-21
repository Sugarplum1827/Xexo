<?php
/**
 * CIT Food Trades – First-Time Setup Script
 * 
 * INSTRUCTIONS:
 *   1. Open this in your browser: http://localhost/cit_food_trades/setup.php
 *   2. It will create the admin account using YOUR server's PHP version.
 *   3. DELETE this file immediately after setup is complete!
 *
 * DO NOT leave this file on a production server.
 */

require_once 'includes/db.php';

$done    = false;
$error   = '';
$message = '';

// Check if admin already exists
$existing = $conn->query("SELECT id FROM users WHERE username='admin' LIMIT 1")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm'] ?? '';
    $full_name   = trim($_POST['full_name'] ?? 'System Administrator');
    $username    = 'admin';
    $email       = trim($_POST['email'] ?? 'admin@citfoodtrades.edu.ph');

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        if ($existing) {
            // Update existing admin
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, password=?, is_active=1 WHERE username='admin'");
            $stmt->bind_param("sss", $full_name, $email, $hash);
        } else {
            // Create fresh admin
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, is_active) VALUES (?, 'admin', ?, ?, 'admin', 1)");
            $stmt->bind_param("sss", $full_name, $email, $hash);
        }

        if ($stmt->execute()) {
            $done    = true;
            $message = $existing ? 'Admin password reset successfully!' : 'Admin account created successfully!';
        } else {
            $error = 'Database error: ' . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CIT Food Trades – Setup</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #1c1c1e;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.card {
    background: #f2f2f7;
    border-radius: 20px;
    padding: 40px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.4);
}
.logo {
    text-align: center;
    margin-bottom: 28px;
}
.logo-icon {
    width: 60px; height: 60px;
    background: #b91c1c;
    border-radius: 16px;
    font-size: 28px;
    display: flex; align-items:center; justify-content:center;
    margin: 0 auto 14px;
}
h1 {
    font-family: 'Fraunces', serif;
    font-size: 24px;
    color: #1c1c1e;
    margin-bottom: 4px;
}
.subtitle { font-size: 13px; color: #6b7280; }
.warning {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: #856404;
    margin-bottom: 24px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.form-group { margin-bottom: 16px; }
label { display:block; font-size:13px; font-weight:600; color:#1c1c1e; margin-bottom:6px; }
input[type=text], input[type=email], input[type=password] {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #e5e5ea;
    border-radius: 9px;
    font-size: 14px;
    font-family: 'DM Sans', sans-serif;
    background: #fff;
    color: #1a1a1a;
    transition: border-color 0.2s;
}
input:focus { outline: none; border-color: #b91c1c; box-shadow: 0 0 0 3px rgba(185,28,28,0.12); }
.btn {
    width: 100%;
    padding: 13px;
    background: #1c1c1e;
    color: #f2f2f7;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    margin-top: 6px;
    transition: background 0.2s;
}
.btn:hover { background: #2c2c2e; }
.alert {
    padding: 12px 16px;
    border-radius: 9px;
    font-size: 13.5px;
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
    align-items: center;
}
.alert-danger  { background: rgba(185,28,28,0.08); color: #991b1b; border: 1px solid rgba(185,28,28,0.20); }
.alert-success { background: rgba(22,163,74,0.08);  color: #15803d; border: 1px solid rgba(22,163,74,0.20); }
.success-box { text-align: center; padding: 10px 0; }
.success-icon { font-size: 52px; margin-bottom: 12px; }
.success-box h2 { font-family:'Fraunces',serif; font-size:22px; color:#1c1c1e; margin-bottom:8px; }
.success-box p { font-size:14px; color:#6b7280; margin-bottom:20px; }
.creds {
    background: #fff;
    border: 1.5px solid #e5e5ea;
    border-radius: 10px;
    padding: 16px;
    margin: 16px 0;
    font-size: 14px;
    text-align: left;
}
.creds div { display:flex; justify-content:space-between; padding:4px 0; }
.creds strong { color: #1c1c1e; }
.login-btn {
    display: block;
    padding: 13px;
    background: #b91c1c;
    color: #1c1c1e;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
    transition: background 0.2s;
}
.login-btn:hover { background: #e0c46a; }
.delete-note {
    margin-top: 16px;
    padding: 10px 14px;
    background: rgba(192,57,43,0.08);
    border-radius: 8px;
    font-size: 12px;
    color: #991b1b;
    text-align: center;
}
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🍽️</div>
        <h1>System Setup</h1>
        <p class="subtitle">CIT Food Trades – Budgeting & Inventory System</p>
    </div>

    <?php if ($done): ?>
    <div class="success-box">
        <div class="success-icon">✅</div>
        <h2><?= $message ?></h2>
        <p>Your admin account is ready. You can now log in.</p>
        <div class="creds">
            <div><span>Username:</span> <strong>admin</strong></div>
            <div><span>Password:</span> <strong><?= htmlspecialchars($_POST['password']) ?></strong></div>
        </div>
        <a href="index.php" class="login-btn">Go to Login →</a>
        <div class="delete-note">⚠️ Please delete <strong>setup.php</strong> from your server now!</div>
    </div>

    <?php else: ?>

    <div class="warning">
        ⚠️ <span><strong>Setup Mode:</strong> <?= $existing ? 'An admin account already exists. Use this form to reset the password.' : 'No admin account found. Create one below.' ?> Delete this file after setup!</span>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" value="System Administrator" required>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="admin@citfoodtrades.edu.ph" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter admin password" required autofocus>
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn">
            <?= $existing ? '🔑 Reset Admin Password' : '🚀 Create Admin Account' ?>
        </button>
    </form>

    <?php endif; ?>
</div>
</body>
</html>
