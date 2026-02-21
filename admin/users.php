<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'User Management';

$msg = ''; $msgType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $full_name = trim($_POST['full_name']);
        $username  = trim($_POST['username']);
        $email     = trim($_POST['email']);
        $role      = $_POST['role'];
        $password  = $_POST['password'];
        if ($full_name && $username && $email && $role && $password) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $uid  = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO users (full_name,username,email,password,role,created_by) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("sssssi", $full_name, $username, $email, $hash, $role, $uid);
            if ($stmt->execute()) {
                $newId = $stmt->insert_id;
                logActivity($conn, 'CREATE_USER', "Created user: $username ($role)");
                $msg = "User '$username' created successfully."; $msgType='success';
            } else {
                $msg = "Error: " . $conn->error; $msgType='danger';
            }
            $stmt->close();
        } else { $msg='All fields required.'; $msgType='danger'; }
    }

    if ($action === 'toggle') {
        $tid = (int)$_POST['user_id'];
        $conn->query("UPDATE users SET is_active = NOT is_active WHERE id=$tid");
        logActivity($conn, 'TOGGLE_USER', "Toggled active status for user ID $tid");
        $msg = 'User status updated.'; $msgType='success';
    }

    if ($action === 'delete') {
        $tid = (int)$_POST['user_id'];
        $conn->query("DELETE FROM users WHERE id=$tid AND role != 'admin'");
        logActivity($conn, 'DELETE_USER', "Deleted user ID $tid");
        $msg = 'User deleted.'; $msgType='success';
    }

    if ($action === 'reset_password') {
        $tid = (int)$_POST['user_id'];
        $np  = $_POST['new_password'];
        if ($np) {
            $hash = password_hash($np, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hash, $tid);
            $stmt->execute(); $stmt->close();
            logActivity($conn, 'RESET_PASSWORD', "Reset password for user ID $tid");
            $msg = 'Password reset successfully.'; $msgType='success';
        }
    }
}

$users = $conn->query("SELECT u.*, c.full_name AS created_by_name FROM users u LEFT JOIN users c ON c.id = u.created_by ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<style>
.pw-wrap { position: relative; }
.pw-wrap .form-control { padding-right: 42px; }
.eye-btn {
    position: absolute;
    right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none;
    color: #98989a;
    font-size: 15px; cursor: pointer;
    padding: 2px 4px; line-height: 1;
    transition: color 0.2s;
}
.eye-btn:hover { color: #b91c1c; }
</style>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">All Users</span>
        <button class="btn btn-gold" onclick="document.getElementById('createModal').classList.add('open')">
            <i class="fas fa-plus"></i> Add New User
        </button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $i => $u): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <span class="badge" style="background:<?= ['admin'=>'rgba(26,58,42,0.12)','budget_manager'=>'rgba(41,128,185,0.12)','inventory_manager'=>'rgba(39,174,96,0.12)','user'=>'rgba(230,126,34,0.12)'][$u['role']] ?>;color:<?= ['admin'=>'#1a3a2a','budget_manager'=>'#2980b9','inventory_manager'=>'#27ae60','user'=>'#e67e22'][$u['role']] ?>">
                        <?= ucwords(str_replace('_',' ',$u['role'])) ?>
                    </span>
                </td>
                <td><span class="badge <?= $u['is_active']?'badge-approved':'badge-rejected' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                <td style="font-size:12px;color:var(--text-muted)"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td>
                    <?php if ($u['role'] !== 'admin'): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline" title="<?= $u['is_active']?'Deactivate':'Activate' ?>">
                                <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-primary" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">
                            <i class="fas fa-key"></i>
                        </button>
                        <form method="POST" onsubmit="return confirm('Delete this user?');" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                    <?php else: ?><span style="font-size:12px;color:var(--text-muted)">Protected</span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Add New User</span>
            <button class="modal-close" onclick="document.getElementById('createModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="e.g. Juan dela Cruz">
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required placeholder="e.g. jdelacruz">
                </div>
            </div>
            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="user@example.com">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="budget_manager">Budget Manager</option>
                        <option value="inventory_manager">Inventory Manager</option>
                        <option value="user">Encoder (Student)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <div class="pw-wrap">
                        <input type="password" name="password" id="createPassword" class="form-control" required placeholder="Min 8 characters">
                        <button type="button" class="eye-btn" onclick="toggleEye('createPassword',this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-user-plus"></i> Create User</button>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Reset Password – <span id="resetName"></span></span>
            <button class="modal-close" onclick="document.getElementById('resetModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div class="form-group">
                <label>New Password</label>
                <div class="pw-wrap">
                    <input type="password" name="new_password" id="resetPassword" class="form-control" required placeholder="Enter new password">
                    <button type="button" class="eye-btn" onclick="toggleEye('resetPassword',this)" tabindex="-1"><i class="fas fa-eye"></i></button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-key"></i> Reset Password</button>
        </form>
    </div>
</div>

<script>
function openResetModal(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetName').textContent = name;
    // Reset eye state when modal opens
    const rp = document.getElementById('resetPassword');
    if (rp) { rp.type = 'password'; }
    const eyeBtn = document.querySelector('#resetModal .eye-btn i');
    if (eyeBtn) { eyeBtn.classList.replace('fa-eye-slash','fa-eye'); }
    document.getElementById('resetModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
function toggleEye(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
        btn.style.color = '#b91c1c';
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
        btn.style.color = '';
    }
}
</script>
<?php include '../includes/footer.php'; ?>