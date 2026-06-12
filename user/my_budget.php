<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'My Budget';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

// Handle budget consumption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'consume') {
    $alloc_id = (int)$_POST['allocation_id'];
    $amount   = (float)$_POST['amount_spent'];
    $desc     = trim($_POST['description']);
    // Verify allocation belongs to this encoder (or is shared+approved)
    $alloc = $conn->query("SELECT * FROM budget_allocations WHERE id=$alloc_id AND admin_approval_status='approved' AND (encoder_id=$uid OR is_shared=1)")->fetch_assoc();
    if ($alloc) {
        $remaining = $alloc['allocated_amount'] - $alloc['amount_used'];
        if ($amount > $remaining) {
            $msg = 'Amount exceeds remaining allocated budget.'; $msgType = 'danger';
        } else {
            $stmt = $conn->prepare("INSERT INTO budget_consumption_log (budget_allocation_id, encoder_id, amount_spent, description, spent_at) VALUES (?,?,?,?,NOW())");
            $stmt->bind_param("iids", $alloc_id, $uid, $amount, $desc);
            $stmt->execute(); $stmt->close();
            $conn->query("UPDATE budget_allocations SET amount_used=amount_used+$amount WHERE id=$alloc_id");
            logActivity($conn, 'CONSUME_BUDGET', "Spent ₱$amount from allocation ID $alloc_id: $desc");
            $msg = 'Budget consumption recorded.'; $msgType = 'success';
        }
    } else {
        $msg = 'Invalid or unauthorized allocation.'; $msgType = 'danger';
    }
}

// My approved allocations (personal + shared)
$allocations = $conn->query("
    SELECT ba.*, b.period_label, u.full_name AS created_by_name
    FROM budget_allocations ba
    JOIN budgets b ON ba.budget_id = b.id
    JOIN users u ON ba.created_by = u.id
    WHERE ba.admin_approval_status='approved'
      AND (ba.encoder_id=$uid OR ba.is_shared=1)
    ORDER BY ba.allocation_date DESC
")->fetch_all(MYSQLI_ASSOC);

// My consumption logs
$logs = $conn->query("
    SELECT bcl.*, ba.allocation_title
    FROM budget_consumption_log bcl
    JOIN budget_allocations ba ON bcl.budget_allocation_id = ba.id
    WHERE bcl.encoder_id=$uid
    ORDER BY bcl.spent_at DESC LIMIT 30
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Allocated Budgets -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <span class="card-title">My Allocated Budgets</span>
        <a href="budget_requests.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> Request Budget</a>
    </div>
    <?php if (empty($allocations)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-muted);">No budgets allocated yet.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Title</th><th>Period</th><th>Type</th><th>Allocated</th><th>Used</th><th>Remaining</th><th>Date</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($allocations as $i => $a):
                $remaining = $a['allocated_amount'] - $a['amount_used'];
                $pct = $a['allocated_amount'] > 0 ? min(100, ($a['amount_used']/$a['allocated_amount'])*100) : 0;
            ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($a['allocation_title']??'—') ?></strong><?php if($a['is_shared']): ?> <span class="badge badge-pending" style="font-size:10px;">Shared</span><?php endif; ?></td>
                <td style="font-size:12px;"><?= htmlspecialchars($a['period_label']) ?></td>
                <td><span class="badge <?= $a['is_shared']?'badge-pending':'badge-approved' ?>"><?= $a['is_shared']?'Shared':'Personal' ?></span></td>
                <td><?= formatCurrency($a['allocated_amount']) ?></td>
                <td><?= formatCurrency($a['amount_used']) ?></td>
                <td style="color:<?= $remaining>0?'var(--success)':'var(--danger)' ?>;font-weight:700;"><?= formatCurrency($remaining) ?></td>
                <td style="font-size:12px;"><?= $a['allocation_date'] ?></td>
                <td>
                    <?php if ($remaining > 0): ?>
                    <button class="btn btn-sm btn-gold" onclick="openConsume(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['allocation_title']??'')) ?>', <?= $remaining ?>)"><i class="fas fa-minus-circle"></i> Use</button>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted)">Depleted</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Consumption History -->
<div class="card">
    <div class="card-header"><span class="card-title">My Budget Consumption Log</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Allocation</th><th>Amount Spent</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $i => $l): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= htmlspecialchars($l['allocation_title']??'—') ?></td>
                <td><strong><?= formatCurrency($l['amount_spent']) ?></strong></td>
                <td style="font-size:13px;"><?= htmlspecialchars($l['description']??'—') ?></td>
                <td style="font-size:12px;color:var(--text-muted);"><?= date('M d, Y H:i', strtotime($l['spent_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:24px">No consumption records yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Consume Modal -->
<div class="modal-overlay" id="consumeModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Record Budget Usage</span>
            <button class="modal-close" onclick="document.getElementById('consumeModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="consume">
            <input type="hidden" name="allocation_id" id="consumeAllocId">
            <p id="consumeInfo" style="font-size:13px;color:var(--text-muted);margin-bottom:16px;"></p>
            <div class="form-group">
                <label>Amount to Spend (₱) *</label>
                <input type="number" name="amount_spent" id="consumeAmt" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <div class="form-group">
                <label>Description *</label>
                <input type="text" name="description" class="form-control" required placeholder="What was this spent on?">
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-check"></i> Confirm</button>
        </form>
    </div>
</div>

<script>
function openConsume(id, title, remaining) {
    document.getElementById('consumeAllocId').value = id;
    document.getElementById('consumeInfo').textContent = 'Allocation: ' + title + ' | Remaining: ₱' + parseFloat(remaining).toLocaleString('en-PH', {minimumFractionDigits:2});
    document.getElementById('consumeAmt').max = remaining;
    document.getElementById('consumeModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
