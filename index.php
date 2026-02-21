<?php
session_start();
require_once 'includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    $map = ['admin'=>'admin/dashboard.php','budget_manager'=>'budget_manager/dashboard.php','inventory_manager'=>'inventory_manager/dashboard.php','user'=>'user/dashboard.php'];
    header('Location: ' . ($map[$role] ?? 'index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $conn->prepare("SELECT id, full_name, username, password, role, is_active FROM users WHERE username=? OR email=? LIMIT 1");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $uid = $user['id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            $conn->query("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES ($uid, 'LOGIN', 'User logged in', '$ip', NOW())");
            $map = ['admin'=>'admin/dashboard.php','budget_manager'=>'budget_manager/dashboard.php','inventory_manager'=>'inventory_manager/dashboard.php','user'=>'user/dashboard.php'];
            header('Location: ' . ($map[$user['role']] ?? 'index.php'));
            exit;
        } else {
            $error = 'Invalid credentials or account is inactive.';
        }
    } else {
        $error = 'Please enter your username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login – CIT Food Trades</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,600;0,800;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary:      #b91c1c;
    --primary-dark: #991b1b;
    --primary-mid:  #dc2626;
    --primary-light:#fca5a5;
    --grey-900:     #1c1c1e;
    --grey-800:     #2c2c2e;
    --grey-700:     #3a3a3c;
    --grey-600:     #48484a;
    --grey-400:     #98989a;
    --grey-200:     #e5e5ea;
    --grey-100:     #f2f2f7;
    --white:        #ffffff;
    --text:         #1c1c1e;
    --text-muted:   #6e6e73;
}

* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: 'DM Sans', sans-serif;
    min-height: 100vh;
    display: flex;
    background: var(--grey-900);
    position: relative;
    overflow: hidden;
}

body::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse at 15% 60%, rgba(185,28,28,0.18) 0%, transparent 55%),
        radial-gradient(ellipse at 85% 15%, rgba(185,28,28,0.10) 0%, transparent 50%);
    pointer-events: none;
}

.pattern {
    position: absolute; inset: 0;
    opacity: 0.035;
    background-image:
        repeating-linear-gradient(0deg,  transparent, transparent 48px, rgba(255,255,255,1) 48px, rgba(255,255,255,1) 49px),
        repeating-linear-gradient(90deg, transparent, transparent 48px, rgba(255,255,255,1) 48px, rgba(255,255,255,1) 49px);
    pointer-events: none;
}

.accent-stripe {
    position: absolute;
    top: -120px; left: -80px;
    width: 520px; height: 520px;
    background: rgba(185,28,28,0.08);
    border-radius: 50%;
    pointer-events: none;
}

/* ── LEFT PANEL ── */
.left-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 64px 72px;
    position: relative;
    z-index: 1;
}

.brand { margin-bottom: 52px; }

.brand-icon {
    width: 250px;
    height: 75px;
    background: var(--primary);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 22px;
    box-shadow: 0 8px 32px rgba(185,28,28,0.45);
    overflow: hidden;
}

.brand-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand h1 {
    font-family: 'Fraunces', serif;
    font-size: 44px;
    font-weight: 800;
    color: var(--white);
    line-height: 1.1;
}

.brand h1 span {
    color: var(--primary-light);
    display: block;
}

.brand p {
    color: rgba(255,255,255,0.5);
    font-size: 15px;
    margin-top: 12px;
    line-height: 1.7;
    max-width: 360px;
}

.features { display: flex; flex-direction: column; gap: 14px; }

.feature {
    display: flex; align-items: center; gap: 14px;
    color: rgba(255,255,255,0.65);
    font-size: 14px;
}

.feature-icon {
    width: 38px; height: 38px;
    border-radius: 11px;
    background: rgba(185,28,28,0.20);
    border: 1px solid rgba(185,28,28,0.40);
    display: flex; align-items: center; justify-content: center;
    color: var(--primary-light);
    font-size: 15px;
    flex-shrink: 0;
}

.left-divider {
    width: 40px; height: 3px;
    background: var(--primary);
    border-radius: 99px;
    margin-bottom: 28px;
}

/* ── RIGHT PANEL ── */
.right-panel {
    width: 480px;
    background: var(--white);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 60px 52px;
    position: relative;
    z-index: 1;
    box-shadow: -24px 0 64px rgba(0,0,0,0.45);
}

.right-panel::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-dark), var(--primary-mid), var(--primary-light));
}

.right-panel::after {
    content: '';
    position: absolute;
    bottom: -60px; right: -60px;
    width: 200px; height: 200px;
    background: rgba(185,28,28,0.06);
    border-radius: 50%;
    pointer-events: none;
}

.login-header { margin-bottom: 32px; }

.login-header .badge {
    display: inline-block;
    background: rgba(185,28,28,0.10);
    color: var(--primary);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    padding: 4px 10px;
    border-radius: 6px;
    margin-bottom: 12px;
}

.login-header h2 {
    font-family: 'Fraunces', serif;
    font-size: 30px; font-weight: 700;
    color: var(--text);
    margin-bottom: 6px;
}

.login-header p { font-size: 14px; color: var(--text-muted); }

/* Form */
.form-group { margin-bottom: 18px; }

.form-group label {
    display: block;
    font-size: 13px; font-weight: 600;
    color: var(--grey-800);
    margin-bottom: 7px;
}

.input-wrap { position: relative; }

/* Left icon — use a span so it never interferes with eye button */
.input-wrap .field-icon {
    position: absolute;
    left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--grey-400);
    font-size: 14px;
    pointer-events: none;
    transition: color 0.2s;
    z-index: 1;
}
.input-wrap:focus-within .field-icon { color: var(--primary); }

.form-control {
    width: 100%;
    padding: 12px 14px 12px 40px;
    border: 1.5px solid var(--grey-200);
    border-radius: 10px;
    font-size: 14px;
    font-family: 'DM Sans', sans-serif;
    background: var(--white);
    transition: all 0.2s;
    color: var(--text);
}

/* extra right padding when eye button is present */
.form-control.with-eye { padding-right: 44px; }

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(185,28,28,0.12);
}

/* ── EYE TOGGLE ── */
.eye-btn {
    position: absolute;
    right: 12px; top: 50%; transform: translateY(-50%);
    background: none;
    border: none;
    padding: 4px 5px;
    cursor: pointer;
    color: var(--grey-400);
    font-size: 15px;
    line-height: 1;
    border-radius: 5px;
    transition: color 0.2s, background 0.2s;
    z-index: 2;
}
.eye-btn:hover        { color: var(--primary); background: rgba(185,28,28,0.07); }
.eye-btn.showing      { color: var(--primary); }

.btn-login {
    width: 100%;
    padding: 13px;
    background: var(--primary);
    color: var(--white);
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: all 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    margin-top: 8px;
}

.btn-login:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(185,28,28,0.35);
}

.btn-login:active { transform: translateY(0); }

.alert-error {
    background: rgba(185,28,28,0.08);
    border: 1px solid rgba(185,28,28,0.22);
    color: var(--primary-dark);
    padding: 12px 14px;
    border-radius: 9px;
    font-size: 13.5px;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
}

/* Demo credentials */
.demo-creds {
    margin-top: 26px;
    padding: 16px;
    background: var(--grey-100);
    border-radius: 10px;
    border: 1px solid var(--grey-200);
}

.demo-creds h4 {
    font-size: 11px; font-weight: 700;
    color: var(--grey-600);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
}

.demo-item {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 12px; color: var(--text-muted);
    padding: 5px 0;
    border-bottom: 1px solid var(--grey-200);
}
.demo-item:last-child { border-bottom: none; }

.demo-item .role-tag {
    font-size: 11px;
    background: rgba(185,28,28,0.09);
    color: var(--primary);
    padding: 2px 8px;
    border-radius: 5px;
    font-weight: 600;
}

.demo-item strong {
    font-family: monospace;
    font-size: 12px;
    color: var(--grey-700);
    background: var(--grey-200);
    padding: 2px 7px;
    border-radius: 5px;
}

@media(max-width:768px){
    .left-panel { display: none; }
    .right-panel { width: 100%; padding: 40px 28px; }
}
</style>
</head>
<body>
<div class="pattern"></div>
<div class="accent-stripe"></div>

<div class="left-panel">
    <div class="brand">
        <div class="brand-icon">
            <img src="image/CIT.png" alt="Logo">
        </div>
        <h1>CIT Food Trades<span>Xexo Management System</span></h1>
        <p>Comprehensive budgeting, inventory tracking, and expense management for the CIT Food Trades department.</p>
    </div>
    <div class="left-divider"></div>
    <div class="features">
        <div class="feature">
            <div class="feature-icon"><i class="fas fa-wallet"></i></div>
            Real-time budget monitoring &amp; alerts
        </div>
        <div class="feature">
            <div class="feature-icon"><i class="fas fa-boxes"></i></div>
            Inventory tracking &amp; purchase management
        </div>
        <div class="feature">
            <div class="feature-icon"><i class="fas fa-check-double"></i></div>
            Review &amp; approval workflow
        </div>
        <div class="feature">
            <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
            Automated reports &amp; activity logs
        </div>
    </div>
</div>

<div class="right-panel">
    <div class="login-header">
        <div class="badge">🔐 Secure Access</div>
        <h2>Welcome back</h2>
        <p>Sign in to your account to continue</p>
    </div>

    <?php if ($error): ?>
    <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username or Email</label>
            <div class="input-wrap">
                <i class="fas fa-user field-icon"></i>
                <input type="text" name="username" class="form-control"
                    placeholder="Enter your username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock field-icon"></i>
                <input type="password" name="password" id="loginPassword"
                    class="form-control with-eye"
                    placeholder="Enter your password" required>
                <button type="button" class="eye-btn" id="eyeBtn"
                    onclick="togglePw()" tabindex="-1" title="Show / hide password">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="fas fa-sign-in-alt"></i> Sign In
        </button>
    </form>

    <div class="demo-creds">
        <h4>🔑 Demo Credentials</h4>
        <div class="demo-item">
            <span class="role-tag">Admin</span>
            <strong>admin / admin123</strong>
        </div>
        <div class="demo-item">
            <span class="role-tag">Budget Manager</span>
            <strong>budgetmgr / budgetmgr123</strong>
        </div>
        <div class="demo-item">
            <span class="role-tag">Inventory Manager</span>
            <strong>invmgr / invmgr123</strong>
        </div>
        <div class="demo-item">
            <span class="role-tag">Encoder</span>
            <strong>encoder1 / encoder123</strong>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const input = document.getElementById('loginPassword');
    const icon  = document.getElementById('eyeIcon');
    const btn   = document.getElementById('eyeBtn');
    const showing = input.type === 'text';

    input.type = showing ? 'password' : 'text';
    icon.classList.replace(
        showing ? 'fa-eye-slash' : 'fa-eye',
        showing ? 'fa-eye'       : 'fa-eye-slash'
    );
    btn.classList.toggle('showing', !showing);
    input.focus();
}
</script>
</body>
</html>