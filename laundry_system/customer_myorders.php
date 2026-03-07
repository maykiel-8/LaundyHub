<?php include("customer_header.php"); include("config/db.php");
$cust_id = (int)$_SESSION['customer']['customer_id'];

$filterStatus = sanitize($conn, $_GET['status'] ?? '');
$where = "WHERE o.customer_id = $cust_id";
if ($filterStatus) $where .= " AND o.order_status = '$filterStatus'";

$orders = $conn->query("
    SELECT o.*, s.service_name, s.pricing_type,
           d.delivery_status, d.delivery_address, d.assigned_to,
           u.fullname as rider_name
    FROM orders o
    JOIN services s ON o.service_id = s.id
    LEFT JOIN deliveries d ON o.id = d.order_id
    LEFT JOIN users u ON d.assigned_to = u.id
    $where
    ORDER BY o.created_at DESC
");

// Count by status
$counts = $conn->query("SELECT order_status, COUNT(*) as c FROM orders WHERE customer_id=$cust_id GROUP BY order_status");
$cnt_map = [];
while($r = $counts->fetch_assoc()) $cnt_map[$r['order_status']] = $r['c'];
?>

<div class="page-header">
    <h3><i class="fas fa-list me-2 text-primary"></i> My Orders</h3>
    <p>Track all your laundry orders and their current status</p>
</div>

<!-- Status filter tabs -->
<div class="d-flex gap-2 flex-wrap mb-4">
    <a href="customer_myorders.php" class="btn btn-sm <?= !$filterStatus?'btn-primary':'btn-outline-secondary' ?>">All (<?= array_sum($cnt_map) ?>)</a>
    <?php foreach(['Received','Processing','Finishing','Ready','Released'] as $st): ?>
        <a href="?status=<?= $st ?>" class="btn btn-sm <?= $filterStatus===$st?'btn-primary':'btn-outline-secondary' ?>">
            <?= $st ?> <?php if(isset($cnt_map[$st])): ?><span class="badge bg-light text-dark ms-1"><?= $cnt_map[$st] ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($orders->num_rows === 0): ?>
    <div class="card p-5 text-center text-muted">
        <i class="fas fa-soap" style="font-size:3rem; opacity:0.2; display:block; margin-bottom:14px;"></i>
        <p>No orders found. <a href="customer_preorder.php">Place your first pre-order!</a></p>
    </div>
<?php else: ?>

<?php while ($row = $orders->fetch_assoc()):
    $stype = $row['service_type'] ?? 'walk_in';
    $typeLabel = ['walk_in'=>'Walk-in','pickup'=>'Pickup','delivery'=>'Delivery'][$stype] ?? 'Walk-in';
    $typeIcon  = ['walk_in'=>'🏪','pickup'=>'📦','delivery'=>'🏍️'][$stype] ?? '🏪';
    $typeColor = ['walk_in'=>'#eceff1','pickup'=>'#fff3e0','delivery'=>'#e0f7fa'][$stype] ?? '#eceff1';
    $typeTxt   = ['walk_in'=>'#455a64','pickup'=>'#e65100','delivery'=>'#006064'][$stype] ?? '#333';

    // Status step indicator
    $steps = ['Received','Processing','Finishing','Ready','Released'];
    $curStep = array_search($row['order_status'], $steps);
?>

<div class="card mb-3">
    <!-- Order Header -->
    <div class="card-header-custom justify-content-between" style="background:#fafbff; border-radius:14px 14px 0 0;">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-bold text-primary" style="font-size:1rem;"><?= htmlspecialchars($row['reference_no']) ?></span>
            <span style="background:<?= $typeColor ?>; color:<?= $typeTxt ?>; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:600;">
                <?= $typeIcon ?> <?= $typeLabel ?>
            </span>
            <?php if($row['is_preorder']): ?>
                <span style="background:#fce4ec;color:#880e4f;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600;">📋 Pre-Order</span>
            <?php endif; ?>
            <span class="badge-status status-<?= $row['order_status'] ?>"><?= $row['order_status'] ?></span>
            <?php if($row['payment_status']==='Paid'): ?>
                <span class="badge bg-success">Paid ✓</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark">Unpaid</span>
            <?php endif; ?>
        </div>
        <small class="text-muted"><?= date("M j, Y g:i A", strtotime($row['created_at'])) ?></small>
    </div>

    <div class="p-4">
        <div class="row g-3">
            <!-- Order Details -->
            <div class="col-md-4">
                <div style="font-size:0.72rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">Order Info</div>
                <div><strong><?= htmlspecialchars($row['service_name']) ?></strong></div>
                <div style="font-size:0.85rem; color:#6c7a8a;">
                    Quantity: <?= $row['quantity'] ?> <?= $row['pricing_type']==='per_kg'?'kg':($row['pricing_type']==='per_item'?'items':'') ?>
                </div>
                <?php if($row['pickup_date']): ?>
                <div style="font-size:0.85rem; color:#6c7a8a;">
                    📅 <?= date("M j, Y", strtotime($row['pickup_date'])) ?>
                </div>
                <?php endif; ?>
                <?php if($row['notes']): ?>
                <div style="font-size:0.8rem; color:#6c7a8a; margin-top:4px; font-style:italic;">
                    "<?= htmlspecialchars($row['notes']) ?>"
                </div>
                <?php endif; ?>
                <div class="mt-2">
                    <span style="font-size:1.3rem; font-weight:700; color:#1e88e5;">₱<?= number_format($row['total_amount'], 2) ?></span>
                    <?php if(($row['delivery_fee'] ?? 0) > 0): ?>
                        <span style="font-size:0.75rem; color:#6c7a8a;"> (incl. ₱<?= number_format($row['delivery_fee'],2) ?> delivery)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Progress Tracker -->
            <div class="col-md-5">
                <div style="font-size:0.72rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:10px;">Progress</div>
                <div class="d-flex align-items-center gap-1 flex-wrap">
                <?php foreach($steps as $i => $step):
                    $done    = $i < $curStep;
                    $current = $i === $curStep;
                    $pending = $i > $curStep;
                    $bg      = $done ? '#43a047' : ($current ? '#1e88e5' : '#e0e0e0');
                    $tc      = ($done || $current) ? '#fff' : '#9e9e9e';
                ?>
                    <div style="display:flex; flex-direction:column; align-items:center; gap:4px; flex:1; min-width:50px;">
                        <div style="width:30px;height:30px;border-radius:50%;background:<?= $bg ?>;color:<?= $tc ?>;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;transition:all 0.2s;">
                            <?= $done ? '✓' : ($i+1) ?>
                        </div>
                        <div style="font-size:0.62rem;color:<?= $current?'#1e88e5':($done?'#43a047':'#9e9e9e') ?>;font-weight:<?= $current?'700':'400' ?>;text-align:center;">
                            <?= $step ?>
                        </div>
                    </div>
                    <?php if($i < count($steps)-1): ?>
                        <div style="flex:1;height:2px;background:<?= $done?'#43a047':'#e0e0e0' ?>;min-width:8px;margin-bottom:16px;"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
                </div>

                <?php if($row['order_status'] === 'Ready'): ?>
                <div class="mt-3" style="background:#e8f5e9; border-radius:8px; padding:10px; font-size:0.82rem; color:#1b5e20;">
                    <i class="fas fa-check-circle me-1"></i><strong>Your laundry is ready!</strong> Please come to the shop to pick it up and pay.
                </div>
                <?php elseif($row['order_status'] === 'Released'): ?>
                <div class="mt-3" style="background:#e3f2fd; border-radius:8px; padding:10px; font-size:0.82rem; color:#1565c0;">
                    <i class="fas fa-smile me-1"></i>Order completed. Thank you for choosing LaundryHub!
                </div>
                <?php endif; ?>
            </div>

            <!-- Delivery Info (if applicable) -->
            <div class="col-md-3">
                <?php if ($stype !== 'walk_in' && $row['delivery_status']): ?>
                <div style="font-size:0.72rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">
                    <?= $stype === 'pickup' ? 'Pickup' : 'Delivery' ?> Status
                </div>
                <?php
                $ds_colors = [
                    'Pending'          => ['#fff3e0','#e65100'],
                    'Assigned'         => ['#e3f2fd','#1565c0'],
                    'Out for Pickup'   => ['#ede7f6','#4527a0'],
                    'Picked Up'        => ['#e8eaf6','#283593'],
                    'Out for Delivery' => ['#e0f7fa','#006064'],
                    'Delivered'        => ['#e8f5e9','#1b5e20'],
                    'Failed'           => ['#ffebee','#b71c1c'],
                ];
                [$dsbg, $dstxt] = $ds_colors[$row['delivery_status']] ?? ['#f5f5f5','#333'];
                ?>
                <span style="background:<?= $dsbg ?>; color:<?= $dstxt ?>; padding:5px 12px; border-radius:20px; font-size:0.75rem; font-weight:600; display:inline-block; margin-bottom:8px;">
                    <?= htmlspecialchars($row['delivery_status']) ?>
                </span>
                <?php if($row['rider_name']): ?>
                    <div style="font-size:0.8rem; color:#6c7a8a;"><i class="fas fa-motorcycle me-1"></i><?= htmlspecialchars($row['rider_name']) ?></div>
                <?php endif; ?>
                <?php if($row['delivery_address']): ?>
                    <div style="font-size:0.78rem; color:#6c7a8a; margin-top:4px;"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?= htmlspecialchars(substr($row['delivery_address'],0,60)) ?>...</div>
                <?php endif; ?>
                <?php else: ?>
                <div style="font-size:0.72rem; text-transform:uppercase; color:#7a8a9a; font-weight:600; margin-bottom:8px;">Payment</div>
                <?php if($row['payment_status'] !== 'Paid'): ?>
                    <div style="background:#fff3e0; border-radius:8px; padding:10px; font-size:0.8rem; color:#e65100;">
                        <i class="fas fa-info-circle me-1"></i>Payment to be made at the shop upon pickup.
                    </div>
                <?php else: ?>
                    <div style="background:#e8f5e9; border-radius:8px; padding:10px; font-size:0.8rem; color:#1b5e20;">
                        <i class="fas fa-check-circle me-1"></i>Payment received. Thank you!
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>
<?php endif; ?>

<?php include("customer_footer.php"); ?>