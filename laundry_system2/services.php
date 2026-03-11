<?php include("includes/header.php"); include("config/db.php");

// Only admin and operator can access services
requirePermission(['admin', 'operator']);

$success = $error = "";

if (isset($_POST['add'])) {
    $name     = sanitize($conn, $_POST['service_name']);
    $desc     = sanitize($conn, $_POST['description']);
    $type     = sanitize($conn, $_POST['pricing_type']);
    $price    = (float)$_POST['price'];
    $conn->query("INSERT INTO services (service_name, description, pricing_type, price, created_at)
        VALUES ('$name','$desc','$type','$price', NOW())");
    $success = "Service added.";
}

if (isset($_POST['edit'])) {
    $id    = (int)$_POST['service_id'];
    $name  = sanitize($conn, $_POST['service_name']);
    $desc  = sanitize($conn, $_POST['description']);
    $type  = sanitize($conn, $_POST['pricing_type']);
    $price = (float)$_POST['price'];
    $conn->query("UPDATE services SET service_name='$name', description='$desc', pricing_type='$type', price='$price' WHERE id=$id");
    $success = "Service updated.";
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $used = $conn->query("SELECT id FROM orders WHERE service_id=$id");
    if ($used->num_rows > 0) {
        $error = "Cannot delete: service is linked to existing orders.";
    } else {
        $conn->query("DELETE FROM services WHERE id=$id");
        $success = "Service deleted.";
    }
}

$services = $conn->query("SELECT * FROM services ORDER BY created_at DESC");
?>

<div class="page-header">
    <h3><i class="fas fa-tags me-2 text-primary"></i> Services & Pricing</h3>
    <p>Manage laundry services and pricing configurations</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-plus-circle"></i> Add Service</div>
            <div class="p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Service Name *</label>
                        <input name="service_name" class="form-control" placeholder="e.g. Regular Wash" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Short description..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pricing Type *</label>
                        <select name="pricing_type" class="form-select" required>
                            <option value="per_kg">Per Kilogram</option>
                            <option value="per_item">Per Item</option>
                            <option value="flat">Flat Rate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (&#8369;) *</label>
                        <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00" required>
                    </div>
                    <button name="add" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Save Service</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-list"></i> Service List</div>
            <div class="p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th>Service</th><th>Description</th><th>Type</th><th>Price</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php $i=1; while($s = $services->fetch_assoc()): ?>
                        <?php
                        $typeLabel = ['per_kg'=>'Per KG','per_item'=>'Per Item','flat'=>'Flat Rate'][$s['pricing_type']] ?? $s['pricing_type'];
                        ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= htmlspecialchars($s['service_name']) ?></strong></td>
                            <td><small class="text-muted"><?= htmlspecialchars($s['description'] ?: '—') ?></small></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary"><?= $typeLabel ?></span></td>
                            <td><strong>&#8369;<?= number_format($s['price'], 2) ?></strong></td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm"
                                    onclick="openEditSvc(<?= $s['id'] ?>, '<?= addslashes($s['service_name']) ?>', '<?= addslashes($s['description']) ?>', '<?= $s['pricing_type'] ?>', <?= $s['price'] ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $s['id'] ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Delete this service?')">
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

<!-- Edit Modal -->
<div class="modal fade" id="editSvcModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:14px; border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#1565c0,#1e88e5); color:white; border-radius:14px 14px 0 0;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="service_id" id="esvc_id">
                    <div class="mb-3">
                        <label class="form-label">Service Name</label>
                        <input name="service_name" id="esvc_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="esvc_desc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pricing Type</label>
                        <select name="pricing_type" id="esvc_type" class="form-select">
                            <option value="per_kg">Per Kilogram</option>
                            <option value="per_item">Per Item</option>
                            <option value="flat">Flat Rate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (&#8369;)</label>
                        <input type="number" step="0.01" name="price" id="esvc_price" class="form-control" required>
                    </div>
                    <button name="edit" class="btn btn-primary w-100">Update Service</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openEditSvc(id, name, desc, type, price) {
    document.getElementById('esvc_id').value = id;
    document.getElementById('esvc_name').value = name;
    document.getElementById('esvc_desc').value = desc;
    document.getElementById('esvc_type').value = type;
    document.getElementById('esvc_price').value = price;
    new bootstrap.Modal(document.getElementById('editSvcModal')).show();
}
</script>

<?php include("includes/footer.php"); ?>