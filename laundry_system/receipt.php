<?php
session_start();
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }
include("config/db.php");

$payment_id = (int)($_GET['payment_id'] ?? 0);
if (!$payment_id) { echo "Invalid receipt."; exit(); }

$payment = $conn->query("
    SELECT p.*, o.reference_no, o.total_amount, o.quantity, o.surcharge,
           o.delivery_fee, o.service_type, o.pickup_date, o.notes, o.order_status,
           c.fullname as customer_name, c.contact_number, c.address,
           s.service_name, s.pricing_type, s.price as unit_price,
           u.fullname as cashier_name
    FROM payments p
    JOIN orders    o ON p.order_id = o.id
    JOIN customers c ON o.customer_id = c.id
    JOIN services  s ON o.service_id = s.id
    LEFT JOIN users u ON p.received_by = u.id
    WHERE p.id = $payment_id
")->fetch_assoc();

if (!$payment) { echo "Receipt not found."; exit(); }

$service_type_label = ['walk_in'=>'Walk-in','pickup'=>'Pickup','delivery'=>'Delivery'][$payment['service_type']] ?? '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt — <?= htmlspecialchars($payment['reference_no']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Courier New', monospace; background:#f0f4f9; display:flex; justify-content:center; padding:30px; }
.receipt {
    background:#fff;
    width: 320px;
    padding: 24px 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    border-radius: 8px;
}
.logo { text-align:center; margin-bottom:12px; }
.logo h2 { font-size:1.3rem; font-weight:900; letter-spacing:1px; }
.logo p { font-size:0.7rem; color:#666; margin-top:2px; }
.divider { border:none; border-top:1px dashed #bbb; margin:10px 0; }
.divider-solid { border:none; border-top:2px solid #333; margin:10px 0; }
.row { display:flex; justify-content:space-between; font-size:0.78rem; margin-bottom:4px; }
.row .label { color:#555; }
.row .value { font-weight:600; text-align:right; }
.section-title { font-size:0.65rem; text-transform:uppercase; letter-spacing:1px; color:#888; margin:10px 0 4px; }
.total-row { display:flex; justify-content:space-between; font-size:1rem; font-weight:900; margin-top:6px; }
.change-row { display:flex; justify-content:space-between; font-size:0.95rem; font-weight:700; color:#1b5e20; margin-top:4px; }
.badge { display:inline-block; background:#e8f5e9; color:#1b5e20; border-radius:4px; padding:2px 8px; font-size:0.7rem; font-weight:700; }
.footer-note { text-align:center; font-size:0.68rem; color:#888; margin-top:14px; line-height:1.5; }
.barcode { text-align:center; font-size:0.6rem; letter-spacing:3px; color:#bbb; margin-top:8px; }
@media print {
    body { background:#fff; padding:0; }
    .no-print { display:none; }
    .receipt { box-shadow:none; border-radius:0; }
}
</style>
</head>
<body>

<div class="receipt">
    <div class="logo">
        <div style="font-size:1.8rem;">🧺</div>
        <h2>LaundryHub</h2>
        <p>Laundry Shop Management System</p>
        <p>Official Cash Receipt</p>
    </div>

    <hr class="divider-solid">

    <div class="section-title">Transaction Details</div>
    <div class="row"><span class="label">Reference #</span><span class="value"><?= htmlspecialchars($payment['reference_no']) ?></span></div>
    <div class="row"><span class="label">Date</span><span class="value"><?= date("M j, Y g:i A", strtotime($payment['paid_at'])) ?></span></div>
    <div class="row"><span class="label">Cashier</span><span class="value"><?= htmlspecialchars($payment['cashier_name'] ?? '—') ?></span></div>

    <hr class="divider">

    <div class="section-title">Customer</div>
    <div class="row"><span class="label">Name</span><span class="value"><?= htmlspecialchars($payment['customer_name']) ?></span></div>
    <div class="row"><span class="label">Contact</span><span class="value"><?= htmlspecialchars($payment['contact_number']) ?></span></div>

    <hr class="divider">

    <div class="section-title">Order</div>
    <div class="row"><span class="label">Service</span><span class="value"><?= htmlspecialchars($payment['service_name']) ?></span></div>
    <div class="row">
        <span class="label">Type</span>
        <span class="value"><span class="badge"><?= $service_type_label ?></span></span>
    </div>
    <div class="row">
        <span class="label">Qty / Weight</span>
        <span class="value"><?= $payment['quantity'] ?> <?= $payment['pricing_type']==='per_kg'?'kg':($payment['pricing_type']==='per_item'?'item(s)':'') ?></span>
    </div>
    <div class="row">
        <span class="label">Unit Price</span>
        <span class="value">₱<?= number_format($payment['unit_price'], 2) ?></span>
    </div>
    <?php if ($payment['surcharge'] > 0): ?>
    <div class="row"><span class="label">Surcharge</span><span class="value">₱<?= number_format($payment['surcharge'], 2) ?></span></div>
    <?php endif; ?>
    <?php if ($payment['delivery_fee'] > 0): ?>
    <div class="row"><span class="label">Delivery Fee</span><span class="value">₱<?= number_format($payment['delivery_fee'], 2) ?></span></div>
    <?php endif; ?>
    <?php if ($payment['pickup_date']): ?>
    <div class="row"><span class="label">Pickup Date</span><span class="value"><?= date("M j, Y", strtotime($payment['pickup_date'])) ?></span></div>
    <?php endif; ?>
    <?php if ($payment['notes']): ?>
    <div class="row"><span class="label">Notes</span><span class="value" style="max-width:150px; text-align:right;"><?= htmlspecialchars($payment['notes']) ?></span></div>
    <?php endif; ?>

    <hr class="divider-solid">

    <div class="total-row">
        <span>TOTAL</span>
        <span>₱<?= number_format($payment['total_amount'], 2) ?></span>
    </div>
    <div class="row" style="margin-top:4px;"><span class="label">Cash Received</span><span class="value">₱<?= number_format($payment['amount_paid'], 2) ?></span></div>
    <div class="change-row">
        <span>CHANGE</span>
        <span>₱<?= number_format($payment['change_amount'], 2) ?></span>
    </div>

    <hr class="divider">

    <div class="row"><span class="label">Payment Method</span><span class="value badge" style="background:#e3f2fd; color:#1565c0;">CASH</span></div>
    <div class="row"><span class="label">Status</span><span class="value badge">PAID ✓</span></div>

    <div class="footer-note">
        Thank you for choosing LaundryHub!<br>
        Please keep this receipt for reference.<br>
        <strong>This serves as your official receipt.</strong>
    </div>

    <div class="barcode"><?= str_repeat('|', 30) ?></div>
    <div style="text-align:center; font-size:0.6rem; color:#bbb; margin-top:4px;"><?= htmlspecialchars($payment['reference_no']) ?> · ID:<?= $payment_id ?></div>
</div>

<div class="no-print" style="text-align:center; margin-top:20px;">
    <button onclick="window.print()" style="background:#1e88e5; color:#fff; border:none; padding:10px 28px; border-radius:8px; cursor:pointer; font-size:0.95rem;">
        🖨️ Print Receipt
    </button>
    <button onclick="window.close()" style="background:#6c757d; color:#fff; border:none; padding:10px 20px; border-radius:8px; cursor:pointer; font-size:0.95rem; margin-left:8px;">
        Close
    </button>
</div>

<script>
// Auto-open print dialog
window.onload = () => setTimeout(() => window.print(), 300);
</script>
</body>
</html>