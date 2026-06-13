<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'Submit Purchase';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

// Load encoder's approved allocations (personal + shared)
// Uses (int) cast on id so JS and PHP comparisons are consistent
function loadAllocations($conn, $uid) {
    $rows = $conn->query("
        SELECT ba.*,
               b.period_label,
               b.start_date   AS budget_start,
               b.end_date     AS budget_end,
               u.full_name    AS created_by_name
        FROM budget_allocations ba
        JOIN budgets b ON ba.budget_id = b.id
        JOIN users u   ON ba.created_by = u.id
        WHERE ba.admin_approval_status = 'approved'
          AND (ba.encoder_id = $uid OR ba.is_shared = 1)
          AND (ba.end_datetime IS NULL OR ba.end_datetime > NOW())
        ORDER BY ba.allocation_date DESC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$r) {
        $r['id']        = (int)$r['id'];          // always int — fixes === mismatch
        $r['remaining'] = round($r['allocated_amount'] - $r['amount_used'], 2);
    }
    unset($r);
    return $rows;
}

$myAllocations  = loadAllocations($conn, $uid);
$totalAvailable = array_sum(array_column($myAllocations, 'remaining'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName = trim($_POST['item_name']);
    $qty      = (float)$_POST['quantity'];
    $unit     = trim($_POST['unit']);
    $price    = (float)$_POST['unit_price'];
    $supplier = trim($_POST['supplier']);
    $date     = $_POST['purchase_date'];
    $alloc_id = (int)$_POST['allocation_id'];   // int cast
    $total    = round($qty * $price, 2);

    // --- Validate allocation: re-query directly from DB (authoritative, avoids stale PHP array) ---
    $alloc = $conn->query("
        SELECT ba.*,
               b.period_label,
               b.start_date AS budget_start,
               b.end_date   AS budget_end
        FROM budget_allocations ba
        JOIN budgets b ON ba.budget_id = b.id
        WHERE ba.id = $alloc_id
          AND ba.admin_approval_status = 'approved'
          AND (ba.encoder_id = $uid OR ba.is_shared = 1)
          AND (ba.end_datetime IS NULL OR ba.end_datetime > NOW())
        LIMIT 1
    ")->fetch_assoc();

    // Check expiry separately — query above already excludes expired ones via AND end_datetime > NOW()
    // so $alloc = null could mean either not found OR expired. Re-check to give a clear message.
    $allocExpired = null;
    if (!$alloc) {
        $allocExpired = $conn->query("
            SELECT ba.end_datetime, ba.allocation_title
            FROM budget_allocations ba
            WHERE ba.id = $alloc_id
              AND ba.admin_approval_status = 'approved'
              AND (ba.encoder_id = $uid OR ba.is_shared = 1)
              AND ba.end_datetime IS NOT NULL
              AND ba.end_datetime <= NOW()
            LIMIT 1
        ")->fetch_assoc();
    }

    if (!$alloc && $allocExpired) {
        $msg = 'The allocation "' . htmlspecialchars($allocExpired['allocation_title'] ?? '')
             . '" expired on ' . date('M d, Y h:i A', strtotime($allocExpired['end_datetime']))
             . '. You can no longer make purchases against it.';
        $msgType = 'danger';
    } elseif (!$alloc) {
        $msg = 'Invalid or unauthorized budget allocation selected. Please refresh the page and try again.';
        $msgType = 'danger';
    } elseif ($total <= 0) {
        $msg = 'Purchase total must be greater than zero.';
        $msgType = 'danger';
    } else {
        $live_remaining = round($alloc['allocated_amount'] - $alloc['amount_used'], 2);
        if ($total > $live_remaining) {
            $msg = 'Insufficient budget in the selected allocation. '
                 . 'Available: ' . formatCurrency($live_remaining)
                 . ', Purchase total: ' . formatCurrency($total) . '.';
            $msgType = 'danger';
        }
    }

    // --- Handle receipt upload ---
    $receiptPath = null;
    if ($msgType !== 'danger' && !empty($_FILES['receipt']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'gif'];
        if (in_array($ext, $allowed)) {
            $fname = uniqid('receipt_') . '.' . $ext;
            $dest  = '../uploads/receipts/' . $fname;
            if (!is_dir('../uploads/receipts/')) mkdir('../uploads/receipts/', 0775, true);
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
                $receiptPath = 'uploads/receipts/' . $fname;
            }
        } else {
            $msg = 'Invalid file type. Allowed: JPG, PNG, PDF.';
            $msgType = 'danger';
        }
    }

    // --- Insert purchase and reserve budget ---
    if ($msgType !== 'danger') {
        $rpQ   = $receiptPath ? "'" . $conn->real_escape_string($receiptPath) . "'" : 'NULL';
        $supp  = $conn->real_escape_string($supplier);
        $iname = $conn->real_escape_string($itemName);
        $iunit = $conn->real_escape_string($unit);

        $conn->query("
            INSERT INTO purchases
                (item_name, quantity, unit, unit_price, supplier, purchase_date,
                 receipt_path, status, submitted_by, budget_id, allocation_id, created_at)
            VALUES
                ('$iname', $qty, '$iunit', $price, '$supp', '$date',
                 $rpQ, 'pending', $uid, {$alloc['budget_id']}, $alloc_id, NOW())
        ");
        $newPid = $conn->insert_id;

        // Reserve the amount so the encoder cannot double-spend while pending
        $conn->query("
            UPDATE budget_allocations
            SET amount_used = amount_used + $total
            WHERE id = $alloc_id
        ");

        logActivity($conn, 'SUBMIT_PURCHASE',
            "Purchase #$newPid: $itemName x$qty @ ₱$price = ₱$total charged to allocation #$alloc_id (budget #{$alloc['budget_id']})");

        $msg = 'Purchase submitted! ₱' . number_format($total, 2)
             . ' reserved from "' . htmlspecialchars($alloc['allocation_title'] ?? 'Allocation')
             . '". Awaiting admin review.';
        $msgType = 'success';

        // Reload after reservation
        $myAllocations  = loadAllocations($conn, $uid);
        $totalAvailable = array_sum(array_column($myAllocations, 'remaining'));
    }
}

$inventoryItems = $conn->query("SELECT * FROM inventory ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType==='success'?'check-circle':($msgType==='warning'?'exclamation-triangle':'exclamation-circle') ?>"></i>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<?php if (empty($myAllocations) || $totalAvailable <= 0): ?>
<div class="alert alert-danger" style="margin-bottom:20px;">
    <i class="fas fa-lock"></i>
    <strong>No approved budget available.</strong>
    You need an approved budget allocation before submitting a purchase.
    <a href="budget_requests.php" style="color:var(--danger);font-weight:700;margin-left:8px;">
        Request a Budget →
    </a>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;">

    <!-- LEFT: Form -->
    <div class="card">
        <div class="card-header"><span class="card-title">New Purchase Submission</span></div>

        <?php if (empty($myAllocations) || $totalAvailable <= 0): ?>
        <div style="text-align:center;padding:40px;color:var(--text-muted);">
            <i class="fas fa-wallet" style="font-size:40px;margin-bottom:12px;display:block;color:var(--danger);"></i>
            <strong>No available budget to make a purchase.</strong>
            <p style="margin-top:8px;font-size:13px;">Request a budget from the Budget Manager first.</p>
            <a href="budget_requests.php" class="btn btn-gold" style="margin-top:12px;">
                <i class="fas fa-hand-holding-usd"></i> Request Budget
            </a>
        </div>

        <?php else: ?>

        <!-- Budget allocation selector -->
        <div style="margin-bottom:20px;padding:14px;background:var(--cream);border-radius:10px;border:1px solid var(--cream-dark);">
            <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:var(--forest);">
                <i class="fas fa-wallet"></i> Select Budget Allocation *
            </div>
            <select id="allocSelect" class="form-control" onchange="updateBudgetInfo(this)">
                <option value="">-- Choose an allocation --</option>
                <?php foreach ($myAllocations as $a): ?>
                <?php if ($a['remaining'] > 0): ?>
                <option value="<?= $a['id'] ?>"
                    data-remaining="<?= $a['remaining'] ?>"
                    data-title="<?= htmlspecialchars(addslashes($a['allocation_title'] ?? 'Allocation')) ?>"
                    data-period="<?= htmlspecialchars($a['period_label']) ?>"
                    data-start="<?= $a['budget_start'] ?>"
                    data-end="<?= $a['budget_end'] ?>"
                    data-enddatetime="<?= $a['end_datetime'] ?? '' ?>"
                    data-shared="<?= $a['is_shared'] ? 1 : 0 ?>">
                    <?= htmlspecialchars($a['allocation_title'] ?? 'Allocation') ?>
                    <?= $a['is_shared'] ? ' [Shared]' : '' ?>
                    — <?= formatCurrency($a['remaining']) ?> remaining
                    (<?= htmlspecialchars($a['period_label']) ?>)
                </option>
                <?php endif; ?>
                <?php endforeach; ?>
            </select>

            <!-- Live budget info -->
            <div id="budgetInfoBox" style="display:none;margin-top:14px;">
                <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;margin-bottom:8px;">
                    <div>
                        <span style="color:var(--text-muted)">Period:</span>
                        <strong id="biPeriod">—</strong>
                    </div>
                    <div id="biDeadlineWrap" style="display:none;">
                        <span style="color:var(--text-muted)">Deadline:</span>
                        <strong id="biDeadline" style="color:var(--danger)">—</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted)">Available:</span>
                        <strong id="biRemaining" style="color:var(--success)">—</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted)">This Purchase:</span>
                        <strong id="biTotal" style="color:var(--primary)">₱0.00</strong>
                    </div>
                    <div>
                        <span style="color:var(--text-muted)">After:</span>
                        <strong id="biAfter">—</strong>
                    </div>
                </div>
                <div class="progress-bar" style="height:8px;">
                    <div class="progress-fill green" id="biBar" style="width:0%;transition:width .3s,background .3s;"></div>
                </div>
                <div id="biWarning" style="display:none;margin-top:8px;padding:8px 12px;
                     background:rgba(185,28,28,.08);border-radius:8px;
                     color:var(--danger);font-size:13px;font-weight:600;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Purchase total exceeds your available allocation balance!
                </div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="purchaseForm">
            <!-- Hidden: set by the selector above, not by the <select> itself to avoid
                 any accidental value mismatch when the form posts -->
            <input type="hidden" name="allocation_id" id="allocIdInput" value="">

            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" id="itemNameInput"
                       class="form-control" required
                       placeholder="e.g. All-Purpose Flour"
                       oninput="checkInventory(this.value)">
            </div>

            <div id="stockHint" style="display:none;padding:10px 14px;background:var(--cream);
                 border-radius:8px;margin-bottom:12px;font-size:13px;border:1px solid var(--cream-dark);">
                <i class="fas fa-info-circle" style="color:var(--forest)"></i>
                <span id="stockHintText"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" id="qtyInput"
                           class="form-control" required step="0.01" min="0.01"
                           placeholder="0.00" oninput="recalcTotal()">
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <input type="text" name="unit" id="unitInput"
                           class="form-control" required placeholder="kg, pcs, L…">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Unit Price (₱) *</label>
                    <input type="number" name="unit_price" id="priceInput"
                           class="form-control" required step="0.01" min="0.01"
                           placeholder="0.00" oninput="recalcTotal()">
                </div>
                <div class="form-group">
                    <label>Total Amount</label>
                    <div id="totalDisplay" style="
                        background: var(--cream);
                        border: 2px solid var(--primary);
                        border-radius: 10px;
                        padding: 10px 16px;
                        font-size: 22px;
                        font-weight: 800;
                        font-family: 'Fraunces', serif;
                        color: var(--forest);
                        text-align: right;
                        letter-spacing: -0.02em;
                        transition: border-color .2s, color .2s;
                    ">₱0.00</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Purchase Date *</label>
                    <input type="date" name="purchase_date" class="form-control"
                           required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" class="form-control"
                           placeholder="Supplier name or store">
                </div>
            </div>

            <div class="form-group">
                <label>Receipt / Invoice (Image or PDF)</label>
                <input type="file" name="receipt" class="form-control"
                       accept=".jpg,.jpeg,.png,.pdf,.gif">
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                    Max 5MB · Allowed: JPG, PNG, PDF
                </div>
            </div>

            <button type="submit" class="btn btn-gold" style="width:100%" id="submitBtn" disabled>
                <i class="fas fa-paper-plane"></i> Submit Purchase
            </button>
            <p id="submitHint" style="font-size:12px;color:var(--text-muted);text-align:center;margin-top:8px;">
                Select an allocation above to enable this button.
            </p>
        </form>

        <?php endif; ?>
    </div>

    <!-- RIGHT: Budget summary + inventory reference -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <div class="card">
            <div class="card-header">
                <span class="card-title">My Budget Allocations</span>
                <a href="my_budget.php" class="btn btn-sm btn-outline">Full View</a>
            </div>
            <?php if (empty($myAllocations)): ?>
            <p style="text-align:center;color:var(--text-muted);padding:20px;">No allocations yet.</p>
            <?php else: ?>
            <?php foreach ($myAllocations as $a):
                $pct = $a['allocated_amount'] > 0
                    ? min(100, ($a['amount_used'] / $a['allocated_amount']) * 100) : 0;
            ?>
            <div style="margin-bottom:12px;padding:12px;border:1px solid var(--grey-200);border-radius:10px;
                 <?= $a['remaining'] <= 0 ? 'opacity:.5;' : '' ?>">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-weight:600;font-size:13px;">
                        <?= htmlspecialchars($a['allocation_title'] ?? 'Allocation') ?>
                    </span>
                    <?php if ($a['is_shared']): ?>
                    <span class="badge badge-pending" style="font-size:10px;">Shared</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">
                    <?= htmlspecialchars($a['period_label']) ?>
                    · <?= $a['budget_start'] ?> to <?= $a['budget_end'] ?>
                </div>
                <div class="progress-bar" style="height:6px;margin-bottom:6px;">
                    <div class="progress-fill <?= $pct>=90?'red':($pct>=70?'orange':'green') ?>"
                         style="width:<?= $pct ?>%"></div>
                </div>
                <div style="display:flex;gap:12px;font-size:12px;color:var(--text-muted);">
                    <span>Allocated: <strong style="color:var(--text)"><?= formatCurrency($a['allocated_amount']) ?></strong></span>
                    <span>Used: <strong style="color:var(--danger)"><?= formatCurrency($a['amount_used']) ?></strong></span>
                    <span>Left: <strong style="color:<?= $a['remaining']>0?'var(--success)':'var(--danger)' ?>">
                        <?= formatCurrency($a['remaining']) ?>
                    </strong></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Inventory Stock Reference</span></div>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
                Check existing stock before purchasing.
            </p>
            <div class="table-wrap" style="max-height:280px;overflow-y:auto;">
                <table>
                    <thead><tr><th>Item</th><th>Stock</th><th>Unit</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($inventoryItems as $item): ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>"
                        data-unit="<?= htmlspecialchars($item['unit']) ?>">
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td style="font-weight:700;color:<?= $item['current_stock']<=$item['minimum_stock']?'var(--danger)':'inherit' ?>">
                            <?= $item['current_stock'] ?>
                        </td>
                        <td><?= htmlspecialchars($item['unit']) ?></td>
                        <td>
                            <span class="badge badge-<?= $item['current_stock']<=0?'rejected':($item['current_stock']<=$item['minimum_stock']?'pending':'ok') ?>">
                                <?= $item['current_stock']<=0?'Out':($item['current_stock']<=$item['minimum_stock']?'Low':'OK') ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
const inventory = <?= json_encode(array_map(fn($i) => [
    'name'  => strtolower($i['item_name']),
    'stock' => $i['current_stock'],
    'unit'  => $i['unit'],
    'min'   => $i['minimum_stock']
], $inventoryItems)) ?>;

let currentRemaining = 0;

function fmtCur(n) {
    return '₱' + parseFloat(n).toLocaleString('en-PH', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
    });
}

function updateBudgetInfo(sel) {
    const opt        = sel.options[sel.selectedIndex];
    const box        = document.getElementById('budgetInfoBox');
    const allocInput = document.getElementById('allocIdInput');
    const hint       = document.getElementById('submitHint');

    if (!opt.value) {
        box.style.display    = 'none';
        allocInput.value     = '';
        currentRemaining     = 0;
        setSubmitState(false, 'Select an allocation above to enable this button.');
        recalcTotal();
        return;
    }

    // Set the hidden input to the selected allocation id
    allocInput.value = opt.value;
    currentRemaining = parseFloat(opt.dataset.remaining) || 0;

    document.getElementById('biPeriod').textContent    = opt.dataset.period + ' (' + opt.dataset.start + ' – ' + opt.dataset.end + ')';
    document.getElementById('biRemaining').textContent = fmtCur(currentRemaining);

    const deadlineWrap = document.getElementById('biDeadlineWrap');
    const deadlineEl   = document.getElementById('biDeadline');
    if (opt.dataset.enddatetime) {
        const dl = new Date(opt.dataset.enddatetime.replace(' ', 'T'));
        deadlineEl.textContent = dl.toLocaleString('en-PH', {month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'});
        deadlineWrap.style.display = '';
    } else {
        deadlineWrap.style.display = 'none';
    }

    box.style.display = 'block';

    recalcTotal();
}

function recalcTotal() {
    const qty   = parseFloat(document.getElementById('qtyInput')?.value)   || 0;
    const price = parseFloat(document.getElementById('priceInput')?.value) || 0;
    const total = Math.round(qty * price * 100) / 100;

    // Big total display
    const display    = document.getElementById('totalDisplay');
    const overBudget = currentRemaining > 0 && total > currentRemaining;
    display.textContent      = fmtCur(total);
    display.style.borderColor = overBudget ? 'var(--danger)' : 'var(--primary)';
    display.style.color       = overBudget ? 'var(--danger)' : 'var(--forest)';

    // Live bar
    if (currentRemaining > 0) {
        const after = Math.round((currentRemaining - total) * 100) / 100;
        const pct   = Math.min(100, (total / currentRemaining) * 100);

        document.getElementById('biTotal').textContent = fmtCur(total);
        document.getElementById('biAfter').textContent = fmtCur(after);
        document.getElementById('biAfter').style.color = after >= 0 ? 'var(--success)' : 'var(--danger)';

        const bar = document.getElementById('biBar');
        bar.style.width = pct + '%';
        bar.className   = 'progress-fill ' + (pct >= 100 ? 'red' : pct >= 75 ? 'orange' : 'green');

        document.getElementById('biWarning').style.display = overBudget ? 'block' : 'none';
    }

    const allocChosen = !!document.getElementById('allocIdInput').value;
    if (!allocChosen) {
        setSubmitState(false, 'Select an allocation above to enable this button.');
    } else if (overBudget) {
        setSubmitState(false, 'Total exceeds available allocation balance.');
    } else if (total <= 0) {
        setSubmitState(false, 'Enter a valid quantity and unit price.');
    } else {
        setSubmitState(true, '');
    }
}

function setSubmitState(enabled, hint) {
    const btn  = document.getElementById('submitBtn');
    const htxt = document.getElementById('submitHint');
    if (btn)  btn.disabled = !enabled;
    if (htxt) htxt.textContent = hint;
}

function checkInventory(val) {
    const hint      = document.getElementById('stockHint');
    const hintText  = document.getElementById('stockHintText');
    const unitInput = document.getElementById('unitInput');
    const lower     = val.toLowerCase().trim();
    const match     = inventory.find(i => i.name.includes(lower) || lower.includes(i.name));
    if (match && lower.length >= 3) {
        hintText.textContent = 'Existing stock: ' + match.stock + ' ' + match.unit + ' (minimum: ' + match.min + ').';
        if (!unitInput.value) unitInput.value = match.unit;
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}

// Hard guard on submit
document.getElementById('purchaseForm')?.addEventListener('submit', function(e) {
    const allocId = document.getElementById('allocIdInput').value;
    if (!allocId) {
        e.preventDefault();
        alert('Please select a budget allocation before submitting.');
        document.getElementById('allocSelect').focus();
        return;
    }
    const qty   = parseFloat(document.getElementById('qtyInput').value)   || 0;
    const price = parseFloat(document.getElementById('priceInput').value) || 0;
    const total = Math.round(qty * price * 100) / 100;
    if (currentRemaining > 0 && total > currentRemaining) {
        e.preventDefault();
        alert('Purchase total (' + fmtCur(total) + ') exceeds your available balance (' + fmtCur(currentRemaining) + ').');
    }
});
</script>
<?php include '../includes/footer.php'; ?>
