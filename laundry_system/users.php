<?php include("includes/header.php"); include("config/db.php");

// Only admins can access this page
requirePermission(['admin']);

if ($_SESSION['user']['role'] !== 'admin') {
    echo "<div class='alert alert-danger'><i class='fas fa-ban me-2'></i>Access Denied. Admin only.</div>";
    include("includes/footer.php");
    exit();
}

$success = $error = "";

if (isset($_POST['add'])) {
    $fullname = sanitize($conn, $_POST['fullname']);
    $username = sanitize($conn, $_POST['username']);
    $role     = sanitize($conn, $_POST['role']);
    $contact  = sanitize($conn, $_POST['contact'] ?? '');
    $password = md5($_POST['password']);

    $dup = $conn->query("SELECT id FROM users WHERE username='$username'");
    if ($dup->num_rows > 0) {
        $error = "Username already exists.";
    } else {
        $conn->query("INSERT INTO users (fullname, username, password, role, contact, created_at)
            VALUES ('$fullname','$username','$password','$role','$contact', NOW())");
        $success = "User registered successfully.";
    }
}

if (isset($_POST['reset_pw'])) {
    $id = (int)$_POST['user_id'];
    $pw = md5($_POST['new_password']);
    $conn->query("UPDATE users SET password='$pw' WHERE id=$id");
    $success = "Password reset.";
}

if (isset($_POST['toggle_active'])) {
    $id  = (int)$_POST['user_id'];
    $cur = (int)$_POST['current_active'];
    $new = $cur ? 0 : 1;
    $conn->query("UPDATE users SET is_active=$new WHERE id=$id");
    $success = "User " . ($new ? "activated" : "deactivated") . ".";
}

if (isset($_GET['delete']) && (int)$_GET['delete'] !== (int)$_SESSION['user']['id']) {
    $del_id = (int)$_GET['delete'];
    // Nullify references in related tables before deleting
    $conn->query("UPDATE delivery_logs SET updated_by = NULL WHERE updated_by = $del_id");
    $conn->query("UPDATE payments SET received_by = NULL WHERE received_by = $del_id");
    $conn->query("UPDATE deliveries SET assigned_to = NULL WHERE assigned_to = $del_id");
    $conn->query("DELETE FROM users WHERE id = $del_id");
    $success = "User deleted.";
}

$users = $conn->query("SELECT * FROM users ORDER BY role, fullname");
?>

<div class="page-header">
    <h3><i class="fas fa-user-shield me-2 text-primary"></i> User Management</h3>
    <p>Manage system accounts and access roles</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-user-plus"></i> Register New User</div>
            <div class="p-4">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input name="fullname" class="form-control" placeholder="Full Name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input name="username" class="form-control" placeholder="Username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number</label>
                        <input name="contact" class="form-control" placeholder="09XXXXXXXXX">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="cashier">Cashier — POS & Payments</option>
                            <option value="operator">Operator — Orders & Inventory</option>
                            <option value="delivery">Delivery Staff — Pickup & Delivery</option>
                            <option value="admin">Admin — Full Access</option>
                        </select>
                    </div>
                    <button name="add" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Register User</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-users"></i> System Users</div>
            <div class="p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr><th>#</th><th>Name</th><th>Username</th><th>Contact</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php $i=1; while ($u = $users->fetch_assoc()):
                        $roleBadge = [
                            'admin'    => '<span class="badge bg-danger">Admin</span>',
                            'operator' => '<span class="badge bg-warning text-dark">Operator</span>',
                            'cashier'  => '<span class="badge bg-info">Cashier</span>',
                            'delivery' => '<span class="badge bg-success">Delivery</span>',
                        ][$u['role']] ?? "<span class='badge bg-secondary'>{$u['role']}</span>";
                        $isMe = (int)$u['id'] === (int)$_SESSION['user']['id'];
                    ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($u['fullname']) ?></strong>
                                <?= $isMe ? '<span class="badge bg-success ms-1" style="font-size:0.68rem;">You</span>' : '' ?>
                            </td>
                            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                            <td><small><?= htmlspecialchars($u['contact'] ?? '—') ?></small></td>
                            <td><?= $roleBadge ?></td>
                            <td>
                                <?php if($u['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-flex gap-1">
                                <button class="btn btn-outline-warning btn-sm"
                                    onclick="openReset(<?= $u['id'] ?>, '<?= addslashes($u['fullname']) ?>')" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                <?php if(!$isMe): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="current_active" value="<?= $u['is_active'] ?>">
                                    <button name="toggle_active" class="btn btn-outline-secondary btn-sm" title="Toggle Active">
                                        <i class="fas fa-<?= $u['is_active']?'ban':'check' ?>"></i>
                                    </button>
                                </form>
                                <a href="?delete=<?= $u['id'] ?>" class="btn btn-outline-danger btn-sm"
                                    onclick="return confirm('Delete user?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:14px; border:none;">
            <div class="modal-header" style="background:linear-gradient(135deg,#e65100,#fb8c00); color:white; border-radius:14px 14px 0 0;">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST">
                    <input type="hidden" name="user_id" id="reset_id">
                    <p>Reset for: <strong id="reset_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <button name="reset_pw" class="btn btn-warning w-100">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openReset(id, name) {
    document.getElementById('reset_id').value = id;
    document.getElementById('reset_name').textContent = name;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
</script>

<?php include("includes/footer.php"); ?>