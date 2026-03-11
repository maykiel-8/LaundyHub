<?php include("includes/header.php"); include("config/db.php");

// Only admin and cashier can access reports
requirePermission(['admin', 'cashier']);

// Date filters
$dateFrom = sanitize($conn, $_GET['date_from'] ?? date('Y-m-01'));
$dateTo   = sanitize($conn, $_GET['date_to']   ?? date('Y-m-d'));

// Summary
$summary = $conn->query("
    SELECT
        COUNT(*)            as total_orders,
        SUM(total_amount)   as total_sales,
        AVG(total_amount)   as avg_order,
        SUM(CASE WHEN payment_status='Paid' THEN total_amount ELSE 0 END) as collected,
        SUM(CASE WHEN payment_status!='Paid' OR payment_status IS NULL THEN total_amount ELSE 0 END) as uncollected
    FROM orders
    WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// By Service
$byService = $conn->query("
    SELECT services.service_name,
           COUNT(orders.id) as order_count,
           SUM(orders.total_amount) as revenue
    FROM orders
    JOIN services ON orders.service_id = services.id
    WHERE DATE(orders.created_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY services.service_name
    ORDER BY revenue DESC
");

// By Status
$byStatus = $conn->query("
    SELECT order_status, COUNT(*) as total
    FROM orders
    WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY order_status
");

// Daily sales for chart
$dailySales = $conn->query("
    SELECT DATE(created_at) as day, SUM(total_amount) as total
    FROM orders
    WHERE DATE(created_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(created_at)
    ORDER BY day
");
$chartDays=[]; $chartAmounts=[];
while($r=$dailySales->fetch_assoc()){
    $chartDays[] = date("M j", strtotime($r['day']));
    $chartAmounts[] = (float)$r['total'];
}

// Detail list
$orders = $conn->query("
    SELECT orders.*, customers.fullname, services.service_name
    FROM orders
    JOIN customers ON orders.customer_id = customers.id
    JOIN services  ON orders.service_id = services.id
    WHERE DATE(orders.created_at) BETWEEN '$dateFrom' AND '$dateTo'
    ORDER BY orders.created_at DESC
");
?>

<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h3><i class="fas fa-file-chart-line me-2 text-primary"></i> Sales Report</h3>
        <p>Financial overview and transaction summary</p>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-end">
        <div>
            <label class="form-label mb-1" style="font-size:0.8rem;">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $dateFrom ?>">
        </div>
        <div>
            <label class="form-label mb-1" style="font-size:0.8rem;">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $dateTo ?>">
        </div>
        <button class="btn btn-primary btn-sm"><i class="fas fa-filter me-1"></i>Filter</button>
    </form>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-sales">
            <h6>Total Sales</h6>
            <h2>&#8369;<?= number_format($summary['total_sales'] ?? 0, 2) ?></h2>
            <i class="fas fa-chart-line stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-orders">
            <h6>Collected</h6>
            <h2>&#8369;<?= number_format($summary['collected'] ?? 0, 2) ?></h2>
            <i class="fas fa-check-circle stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-pending">
            <h6>Uncollected</h6>
            <h2>&#8369;<?= number_format($summary['uncollected'] ?? 0, 2) ?></h2>
            <i class="fas fa-clock stat-icon"></i>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-customers">
            <h6>Total Orders</h6>
            <h2><?= $summary['total_orders'] ?? 0 ?></h2>
            <small>Avg: &#8369;<?= number_format($summary['avg_order'] ?? 0, 2) ?></small>
            <i class="fas fa-list stat-icon"></i>
        </div>
    </div>
</div>

<!-- Chart + Service Breakdown -->
<div class="row g-3 mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-chart-area"></i> Daily Sales Trend</div>
            <div class="p-3">
                <canvas id="dailyChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header-custom"><i class="fas fa-tags"></i> Revenue by Service</div>
            <div class="p-3">
                <?php while($s = $byService->fetch_assoc()): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <small><?= htmlspecialchars($s['service_name']) ?></small>
                            <small><strong>&#8369;<?= number_format($s['revenue'],2) ?></strong></small>
                        </div>
                        <div class="progress" style="height:6px; border-radius:4px;">
                            <?php $pct = ($summary['total_sales'] > 0) ? ($s['revenue']/$summary['total_sales']*100) : 0; ?>
                            <div class="progress-bar" style="width:<?= $pct ?>%; background:#1e88e5; border-radius:4px;"></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header-custom justify-content-between">
        <span><i class="fas fa-table"></i> Order Details</span>
        <span class="text-muted" style="font-size:0.85rem;"><?= $dateFrom ?> to <?= $dateTo ?></span>
    </div>
    <div class="p-3">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Qty</th>
                    <th>Surcharge</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $orders->fetch_assoc()): ?>
                <tr>
                    <td><span class="text-primary fw-bold"><?= htmlspecialchars($row['reference_no']) ?></span></td>
                    <td><?= htmlspecialchars($row['fullname']) ?></td>
                    <td><small><?= htmlspecialchars($row['service_name']) ?></small></td>
                    <td><?= $row['quantity'] ?></td>
                    <td>&#8369;<?= number_format($row['surcharge'], 2) ?></td>
                    <td><strong>&#8369;<?= number_format($row['total_amount'], 2) ?></strong></td>
                    <td>
                        <?php if($row['payment_status']==='Paid'): ?>
                            <span class="badge bg-success">Paid</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status status-<?= $row['order_status'] ?>"><?= $row['order_status'] ?></span></td>
                    <td><small><?= date("M j, Y", strtotime($row['created_at'])) ?></small></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
new Chart(document.getElementById('dailyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($chartDays) ?>,
        datasets: [{
            label: 'Sales (₱)',
            data: <?= json_encode($chartAmounts) ?>,
            borderColor: '#1e88e5',
            backgroundColor: 'rgba(30,136,229,0.08)',
            fill: true,
            tension: 0.3,
            pointBackgroundColor: '#1e88e5',
            pointRadius: 5,
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
</script>

<?php include("includes/footer.php"); ?>