<?php
require_once '../includes/auth.php';
requireRole('user', '../index.php');
$pageTitle = 'Submit Purchase';
$uid = $_SESSION['user_id'];

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemName  = trim($_POST['item_name']);
    $qty       = (float)$_POST['quantity'];
    $unit      = trim($_POST['unit']);
    $price     = (float)$_POST['unit_price'];
    $supplier  = trim($_POST['supplier']);
    $date      = $_POST['purchase_date'];
    $receiptPath = null;

    // Check budget
    $budget = $conn->query("SELECT * FROM budgets WHERE is_active=1 LIMIT 1")->fetch_assoc();
    $totalSpent = $conn->query("SELECT COALESCE(SUM(total_price),0) s FROM purchases WHERE status='approved'")->fetch_assoc()['s'];
    $total = $qty * $price;

    if ($budget && ($totalSpent + $total) > $budget['allocated_amount']) {
        $msg = "⚠ Warning: This purchase of ".formatCurrency($total)." would exceed the remaining budget of ".formatCurrency($budget['allocated_amount']-$totalSpent).". Submission will proceed but may be rejected.";
        $msgType = 'warning';
    }

    // Handle receipt upload
    if (!empty($_FILES['receipt']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png','pdf','gif'];
        if (in_array($ext, $allowed)) {
            $fname  = uniqid('receipt_').'.'.$ext;
            $dest   = '../uploads/receipts/'.$fname;
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
                $receiptPath = 'uploads/receipts/'.$fname;
            }
        } else {
            $msg = 'Invalid file type. Allowed: JPG, PNG, PDF.'; $msgType = 'danger';
        }
    }

    if ($msgType !== 'danger') {
        $rp   = $receiptPath ? $conn->real_escape_string($receiptPath) : null;
        $rpQ  = $rp ? "'$rp'" : 'NULL';
        $supp = $conn->real_escape_string($supplier);
        $name = $conn->real_escape_string($itemName);
        $unit = $conn->real_escape_string($unit);
        $conn->query("INSERT INTO purchases (item_name, quantity, unit, unit_price, supplier, purchase_date, receipt_path, status, submitted_by, created_at) VALUES ('$name',$qty,'$unit',$price,'$supp','$date',$rpQ,'pending',$uid,NOW())");
        logActivity($conn,'SUBMIT_PURCHASE',"Submitted purchase: $itemName x$qty @ $price");
        if ($msgType !== 'warning') { $msg = 'Purchase submitted successfully! Awaiting review.'; $msgType='success'; }
        else { $msg .= ' Purchase submitted.'; }
    }
}

$inventoryItems = $conn->query("SELECT * FROM inventory ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-<?= $msgType==='success'?'check-circle':($msgType==='warning'?'exclamation-triangle':'exclamation-circle') ?>"></i><?= $msg ?></div><?php endif; ?>

<div class="grid-2" style="gap:24px;">
    <div class="card">
        <div class="card-header"><span class="card-title">New Purchase Submission</span></div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" id="itemNameInput" class="form-control" required placeholder="e.g. All-Purpose Flour" oninput="checkInventory(this.value)">
            </div>
            <div id="stockHint" style="display:none;padding:10px 14px;background:var(--cream);border-radius:8px;margin-bottom:12px;font-size:13px;border:1px solid var(--cream-dark);">
                <i class="fas fa-info-circle" style="color:var(--forest)"></i> <span id="stockHintText"></span>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Quantity *</label><input type="number" name="quantity" class="form-control" required step="0.01" min="0.01" placeholder="0.00"></div>
                <div class="form-group"><label>Unit *</label><input type="text" name="unit" id="unitInput" class="form-control" required placeholder="kg, pcs, L…"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Unit Price (₱) *</label><input type="number" name="unit_price" class="form-control" required step="0.01" min="0" placeholder="0.00"></div>
                <div class="form-group"><label>Purchase Date *</label><input type="date" name="purchase_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
            </div>
            <div class="form-group"><label>Supplier</label><input type="text" name="supplier" class="form-control" placeholder="Supplier name or store"></div>
            <div class="form-group">
                <label>Receipt / Invoice (Image or PDF)</label>
                <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.gif">
                <div style="font-size:12px;color:var(--text-muted);margin-top:4px">Max 5MB. Allowed: JPG, PNG, PDF</div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-paper-plane"></i> Submit Purchase</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Current Inventory Stock</span></div>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">Check existing stock before submitting to avoid duplicate purchases.</p>
        <div class="table-wrap" style="max-height:480px;overflow-y:auto;">
            <table>
                <thead><tr><th>Item</th><th>Stock</th><th>Unit</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($inventoryItems as $item): ?>
                <tr class="inv-row" data-name="<?= strtolower(htmlspecialchars($item['item_name'])) ?>" data-unit="<?= htmlspecialchars($item['unit']) ?>">
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td style="font-weight:700;color:<?= $item['current_stock']<=$item['minimum_stock']?'var(--danger)':'inherit' ?>"><?= $item['current_stock'] ?></td>
                    <td><?= htmlspecialchars($item['unit']) ?></td>
                    <td><span class="badge badge-<?= $item['current_stock']<=0?'rejected':($item['current_stock']<=$item['minimum_stock']?'pending':'ok') ?>"><?= $item['current_stock']<=0?'Out':($item['current_stock']<=$item['minimum_stock']?'Low':'OK') ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const inventory = <?= json_encode(array_map(fn($i) => ['name'=>strtolower($i['item_name']),'stock'=>$i['current_stock'],'unit'=>$i['unit'],'min'=>$i['minimum_stock']], $inventoryItems)) ?>;

function checkInventory(val) {
    const hint = document.getElementById('stockHint');
    const hintText = document.getElementById('stockHintText');
    const unitInput = document.getElementById('unitInput');
    const lower = val.toLowerCase();
    const match = inventory.find(i => i.name.includes(lower) || lower.includes(i.name));
    if (match && lower.length >= 3) {
        hintText.textContent = `This item exists in inventory with ${match.stock} ${match.unit} in stock (min: ${match.min} ${match.unit}).`;
        unitInput.value = match.unit;
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}
</script>
<?php include '../includes/footer.php'; ?>
