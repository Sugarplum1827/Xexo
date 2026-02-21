<?php
require_once '../includes/auth.php';
requireRole('inventory_manager', '../index.php');
$pageTitle = 'Inventory Management';

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $name  = trim($_POST['item_name']);
        $cat   = trim($_POST['category']);
        $unit  = trim($_POST['unit']);
        $stock = (float)$_POST['current_stock'];
        $min   = (float)$_POST['minimum_stock'];
        $cost  = (float)$_POST['unit_cost'];
        $exp   = $_POST['expiry_date'] ?: null;
        $stmt  = $conn->prepare("INSERT INTO inventory (item_name,category,unit,current_stock,minimum_stock,unit_cost,expiry_date) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param("sssddds", $name,$cat,$unit,$stock,$min,$cost,$exp);
        $stmt->execute(); $stmt->close();
        logActivity($conn,'ADD_INVENTORY',"Added inventory item: $name");
        $msg='Item added.'; $msgType='success';
    }
    if ($action === 'edit') {
        $id    = (int)$_POST['item_id'];
        $name  = trim($_POST['item_name']);
        $cat   = trim($_POST['category']);
        $unit  = trim($_POST['unit']);
        $stock = (float)$_POST['current_stock'];
        $min   = (float)$_POST['minimum_stock'];
        $cost  = (float)$_POST['unit_cost'];
        $exp   = $_POST['expiry_date'] ?: null;
        $stmt  = $conn->prepare("UPDATE inventory SET item_name=?,category=?,unit=?,current_stock=?,minimum_stock=?,unit_cost=?,expiry_date=? WHERE id=?");
        $stmt->bind_param("sssdddsi", $name,$cat,$unit,$stock,$min,$cost,$exp,$id);
        $stmt->execute(); $stmt->close();
        logActivity($conn,'EDIT_INVENTORY',"Edited inventory item ID $id: $name");
        $msg='Item updated.'; $msgType='success';
    }
    if ($action === 'delete') {
        $id = (int)$_POST['item_id'];
        $conn->query("DELETE FROM inventory WHERE id=$id");
        logActivity($conn,'DELETE_INVENTORY',"Deleted inventory item ID $id");
        $msg='Item deleted.'; $msgType='success';
    }
}

$search = $conn->real_escape_string(trim($_GET['q'] ?? ''));
$where  = $search ? "WHERE item_name LIKE '%$search%' OR category LIKE '%$search%'" : '';
$items  = $conn->query("SELECT * FROM inventory $where ORDER BY item_name")->fetch_all(MYSQLI_ASSOC);
include '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><i class="fas fa-check-circle"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Inventory Items</span>
        <div style="display:flex;gap:8px;align-items:center;">
            <form method="GET" style="display:flex;gap:6px;">
                <input type="text" name="q" class="form-control" style="width:200px;padding:8px 12px;" placeholder="Search…" value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
            </form>
            <button class="btn btn-gold" onclick="document.getElementById('addModal').classList.add('open')">
                <i class="fas fa-plus"></i> Add Item
            </button>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Item Name</th><th>Category</th><th>Unit</th><th>Stock</th><th>Min Stock</th><th>Unit Cost</th><th>Total Value</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                <td><?= htmlspecialchars($item['category']??'—') ?></td>
                <td><?= htmlspecialchars($item['unit']) ?></td>
                <td style="font-weight:700;color:<?= $item['current_stock']<=$item['minimum_stock']?'var(--danger)':'var(--success)' ?>"><?= $item['current_stock'] ?></td>
                <td><?= $item['minimum_stock'] ?></td>
                <td><?= formatCurrency($item['unit_cost']) ?></td>
                <td><?= formatCurrency($item['current_stock'] * $item['unit_cost']) ?></td>
                <td style="font-size:12px;"><?= $item['expiry_date'] ?? '—' ?></td>
                <td><span class="badge badge-<?= $item['current_stock']<=$item['minimum_stock']?($item['current_stock']<=0?'rejected':'pending'):'ok' ?>"><?= $item['current_stock']<=0?'Out of Stock':($item['current_stock']<=$item['minimum_stock']?'Low':'OK') ?></span></td>
                <td>
                    <div style="display:flex;gap:5px;">
                        <button class="btn btn-sm btn-primary" onclick='openEdit(<?= json_encode($item) ?>)'><i class="fas fa-edit"></i></button>
                        <form method="POST" onsubmit="return confirm('Delete this item?');" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?><tr><td colspan="11" style="text-align:center;color:var(--text-muted);padding:32px">No inventory items found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title">Add Inventory Item</span>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group"><label>Item Name *</label><input type="text" name="item_name" class="form-control" required></div>
                <div class="form-group"><label>Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Dry Goods"></div>
            </div>
            <div class="form-row three">
                <div class="form-group"><label>Unit *</label><input type="text" name="unit" class="form-control" required placeholder="kg, pcs, L…"></div>
                <div class="form-group"><label>Current Stock</label><input type="number" name="current_stock" class="form-control" step="0.01" value="0"></div>
                <div class="form-group"><label>Minimum Stock</label><input type="number" name="minimum_stock" class="form-control" step="0.01" value="5"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Unit Cost (₱)</label><input type="number" name="unit_cost" class="form-control" step="0.01" value="0"></div>
                <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date" class="form-control"></div>
            </div>
            <button type="submit" class="btn btn-gold" style="width:100%"><i class="fas fa-plus"></i> Add Item</button>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:600px">
        <div class="modal-header">
            <span class="modal-title">Edit Inventory Item</span>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="item_id" id="editId">
            <div class="form-row">
                <div class="form-group"><label>Item Name *</label><input type="text" name="item_name" id="editName" class="form-control" required></div>
                <div class="form-group"><label>Category</label><input type="text" name="category" id="editCategory" class="form-control"></div>
            </div>
            <div class="form-row three">
                <div class="form-group"><label>Unit *</label><input type="text" name="unit" id="editUnit" class="form-control" required></div>
                <div class="form-group"><label>Current Stock</label><input type="number" name="current_stock" id="editStock" class="form-control" step="0.01"></div>
                <div class="form-group"><label>Minimum Stock</label><input type="number" name="minimum_stock" id="editMin" class="form-control" step="0.01"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Unit Cost (₱)</label><input type="number" name="unit_cost" id="editCost" class="form-control" step="0.01"></div>
                <div class="form-group"><label>Expiry Date</label><input type="date" name="expiry_date" id="editExpiry" class="form-control"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-save"></i> Save Changes</button>
        </form>
    </div>
</div>

<script>
function openEdit(item) {
    document.getElementById('editId').value      = item.id;
    document.getElementById('editName').value    = item.item_name;
    document.getElementById('editCategory').value= item.category || '';
    document.getElementById('editUnit').value    = item.unit;
    document.getElementById('editStock').value   = item.current_stock;
    document.getElementById('editMin').value     = item.minimum_stock;
    document.getElementById('editCost').value    = item.unit_cost;
    document.getElementById('editExpiry').value  = item.expiry_date || '';
    document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); }));
</script>
<?php include '../includes/footer.php'; ?>
