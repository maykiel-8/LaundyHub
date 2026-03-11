<?php include("includes/header.php"); include("config/db.php"); ?>

<?php
// Stats
$totalSales    = $conn->query("SELECT SUM(total_amount) as total FROM orders")->fetch_assoc();
$totalOrders   = $conn->query("SELECT COUNT(*) as total FROM orders")->fetch_assoc();
$pending       = $conn->query("SELECT COUNT(*) as total FROM orders WHERE order_status != 'Released'")->fetch_assoc();
$totalCustomers= $conn->query("SELECT COUNT(*) as total FROM customers")->fetch_assoc();
$todaySales    = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_assoc();
$todayOrders   = $conn->query("SELECT COUNT(*) as total FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_assoc();

// Recent orders
$recentOrders  = $conn->query("
    SELECT orders.*, customers.fullname, services.service_name
    FROM orders
    JOIN customers ON orders.customer_id = customers.id
    JOIN services  ON orders.service_id  = services.id
    ORDER BY orders.created_at DESC LIMIT 8
");

// Monthly sales for chart (last 6 months)
$monthlySales = $conn->query("
    SELECT DATE_FORMAT(created_at,'%b %Y') as month, SUM(total_amount) as total
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY YEAR(created_at), MONTH(created_at)
");
$chartLabels = []; $chartData = [];
while($r = $monthlySales->fetch_assoc()){
    $chartLabels[] = $r['month'];
    $chartData[]   = (float)$r['total'];
}

// Status breakdown
$statusBreakdown = $conn->query("
    SELECT order_status, COUNT(*) as total FROM orders
    WHERE order_status != 'Released'
    GROUP BY order_status
");
$statusLabels = []; $statusData = []; $statusColors = [];
$colorMap = ['Received'=>'#1e88e5','Processing'=>'#fb8c00','Finishing'=>'#8e24aa','Ready'=>'#43a047'];
while($s = $statusBreakdown->fetch_assoc()){
    $statusLabels[] = $s['order_status'];
    $statusData[]   = (int)$s['total'];
    $statusColors[] = $colorMap[$s['order_status']] ?? '#90a4ae';
}
?>

<div class="page-header">
    <h3><i class="fas fa-chart-line me-2 text-primary"></i> Dashboard</h3>
    <p>Welcome back, <strong><?= htmlspecialchars($_SESSION['user']['fullname']) ?></strong> &mdash; <?= date("l, F j, Y") ?></p>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-sales">
            <h6>Total Sales</h6>
            <h2>&#8369;<?= number_format($totalSales['total'] ?? 0, 2) ?></h2>
            <small>Today: &#8369;<?= number_format($todaySales['total'] ?? 0, 2) ?></small>
            <i class="fas fa-peso-sign stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-orders">
            <h6>Total Orders</h6>
            <h2><?= $totalOrders['total'] ?></h2>
            <small>Today: <?= $todayOrders['total'] ?> new</small>
            <i class="fas fa-shopping-basket stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-pending">
            <h6>Pending Orders</h6>
            <h2><?= $pending['total'] ?></h2>
            <small>Needs attention</small>
            <i class="fas fa-clock stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-customers">
            <h6>Customers</h6>
            <h2><?= $totalCustomers['total'] ?></h2>
            <small>Registered</small>
            <i class="fas fa-users stat-icon"></i>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-chart-bar"></i> Monthly Sales (Last 6 Months)</div>
            <div class="p-3">
                <canvas id="salesChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-chart-pie"></i> Active Order Status</div>
            <div class="p-3 text-center">
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header-custom justify-content-between">
        <span><i class="fas fa-list"></i> Recent Orders</span>
        <a href="orders.php" class="btn btn-primary btn-sm">View All</a>
    </div>
    <div class="p-3">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Pickup Date</th>
                </tr>
            </thead>
            <tbody>
            <?php while($row = $recentOrders->fetch_assoc()): ?>
                <tr>
                    <td><span class="text-primary fw-bold"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                    <td><?= htmlspecialchars($row['service_name']) ?></td>
                    <td><strong>&#8369;<?= number_format($row['total_amount'], 2) ?></strong></td>
                    <td><span class="badge-status status-<?= $row['order_status'] ?>"><?= $row['order_status'] ?></span></td>
                    <td><?= $row['pickup_date'] ? date("M j, Y", strtotime($row['pickup_date'])) : '—' ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Sales Chart
new Chart(document.getElementById('salesChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Sales (₱)',
            data: <?= json_encode($chartData) ?>,
            backgroundColor: 'rgba(30,136,229,0.75)',
            borderRadius: 8,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});

// Status Doughnut
<?php if (count($statusData) > 0): ?>
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($statusLabels) ?>,
        datasets: [{
            data: <?= json_encode($statusData) ?>,
            backgroundColor: <?= json_encode($statusColors) ?>,
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        cutout: '65%',
        plugins: { legend: { position: 'bottom', labels: { padding: 14, font: { size: 12 } } } }
    }
});
<?php else: ?>
document.getElementById('statusChart').parentElement.innerHTML = '<p class="text-muted text-center py-4">No active orders</p>';
<?php endif; ?>
</script>

<?php include("includes/footer.php"); ?>