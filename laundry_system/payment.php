<?php include("includes/header.php"); include("config/db.php");

$success = $error = "";
$last_payment_id = null;

// Record Cash Payment
if (isset($_POST['pay'])) {
    $order_id = (int)$_POST['order_id'];
    $amount   = (float)$_POST['amount_paid'];
    $by       = (int)$_SESSION['user']['id'];

    $ord = $conn->query("SELECT * FROM orders WHERE id=$order_id")->fetch_assoc();

    if (!$ord) {
        $error = "Order not found.";
    } elseif ($ord['payment_status'] === 'Paid') {
        $error = "This order is already paid.";
    } elseif ($amount < $ord['total_amount']) {
        $error = "Amount paid (₱" . number_format($amount,2) . ") is less than the total (₱" . number_format($ord['total_amount'], 2) . ").";
    } else {
        $change = $amount - $ord['total_amount'];
        $conn->query("INSERT INTO payments (order_id, amount_paid, change_amount, paid_at, received_by)
            VALUES ($order_id, $amount, $change, NOW(), $by)");
        $last_payment_id = $conn->insert_id;
        $conn->query("UPDATE orders SET payment_status='Paid' WHERE id=$order_id");
        $success = "✅ Payment recorded! Change: ₱" . number_format($change, 2);
    }
}

// Unpaid orders
$unpaid = $conn->query("
    SELECT orders.*, customers.fullname, services.service_name
    FROM orders
    JOIN customers ON orders.customer_id = customers.id
    JOIN services  ON orders.service_id  = services.id
    WHERE orders.payment_status != 'Paid' OR orders.payment_status IS NULL
    ORDER BY orders.created_at DESC
");

// Recent payments
$payments = $conn->query("
    SELECT p.*, o.reference_no, o.total_amount, o.service_type,
           c.fullname, u.fullname as cashier_name
    FROM payments p
    JOIN orders    o ON p.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    LEFT JOIN users u ON p.received_by = u.id
    ORDER BY p.paid_at DESC
    LIMIT 25
");

// Summary today
$todayPay = $conn->query("SELECT SUM(amount_paid) as total, COUNT(*) as cnt FROM payments WHERE DATE(paid_at)=CURDATE()")->fetch_assoc();
?>

<div class="page-header">
    <h3><i class="fas fa-cash-register me-2 text-primary"></i> Payment — Cash Only</h3>
    <p>Record cash payments and generate receipts</p>
</div>

<?php if ($success): ?>
<div class="alert alert-success d-flex justify-content-between align-items-center">
    <span><i class="fas fa-check-circle me-2"></i><?= $success ?></span>
    <?php if ($last_payment_id): ?>
        <a href="receipt.php?payment_id=<?= $last_payment_id ?>" target="_blank" class="btn btn-success btn-sm">
            <i class="fas fa-print me-1"></i>Print Receipt
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<!-- Today Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="stat-card bg-sales">
            <h6>Today's Collections</h6>
            <h2>₱<?= number_format($todayPay['total'] ?? 0, 2) ?></h2>
            <small><?= $todayPay['cnt'] ?> transaction(s)</small>
            <i class="fas fa-money-bill-wave stat-icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-pending">
            <h6>Unpaid Orders</h6>
            <h2><?= $unpaid->num_rows ?></h2>
            <small>Awaiting payment</small>
            <i class="fas fa-clock stat-icon"></i>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card bg-orders">
            <h6>Payment Method</h6>
            <h2 style="font-size:1.2rem; padding-top:4px;"><i class="fas fa-money-bill-alt me-2"></i>Cash Only</h2>
            <small>All transactions are cash</small>
            <i class="fas fa-hand-holding-usd stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Payment Form -->
    <div class="col-md-5">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-money-bill-wave"></i> Record Cash Payment</div>
            <div class="p-4">
                <form method="POST" id="payForm">
                    <div class="mb-3">
                        <label class="form-label">Select Unpaid Order *</label>
                        <select name="order_id" class="form-select" required onchange="setTotal(this)">
                            <option value="">Choose order...</option>
                            <?php
                            $unpaid->data_seek(0);
                            while ($o = $unpaid->fetch_assoc()): ?>
                                <option value="<?= $o['id'] ?>" data-total="<?= $o['total_amount'] ?>">
                                    <?= htmlspecialchars($o['reference_no']) ?> — <?= htmlspecialchars($o['fullname']) ?> — ₱<?= number_format($o['total_amount'], 2) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Amount Due Display -->
                    <div class="mb-3">
                        <label class="form-label">Amount Due</label>
                        <div class="p-3 rounded-3 text-center" style="background:linear-gradient(135deg,#e3f2fd,#f0f8ff); border:1.5px solid #90caf9;">
                            <div style="font-size:0.75rem; color:#1565c0; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Total to Pay</div>
                            <div id="totalDue" style="font-size:2rem; font-weight:700; color:#1565c0;">—</div>
                        </div>
                    </div>

                    <!-- Cash Received -->
                    <div class="mb-3">
                        <label class="form-label">Cash Received (₱) *</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#f0f8ff; border:1.5px solid #90caf9; font-weight:700; color:#1565c0;">₱</span>
                            <input type="number" step="0.01" name="amount_paid" id="amountPaid"
                                class="form-control" style="font-size:1.2rem; font-weight:600;"
                                placeholder="0.00" required oninput="calcChange()" min="0">
                        </div>
                        <!-- Quick cash buttons -->
                        <div class="d-flex gap-1 mt-2 flex-wrap">
                            <?php foreach([20,50,100,200,500,1000] as $amt): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setCash(<?= $amt ?>)">₱<?= $amt ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Change -->
                    <div class="mb-4">
                        <label class="form-label">Change</label>
                        <div class="p-3 rounded-3 text-center" style="background:linear-gradient(135deg,#e8f5e9,#f0fff4); border:1.5px solid #a5d6a7;">
                            <div style="font-size:0.75rem; color:#1b5e20; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Change to Return</div>
                            <div id="changeDisplay" style="font-size:2rem; font-weight:700; color:#1b5e20;">—</div>
                        </div>
                    </div>

                    <button name="pay" class="btn btn-success w-100" style="padding:13px; font-size:1rem; font-weight:600;">
                        <i class="fas fa-check-circle me-2"></i>Confirm Payment
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-history"></i> Recent Payments</div>
            <div class="p-3">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Order Ref</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount Paid</th>
                            <th>Change</th>
                            <th>Cashier</th>
                            <th>Date & Time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($p = $payments->fetch_assoc()):
                        $typeLabel = ['walk_in'=>'Walk-in','pickup'=>'Pickup','delivery'=>'Delivery'][$p['service_type']] ?? '—';
                    ?>
                        <tr>
                            <td><strong class="text-primary"><?= htmlspecialchars($p['reference_no']) ?></strong></td>
                            <td><?= htmlspecialchars($p['fullname']) ?></td>
                            <td><small class="text-muted"><?= $typeLabel ?></small></td>
                            <td class="fw-bold">₱<?= number_format($p['amount_paid'], 2) ?></td>
                            <td>₱<?= number_format($p['change_amount'], 2) ?></td>
                            <td><small><?= htmlspecialchars($p['cashier_name'] ?? '—') ?></small></td>
                            <td><small><?= date("M j, Y g:i A", strtotime($p['paid_at'])) ?></small></td>
                            <td>
                                <a href="receipt.php?payment_id=<?= $p['id'] ?>" target="_blank"
                                   class="btn btn-outline-secondary btn-sm" title="Print Receipt">
                                    <i class="fas fa-print"></i>
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

<script>
let currentTotal = 0;
function setTotal(sel) {
    const opt = sel.options[sel.selectedIndex];
    currentTotal = parseFloat(opt.dataset.total || 0);
    document.getElementById('totalDue').textContent = currentTotal > 0 ? '₱' + currentTotal.toFixed(2) : '—';
    document.getElementById('amountPaid').min = currentTotal;
    document.getElementById('amountPaid').value = '';
    document.getElementById('changeDisplay').textContent = '—';
}
function calcChange() {
    const paid = parseFloat(document.getElementById('amountPaid').value || 0);
    const change = paid - currentTotal;
    const el = document.getElementById('changeDisplay');
    if (paid > 0 && currentTotal > 0) {
        el.textContent = '₱' + Math.max(0, change).toFixed(2);
        el.parentElement.style.borderColor = change >= 0 ? '#a5d6a7' : '#ef9a9a';
        el.style.color = change >= 0 ? '#1b5e20' : '#b71c1c';
    } else {
        el.textContent = '—';
    }
}
function setCash(amount) {
    document.getElementById('amountPaid').value = amount;
    calcChange();
}
</script>

<?php include("includes/footer.php"); ?>