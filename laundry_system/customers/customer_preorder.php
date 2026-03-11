<?php include("customer_header.php"); include("../config/db.php");

$success = $error = "";
$cust_id = (int)$_SESSION['customer']['customer_id'];

// Handle pre-order submission
if (isset($_POST['preorder'])) {
    $service_id   = (int)$_POST['service_id'];
    $quantity     = (float)$_POST['quantity'];
    $pickup_date  = sanitize($conn, $_POST['pickup_date']);
    $notes        = sanitize($conn, $_POST['notes'] ?? '');
    $service_type = sanitize($conn, $_POST['service_type'] ?? 'walk_in');

    $allowed_types = ['walk_in', 'pickup', 'delivery'];
    if (!in_array($service_type, $allowed_types)) $service_type = 'walk_in';

    // Validate pickup date (must be today or future)
    if (strtotime($pickup_date) < strtotime(date('Y-m-d'))) {
        $error = "Pickup date must be today or a future date.";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than 0.";
    } else {
        $service = $conn->query("SELECT * FROM services WHERE id=$service_id")->fetch_assoc();
        if (!$service) {
            $error = "Invalid service selected.";
        } else {
            $reference = "PO-" . strtoupper(substr(uniqid(), -6));

            if ($service['pricing_type'] === 'per_kg' || $service['pricing_type'] === 'per_item') {
                $total = $service['price'] * $quantity;
            } else {
                $total = $service['price'];
            }

            // Delivery address for pickup/delivery
            $del_address = "";
            if ($service_type !== 'walk_in') {
                $del_address = sanitize($conn, $_POST['delivery_address'] ?? '');
                if (empty($del_address)) {
                    $error = "Please provide an address for pickup/delivery.";
                }
            }

            if (!$error) {
                // Check if service_type column exists, insert accordingly
                $cols = "reference_no, customer_id, service_id, quantity, surcharge, total_amount, pickup_date, notes, order_status, payment_status, is_preorder, created_at";
                $vals = "'$reference', $cust_id, $service_id, $quantity, 0, $total, '$pickup_date', '$notes', 'Received', 'Unpaid', 1, NOW()";

                $conn->query("INSERT INTO orders ($cols) VALUES ($vals)");
                $order_id = $conn->insert_id;

                // Create delivery record if pickup/delivery
                if ($service_type !== 'walk_in' && !empty($del_address)) {
                    // Only if deliveries table exists
                    $check = $conn->query("SHOW TABLES LIKE 'deliveries'");
                    if ($check->num_rows > 0) {
                        $del_fee = 50.00;
                        $conn->query("UPDATE orders SET delivery_fee=$del_fee, total_amount=total_amount+$del_fee, service_type='$service_type' WHERE id=$order_id");
                        $conn->query("INSERT INTO deliveries (order_id, service_type, delivery_address, delivery_status, delivery_fee, created_at)
                            VALUES ($order_id, '$service_type', '$del_address', 'Pending', $del_fee, NOW())");
                    }
                }

                $success = "✅ Pre-order <strong>$reference</strong> placed successfully! Our staff will confirm your order shortly.";
            }
        }
    }
}

// Load services for dropdown
$services = $conn->query("SELECT * FROM services ORDER BY service_name");
$preselect = (int)($_GET['service_id'] ?? 0);
?>

<div class="page-header">
    <h3><i class="fas fa-plus-circle me-2 text-primary"></i> Place a Pre-Order</h3>
    <p>Book your laundry service in advance — we'll confirm and process your order</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="border-radius:12px;">
        <i class="fas fa-check-circle me-2"></i><?= $success ?>
        <div class="mt-2">
            <a href="customer_myorders.php" class="btn btn-success btn-sm"><i class="fas fa-list me-1"></i>View My Orders</a>
            <a href="customer_preorder.php" class="btn btn-outline-success btn-sm ms-2"><i class="fas fa-plus me-1"></i>Place Another</a>
        </div>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" style="border-radius:12px;"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div>
<?php endif; ?>

<div class="row g-4">
    <!-- Pre-order Form -->
    <div class="col-md-7">
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-clipboard-list"></i> Order Details</div>
            <div class="p-4">
                <form method="POST" id="preorderForm">
                    <input type="hidden" name="service_type" id="service_type_val" value="walk_in">

                    <!-- Service Type Selector -->
                    <div class="mb-4">
                        <label class="form-label d-block mb-2">How would you like to avail? *</label>
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="border rounded-3 p-3 text-center" id="btn_walkin" style="cursor:pointer; border-color:#1e88e5 !important; background:#e3f2fd; transition:all 0.2s;" onclick="setType('walk_in')">
                                    <div style="font-size:1.4rem;">🏪</div>
                                    <div style="font-size:0.78rem; font-weight:700; color:#1565c0;">Walk-in</div>
                                    <div style="font-size:0.68rem; color:#6c7a8a;">Drop off at shop</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-3 text-center" id="btn_pickup" style="cursor:pointer; transition:all 0.2s;" onclick="setType('pickup')">
                                    <div style="font-size:1.4rem;">📦</div>
                                    <div style="font-size:0.78rem; font-weight:700; color:#555;">Pickup</div>
                                    <div style="font-size:0.68rem; color:#6c7a8a;">We pick up from you</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-3 text-center" id="btn_delivery" style="cursor:pointer; transition:all 0.2s;" onclick="setType('delivery')">
                                    <div style="font-size:1.4rem;">🏍️</div>
                                    <div style="font-size:0.78rem; font-weight:700; color:#555;">Delivery</div>
                                    <div style="font-size:0.68rem; color:#6c7a8a;">We deliver clean items</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Selection -->
                    <div class="mb-3">
                        <label class="form-label">Select Service *</label>
                        <select name="service_id" id="service_select" class="form-select" required onchange="updatePreview()">
                            <option value="">Choose a service...</option>
                            <?php while($s = $services->fetch_assoc()): 
                                $unit = $s['pricing_type']==='per_kg'?'/kg':($s['pricing_type']==='per_item'?'/item':' flat');
                            ?>
                                <option value="<?= $s['id'] ?>"
                                    data-price="<?= $s['price'] ?>"
                                    data-type="<?= $s['pricing_type'] ?>"
                                    <?= $s['id']==$preselect?'selected':'' ?>>
                                    <?= htmlspecialchars($s['service_name']) ?> — ₱<?= number_format($s['price'],2) ?><?= $unit ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Quantity -->
                    <div class="mb-3" id="qty_wrap">
                        <label class="form-label" id="qty_label">Quantity / Weight (kg) *</label>
                        <input type="number" step="0.1" name="quantity" id="qty_input" class="form-control" placeholder="e.g. 3.5" required min="0.1" oninput="updatePreview()">
                        <small class="text-muted" id="qty_hint"></small>
                    </div>

                    <!-- Pickup Date -->
                    <div class="mb-3">
                        <label class="form-label">Preferred Date *</label>
                        <input type="date" name="pickup_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">Date you want to drop off or when you want us to pick up</small>
                    </div>

                    <!-- Delivery Address (shown for pickup/delivery) -->
                    <div class="mb-3" id="addr_wrap" style="display:none;">
                        <label class="form-label" id="addr_label">Pickup/Delivery Address *</label>
                        <textarea name="delivery_address" class="form-control" rows="2" placeholder="Full address including landmark..."></textarea>
                        <small class="text-muted">Delivery fee: <strong>₱50.00</strong> will be added to your total</small>
                    </div>

                    <!-- Notes -->
                    <div class="mb-4">
                        <label class="form-label">Special Instructions <span class="text-muted">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Please separate whites, use unscented detergent, handle with care..."></textarea>
                    </div>

                    <button type="submit" name="preorder" class="btn btn-primary w-100" style="padding:12px; font-size:1rem; font-weight:600;">
                        <i class="fas fa-paper-plane me-2"></i>Submit Pre-Order
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Order Summary + Info -->
    <div class="col-md-5">
        <!-- Live Price Preview -->
        <div class="card mb-3">
            <div class="card-header-custom"><i class="fas fa-calculator"></i> Price Estimate</div>
            <div class="p-4">
                <div id="preview_empty" class="text-center py-3 text-muted">
                    <i class="fas fa-tags" style="font-size:1.8rem; opacity:0.3; display:block; margin-bottom:8px;"></i>
                    Select a service and enter quantity to see estimate
                </div>
                <div id="preview_result" style="display:none;">
                    <div class="mb-2">
                        <div style="font-size:0.75rem; color:#7a8a9a; text-transform:uppercase; font-weight:600;">Service</div>
                        <div id="preview_service" class="fw-bold"></div>
                    </div>
                    <div class="mb-2">
                        <div style="font-size:0.75rem; color:#7a8a9a; text-transform:uppercase; font-weight:600;">Unit Price</div>
                        <div id="preview_unit"></div>
                    </div>
                    <div class="mb-3">
                        <div style="font-size:0.75rem; color:#7a8a9a; text-transform:uppercase; font-weight:600;">Quantity</div>
                        <div id="preview_qty"></div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:0.85rem; color:#6c7a8a;">Estimated Total</span>
                        <span id="preview_total" style="font-size:1.6rem; font-weight:800; color:#1e88e5;">₱0.00</span>
                    </div>
                    <div id="delivery_fee_notice" style="display:none; background:#fff3e0; border-radius:8px; padding:8px; margin-top:10px; font-size:0.78rem; color:#e65100;">
                        <i class="fas fa-motorcycle me-1"></i>+ ₱50.00 delivery fee included
                    </div>
                </div>
            </div>
        </div>

        <!-- How it works -->
        <div class="card">
            <div class="card-header-custom"><i class="fas fa-info-circle"></i> How Pre-Order Works</div>
            <div class="p-4">
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <?php foreach([
                        ['1','fas fa-paper-plane','#1e88e5','Submit','Fill out the form and click Submit Pre-Order'],
                        ['2','fas fa-bell','#fb8c00','Confirmation','Shop staff will review and confirm your order'],
                        ['3','fas fa-soap','#8e24aa','Processing','Your laundry will be washed and processed'],
                        ['4','fas fa-check-circle','#43a047','Ready & Pay','Pick up your clean laundry and pay at the counter'],
                    ] as [$num,$icon,$color,$title,$desc]): ?>
                    <div style="display:flex; gap:12px; align-items:flex-start;">
                        <div style="width:30px; height:30px; border-radius:50%; background:<?= $color ?>; color:#fff; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:700; flex-shrink:0;">
                            <?= $num ?>
                        </div>
                        <div>
                            <div style="font-weight:600; font-size:0.88rem;"><?= $title ?></div>
                            <div style="font-size:0.78rem; color:#6c7a8a;"><?= $desc ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-3" style="background:#e8f5e9; border-radius:8px; padding:10px; font-size:0.8rem; color:#1b5e20;">
                    <i class="fas fa-lightbulb me-1"></i>
                    <strong>Note:</strong> Payment is done upon pickup at the shop (cash only).
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedType = 'walk_in';

function setType(type) {
    selectedType = type;
    document.getElementById('service_type_val').value = type;

    ['walkin','pickup','delivery'].forEach(id => {
        const el = document.getElementById('btn_' + id);
        el.style.borderColor = '#e2e8f0';
        el.style.background = '#fff';
        el.querySelectorAll('div')[1].style.color = '#555';
    });
    const map = {walk_in:'walkin', pickup:'pickup', delivery:'delivery'};
    const sel = document.getElementById('btn_' + map[type]);
    sel.style.borderColor = '#1e88e5';
    sel.style.background = '#e3f2fd';
    sel.querySelectorAll('div')[1].style.color = '#1565c0';

    const addrWrap = document.getElementById('addr_wrap');
    const addrLabel = document.getElementById('addr_label');
    const feeNotice = document.getElementById('delivery_fee_notice');
    if (type !== 'walk_in') {
        addrWrap.style.display = 'block';
        addrLabel.textContent = type === 'pickup' ? 'Pickup Address *' : 'Delivery Address *';
        if (feeNotice) feeNotice.style.display = 'block';
    } else {
        addrWrap.style.display = 'none';
        if (feeNotice) feeNotice.style.display = 'none';
    }
    updatePreview();
}

function updatePreview() {
    const sel = document.getElementById('service_select');
    const opt = sel.options[sel.selectedIndex];
    const qty = parseFloat(document.getElementById('qty_input').value || 0);
    const price = parseFloat(opt.dataset.price || 0);
    const ptype = opt.dataset.type || '';

    const qtyLabel  = document.getElementById('qty_label');
    const qtyHint   = document.getElementById('qty_hint');

    if (ptype === 'per_kg') {
        qtyLabel.textContent = 'Weight (kg) *';
        qtyHint.textContent = 'Enter estimated weight of your laundry';
    } else if (ptype === 'per_item') {
        qtyLabel.textContent = 'Number of Items *';
        qtyHint.textContent = 'Enter number of clothing items';
    } else if (ptype === 'flat') {
        qtyLabel.textContent = 'Quantity *';
        qtyHint.textContent = 'Fixed rate — quantity does not affect price';
    }

    if (!price || !opt.value) {
        document.getElementById('preview_empty').style.display = 'block';
        document.getElementById('preview_result').style.display = 'none';
        return;
    }

    document.getElementById('preview_empty').style.display = 'none';
    document.getElementById('preview_result').style.display = 'block';

    let base = (ptype === 'flat') ? price : (price * Math.max(qty, 0));
    let total = base + (selectedType !== 'walk_in' ? 50 : 0);

    const unit = ptype === 'per_kg' ? '/kg' : ptype === 'per_item' ? '/item' : ' flat';
    document.getElementById('preview_service').textContent = opt.text.split('—')[0].trim();
    document.getElementById('preview_unit').textContent = '₱' + price.toFixed(2) + unit;
    document.getElementById('preview_qty').textContent = ptype === 'flat' ? 'N/A (flat rate)' : (qty > 0 ? qty : '—');
    document.getElementById('preview_total').textContent = base > 0 ? '₱' + total.toFixed(2) : '₱0.00';
}

// Initialize
setType('walk_in');
<?php if($preselect): ?>
document.getElementById('service_select').value = '<?= $preselect ?>';
updatePreview();
<?php endif; ?>
</script>

<?php include("customer_footer.php"); ?>