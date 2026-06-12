<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Allocations';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid    = $_SESSION['user_id'];

    if ($action === 'create_allocation') {
        $budget_id  = (int)$_POST['budget_id'];
        $encoder_id = $_POST['encoder_id'] !== '' ? (int)$_POST['encoder_id'] : null;
        $is_shared  = isset($_POST['is_shared']) ? 1 : 0;
        $title      = trim($_POST['allocation_title']);
        $purpose    = trim($_POST['purpose']);
        $amount     = (float)$_POST['allocated_amount'];
        $date       = date('Y-m-d');
        if ($is_shared) $encoder_id = null;
        $stmt = $conn->prepare("INSERT INTO budget_allocations (budget_id, encoder_id, is_shared, allocation_title, purpose, allocated_amount, allocation_date, admin_approval_status, created_by) VALUES (?,?,?,?,?,?,?,'pending',?)");
        $stmt->bind_param("iiissdsi", $budget_id, $encoder_id, $is_shared, $title, $purpose, $amount, $date, $uid);
        $stmt->execute(); $stmt->close();
        logActivity($conn,'CREATE_ALLOCATION',"Created budget allocation: $title - ₱$amount (pending admin approval)");
        $msg = 'Allocation created and submitted to Admin for approval.'; $msgType = 'success';
    }
}

$allocations = $conn->query("
    SELECT ba.*, b.period_label,
           u.full_name AS encoder_name,
           a.full_name AS admin_name,
           cr.full_name AS created_by_name
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    LEFT JOIN users u ON ba.encoder_id = u.id
    LEFT JOIN users a ON ba.admin_approved_by = a.id
    LEFT JOIN users cr ON ba.created_by = cr.id
    ORDER BY ba.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$activeBudgets = $conn->query("SELECT * FROM budgets WHERE is_active=1 AND approval_status='approved' ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$encoders      = $conn->query("SELECT id, full_name FROM users WHERE role='user' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Remaining budget summary
$totalAllocated = array_sum(array_column(array_filter($allocations, fn($a) => $a['admin_approval_status']==='approved'), 'allocated_amount'));
$totalUsed      = array_sum(array_column(array_filter($allocations, fn($a) => $a['admin_approval_status']==='approved'), 'amount_used'));
$activeBudget   = $activeBudgets[0] ?? null;
$totalApproved  = $activeBudget['allocated_amount'] ?? 0;
$remaining      = $totalApproved - $totalAllocated;

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Remaining Budget Tracker -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-wallet"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalApproved) ?></div><div class="stat-label">Total Budget Approved</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-share-square"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalAllocated) ?></div><div class="stat-label">Total Allocated</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value"><?= formatCurrency($totalUsed) ?></div><div class="stat-label">Total Used</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-piggy-bank"></i></div>
        <div><div class="stat-value"><?= formatCurrency($remaining) ?></div><div class="stat-label">Remaining Available</div></div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <!-- New Allocation Form -->
    <div class="card">
        <div class="card-header"><span class="card-title">Create New Allocation</span></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_allocation">
            <div class="form-group">
                <label>Budget Period *</label>
                <select name="budget_id" class="form-control" required>
                    <?php foreach ($activeBudgets as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['period_label']) ?></option>
                    <?php endforeach; ?>
                    <?php if (empty($activeBudgets)): ?><option value="">No approved budgets</option><?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Allocation Title *</label>
                <input type="text" name="allocation_title" class="form-control" required placeholder="e.g. Cooking Lab Budget">
            </div>
            <div class="form-group">
                <label>Purpose</label>
                <textarea name="purpose" class="form-control" rows="2" placeholder="Describe the purpose..."></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_shared" id="isSharedChk" onchange="toggleEncoder(this)"> Shared (accessible by all Encoders)
                </label>
            </div>
            <div class="form-group" id="encoderField">
                <label>Assign to Encoder *</label>
                <select name="encoder_id" class="form-control">
                    <option value="">-- Select Encoder --</option>
                    <?php foreach ($encoders as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Amount to Allocate (₱) *</label>
                <input type="number" name="allocated_amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;"><i class="fas fa-info-circle"></i> Will be submitted to Admin for approval before taking effect.</p>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-plus"></i> Create Allocation</button>
        </form>
    </div>

    <!-- Allocation History -->
    <div class="card">
        <div class="card-header"><span class="card-title">Allocation History</span></div>
        <div style="max-height:420px;overflow-y:auto;">
        <?php foreach ($allocations as $a):
            $rem = $a['allocated_amount'] - $a['amount_used'];
        ?>
        <div style="border:1px solid var(--grey-200);border-radius:10px;padding:14px;margin-bottom:10px;<?= $a['admin_approval_status']==='approved'?'border-color:var(--success);background:rgba(22,163,74,0.03)':''; ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <strong style="font-size:14px;"><?= htmlspecialchars($a['allocation_title']??'—') ?></strong>
                <span class="badge badge-<?= $a['admin_approval_status']==='approved'?'approved':($a['admin_approval_status']==='rejected'?'rejected':'pending') ?>"><?= ucfirst($a['admin_approval_status']) ?></span>
            </div>
            <div style="font-size:13px;color:var(--text-muted);">
                <?= $a['is_shared'] ? '<span class="badge badge-pending" style="font-size:10px;">Shared</span>' : htmlspecialchars($a['encoder_name']??'—') ?>
                &bull; <?= htmlspecialchars($a['period_label']) ?>
            </div>
            <div style="display:flex;gap:16px;font-size:13px;margin-top:6px;">
                <div>Allocated: <strong><?= formatCurrency($a['allocated_amount']) ?></strong></div>
                <div>Used: <strong style="color:var(--danger)"><?= formatCurrency($a['amount_used']) ?></strong></div>
                <div>Remaining: <strong style="color:var(--success)"><?= formatCurrency($rem) ?></strong></div>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= $a['allocation_date'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($allocations)): ?><p style="text-align:center;color:var(--text-muted);padding:24px">No allocations yet.</p><?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleEncoder(chk) {
    document.getElementById('encoderField').style.display = chk.checked ? 'none' : 'block';
}
</script>
<?php include '../includes/footer.php'; ?>
