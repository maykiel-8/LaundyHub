<?php include("includes/header.php"); include("config/db.php");

// Only admin and cashier can access customers
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

// ── Create / Update customer portal login account ──────────────────────────
if (isset($_POST['create_account'])) {
    $cid   = (int)$_POST['cust_id'];
    $uname = sanitize($conn, $_POST['new_username']);
    $pwd   = md5($_POST['new_password']);

    if (empty($uname) || empty($_POST['new_password'])) {
        $error = "Username and password are required.";
    } else {
        // Does this customer already have an account?
        $existAcc = $conn->query("SELECT id FROM customer_accounts WHERE customer_id=$cid")->num_rows;
        // Is the username taken by someone else?
        $takenBy  = $conn->query("SELECT id FROM customer_accounts WHERE username='$uname' AND customer_id != $cid")->num_rows;

        if ($takenBy > 0) {
            $error = "Username '$uname' is already taken. Choose another.";
        } elseif ($existAcc > 0) {
            // Update existing account
            $conn->query("UPDATE customer_accounts
                          SET username='$uname', password='$pwd', is_active=1
                          WHERE customer_id=$cid");
            $success = "Portal account updated. Username: <strong>$uname</strong>";
        } else {
            // Create brand new account — explicitly set is_active=1
            $res = $conn->query("INSERT INTO customer_accounts
                                    (customer_id, username, password, is_active, created_at)
                                 VALUES ($cid, '$uname', '$pwd', 1, NOW())");
            if ($res) {
                $success = "Portal account created! Username: <strong>$uname</strong> — share these with the customer.";
            } else {
                $error = "Failed to create account: " . $conn->error;
            }
        }
    }
}

// Toggle account active/inactive
if (isset($_POST['toggle_account'])) {
    $cid = (int)$_POST['cust_id'];
    $cur = (int)$_POST['current_active'];
    $new = $cur ? 0 : 1;
    $conn->query("UPDATE customer_accounts SET is_active=$new WHERE customer_id=$cid");
    $success = "Customer portal account " . ($new ? "activated." : "deactivated.");
}

// Search
$search = sanitize($conn, $_GET['search'] ?? '');
$where  = $search ? "WHERE c.fullname LIKE '%$search%' OR c.contact_number LIKE '%$search%'" : "";

$customers = $conn->query("
    SELECT c.*, ca.username AS portal_username, ca.is_active AS portal_active, ca.id AS account_id
    FROM customers c
    LEFT JOIN customer_accounts ca ON c.id = ca.customer_id
    $where
    ORDER BY c.created_at DESC
");
?>

<div class="page-header">
    <h3><i class="fas fa-users me-2 text-primary"></i> Customer Management</h3>
    <p>Manage customers and their Customer Portal login accounts</p>
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
                            <th>Portal Account</th>
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
                                <?php if ($row['portal_username']): ?>
                                    <div class="d-flex align-items-center gap-1 flex-wrap">
                                        <code style="font-size:0.78rem;"><?= htmlspecialchars($row['portal_username']) ?></code>
                                        <?php if ((int)$row['portal_active'] === 1): ?>
                                            <span class="badge bg-success" style="font-size:0.65rem;">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" style="font-size:0.65rem;">Inactive</span>
                                        <?php endif; ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="cust_id" value="<?= $row['id'] ?>">
                                            <input type="hidden" name="current_active" value="<?= (int)$row['portal_active'] ?>">
                                            <button name="toggle_account" class="btn btn-outline-secondary btn-sm" style="padding:1px 6px;font-size:0.7rem;" title="Toggle Active">
                                                <?= ((int)$row['portal_active'] === 1) ? '🔒 Deactivate' : '🔓 Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:0.78rem;"><i class="fas fa-times-circle text-danger me-1"></i>No account yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm"
                                    onclick="openEdit(<?= $row['id'] ?>, '<?= addslashes($row['fullname']) ?>', '<?= addslashes($row['contact_number']) ?>', '<?= addslashes($row['email'] ?? '') ?>', '<?= addslashes($row['address'] ?? '') ?>')"
                                    title="Edit Customer">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-success btn-sm"
                                    onclick="openAccount(<?= $row['id'] ?>, '<?= addslashes($row['fullname']) ?>', '<?= addslashes($row['portal_username'] ?? '') ?>')"
                                    title="<?= $row['portal_username'] ? 'Update Portal Account' : 'Create Portal Account' ?>">
                                    <i class="fas fa-key"></i>
                                </button>
                                <a href="?delete=<?= $row['id'] ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Delete this customer?')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if ($customers->num_rows === 0): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No customers found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
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

<!-- Portal Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#2e7d32,#43a047);color:#fff;border-radius:14px 14px 0 0;">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Customer Portal Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3" style="font-size:0.88rem;">Setting login for: <strong id="acc_name"></strong></p>
                <form method="POST">
                    <input type="hidden" name="cust_id" id="acc_cust_id">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input name="new_username" id="acc_username" class="form-control" placeholder="e.g. juan_santos" required autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" minlength="6" required autocomplete="new-password">
                    </div>
                    <div class="alert" style="background:#e8f5e9;color:#1b5e20;font-size:0.78rem;border-radius:8px;padding:10px 12px;border:none;">
                        <i class="fas fa-info-circle me-1"></i>
                        After saving, share the <strong>username</strong> and <strong>password</strong> with the customer. They log in at <code>customer_login.php</code>.
                    </div>
                    <button name="create_account" class="btn btn-success w-100 mt-1">
                        <i class="fas fa-save me-2"></i>Save Portal Account
                    </button>
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
function openAccount(cust_id, name, existing_username) {
    document.getElementById('acc_cust_id').value = cust_id;
    document.getElementById('acc_name').textContent = name;
    document.getElementById('acc_username').value = existing_username || '';
    document.querySelector('[name="new_password"]').value = '';
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}
</script>

<?php include("includes/footer.php"); ?>