<?php include("includes/header.php"); include("config/db.php");

$success = $error = "";

// Update order status
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status   = sanitize($conn, $_POST['status']);
    $allowed  = ['Received','Processing','Finishing','Ready','Released'];
    if (in_array($status, $allowed)) {
        $conn->query("UPDATE orders SET order_status='$status' WHERE id=$order_id");
        $success = "Order status updated.";
    }
}

// Create new order
if (isset($_POST['add'])) {
    $reference    = "LS-" . strtoupper(substr(uniqid(), -6));
    $customer_id  = (int)$_POST['customer_id'];
    $service_id   = (int)$_POST['service_id'];
    $quantity     = (float)$_POST['quantity'];
    $surcharge    = (float)$_POST['surcharge'];
    $pickup       = sanitize($conn, $_POST['pickup']);
    $notes        = sanitize($conn, $_POST['notes'] ?? '');
    $service_type = sanitize($conn, $_POST['service_type'] ?? 'walk_in');
    $delivery_fee = ($service_type !== 'walk_in') ? (float)($_POST['delivery_fee'] ?? 50) : 0;

    $allowed_types = ['walk_in','pickup','delivery'];
    if (!in_array($service_type, $allowed_types)) $service_type = 'walk_in';

    $service = $conn->query("SELECT * FROM services WHERE id=$service_id")->fetch_assoc();
    if (!$service) {
        $error = "Invalid service selected.";
    } else {
        if ($service['pricing_type'] === 'per_kg' || $service['pricing_type'] === 'per_item') {
            $total = ($service['price'] * $quantity) + $surcharge + $delivery_fee;
        } else {
            $total = $service['price'] + $surcharge + $delivery_fee;
        }

        $conn->query("INSERT INTO orders
            (reference_no, customer_id, service_id, quantity, surcharge, total_amount, pickup_date, notes, service_type, delivery_fee, order_status, created_at)
            VALUES
            ('$reference', $customer_id, $service_id, $quantity, $surcharge, $total, '$pickup', '$notes', '$service_type', $delivery_fee, 'Received', NOW())");

        $order_id_new = $conn->insert_id;

        // If pickup or delivery, create delivery record
        if ($service_type !== 'walk_in') {
            $del_address  = sanitize($conn, $_POST['delivery_address'] ?? '');
            $assigned_to  = (int)($_POST['assigned_to'] ?? 0);
            $scheduled    = sanitize($conn, $_POST['scheduled_at'] ?? '');
            $del_notes    = sanitize($conn, $_POST['delivery_notes'] ?? '');
            $del_type     = ($service_type === 'pickup') ? 'pickup' : 'delivery';
            $assigned_val = $assigned_to > 0 ? $assigned_to : 'NULL';
            $sched_val    = $scheduled ? "'$scheduled'" : 'NULL';
            $init_status  = $assigned_to > 0 ? 'Assigned' : 'Pending';

            $conn->query("INSERT INTO deliveries
                (order_id, service_type, delivery_address, assigned_to, scheduled_at, delivery_status, delivery_fee, notes, created_at)
                VALUES ($order_id_new, '$del_type', '$del_address', $assigned_val, $sched_val, '$init_status', $delivery_fee, '$del_notes', NOW())");

            $del_id = $conn->insert_id;
            // Log initial status
            $by = (int)$_SESSION['user']['id'];
            $conn->query("INSERT INTO delivery_logs (delivery_id, status, remarks, updated_by, logged_at)
                VALUES ($del_id, '$init_status', 'Order created', $by, NOW())");
        }

        $success = "Order #$reference created! " . ($service_type !== 'walk_in' ? "Delivery request added." : "");
    }
}

$filterStatus = sanitize($conn, $_GET['status'] ?? '');
$filterType   = sanitize($conn, $_GET['type'] ?? '');

$where_parts = [];
if ($filterStatus) $where_parts[] = "orders.order_status = '$filterStatus'";
if ($filterType)   $where_parts[] = "orders.service_type = '$filterType'";
$where = $where_parts ? "WHERE " . implode(" AND ", $where_parts) : "";

$orders = $conn->query("
    SELECT orders.*, customers.fullname, services.service_name,
           deliveries.delivery_status, deliveries.id as delivery_id
    FROM orders
    JOIN customers ON orders.customer_id = customers.id
    JOIN services  ON orders.service_id  = services.id
    LEFT JOIN deliveries ON orders.id = deliveries.order_id
    $where
    ORDER BY orders.created_at DESC
");

$customers_list  = $conn->query("SELECT * FROM customers ORDER BY fullname");
$services_list2  = $conn->query("SELECT * FROM services ORDER BY service_name");
$delivery_staff  = $conn->query("SELECT * FROM users WHERE role='delivery' AND is_active=1 ORDER BY fullname");
$staff_arr = [];
while ($s = $delivery_staff->fetch_assoc()) $staff_arr[] = $s;
?>

<div class="page-header">
    <h3><i class="fas fa-shopping-basket me-2 text-primary"></i> Order Management</h3>
    <p>Create and manage laundry orders — Walk-in, Pickup & Delivery</p>
</div>

<?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<!-- Create Order -->
<div class="card mb-4">
    <div class="card-header-custom"><i class="fas fa-plus-circle"></i> Create New Order</div>
    <div class="p-4">
        <form method="POST" id="orderForm">
            <input type="hidden" name="service_type" id="service_type_val" value="walk_in">

            <!-- Service Type Selector -->
            <div class="mb-4">
                <label class="form-label d-block mb-2">Service Type *</label>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="service-type-btn selected" id="btn_walkin" onclick="setType('walk_in')">
                            <i class="fas fa-store"></i>
                            <strong>Walk-in</strong>
                            <div style="font-size:0.78rem; color:#6c7a8a; margin-top:4px;">Customer drops off laundry</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="service-type-btn" id="btn_pickup" onclick="setType('pickup')">
                            <i class="fas fa-box-open"></i>
                            <strong>Pickup</strong>
                            <div style="font-size:0.78rem; color:#6c7a8a; margin-top:4px;">We pick up from customer</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="service-type-btn" id="btn_delivery" onclick="setType('delivery')">
                            <i class="fas fa-motorcycle"></i>
                            <strong>Delivery</strong>
                            <div style="font-size:0.78rem; color:#6c7a8a; margin-top:4px;">We deliver clean laundry</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Common Fields -->
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label">Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select Customer</option>
                        <?php while ($c = $customers_list->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['fullname']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Service *</label>
                    <select name="service_id" class="form-select" required onchange="updatePriceInfo(this)">
                        <option value="">Select Service</option>
                        <?php while ($s = $services_list2->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" data-price="<?= $s['price'] ?>" data-type="<?= $s['pricing_type'] ?>">
                                <?= htmlspecialchars($s['service_name']) ?> — ₱<?= number_format($s['price'],2) ?>/<?= $s['pricing_type']==='flat'?'flat':($s['pricing_type']==='per_kg'?'kg':'item') ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small id="priceHint" class="text-muted"></small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity / Weight *</label>
                    <input type="number" step="0.1" name="quantity" id="qty_input" class="form-control" placeholder="0" required min="0.1" oninput="calcTotal()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Surcharge (₱)</label>
                    <input type="number" step="0.01" name="surcharge" id="surcharge_input" class="form-control" value="0" oninput="calcTotal()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Pickup Date *</label>
                    <input type="date" name="pickup" class="form-control" required min="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <!-- Delivery/Pickup Extra Fields -->
            <div id="delivery_fields" style="display:none;" class="border rounded-3 p-3 mb-3" style="background:#f8faff;">
                <h6 class="mb-3" id="delivery_section_title"><i class="fas fa-motorcycle me-2 text-primary"></i>Delivery / Pickup Details</h6>
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" id="addr_label">Delivery Address *</label>
                        <textarea name="delivery_address" class="form-control" rows="2" placeholder="Full address for pickup/delivery..."></textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Assign Delivery Staff</label>
                        <select name="assigned_to" class="form-select">
                            <option value="">Unassigned (assign later)</option>
                            <?php foreach ($staff_arr as $st): ?>
                                <option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Schedule</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Delivery Fee (₱)</label>
                        <input type="number" step="0.01" name="delivery_fee" id="delivery_fee_input" class="form-control" value="50" oninput="calcTotal()">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Delivery Notes</label>
                        <input name="delivery_notes" class="form-control" placeholder="e.g. Landmark, gate code, special instructions...">
                    </div>
                </div>
            </div>

            <!-- Total Preview -->
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Special Notes</label>
                    <input name="notes" class="form-control" placeholder="Laundry special instructions...">
                </div>
                <div class="col-md-3">
                    <div class="p-3 rounded-3" style="background:linear-gradient(135deg,#e3f2fd,#f0f8ff); border:1.5px solid #90caf9;">
                        <div style="font-size:0.78rem; color:#1565c0; font-weight:600; text-transform:uppercase;">Estimated Total</div>
                        <div id="totalPreview" style="font-size:1.6rem; font-weight:700; color:#1565c0;">₱0.00</div>
                    </div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button name="add" class="btn btn-success w-100" style="padding:12px;">
                        <i class="fas fa-plus me-2"></i>Create Order
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Orders List -->
<div class="card">
    <div class="card-header-custom justify-content-between">
        <span><i class="fas fa-list"></i> Orders List</span>
        <form method="GET" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" style="width:150px;">
                <option value="">All Status</option>
                <?php foreach(['Received','Processing','Finishing','Ready','Released'] as $st): ?>
                    <option value="<?= $st ?>" <?= $filterStatus===$st?'selected':'' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-select form-select-sm" style="width:140px;">
                <option value="">All Types</option>
                <option value="walk_in" <?= $filterType==='walk_in'?'selected':'' ?>>Walk-in</option>
                <option value="pickup"  <?= $filterType==='pickup'?'selected':'' ?>>Pickup</option>
                <option value="delivery"<?= $filterType==='delivery'?'selected':'' ?>>Delivery</option>
            </select>
            <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            <?php if($filterStatus||$filterType): ?><a href="orders.php" class="btn btn-secondary btn-sm">Clear</a><?php endif; ?>
        </form>
    </div>
    <div class="p-3">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Delivery</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $orders->fetch_assoc()): ?>
                <?php
                $typeIcon = ['walk_in'=>'<i class="fas fa-store text-secondary"></i> Walk-in',
                             'pickup' =>'<i class="fas fa-box-open text-warning"></i> Pickup',
                             'delivery'=>'<i class="fas fa-motorcycle text-info"></i> Delivery'][$row['service_type']] ?? $row['service_type'];
                ?>
                <tr>
                    <td><span class="text-primary fw-bold"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                    <td><small><?= htmlspecialchars($row['service_name']) ?></small></td>
                    <td><small><?= $typeIcon ?></small></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><strong>₱<?= number_format($row['total_amount'], 2) ?></strong></td>
                    <td>
                        <?php if($row['payment_status']==='Paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status status-<?= $row['order_status'] ?>"><?= $row['order_status'] ?></span></td>
                    <td>
                        <?php if($row['service_type'] !== 'walk_in' && $row['delivery_id']): ?>
                            <?php $ds = str_replace(' ','',$row['delivery_status']); ?>
                            <span class="badge-status ds-<?= $ds ?>"><?= $row['delivery_status'] ?></span>
                            <a href="delivery.php?order_id=<?= $row['id'] ?>" class="btn btn-outline-info btn-sm ms-1" title="Track"><i class="fas fa-map-marker-alt"></i></a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                            <select name="status" class="form-select form-select-sm" style="width:120px;">
                                <?php foreach(['Received','Processing','Finishing','Ready','Released'] as $st): ?>
                                    <option <?= $st===$row['order_status']?'selected':'' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button name="update_status" class="btn btn-primary btn-sm"><i class="fas fa-check"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let selectedType = 'walk_in';
let selectedPrice = 0;
let selectedPricingType = '';

function setType(type) {
    selectedType = type;
    document.getElementById('service_type_val').value = type;
    ['walkin','pickup','delivery'].forEach(t => document.getElementById('btn_'+t).classList.remove('selected'));
    const map = {walk_in:'walkin', pickup:'pickup', delivery:'delivery'};
    document.getElementById('btn_'+map[type]).classList.add('selected');

    const delFields = document.getElementById('delivery_fields');
    const addrLabel = document.getElementById('addr_label');
    const title     = document.getElementById('delivery_section_title');

    if (type === 'walk_in') {
        delFields.style.display = 'none';
    } else {
        delFields.style.display = 'block';
        if (type === 'pickup') {
            addrLabel.textContent = 'Pickup Address *';
            title.innerHTML = '<i class="fas fa-box-open me-2 text-warning"></i>Pickup Details';
        } else {
            addrLabel.textContent = 'Delivery Address *';
            title.innerHTML = '<i class="fas fa-motorcycle me-2 text-info"></i>Delivery Details';
        }
    }
    calcTotal();
}

function updatePriceInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    selectedPrice = parseFloat(opt.dataset.price || 0);
    selectedPricingType = opt.dataset.type || '';
    const label = selectedPricingType === 'per_kg' ? 'per kg' : selectedPricingType === 'per_item' ? 'per item' : 'flat rate';
    document.getElementById('priceHint').textContent = selectedPrice > 0 ? `₱${selectedPrice.toFixed(2)} ${label}` : '';
    calcTotal();
}

function calcTotal() {
    const qty      = parseFloat(document.getElementById('qty_input').value || 0);
    const surcharge= parseFloat(document.getElementById('surcharge_input').value || 0);
    const delFee   = selectedType !== 'walk_in' ? parseFloat(document.getElementById('delivery_fee_input')?.value || 50) : 0;
    let base = 0;
    if (selectedPricingType === 'per_kg' || selectedPricingType === 'per_item') {
        base = selectedPrice * qty;
    } else if (selectedPricingType === 'flat') {
        base = selectedPrice;
    }
    const total = base + surcharge + delFee;
    document.getElementById('totalPreview').textContent = '₱' + total.toFixed(2);
}
</script>

<?php include("includes/footer.php"); ?>