<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Periods';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $uid    = $_SESSION['user_id'];

    if ($action === 'create') {
        $label  = trim($_POST['period_label']);
        $type   = $_POST['period_type'];
        $amount = (float)$_POST['allocated_amount'];
        $start  = $_POST['start_date'];
        $end    = $_POST['end_date'];
        // Insert with approval_status = 'pending' — Admin must approve
        $stmt = $conn->prepare("INSERT INTO budgets (period_label, period_type, allocated_amount, start_date, end_date, is_active, approval_status, created_by) VALUES (?,?,?,?,?,0,'pending',?)");
        $stmt->bind_param("ssdssi", $label, $type, $amount, $start, $end, $uid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'CREATE_BUDGET_PROPOSAL', "Proposed budget: $label - ₱$amount (pending admin approval)");
        $msg = 'Budget proposal submitted to Admin for approval.'; $msgType = 'success';
    }
}

$budgets = $conn->query("SELECT b.*, u.full_name, a.full_name AS approver_name FROM budgets b LEFT JOIN users u ON b.created_by=u.id LEFT JOIN users a ON b.approved_by=a.id ORDER BY b.id DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Propose New Budget Period</span></div>
        <div class="alert alert-warning" style="margin-bottom:16px;"><i class="fas fa-info-circle"></i> Budget proposals are submitted to the Admin for approval before they become active.</div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Period Label *</label>
                <input type="text" name="period_label" class="form-control" required placeholder="e.g. 2nd Semester 2025-2026">
            </div>
            <div class="form-group">
                <label>Period Type</label>
                <select name="period_type" class="form-control">
                    <option value="semestral">Semestral</option>
                    <option value="yearly">Yearly</option>
                    <option value="monthly">Monthly</option>
                    <option value="daily">Daily</option>
                </select>
            </div>
            <div class="form-group">
                <label>Requested Amount (₱)</label>
                <input type="number" name="allocated_amount" class="form-control" required step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="form-row">
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" required></div>
                <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%">
                <i class="fas fa-paper-plane"></i> Submit Budget Proposal
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Budget History</span></div>
        <?php if (empty($budgets)): ?>
        <p style="text-align:center;color:var(--text-muted);padding:24px">No budget records yet.</p>
        <?php endif; ?>
        <?php foreach ($budgets as $b): ?>
        <div style="border:1px solid var(--cream-dark);border-radius:10px;padding:16px;margin-bottom:12px;
            <?= $b['approval_status']==='approved'&&$b['is_active']?'border-color:var(--success);background:rgba(22,163,74,0.04)':'' ?>
            <?= $b['approval_status']==='rejected'?'border-color:var(--danger);background:rgba(185,28,28,0.03)':'' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-family:'Fraunces',serif;font-weight:700;color:var(--forest)"><?= htmlspecialchars($b['period_label']) ?></span>
                <div style="display:flex;gap:6px;">
                    <span class="badge <?= $b['approval_status']==='approved'?'badge-approved':($b['approval_status']==='rejected'?'badge-rejected':'badge-pending') ?>">
                        <?= ucfirst($b['approval_status']) ?>
                    </span>
                    <?php if ($b['approval_status']==='approved'): ?>
                    <span class="badge <?= $b['is_active']?'badge-approved':'badge-pending' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px;font-family:'Fraunces',serif"><?= formatCurrency($b['allocated_amount']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;"><?= $b['start_date'] ?> to <?= $b['end_date'] ?></div>
            <?php if ($b['approval_status']==='rejected' && $b['rejection_reason']): ?>
            <div style="font-size:12px;color:var(--danger);padding:6px 10px;background:rgba(185,28,28,0.06);border-radius:6px;">
                <i class="fas fa-times-circle"></i> Rejection reason: <?= htmlspecialchars($b['rejection_reason']) ?>
            </div>
            <?php endif; ?>
            <?php if ($b['approver_name']): ?>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Reviewed by: <?= htmlspecialchars($b['approver_name']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
