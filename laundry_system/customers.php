<?php include("includes/header.php"); include("config/db.php");

// Only admin and cashier can access customer
requirePermission(['admin', 'cashier']);

$success = $error = "";

// Add Customer
if (isset($_POST['add'])) {
    $fullname = sanitize($conn, $_POST['fullname']);
    $contact  = sanitize($conn, $_POST['contact']);
    $address  = sanitize($conn, $_POST['address']);
    $email    = sanitize($conn, $_POST['email'] ?? '');

    $dup = $conn->query("SELECT id FROM customers WHERE contact_number = '$contact'");
    if ($dup->num_rows > 0) {
        $error = "A customer with this contact number already exists.";
    } else {
        $conn->query("INSERT INTO customers (fullname, contact_number, address, email, created_at)
            VALUES ('$fullname','$contact','$address','$email', NOW())");
        $success = "Customer added successfully.";
    }
}

// Edit Customer
if (isset($_POST['edit'])) {
    $id       = (int)$_POST['customer_id'];
    $fullname = sanitize($conn, $_POST['fullname']);
    $contact  = sanitize($conn, $_POST['contact']);
    $address  = sanitize($conn, $_POST['address']);
    $email    = sanitize($conn, $_POST['email'] ?? '');
    $conn->query("UPDATE customers SET fullname='$fullname', contact_number='$contact', address='$address', email='$email' WHERE id=$id");
    $success = "Customer updated.";
}

// Delete Customer
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $hasOrders = $conn->query("SELECT id FROM orders WHERE customer_id=$id");
    if ($hasOrders->num_rows > 0) {
        $error = "Cannot delete: customer has existing orders.";
    } else {
        $conn->query("DELETE FROM customers WHERE id=$id");
        $success = "Customer deleted.";
    }
}

// Search
$search = sanitize($conn, $_GET['search'] ?? '');
$where  = $search ? "WHERE c.fullname LIKE '%$search%' OR c.contact_number LIKE '%$search%'" : "";

$customers = $conn->query("
    SELECT c.*
    FROM customers c
    $where
    ORDER BY c.created_at DESC
");
?>

<div class="page-header">
    <h3><i class="fas fa-users me-2 text-primary"></i> Customer Management</h3>
    <p>Manage customers and their information</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Add Form -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-user-plus"></i> Add New Customer</div>
            <div class="p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input name="fullname" class="form-control" placeholder="Juan Dela Cruz" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input name="contact" class="form-control" placeholder="09XXXXXXXXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Street, Barangay, City"></textarea>
                    </div>
                    <button name="add" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Save Customer</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Customer List -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header-custom justify-content-between">
                <span><i class="fas fa-list"></i> Customer List</span>
                <form class="d-flex gap-2" method="GET">
                    <input name="search" class="form-control form-control-sm" placeholder="Search name/contact..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <div class="p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; while ($row = $customers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['fullname']) ?></strong>
                                <?php if($row['email']): ?>
                                    <div style="font-size:0.75rem;color:#9aacbe;"><?= htmlspecialchars($row['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['contact_number']) ?></td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm"
                                    onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes($row['fullname']) ?>', '<?= addslashes($row['contact_number']) ?>', '<?= addslashes($row['email'] ?? '') ?>', '<?= addslashes($row['address'] ?? '') ?>')"
                                    title="Edit Customer">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Delete this customer?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($customers->num_rows === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<!-- Edit Customer Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#1565c0,#1e88e5);color:#fff;border-radius:14px 14px 0 0;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Customer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="customer_id" id="edit_id">
                    <div class="mb-3"><label class="form-label">Full Name *</label><input name="fullname" id="edit_fullname" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Contact Number *</label><input name="contact" id="edit_contact" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Email</label><input name="email" id="edit_email" type="email" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea name="address" id="edit_address" class="form-control" rows="2"></textarea></div>
                    <button name="edit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Update Customer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openEdit(id, fullname, contact, email, address) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_fullname').value = fullname;
    document.getElementById('edit_contact').value = contact;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_address').value = address;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>


<?php include("includes/footer.php"); ?>
