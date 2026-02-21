<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Expense Tracking';

$period = $_GET['period'] ?? 'monthly';
$year   = (int)($_GET['year'] ?? date('Y'));
$month  = (int)($_GET['month'] ?? date('m'));

$whereDate = match($period) {
    'daily'     => "AND DATE(purchase_date)=CURDATE()",
    'monthly'   => "AND YEAR(purchase_date)=$year AND MONTH(purchase_date)=$month",
    'semestral' => $month<=6 ? "AND YEAR(purchase_date)=$year AND MONTH(purchase_date) BETWEEN 1 AND 6" : "AND YEAR(purchase_date)=$year AND MONTH(purchase_date) BETWEEN 7 AND 12",
    'yearly'    => "AND YEAR(purchase_date)=$year",
    default     => "AND YEAR(purchase_date)=$year AND MONTH(purchase_date)=$month",
};

$totalExp  = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved' $whereDate")->fetch_assoc()['s'];
$budget    = $conn->query("SELECT allocated_amount FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
$budgetAmt = $budget['allocated_amount'] ?? 0;
$remaining = $budgetAmt - $totalExp;
$expenses  = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id WHERE p.status='approved' $whereDate ORDER BY p.purchase_date DESC")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<?php if ($remaining < 0): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> OVERSPENDING: Exceeded budget by <?= formatCurrency(abs($remaining)) ?></div>
<?php elseif ($budgetAmt > 0 && ($totalExp/$budgetAmt) >= 0.75): ?>
<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> LOW BUDGET: Only <?= formatCurrency($remaining) ?> remaining.</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0"><label>Period</label>
            <select name="period" class="form-control" style="width:150px">
                <?php foreach (['daily'=>'Daily','monthly'=>'Monthly','semestral'=>'Semestral','yearly'=>'Yearly'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $period===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0"><label>Year</label>
            <select name="year" class="form-control" style="width:100px">
                <?php for ($y=2024;$y<=date('Y');$y++): ?><option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
            </select>
        </div>
        <?php if ($period==='monthly'): ?>
        <div class="form-group" style="margin:0"><label>Month</label>
            <select name="month" class="form-control" style="width:120px">
                <?php for ($m=1;$m<=12;$m++): ?><option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option><?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-wallet"></i></div><div><div class="stat-value"><?= formatCurrency($budgetAmt) ?></div><div class="stat-label">Budget</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-receipt"></i></div><div><div class="stat-value"><?= formatCurrency($totalExp) ?></div><div class="stat-label">Expenses</div></div></div>
    <div class="stat-card"><div class="stat-icon <?= $remaining>=0?'green':'red' ?>"><i class="fas fa-balance-scale"></i></div><div><div class="stat-value"><?= formatCurrency(abs($remaining)) ?></div><div class="stat-label"><?= $remaining>=0?'Remaining':'Over Budget' ?></div></div></div>
    <div class="stat-card"><div class="stat-icon forest"><i class="fas fa-list"></i></div><div><div class="stat-value"><?= count($expenses) ?></div><div class="stat-label">Transactions</div></div></div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Expense Records</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Supplier</th><th>Date</th><th>Submitted By</th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $i => $e): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($e['item_name']) ?></strong></td>
                <td><?= $e['quantity'] ?> <?= htmlspecialchars($e['unit']) ?></td>
                <td><?= formatCurrency($e['unit_price']) ?></td>
                <td><strong><?= formatCurrency($e['total_price']) ?></strong></td>
                <td><?= htmlspecialchars($e['supplier']??'—') ?></td>
                <td><?= $e['purchase_date'] ?></td>
                <td><?= htmlspecialchars($e['full_name']??'—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($expenses)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">No expenses for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
