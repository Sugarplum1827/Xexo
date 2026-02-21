<?php
/**
 * PDF Export – CIT Food Trades
 * Generates a clean printable/saveable PDF report page.
 * Called with GET params: type (admin|budget), period, year, month
 */
require_once __DIR__ . '/auth.php';
requireLogin('../index.php');

$type   = $_GET['type']   ?? 'admin';
$period = $_GET['period'] ?? 'monthly';
$year   = (int)($_GET['year']  ?? date('Y'));
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

$periodLabel = match($period) {
    'daily'     => 'Daily – ' . date('F j, Y'),
    'monthly'   => date('F Y', mktime(0,0,0,$month,1,$year)),
    'semestral' => ($month <= 6 ? '1st' : '2nd') . " Semester $year",
    'yearly'    => "Full Year $year",
    default     => ucfirst($period),
};

// Fetch data
$stats        = $conn->query("SELECT COUNT(*) total, COALESCE(SUM(total_price),0) amount FROM purchases WHERE status='approved' $whereDate")->fetch_assoc();
$purchases    = $conn->query("SELECT p.*, u.full_name FROM purchases p LEFT JOIN users u ON p.submitted_by=u.id WHERE p.status='approved' $whereDate ORDER BY p.purchase_date ASC")->fetch_all(MYSQLI_ASSOC);
$topSuppliers = $conn->query("SELECT supplier, COUNT(*) cnt, SUM(total_price) total FROM purchases WHERE status='approved' $whereDate AND supplier IS NOT NULL GROUP BY supplier ORDER BY total DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
$activeBudget = $conn->query("SELECT * FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
$budgetAmt    = (float)($activeBudget['allocated_amount'] ?? 0);
$remaining    = $budgetAmt - (float)$stats['amount'];
$usedPct      = $budgetAmt > 0 ? min(100, ($stats['amount'] / $budgetAmt) * 100) : 0;

// Inventory report data (for inventory manager type)
$inventoryItems = [];
if ($type === 'inventory') {
    $inventoryItems = $conn->query("SELECT * FROM inventory ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
}

$generatedBy = $_SESSION['full_name'];
$generatedAt = date('F j, Y \a\t h:i A');
$avg = $stats['total'] > 0 ? $stats['amount'] / $stats['total'] : 0;

// Monthly trend for budget report
$monthlyTrend = $conn->query("SELECT MONTH(purchase_date) m, SUM(total_price) total FROM purchases WHERE status='approved' AND YEAR(purchase_date)=$year GROUP BY MONTH(purchase_date) ORDER BY m")->fetch_all(MYSQLI_ASSOC);
$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CIT Food Trades – <?= htmlspecialchars($periodLabel) ?> Report</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;600;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ===== SCREEN STYLES ===== */
:root {
    --red-morning: #b33333ff;
    --forest-mid: #2d5a3d;
    --gold: #c8a84b;
    --cream: #f5f0e8;
    --cream-dark: #ede5d0;
    --danger: #c0392b;
    --success: #27ae60;
    --warning: #e67e22;
    --text: #1a1a1a;
    --text-muted: #6b7280;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'DM Sans', sans-serif;
    background: #e8e8e8;
    color: var(--text);
    font-size: 13px;
}

.toolbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: var(--red-morning);
    padding: 12px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,0.3);
}
.toolbar-title { color: var(--cream); font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.toolbar-title img{
    width:150px; height:50px; object-fit:contain;}
.toolbar-title span { color: var(--gold); }
.toolbar-actions { display: flex; gap: 10px; }
.btn-pdf {
    padding: 9px 20px;
    background: var(--gold);
    color: var(--red-morning);
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: background 0.2s;
}
.btn-pdf:hover { background: #e0c46a; }
.btn-back {
    padding: 9px 20px;
    background: rgba(255,255,255,0.1);
    color: var(--cream);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 7px;
}
.btn-back:hover { background: rgba(255,255,255,0.18); }

.page-wrap {
    margin-top: 60px;
    padding: 32px;
    display: flex;
    justify-content: center;
}

/* ===== PDF PAGE ===== */
.pdf-page {
    background: white;
    width: 210mm;
    min-height: 297mm;
    padding: 18mm 18mm 20mm;
    box-shadow: 0 4px 32px rgba(0,0,0,0.18);
    border-radius: 4px;
}

/* HEADER */
.pdf-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 14px;
    border-bottom: 3px solid var(--red-morning);
    margin-bottom: 20px;
}
.pdf-header-left .school { font-size: 9px; text-transform: uppercase; letter-spacing: 0.12em; color: var(--text-muted); margin-bottom: 4px; }
.pdf-header-left h1 { font-family: 'Fraunces', serif; font-size: 22px; font-weight: 800; color: var(--red-morning); line-height: 1.1; }
.pdf-header-left .subtitle { font-size: 11px; color: var(--text-muted); margin-top: 3px; }
.pdf-header-right { text-align: right; }
.pdf-logo-box {
    width: 200px; height: 75px;
    background: var(--red-morning);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    margin-left: auto;
    margin-bottom: 6px;
}
.pdf-logo-box img{
    width:100%;
    height:100%;
    object-fit:contain;
}
.pdf-header-right .report-type { font-size: 15px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-muted); }

/* META ROW */
.meta-row {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    background: var(--cream);
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--cream-dark);
}
.meta-item {
    flex: 1;
    padding: 10px 14px;
    border-right: 1px solid var(--cream-dark);
}
.meta-item:last-child { border-right: none; }
.meta-item .meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 3px; }
.meta-item .meta-value { font-size: 12px; font-weight: 600; color: var(--red-morning); }

/* SECTION TITLE */
.section-title {
    font-family: 'Fraunces', serif;
    font-size: 13px;
    font-weight: 700;
    color: var(--red-morning);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    border-left: 4px solid var(--gold);
    padding-left: 10px;
    margin-bottom: 12px;
    margin-top: 20px;
}

/* SUMMARY STATS */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 6px;
}
.summary-box {
    background: var(--cream);
    border: 1px solid var(--cream-dark);
    border-radius: 8px;
    padding: 12px 14px;
    border-top: 3px solid var(--red-morning);
}
.summary-box.gold { border-top-color: var(--gold); }
.summary-box.red  { border-top-color: var(--danger); }
.summary-box.green{ border-top-color: var(--success); }
.summary-box .s-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); margin-bottom: 5px; }
.summary-box .s-value { font-family: 'Fraunces', serif; font-size: 18px; font-weight: 800; color: var(--text); line-height: 1; }

/* BUDGET BAR */
.budget-section {
    background: var(--cream);
    border: 1px solid var(--cream-dark);
    border-radius: 8px;
    padding: 14px 16px;
    margin-bottom: 6px;
}
.budget-section .budget-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
.budget-section .budget-label { font-size: 11px; color: var(--text-muted); }
.budget-section .budget-val { font-size: 11px; font-weight: 700; }
.bar-track { height: 10px; background: var(--cream-dark); border-radius: 99px; overflow: hidden; margin-bottom: 6px; }
.bar-fill { height: 100%; border-radius: 99px; }
.bar-fill.green { background: var(--success); }
.bar-fill.orange { background: var(--warning); }
.bar-fill.red    { background: var(--danger); }
.budget-pct-row { display: flex; justify-content: space-between; font-size: 9px; color: var(--text-muted); }

/* TABLES */
.pdf-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.pdf-table thead tr { background: var(--red-morning); color: white; }
.pdf-table thead th { padding: 8px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
.pdf-table tbody td { padding: 7px 10px; border-bottom: 1px solid var(--cream-dark); vertical-align: middle; }
.pdf-table tbody tr:nth-child(even) { background: #fafafa; }
.pdf-table tbody tr:last-child td { border-bottom: none; }
.pdf-table tfoot td { padding: 8px 10px; background: var(--cream); font-weight: 700; font-size: 11px; border-top: 2px solid var(--red-morning); }
.text-right { text-align: right; }
.text-center { text-align: center; }
.fw-bold { font-weight: 700; }
.text-danger { color: var(--danger); }
.text-success { color: var(--success); }
.text-muted { color: var(--text-muted); }

/* TWO COLUMN LAYOUT */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* SUPPLIER TABLE */
.supplier-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.supplier-table th { background: var(--cream-dark); padding: 7px 10px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); }
.supplier-table td { padding: 7px 10px; border-bottom: 1px solid var(--cream-dark); }

/* SIGNATURE SECTION */
.signature-section {
    margin-top: 32px;
    padding-top: 20px;
    border-top: 1px solid var(--cream-dark);
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 24px;
}
.sig-box { text-align: center; }
.sig-line { border-bottom: 1px solid var(--text); margin-bottom: 6px; height: 36px; }
.sig-label { font-size: 10px; color: var(--text-muted); }
.sig-name  { font-size: 11px; font-weight: 700; color: var(--red-morning); }

/* FOOTER */
.pdf-footer {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid var(--cream-dark);
    display: flex;
    justify-content: space-between;
    font-size: 9px;
    color: var(--text-muted);
}

/* STATUS BADGE */
.status-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 99px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
}
.status-ok  { background: rgba(39,174,96,0.12); color: #1e8449; }
.status-low { background: rgba(230,126,34,0.12); color: #a04000; }
.status-out { background: rgba(192,57,43,0.10); color: #922b21; }

/* ===== PRINT STYLES ===== */
@media print {
    body { background: white !important; }
    .toolbar { display: none !important; }
    .page-wrap { margin: 0 !important; padding: 0 !important; background: white !important; }
    .pdf-page {
        width: 100% !important;
        min-height: auto !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 10mm 12mm 14mm !important;
    }
    @page { size: A4 portrait; margin: 0; }
}
</style>
</head>
<body>

<!-- TOOLBAR (hidden on print) -->
<div class="toolbar">
    <div class="toolbar-title">
        <img src="/image/CIT.png" alt="Logo">CIT Food Trades &nbsp;›&nbsp; <span><?= htmlspecialchars($periodLabel) ?> Report</span>
    </div>
        <button class="btn-pdf" onclick="downloadPDF()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download PDF
        </button>
    </div>
</div>

<div class="page-wrap">
<div class="pdf-page" id="pdfPage">

    <!-- HEADER -->
    <div class="pdf-header">
        <div class="pdf-header-left">
            <div class="school">Eulogiou "Amang" Rodriguez Institute of Technology and Science</div>
            <h1>CIT Food Trades<br>Management System</h1>
            <div class="subtitle">Budgeting &amp; Inventory Report</div>
        </div>
        <div class="pdf-header-right">
            <div class="pdf-logo-box"><img src="/image/CIT.png" alt="Logo"></div>
            <div class="report-type"><?= ucfirst($type) ?> Report</div>
        </div>
    </div>

    <!-- META -->
    <div class="meta-row">
        <div class="meta-item">
            <div class="meta-label">Report Period</div>
            <div class="meta-value"><?= htmlspecialchars($periodLabel) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Period Type</div>
            <div class="meta-value"><?= ucfirst($period) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Generated By</div>
            <div class="meta-value"><?= htmlspecialchars($generatedBy) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Generated On</div>
            <div class="meta-value"><?= $generatedAt ?></div>
        </div>
    </div>

    <!-- SUMMARY STATS -->
    <div class="section-title">Summary Overview</div>
    <div class="summary-grid">
        <div class="summary-box gold">
            <div class="s-label">Allocated Budget</div>
            <div class="s-value"><?= formatCurrency($budgetAmt) ?></div>
        </div>
        <div class="summary-box red">
            <div class="s-label">Total Expenses</div>
            <div class="s-value"><?= formatCurrency($stats['amount']) ?></div>
        </div>
        <div class="summary-box <?= $remaining >= 0 ? 'green' : 'red' ?>">
            <div class="s-label"><?= $remaining >= 0 ? 'Remaining Budget' : 'Over Budget By' ?></div>
            <div class="s-value"><?= formatCurrency(abs($remaining)) ?></div>
        </div>
        <div class="summary-box">
            <div class="s-label">Total Purchases</div>
            <div class="s-value"><?= $stats['total'] ?></div>
        </div>
    </div>

    <!-- BUDGET BAR -->
    <?php if ($budgetAmt > 0): ?>
    <div class="budget-section" style="margin-top:10px;">
        <div class="budget-row">
            <span class="budget-label">Budget Utilization</span>
            <span class="budget-val <?= $usedPct>=90?'text-danger':($usedPct>=75?'':'text-success') ?>"><?= number_format($usedPct, 1) ?>% used</span>
        </div>
        <div class="bar-track">
            <div class="bar-fill <?= $usedPct>=90?'red':($usedPct>=75?'orange':'green') ?>" style="width:<?= $usedPct ?>%"></div>
        </div>
        <div class="budget-pct-row">
            <span>₱0</span>
            <span><?= formatCurrency($stats['amount']) ?> spent</span>
            <span><?= formatCurrency($budgetAmt) ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- TWO COLUMN: Suppliers + Expense Summary -->
    <div class="two-col" style="margin-top:4px;">
        <div>
            <div class="section-title">Top Suppliers</div>
            <?php if (!empty($topSuppliers)): ?>
            <table class="supplier-table">
                <thead><tr><th>#</th><th>Supplier</th><th>Orders</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                <?php foreach ($topSuppliers as $i => $s): ?>
                <tr>
                    <td class="text-muted"><?= $i+1 ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($s['supplier']) ?></td>
                    <td><?= $s['cnt'] ?></td>
                    <td class="text-right"><?= formatCurrency($s['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted" style="font-size:11px;padding:12px 0">No supplier data for this period.</p>
            <?php endif; ?>
        </div>
        <div>
            <div class="section-title">Expense Summary</div>
            <table class="supplier-table">
                <tbody>
                <tr><td>Total Approved Expenses</td><td class="text-right fw-bold"><?= formatCurrency($stats['amount']) ?></td></tr>
                <tr><td>Number of Transactions</td><td class="text-right fw-bold"><?= $stats['total'] ?></td></tr>
                <?php if ($stats['total'] > 0): ?>
                <tr><td>Average per Purchase</td><td class="text-right fw-bold"><?= formatCurrency($avg) ?></td></tr>
                <?php endif; ?>
                <?php if ($budgetAmt > 0): ?>
                <tr><td>Budget Allocated</td><td class="text-right fw-bold"><?= formatCurrency($budgetAmt) ?></td></tr>
                <tr><td><?= $remaining >= 0 ? 'Remaining' : 'Over Budget' ?></td>
                    <td class="text-right fw-bold <?= $remaining >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatCurrency(abs($remaining)) ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (!empty($monthlyTrend)): ?>
            <div style="margin-top:14px;">
                <div style="font-size:10px;font-weight:700;color:var(--red-morning);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.06em;">Monthly Trend (<?= $year ?>)</div>
                <?php
                $maxVal = max(array_column($monthlyTrend, 'total') ?: [1]);
                foreach ($monthlyTrend as $mt):
                    $pct = ($mt['total'] / $maxVal) * 100;
                ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;">
                    <span style="width:24px;font-size:9px;color:var(--text-muted)"><?= substr($months[$mt['m']-1], 0, 3) ?></span>
                    <div class="bar-track" style="flex:1;height:6px;">
                        <div class="bar-fill green" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span style="font-size:9px;font-weight:700;width:70px;text-align:right"><?= formatCurrency($mt['total']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PURCHASE RECORDS TABLE -->
    <div class="section-title" style="margin-top:22px;">Purchase Records</div>
    <?php if (!empty($purchases)): ?>
    <table class="pdf-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th class="text-right">Total</th>
                <th>Supplier</th>
                <th>Date</th>
                <th>Submitted By</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($purchases as $i => $p): ?>
        <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td class="fw-bold"><?= htmlspecialchars($p['item_name']) ?></td>
            <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
            <td><?= formatCurrency($p['unit_price']) ?></td>
            <td class="text-right fw-bold"><?= formatCurrency($p['total_price']) ?></td>
            <td><?= htmlspecialchars($p['supplier'] ?? '—') ?></td>
            <td style="white-space:nowrap"><?= $p['purchase_date'] ?></td>
            <td><?= htmlspecialchars($p['full_name'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right">TOTAL</td>
                <td class="text-right"><?= formatCurrency($stats['amount']) ?></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
    <?php else: ?>
    <div style="text-align:center;padding:24px;color:var(--text-muted);font-size:12px;background:var(--cream);border-radius:8px;">
        No approved purchase records found for this period.
    </div>
    <?php endif; ?>

    <?php if ($type === 'inventory' && !empty($inventoryItems)): ?>
    <!-- INVENTORY TABLE -->
    <div class="section-title" style="margin-top:22px;">Inventory Stock Report</div>
    <table class="pdf-table">
        <thead>
            <tr>
                <th>#</th><th>Item Name</th><th>Category</th><th>Unit</th>
                <th class="text-right">Stock</th><th class="text-right">Min Stock</th>
                <th class="text-right">Unit Cost</th><th class="text-right">Total Value</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($inventoryItems as $i => $item): ?>
        <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td class="fw-bold"><?= htmlspecialchars($item['item_name']) ?></td>
            <td><?= htmlspecialchars($item['category'] ?? '—') ?></td>
            <td><?= htmlspecialchars($item['unit']) ?></td>
            <td class="text-right"><?= $item['current_stock'] ?></td>
            <td class="text-right"><?= $item['minimum_stock'] ?></td>
            <td class="text-right"><?= formatCurrency($item['unit_cost']) ?></td>
            <td class="text-right fw-bold"><?= formatCurrency($item['current_stock'] * $item['unit_cost']) ?></td>
            <td>
                <?php if ($item['current_stock'] <= 0): ?>
                <span class="status-badge status-out">Out</span>
                <?php elseif ($item['current_stock'] <= $item['minimum_stock']): ?>
                <span class="status-badge status-low">Low</span>
                <?php else: ?>
                <span class="status-badge status-ok">OK</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- SIGNATURE SECTION -->
    <div class="signature-section">
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-name">Prepared By</div>
            <div class="sig-label"><?= htmlspecialchars($generatedBy) ?></div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-name">Reviewed By</div>
            <div class="sig-label">Budget Manager</div>
        </div>
        <div class="sig-box">
            <div class="sig-line"></div>
            <div class="sig-name">Approved By</div>
            <div class="sig-label">Department Head / Admin</div>
        </div>
    </div>
    <!-- FOOTER -->
    <div class="pdf-footer">
        <span>CIT Food Trades Budgeting &amp; Inventory System</span>
        <span>Generated: <?= $generatedAt ?></span>
        <span>Period: <?= htmlspecialchars($periodLabel) ?></span>
    </div>

</div><!-- end pdf-page -->
</div><!-- end page-wrap -->

<script>
function downloadPDF() {
    window.print();
}

// Auto-trigger print if ?autoprint=1
<?php if (isset($_GET['autoprint'])): ?>
window.onload = function() { setTimeout(window.print, 600); };
<?php endif; ?>
</script>
</body>
</html>
