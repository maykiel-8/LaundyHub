<?php include("customer_header.php"); include("../config/db.php");
$cust_id = (int)$_SESSION['customer']['customer_id'];

// Stats for this customer
$totalOrders  = $conn->query("SELECT COUNT(*) as c FROM orders WHERE customer_id=$cust_id")->fetch_assoc()['c'];
$pendingOrders= $conn->query("SELECT COUNT(*) as c FROM orders WHERE customer_id=$cust_id AND order_status NOT IN ('Released')")->fetch_assoc()['c'];
$unpaidOrders = $conn->query("SELECT COUNT(*) as c FROM orders WHERE customer_id=$cust_id AND payment_status='Unpaid'")->fetch_assoc()['c'];
$totalSpent   = $conn->query("SELECT SUM(total_amount) as t FROM orders WHERE customer_id=$cust_id AND payment_status='Paid'")->fetch_assoc()['t'] ?? 0;

// Recent orders
$recent = $conn->query("
    SELECT o.*, s.service_name
    FROM orders o
    JOIN services s ON o.service_id = s.id
    WHERE o.customer_id = $cust_id
    ORDER BY o.created_at DESC
    LIMIT 5
");
?>

<div class="page-header">
    <h3><i class="fas fa-home me-2 text-primary"></i> Welcome back, <?= htmlspecialchars($_SESSION['customer']['fullname']) ?>!</h3>
    <p><?= date("l, F j, Y") ?> &mdash; Here's your laundry summary</p>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card bg-blue">
            <h6>Total Orders</h6>
            <h2><?= $totalOrders ?></h2>
            <i class="fas fa-shopping-basket stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-orange">
            <h6>Active Orders</h6>
            <h2><?= $pendingOrders ?></h2>
            <i class="fas fa-clock stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-purple">
            <h6>Unpaid</h6>
            <h2><?= $unpaidOrders ?></h2>
            <i class="fas fa-file-invoice-dollar stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card bg-green">
            <h6>Total Spent</h6>
            <h2 style="font-size:1.4rem;">₱<?= number_format($totalSpent, 0) ?></h2>
            <i class="fas fa-peso-sign stat-icon"></i>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="customer_preorder.php" class="text-decoration-none">
            <div class="card p-4 text-center h-100" style="border:2px dashed #90caf9; background:#f0f8ff; transition:all 0.2s;" onmouseover="this.style.background='#e3f2fd'" onmouseout="this.style.background='#f0f8ff'">
                <i class="fas fa-plus-circle text-primary" style="font-size:2.2rem; margin-bottom:10px;"></i>
                <h6 style="font-weight:700; color:#1565c0;">Place a Pre-Order</h6>
                <p style="font-size:0.82rem; color:#6c7a8a; margin:0;">Book your laundry service in advance</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="customer_myorders.php" class="text-decoration-none">
            <div class="card p-4 text-center h-100" style="border:2px dashed #a5d6a7; background:#f0fff4; transition:all 0.2s;" onmouseover="this.style.background='#e8f5e9'" onmouseout="this.style.background='#f0fff4'">
                <i class="fas fa-list text-success" style="font-size:2.2rem; margin-bottom:10px;"></i>
                <h6 style="font-weight:700; color:#1b5e20;">Track My Orders</h6>
                <p style="font-size:0.82rem; color:#6c7a8a; margin:0;">Check status of your current orders</p>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="customer_services.php" class="text-decoration-none">
            <div class="card p-4 text-center h-100" style="border:2px dashed #ce93d8; background:#fdf4ff; transition:all 0.2s;" onmouseover="this.style.background='#f3e5f5'" onmouseout="this.style.background='#fdf4ff'">
                <i class="fas fa-tags" style="font-size:2.2rem; margin-bottom:10px; color:#8e24aa;"></i>
                <h6 style="font-weight:700; color:#6a1b9a;">View Services</h6>
                <p style="font-size:0.82rem; color:#6c7a8a; margin:0;">Browse our laundry services & pricing</p>
            </div>
        </a>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header-custom justify-content-between">
        <span><i class="fas fa-history"></i> Recent Orders</span>
        <a href="customer_myorders.php" class="btn btn-primary btn-sm">View All</a>
    </div>
    <div class="p-3">
        <?php if ($recent->num_rows === 0): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-soap" style="font-size:2.5rem; opacity:0.2; margin-bottom:10px; display:block;"></i>
                <p>No orders yet. <a href="customer_preorder.php">Place your first order!</a></p>
            </div>
        <?php else: ?>
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Service</th>
                    <th>Total</th>
                    <th>Pickup Date</th>
                    <th>Order Status</th>
                    <th>Payment</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $recent->fetch_assoc()): ?>
                <tr>
                    <td><span class="text-primary fw-bold"><?= htmlspecialchars($row['reference_no']) ?></span>
                        <?php if($row['is_preorder']): ?><span class="badge bg-pink ms-1" style="background:#fce4ec;color:#880e4f;font-size:0.65rem;">Pre-Order</span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                    <td><strong>₱<?= number_format($row['total_amount'], 2) ?></strong></td>
                    <td><?= $row['pickup_date'] ? date("M j, Y", strtotime($row['pickup_date'])) : '—' ?></td>
                    <td><span class="badge-status status-<?= $row['order_status'] ?>"><?= $row['order_status'] ?></span></td>
                    <td>
                        <?php if($row['payment_status']==='Paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Unpaid</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Account Info -->
<div class="card mt-4">
    <div class="card-header-custom"><i class="fas fa-user"></i> My Account Info</div>
    <div class="p-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="text-muted" style="font-size:0.78rem; text-transform:uppercase; font-weight:600;">Full Name</div>
                <div class="fw-bold"><?= htmlspecialchars($_SESSION['customer']['fullname']) ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:0.78rem; text-transform:uppercase; font-weight:600;">Contact</div>
                <div><?= htmlspecialchars($_SESSION['customer']['contact_number'] ?? '—') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:0.78rem; text-transform:uppercase; font-weight:600;">Email</div>
                <div><?= htmlspecialchars($_SESSION['customer']['email'] ?? '—') ?></div>
            </div>
            <div class="col-md-3">
                <div class="text-muted" style="font-size:0.78rem; text-transform:uppercase; font-weight:600;">Address</div>
                <div><?= htmlspecialchars($_SESSION['customer']['address'] ?? '—') ?></div>
            </div>
        </div>
    </div>
</div>

<?php include("customer_footer.php"); ?>