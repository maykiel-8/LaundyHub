<?php include("includes/header.php"); include("config/db.php");

$success = $error = "";

if (isset($_POST['add'])) {
    $name     = sanitize($conn, $_POST['item_name']);
    $unit     = sanitize($conn, $_POST['unit']);
    $qty      = (float)$_POST['quantity'];
    $reorder  = (float)$_POST['reorder_level'];
    $conn->query("INSERT INTO inventory (item_name, unit, quantity, reorder_level, updated_at)
        VALUES ('$name','$unit',$qty,$reorder, NOW())");
    $success = "Item added.";
}

if (isset($_POST['restock'])) {
    $id  = (int)$_POST['item_id'];
    $add = (float)$_POST['add_qty'];
    $conn->query("UPDATE inventory SET quantity = quantity + $add, updated_at = NOW() WHERE id=$id");
    $success = "Stock updated.";
}

if (isset($_POST['use'])) {
    $id  = (int)$_POST['item_id'];
    $use = (float)$_POST['use_qty'];
    $item = $conn->query("SELECT * FROM inventory WHERE id=$id")->fetch_assoc();
    if ($item['quantity'] < $use) {
        $error = "Insufficient stock.";
    } else {
        $conn->query("UPDATE inventory SET quantity = quantity - $use, updated_at = NOW() WHERE id=$id");
        $success = "Usage recorded.";
    }
}

if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM inventory WHERE id=" . (int)$_GET['delete']);
    $success = "Item removed.";
}

$items = $conn->query("SELECT * FROM inventory ORDER BY item_name");
?>

<div class="page-header">
    <h3><i class="fas fa-boxes me-2 text-primary"></i> Inventory Management</h3>
    <p>Track detergents, fabric conditioners, and other supplies</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Add Item -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-plus-circle"></i> Add Supply Item</div>
            <div class="p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Item Name *</label>
                        <input name="item_name" class="form-control" placeholder="e.g. Ariel Detergent" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit *</label>
                        <select name="unit" class="form-select" required>
                            <option value="kg">Kilogram (kg)</option>
                            <option value="pcs">Pieces (pcs)</option>
                            <option value="liters">Liters (L)</option>
                            <option value="sachets">Sachets</option>
                            <option value="bottles">Bottles</option>
                            <option value="packs">Packs</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Quantity *</label>
                        <input type="number" step="0.01" name="quantity" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reorder Level <small class="text-muted">(alert threshold)</small></label>
                        <input type="number" step="0.01" name="reorder_level" class="form-control" value="5">
                    </div>
                    <button name="add" class="btn btn-primary w-100">Add Item</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-warehouse"></i> Current Stock</div>
            <div class="p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>Item</th><th>Qty</th><th>Unit</th><th>Level</th><th>Last Update</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php while ($item = $items->fetch_assoc()): ?>
                        <?php $low = $item['quantity'] <= $item['reorder_level']; ?>
                        <tr class="<?= $low ? 'table-warning' : '' ?>">
                            <td>
                                <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                <?php if ($low): ?>
                                    <span class="badge bg-danger ms-1" style="font-size:0.7rem;">Low Stock</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($item['quantity'], 2) ?></strong></td>
                            <td><?= htmlspecialchars($item['unit']) ?></td>
                            <td>
                                <?php $pct = min(100, ($item['quantity'] / max(1, $item['reorder_level'] * 3)) * 100); ?>
                                <div class="progress" style="height:6px; width:80px; border-radius:4px;">
                                    <div class="progress-bar <?= $low ? 'bg-danger' : 'bg-success' ?>"
                                        style="width:<?= $pct ?>%; border-radius:4px;"></div>
                                </div>
                            </td>
                            <td><small class="text-muted"><?= date("M j, Y", strtotime($item['updated_at'])) ?></small></td>
                            <td>
                                <button class="btn btn-success btn-sm"
                                    onclick="openRestock(<?= $item['id'] ?>, '<?= addslashes($item['item_name']) ?>')">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn btn-warning btn-sm"
                                    onclick="openUse(<?= $item['id'] ?>, '<?= addslashes($item['item_name']) ?>')">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <a href="?delete=<?= $item['id'] ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Remove this item?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:14px; border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#2e7d32,#43a047); color:white; border-radius:14px 14px 0 0;">
                <h5 class="modal-title">Restock Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="item_id" id="restock_id">
                    <p id="restock_name" class="fw-bold mb-3"></p>
                    <div class="mb-3">
                        <label class="form-label">Add Quantity</label>
                        <input type="number" step="0.01" name="add_qty" class="form-control" min="0.01" required>
                    </div>
                    <button name="restock" class="btn btn-success w-100">Add Stock</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Use Modal -->
<div class="modal fade" id="useModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:14px; border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#e65100,#fb8c00); color:white; border-radius:14px 14px 0 0;">
                <h5 class="modal-title">Record Usage</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="item_id" id="use_id">
                    <p id="use_name" class="fw-bold mb-3"></p>
                    <div class="mb-3">
                        <label class="form-label">Quantity Used</label>
                        <input type="number" step="0.01" name="use_qty" class="form-control" min="0.01" required>
                    </div>
                    <button name="use" class="btn btn-warning w-100">Record Usage</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openRestock(id, name) {
    document.getElementById('restock_id').value = id;
    document.getElementById('restock_name').textContent = name;
    new bootstrap.Modal(document.getElementById('restockModal')).show();
}
function openUse(id, name) {
    document.getElementById('use_id').value = id;
    document.getElementById('use_name').textContent = name;
    new bootstrap.Modal(document.getElementById('useModal')).show();
}
</script>

<?php include("includes/footer.php"); ?>