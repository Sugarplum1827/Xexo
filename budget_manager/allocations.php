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
        $encoder_id = ($_POST['encoder_id'] !== '') ? (int)$_POST['encoder_id'] : null;
        $is_shared  = isset($_POST['is_shared']) ? 1 : 0;
        $title      = trim($_POST['allocation_title']);
        $purpose    = trim($_POST['purpose']);
        $amount     = (float)$_POST['allocated_amount'];
        $date       = date('Y-m-d');

        if ($is_shared) $encoder_id = null;

        // Check how much is still available in this specific budget
        $budget = $conn->query("SELECT * FROM budgets WHERE id=$budget_id AND approval_status='approved'")->fetch_assoc();
        if (!$budget) {
            $msg = 'Selected budget period not found or not approved.'; $msgType = 'danger';
        } else {
            // Sum allocations already approved against THIS budget only
            $alreadyAllocated = (float)$conn->query("
                SELECT COALESCE(SUM(allocated_amount), 0) AS s
                FROM budget_allocations
                WHERE budget_id = $budget_id
                  AND admin_approval_status = 'approved'
            ")->fetch_assoc()['s'];

            $budgetRemaining = $budget['allocated_amount'] - $alreadyAllocated;

            if ($amount > $budgetRemaining) {
                $msg = 'Allocation amount (₱' . number_format($amount, 2) . ') exceeds the '
                     . 'remaining unallocated balance of this budget period (₱'
                     . number_format($budgetRemaining, 2) . ').';
                $msgType = 'danger';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO budget_allocations
                        (budget_id, encoder_id, is_shared, allocation_title, purpose,
                         allocated_amount, allocation_date,
                         admin_approval_status, admin_approved_by, admin_approved_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), ?)
                ");
                $stmt->bind_param("iiissdii", $budget_id, $encoder_id, $is_shared,
                                  $title, $purpose, $amount, $date, $uid, $uid);
                $stmt->execute(); $stmt->close();

                logActivity($conn, 'CREATE_ALLOCATION',
                    "Allocated ₱$amount from budget #$budget_id: $title");
                $msg = 'Allocation created. ₱' . number_format($amount, 2)
                     . ' is now available to the encoder immediately.';
                $msgType = 'success';
            }
        }
    }
}

// Load all approved budgets with per-budget allocation stats
$budgets = $conn->query("
    SELECT b.*,
           COALESCE(SUM(CASE WHEN ba.admin_approval_status='approved' THEN ba.allocated_amount ELSE 0 END), 0) AS total_allocated,
           COALESCE(SUM(CASE WHEN ba.admin_approval_status='approved' THEN ba.amount_used ELSE 0 END), 0)      AS total_used
    FROM budgets b
    LEFT JOIN budget_allocations ba ON ba.budget_id = b.id
    WHERE b.approval_status = 'approved'
    GROUP BY b.id
    ORDER BY b.id DESC
")->fetch_all(MYSQLI_ASSOC);

// Add computed remaining per budget
foreach ($budgets as &$b) {
    $b['budget_remaining'] = $b['allocated_amount'] - $b['total_allocated'];
}
unset($b);

$activeBudgets = array_filter($budgets, fn($b) => $b['is_active'] == 1);
$activeBudgets = array_values($activeBudgets);

// All allocations with encoder + budget info
$allocations = $conn->query("
    SELECT ba.*,
           b.period_label, b.start_date, b.end_date,
           u.full_name  AS encoder_name,
           cr.full_name AS created_by_name
    FROM budget_allocations ba
    JOIN budgets b  ON ba.budget_id  = b.id
    LEFT JOIN users u  ON ba.encoder_id  = u.id
    LEFT JOIN users cr ON ba.created_by  = cr.id
    ORDER BY ba.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$encoders = $conn->query("SELECT id, full_name FROM users WHERE role='user' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Per-budget remaining tracker -->
<?php if (!empty($budgets)): ?>
<div style="margin-bottom:24px;">
    <?php foreach ($budgets as $b):
        $allocPct = $b['allocated_amount'] > 0
            ? min(100, ($b['total_allocated'] / $b['allocated_amount']) * 100) : 0;
    ?>
    <div style="padding:16px;border:1px solid var(--grey-200);border-radius:12px;margin-bottom:12px;
        <?= $b['is_active'] ? 'border-color:var(--primary);background:rgba(212,175,55,.04);' : 'opacity:.65;' ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
            <div>
                <span style="font-family:'Fraunces',serif;font-weight:700;font-size:15px;color:var(--forest);">
                    <?= htmlspecialchars($b['period_label']) ?>
                </span>
                <?php if ($b['is_active']): ?>
                <span class="badge badge-approved" style="margin-left:8px;font-size:10px;">Active</span>
                <?php endif; ?>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                    <?= $b['start_date'] ?> — <?= $b['end_date'] ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:11px;color:var(--text-muted);">Unallocated Balance</div>
                <div style="font-size:20px;font-weight:800;font-family:'Fraunces',serif;
                     color:<?= $b['budget_remaining']>0?'var(--success)':'var(--danger)' ?>;">
                    <?= formatCurrency($b['budget_remaining']) ?>
                </div>
            </div>
        </div>
        <div class="progress-bar" style="height:8px;margin-bottom:8px;">
            <div class="progress-fill <?= $allocPct>=90?'red':($allocPct>=70?'orange':'green') ?>"
                 style="width:<?= $allocPct ?>%"></div>
        </div>
        <div style="display:flex;gap:24px;font-size:13px;flex-wrap:wrap;">
            <span>Total Budget: <strong><?= formatCurrency($b['allocated_amount']) ?></strong></span>
            <span>Total Allocated: <strong style="color:var(--primary)"><?= formatCurrency($b['total_allocated']) ?></strong></span>
            <span>Total Used by Encoders: <strong style="color:var(--danger)"><?= formatCurrency($b['total_used']) ?></strong></span>
            <span>Unallocated: <strong style="color:<?= $b['budget_remaining']>=0?'var(--success)':'var(--danger)' ?>">
                <?= formatCurrency($b['budget_remaining']) ?>
            </strong></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">

    <!-- New Allocation Form -->
    <div class="card">
        <div class="card-header"><span class="card-title">Create New Allocation</span></div>
        <?php if (empty($activeBudgets)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            No active approved budget periods. Ask Admin to approve a budget first.
        </div>
        <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="create_allocation">
            <div class="form-group">
                <label>Budget Period *</label>
                <select name="budget_id" id="budgetSelect" class="form-control" required onchange="updateBudgetRemaining(this)">
                    <option value="">-- Select Budget Period --</option>
                    <?php foreach ($activeBudgets as $b): ?>
                    <option value="<?= $b['id'] ?>"
                            data-remaining="<?= $b['budget_remaining'] ?>"
                            data-label="<?= htmlspecialchars(addslashes($b['period_label'])) ?>">
                        <?= htmlspecialchars($b['period_label']) ?>
                        — Unallocated: <?= formatCurrency($b['budget_remaining']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="budgetRemainingHint" style="font-size:12px;color:var(--text-muted);margin-top:4px;display:none;">
                    Unallocated balance for this period:
                    <strong id="budgetRemainingAmt" style="color:var(--success)"></strong>
                </div>
            </div>
            <div class="form-group">
                <label>Allocation Title *</label>
                <input type="text" name="allocation_title" class="form-control" required
                       placeholder="e.g. Cooking Lab Budget">
            </div>
            <div class="form-group">
                <label>Purpose</label>
                <textarea name="purpose" class="form-control" rows="2"
                          placeholder="Describe the purpose of this allocation..."></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_shared" id="isSharedChk"
                           onchange="toggleEncoder(this)">
                    Shared allocation (accessible by <em>all</em> Encoders)
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
                <input type="number" name="allocated_amount" id="allocAmtInput"
                       class="form-control" step="0.01" min="0.01" required
                       placeholder="0.00" oninput="checkAllocAmt(this)">
                <div id="allocAmtWarn" style="display:none;font-size:12px;color:var(--danger);margin-top:4px;">
                    <i class="fas fa-exclamation-triangle"></i> Amount exceeds the unallocated balance!
                </div>
            </div>
            <p style="font-size:12px;color:var(--text-muted);margin-bottom:12px;">
                <i class="fas fa-check-circle" style="color:var(--success)"></i>
                Allocations are <strong>immediately available</strong> to the encoder — no extra Admin approval needed.
            </p>
            <button type="submit" class="btn btn-gold" style="width:100%">
                <i class="fas fa-plus"></i> Create Allocation
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Allocation History -->
    <div class="card">
        <div class="card-header"><span class="card-title">Allocation History</span></div>
        <div style="max-height:480px;overflow-y:auto;">
        <?php foreach ($allocations as $a):
            $rem = $a['allocated_amount'] - $a['amount_used'];
        ?>
        <div style="border:1px solid var(--grey-200);border-radius:10px;padding:14px;margin-bottom:10px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                <div>
                    <strong style="font-size:14px;"><?= htmlspecialchars($a['allocation_title'] ?? '—') ?></strong>
                    <?php if ($a['is_shared']): ?>
                    <span class="badge badge-pending" style="font-size:10px;margin-left:4px;">Shared</span>
                    <?php endif; ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                        <?= htmlspecialchars($a['period_label']) ?>
                        · <?= $a['start_date'] ?> – <?= $a['end_date'] ?>
                    </div>
                </div>
                <span class="badge badge-<?= $a['admin_approval_status']==='approved'?'approved':($a['admin_approval_status']==='rejected'?'rejected':'pending') ?>">
                    <?= ucfirst($a['admin_approval_status']) ?>
                </span>
            </div>
            <div style="font-size:13px;color:var(--text-muted);margin-bottom:6px;">
                <?= $a['is_shared'] ? '<i class="fas fa-users"></i> All Encoders' : htmlspecialchars($a['encoder_name'] ?? '—') ?>
            </div>
            <div style="display:flex;gap:16px;font-size:13px;">
                <span>Alloc: <strong><?= formatCurrency($a['allocated_amount']) ?></strong></span>
                <span>Used: <strong style="color:var(--danger)"><?= formatCurrency($a['amount_used']) ?></strong></span>
                <span>Left: <strong style="color:<?= $rem>=0?'var(--success)':'var(--danger)' ?>"><?= formatCurrency($rem) ?></strong></span>
            </div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:4px;"><?= $a['allocation_date'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($allocations)): ?>
        <p style="text-align:center;color:var(--text-muted);padding:24px">No allocations yet.</p>
        <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentBudgetRemaining = 0;

function updateBudgetRemaining(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const hint = document.getElementById('budgetRemainingHint');
    const amt  = document.getElementById('budgetRemainingAmt');
    if (!opt.value) {
        hint.style.display = 'none';
        currentBudgetRemaining = 0;
        return;
    }
    currentBudgetRemaining = parseFloat(opt.dataset.remaining) || 0;
    amt.textContent = '₱' + currentBudgetRemaining.toLocaleString('en-PH', {minimumFractionDigits:2});
    amt.style.color = currentBudgetRemaining > 0 ? 'var(--success)' : 'var(--danger)';
    hint.style.display = 'block';
    checkAllocAmt(document.getElementById('allocAmtInput'));
}

function checkAllocAmt(input) {
    const val  = parseFloat(input.value) || 0;
    const warn = document.getElementById('allocAmtWarn');
    if (currentBudgetRemaining > 0 && val > currentBudgetRemaining) {
        warn.style.display = 'block';
        input.style.borderColor = 'var(--danger)';
    } else {
        warn.style.display = 'none';
        input.style.borderColor = '';
    }
}

function toggleEncoder(chk) {
    document.getElementById('encoderField').style.display = chk.checked ? 'none' : 'block';
}
</script>
<?php include '../includes/footer.php'; ?>
