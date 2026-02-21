<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');
$pageTitle = 'Reports';

$period = $_GET['period'] ?? 'monthly';
$year   = (int)($_GET['year'] ?? date('Y'));
$month  = (int)($_GET['month'] ?? date('m'));

$whereDate = match($period) {
    'daily'     => "AND DATE(purchase_date) = CURDATE()",
    'monthly'   => "AND YEAR(purchase_date)=$year AND MONTH(purchase_date)=$month",
    'semestral' => $month <= 6
        ? "AND YEAR(purchase_date)=$year AND MONTH(purchase_date) BETWEEN 1 AND 6"
        : "AND YEAR(purchase_date)=$year AND MONTH(purchase_date) BETWEEN 7 AND 12",
    'yearly'    => "AND YEAR(purchase_date)=$year",
    default     => "AND YEAR(purchase_date)=$year AND MONTH(purchase_date)=$month",
};

$stats = $conn->query("SELECT COUNT(*) total, COALESCE(SUM(total_price),0) amount FROM purchases WHERE status='approved' $whereDate")->fetch_assoc();
$purchases = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id WHERE p.status='approved' $whereDate ORDER BY p.purchase_date DESC")->fetch_all(MYSQLI_ASSOC);
$topSuppliers = $conn->query("SELECT supplier, COUNT(*) cnt, SUM(total_price) total FROM purchases WHERE status='approved' $whereDate AND supplier IS NOT NULL GROUP BY supplier ORDER BY total DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="card" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0">
            <label>Period</label>
            <select name="period" class="form-control" style="width:160px">
                <?php foreach (['daily'=>'Daily','monthly'=>'Monthly','semestral'=>'Semestral','yearly'=>'Yearly'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $period===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0">
            <label>Year</label>
            <select name="year" class="form-control" style="width:100px">
                <?php for ($y=2024;$y<=date('Y');$y++): ?>
                <option value="<?= $y ?>" <?= $year===$y?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <?php if ($period === 'monthly'): ?>
        <div class="form-group" style="margin:0">
            <label>Month</label>
            <select name="month" class="form-control" style="width:120px">
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $month===$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        <button class="btn btn-primary"><i class="fas fa-filter"></i> Generate</button>
        <a href="../includes/export_pdf.php?<?= http_build_query(array_merge($_GET, ['type'=>'admin'])) ?>" target="_blank" class="btn btn-gold"><i class="fas fa-file-pdf"></i> Export PDF</a>
    </form>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Purchases</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-peso-sign"></i></div>
        <div><div class="stat-value"><?= formatCurrency($stats['amount']) ?></div><div class="stat-label">Total Expenses</div></div>
    </div>
</div>

<div class="grid-2" style="gap:24px;margin-bottom:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Top Suppliers</span></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Supplier</th><th>Orders</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($topSuppliers as $s): ?>
                <tr><td><?= htmlspecialchars($s['supplier']) ?></td><td><?= $s['cnt'] ?></td><td><?= formatCurrency($s['total']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($topSuppliers)): ?><tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:24px">No data</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><span class="card-title">Expense Summary</span></div>
        <div style="padding:8px 0;">
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--cream-dark);font-size:14px;">
                <span>Total Approved Expenses</span><strong><?= formatCurrency($stats['amount']) ?></strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:10px 0;font-size:14px;">
                <span>Number of Purchases</span><strong><?= $stats['total'] ?></strong>
            </div>
            <?php if ($stats['total'] > 0): ?>
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--cream-dark);font-size:14px;">
                <span>Average per Purchase</span><strong><?= formatCurrency($stats['amount']/$stats['total']) ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Purchase Records</span></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Supplier</th><th>Date</th><th>Submitted By</th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $i => $p): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($p['item_name']) ?></strong></td>
                <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                <td><?= formatCurrency($p['unit_price']) ?></td>
                <td><?= formatCurrency($p['total_price']) ?></td>
                <td><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
                <td><?= $p['purchase_date'] ?></td>
                <td><?= htmlspecialchars($p['full_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($purchases)): ?><tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px">No records found for this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
