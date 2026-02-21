<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Allocation';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'create') {
        $label  = trim($_POST['period_label']);
        $type   = $_POST['period_type'];
        $amount = (float)$_POST['allocated_amount'];
        $start  = $_POST['start_date'];
        $end    = $_POST['end_date'];
        $uid    = $_SESSION['user_id'];
        // Deactivate old
        $conn->query("UPDATE budgets SET is_active=0");
        $stmt = $conn->prepare("INSERT INTO budgets (period_label, period_type, allocated_amount, start_date, end_date, is_active, created_by) VALUES (?,?,?,?,?,1,?)");
        $stmt->bind_param("ssdssi", $label, $type, $amount, $start, $end, $uid);
        $stmt->execute(); $stmt->close();
        logActivity($conn, 'CREATE_BUDGET', "Created budget: $label - $amount");
        $msg = 'Budget created and activated.'; $msgType = 'success';
    }
    if ($action === 'adjust') {
        $bid    = (int)$_POST['budget_id'];
        $amount = (float)$_POST['new_amount'];
        $conn->query("UPDATE budgets SET allocated_amount=$amount WHERE id=$bid");
        logActivity($conn, 'ADJUST_BUDGET', "Adjusted budget ID $bid to $amount");
        $msg = 'Budget adjusted.'; $msgType = 'success';
    }
}

$budgets = $conn->query("SELECT b.*, u.full_name FROM budgets b LEFT JOIN users u ON b.created_by=u.id ORDER BY b.id DESC")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Create New Budget Period</span></div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Period Label</label>
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
                <label>Allocated Amount (₱)</label>
                <input type="number" name="allocated_amount" class="form-control" required step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="form-row">
                <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" required></div>
                <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" required></div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%" onclick="return confirm('This will deactivate the current budget. Continue?')">
                <i class="fas fa-plus"></i> Create & Activate Budget
            </button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Budget History</span></div>
        <?php foreach ($budgets as $b): ?>
        <div style="border:1px solid var(--cream-dark);border-radius:10px;padding:16px;margin-bottom:12px;<?= $b['is_active']?'border-color:var(--forest-light);background:rgba(39,174,96,0.04)':'' ?>">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-family:'Fraunces',serif;font-weight:700;color:var(--forest)"><?= htmlspecialchars($b['period_label']) ?></span>
                <span class="badge <?= $b['is_active']?'badge-approved':'badge-pending' ?>"><?= $b['is_active']?'Active':'Inactive' ?></span>
            </div>
            <div style="font-size:22px;font-weight:800;color:var(--text);margin-bottom:4px;font-family:'Fraunces',serif"><?= formatCurrency($b['allocated_amount']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;"><?= $b['start_date'] ?> to <?= $b['end_date'] ?></div>
            <?php if ($b['is_active']): ?>
            <form method="POST" style="display:flex;gap:8px;align-items:center;">
                <input type="hidden" name="action" value="adjust">
                <input type="hidden" name="budget_id" value="<?= $b['id'] ?>">
                <input type="number" name="new_amount" class="form-control" style="width:140px;padding:8px;" step="0.01" placeholder="New amount">
                <button class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Adjust</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
