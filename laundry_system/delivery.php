<?php include("includes/header.php"); include("config/db.php");

$success = $error = "";

// Update delivery status
if (isset($_POST['update_delivery'])) {
    $del_id    = (int)$_POST['delivery_id'];
    $new_status= sanitize($conn, $_POST['delivery_status']);
    $remarks   = sanitize($conn, $_POST['remarks'] ?? '');
    $by        = (int)$_SESSION['user']['id'];

    $allowed_statuses = ['Pending','Assigned','Out for Pickup','Picked Up','Out for Delivery','Delivered','Failed'];
    if (in_array($new_status, $allowed_statuses)) {
        $conn->query("UPDATE deliveries SET delivery_status='$new_status', updated_at=NOW() WHERE id=$del_id");
        $conn->query("INSERT INTO delivery_logs (delivery_id, status, remarks, updated_by, logged_at)
            VALUES ($del_id, '$new_status', '$remarks', $by, NOW())");
        $success = "Delivery status updated to: $new_status";
    }
}

// Assign staff
if (isset($_POST['assign_staff'])) {
    $del_id   = (int)$_POST['delivery_id'];
    $staff_id = (int)$_POST['staff_id'];
    $by       = (int)$_SESSION['user']['id'];
    $conn->query("UPDATE deliveries SET assigned_to=$staff_id, delivery_status='Assigned', updated_at=NOW() WHERE id=$del_id");
    $conn->query("INSERT INTO delivery_logs (delivery_id, status, remarks, updated_by, logged_at)
        VALUES ($del_id, 'Assigned', 'Staff assigned', $by, NOW())");
    $success = "Staff assigned successfully.";
}

// Filter
$filterStatus = sanitize($conn, $_GET['dstatus'] ?? '');
$filterType   = sanitize($conn, $_GET['dtype'] ?? '');
$filterOrder  = (int)($_GET['order_id'] ?? 0);

$where_parts = [];
if ($filterStatus) $where_parts[] = "d.delivery_status = '$filterStatus'";
if ($filterType)   $where_parts[] = "d.service_type = '$filterType'";
if ($filterOrder)  $where_parts[] = "d.order_id = $filterOrder";
$where = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";

$deliveries = $conn->query("
    SELECT d.*, o.reference_no, o.total_amount, o.order_status, o.payment_status,
           c.fullname as customer_name, c.contact_number, c.address as customer_address,
           u.fullname as staff_name, u.contact as staff_contact,
           svc.service_name
    FROM deliveries d
    JOIN orders   o ON d.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    JOIN services  svc ON o.service_id = svc.id
    LEFT JOIN users u ON d.assigned_to = u.id
    $where
    ORDER BY d.created_at DESC
");

$staff_list = $conn->query("SELECT * FROM users WHERE role='delivery' AND is_active=1 ORDER BY fullname");
$staff_arr  = [];
while ($s = $staff_list->fetch_assoc()) $staff_arr[] = $s;

// Stats
$stats = $conn->query("SELECT delivery_status, COUNT(*) as cnt FROM deliveries GROUP BY delivery_status")->fetch_all(MYSQLI_ASSOC);
$stat_map = [];
foreach($stats as $st) $stat_map[$st['delivery_status']] = $st['cnt'];

// Status colors for badges
$status_colors = [
    'Pending'          => ['bg'=>'#fff3e0','color'=>'#e65100'],
    'Assigned'         => ['bg'=>'#e3f2fd','color'=>'#1565c0'],
    'Out for Pickup'   => ['bg'=>'#ede7f6','color'=>'#4527a0'],
    'Picked Up'        => ['bg'=>'#e8eaf6','color'=>'#283593'],
    'Out for Delivery' => ['bg'=>'#e0f7fa','color'=>'#006064'],
    'Delivered'        => ['bg'=>'#e8f5e9','color'=>'#1b5e20'],
    'Failed'           => ['bg'=>'#ffebee','color'=>'#b71c1c'],
];
?>

<div class="page-header">
    <h3><i class="fas fa-motorcycle me-2 text-primary"></i> Pickup & Delivery Management</h3>
    <p>Track, assign, and manage all pickup and delivery requests</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['Pending',          'fa-clock',          '#fb8c00'],
        ['Assigned',         'fa-user-check',      '#1e88e5'],
        ['Out for Pickup',   'fa-box-open',        '#8e24aa'],
        ['Out for Delivery', 'fa-motorcycle',      '#00838f'],
        ['Delivered',        'fa-check-circle',    '#43a047'],
        ['Failed',           'fa-times-circle',    '#e53935'],
    ];
    foreach($statCards as [$lbl, $icon, $color]):
        $cnt = $stat_map[$lbl] ?? 0;
    ?>
    <div class="col-md-2">
        <div class="card text-center p-3">
            <i class="fas <?= $icon ?>" style="font-size:1.5rem; color:<?= $color ?>; margin-bottom:6px;"></i>
            <div style="font-size:1.5rem; font-weight:700; color:#1a2a3a;"><?= $cnt ?></div>
            <div style="font-size:0.72rem; color:#6c7a8a; text-transform:uppercase;"><?= $lbl ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Delivery Status</label>
                <select name="dstatus" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach(array_keys($status_colors) as $st): ?>
                        <option value="<?= $st ?>" <?= $filterStatus===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="dtype" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="pickup"   <?= $filterType==='pickup'?'selected':'' ?>>Pickup</option>
                    <option value="delivery" <?= $filterType==='delivery'?'selected':'' ?>>Delivery</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button>
            </div>
            <?php if($filterStatus||$filterType||$filterOrder): ?>
            <div class="col-md-2">
                <a href="delivery.php" class="btn btn-secondary btn-sm w-100">Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Delivery Records -->
<?php if ($deliveries->num_rows === 0): ?>
    <div class="card p-5 text-center text-muted">
        <i class="fas fa-motorcycle" style="font-size:3rem; opacity:0.2; margin-bottom:12px;"></i>
        <p>No delivery records found.</p>
        <a href="orders.php" class="btn btn-primary btn-sm">Create an Order with Pickup/Delivery</a>
    </div>
<?php else: ?>

<?php while ($d = $deliveries->fetch_assoc()):
    $sc = $status_colors[$d['delivery_status']] ?? ['bg'=>'#f5f5f5','color'=>'#333'];
    $typeIcon = $d['service_type'] === 'pickup'
        ? '<i class="fas fa-box-open text-warning"></i> Pickup'
        : '<i class="fas fa-motorcycle text-info"></i> Delivery';

    // Fetch logs for this delivery
    $logs = $conn->query("
        SELECT dl.*, u.fullname as updated_by_name
        FROM delivery_logs dl
        LEFT JOIN users u ON dl.updated_by = u.id
        WHERE dl.delivery_id = {$d['id']}
        ORDER BY dl.logged_at DESC
    ");
?>

<div class="card mb-3">
    <div class="card-header-custom justify-content-between" style="background:#fafbff;">
        <div class="d-flex align-items-center gap-3">
            <span class="text-primary fw-bold"><?= htmlspecialchars($d['reference_no']) ?></span>
            <small><?= $typeIcon ?></small>
            <span class="badge-status" style="background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; padding:5px 12px; border-radius:20px; font-size:0.75rem; font-weight:600;">
                <?= htmlspecialchars($d['delivery_status']) ?>
            </span>
            <?php if($d['payment_status']==='Paid'): ?>
                <span class="badge bg-success" style="font-size:0.72rem;">Paid</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark" style="font-size:0.72rem;">Unpaid</span>
            <?php endif; ?>
        </div>
        <small class="text-muted"><?= date("M j, Y g:i A", strtotime($d['created_at'])) ?></small>
    </div>

    <div class="p-4">
        <div class="row g-4">
            <!-- Customer Info -->
            <div class="col-md-3">
                <div style="font-size:0.75rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">Customer</div>
                <div class="fw-bold"><?= htmlspecialchars($d['customer_name']) ?></div>
                <div class="text-muted" style="font-size:0.85rem;"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($d['contact_number']) ?></div>
                <div class="text-muted mt-2" style="font-size:0.82rem;">
                    <i class="fas fa-map-marker-alt me-1 text-danger"></i>
                    <strong><?= $d['service_type']==='pickup' ? 'Pickup From:' : 'Deliver To:' ?></strong><br>
                    <?= nl2br(htmlspecialchars($d['delivery_address'])) ?>
                </div>
            </div>

            <!-- Order Info -->
            <div class="col-md-2">
                <div style="font-size:0.75rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">Order</div>
                <div style="font-size:0.85rem;"><?= htmlspecialchars($d['service_name']) ?></div>
                <div class="fw-bold text-primary mt-1">₱<?= number_format($d['total_amount'], 2) ?></div>
                <div style="font-size:0.8rem; color:#6c7a8a;">Delivery Fee: ₱<?= number_format($d['delivery_fee'], 2) ?></div>
                <?php if($d['scheduled_at']): ?>
                <div class="mt-2" style="font-size:0.8rem;"><i class="fas fa-calendar me-1 text-primary"></i><?= date("M j, Y g:i A", strtotime($d['scheduled_at'])) ?></div>
                <?php endif; ?>
            </div>

            <!-- Assigned Staff -->
            <div class="col-md-3">
                <div style="font-size:0.75rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">Assigned Staff</div>
                <?php if ($d['staff_name']): ?>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#1e88e5,#42a5f5);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;">
                            <?= strtoupper(substr($d['staff_name'],0,1)) ?>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:0.9rem;"><?= htmlspecialchars($d['staff_name']) ?></div>
                            <?php if($d['staff_contact']): ?>
                            <div style="font-size:0.78rem; color:#6c7a8a;"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($d['staff_contact']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Unassigned</span>
                <?php endif; ?>

                <!-- Assign Form -->
                <form method="POST" class="mt-2 d-flex gap-1">
                    <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                    <select name="staff_id" class="form-select form-select-sm">
                        <option value="">Reassign...</option>
                        <?php foreach($staff_arr as $st): ?>
                            <option value="<?= $st['id'] ?>" <?= $st['id']==$d['assigned_to']?'selected':'' ?>><?= htmlspecialchars($st['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button name="assign_staff" class="btn btn-outline-primary btn-sm"><i class="fas fa-check"></i></button>
                </form>
            </div>

            <!-- Update Status -->
            <div class="col-md-4">
                <div style="font-size:0.75rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">Update Status</div>
                <form method="POST">
                    <input type="hidden" name="delivery_id" value="<?= $d['id'] ?>">
                    <select name="delivery_status" class="form-select form-select-sm mb-2">
                        <?php foreach(array_keys($status_colors) as $st): ?>
                            <option value="<?= $st ?>" <?= $st===$d['delivery_status']?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="remarks" class="form-control form-control-sm mb-2" placeholder="Remarks (optional)">
                    <button name="update_delivery" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-sync me-1"></i>Update Status
                    </button>
                </form>

                <!-- Status Timeline (mini) -->
                <div class="mt-3">
                    <div style="font-size:0.72rem; text-transform:uppercase; color:#7a8a9a; margin-bottom:6px;">Activity Log</div>
                    <div style="max-height:110px; overflow-y:auto;">
                    <?php while($log = $logs->fetch_assoc()): ?>
                        <div class="d-flex gap-2 mb-1 align-items-start">
                            <i class="fas fa-circle" style="font-size:0.4rem; margin-top:6px; color:#1e88e5; flex-shrink:0;"></i>
                            <div>
                                <span class="fw-bold" style="font-size:0.78rem;"><?= htmlspecialchars($log['status']) ?></span>
                                <?php if($log['remarks']): ?>
                                    <span class="text-muted" style="font-size:0.75rem;"> — <?= htmlspecialchars($log['remarks']) ?></span>
                                <?php endif; ?>
                                <div style="font-size:0.7rem; color:#9aacbe;"><?= date("M j, g:i A", strtotime($log['logged_at'])) ?> · <?= htmlspecialchars($log['updated_by_name'] ?? '—') ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>
<?php endif; ?>

<?php include("includes/footer.php"); ?>