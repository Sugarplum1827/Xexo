<?php
require_once '../includes/auth.php';
requireRole('budget_manager', '../index.php');
$pageTitle = 'Budget Reports';

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
$stats       = $conn->query("SELECT COUNT(*) total, COALESCE(SUM(total_price),0) amount FROM purchases WHERE status='approved' $whereDate")->fetch_assoc();
$budgets     = $conn->query("SELECT * FROM budgets ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$byCategory  = $conn->query("SELECT supplier, COUNT(*) cnt, SUM(total_price) total FROM purchases WHERE status='approved' $whereDate AND supplier IS NOT NULL GROUP BY supplier ORDER BY total DESC")->fetch_all(MYSQLI_ASSOC);
$monthlyTrend= $conn->query("SELECT MONTH(purchase_date) m, SUM(total_price) total FROM purchases WHERE status='approved' AND YEAR(purchase_date)=$year GROUP BY MONTH(purchase_date) ORDER BY m")->fetch_all(MYSQLI_ASSOC);
$activeBudget= $conn->query("SELECT * FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
$budgetAmt   = $activeBudget['allocated_amount'] ?? 0;
$usedPct     = $budgetAmt > 0 ? min(100, ($stats['amount']/$budgetAmt)*100) : 0;

include '../includes/header.php';
?>
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
        <button class="btn btn-primary"><i class="fas fa-filter"></i> Generate</button>
        <a href="../includes/export_pdf.php?<?= http_build_query(array_merge($_GET, ['type'=>'budget'])) ?>" target="_blank" class="btn btn-gold"><i class="fas fa-file-pdf"></i> Export PDF</a>
    </form>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-wallet"></i></div><div><div class="stat-value"><?= formatCurrency($budgetAmt) ?></div><div class="stat-label">Allocated</div></div></div>
    <div class="stat-card"><div class="stat-icon red"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-value"><?= formatCurrency($stats['amount']) ?></div><div class="stat-label">Total Spent</div></div></div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-piggy-bank"></i></div><div><div class="stat-value"><?= formatCurrency($budgetAmt - $stats['amount']) ?></div><div class="stat-label">Remaining</div></div></div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-percent"></i></div><div><div class="stat-value"><?= number_format($usedPct,1) ?>%</div><div class="stat-label">Utilization</div></div></div>
</div>

<?php if ($activeBudget): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><span class="card-title">Budget Utilization</span></div>
    <div class="progress-bar" style="height:14px;margin-bottom:10px;">
        <div class="progress-fill <?= $usedPct>=90?'red':($usedPct>=75?'orange':'green') ?>" style="width:<?= $usedPct ?>%"></div>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-muted)">
        <span>0%</span><span><?= number_format($usedPct,1) ?>% used</span><span>100%</span>
    </div>
</div>
<?php endif; ?>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Expenses by Supplier</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Supplier</th><th>Orders</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($byCategory as $r): ?>
                <tr><td><?= htmlspecialchars($r['supplier']) ?></td><td><?= $r['cnt'] ?></td><td><strong><?= formatCurrency($r['total']) ?></strong></td></tr>
                <?php endforeach; ?>
                <?php if (empty($byCategory)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:20px">No data</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Monthly Trend (<?= $year ?>)</span></div>
        <?php
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $maxVal = max(array_column($monthlyTrend,'total') ?: [1]);
        foreach ($monthlyTrend as $mt): $pct = ($mt['total']/$maxVal)*100; ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
            <span style="width:28px;font-size:12px;color:var(--text-muted)"><?= $months[$mt['m']-1] ?></span>
            <div class="progress-bar" style="flex:1;height:8px;">
                <div class="progress-fill green" style="width:<?= $pct ?>%"></div>
            </div>
            <span style="font-size:12px;font-weight:600;width:80px;text-align:right"><?= formatCurrency($mt['total']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($monthlyTrend)): ?><p style="text-align:center;color:var(--text-muted);font-size:14px;padding:20px">No data</p><?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
