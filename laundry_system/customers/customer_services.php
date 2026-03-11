<?php include("customer_header.php"); include("../config/db.php");

$services = $conn->query("SELECT * FROM services ORDER BY pricing_type, price");

$icons = [
    'per_kg'   => ['icon' => '⚖️', 'color' => '#e3f2fd', 'text' => '#1565c0', 'label' => 'Per Kilogram'],
    'per_item' => ['icon' => '👕', 'color' => '#f3e5f5', 'text' => '#6a1b9a', 'label' => 'Per Item'],
    'flat'     => ['icon' => '📋', 'color' => '#fff3e0', 'text' => '#e65100', 'label' => 'Flat Rate'],
];
?>

<div class="page-header">
    <h3><i class="fas fa-tags me-2 text-primary"></i> Our Services & Pricing</h3>
    <p>Browse all available laundry services — click "Pre-Order" on any service to book</p>
</div>

<!-- Info Banner -->
<div class="alert mb-4" style="background:linear-gradient(135deg,#e3f2fd,#f0f8ff); border:1.5px solid #90caf9; color:#1565c0; border-radius:12px;">
    <i class="fas fa-info-circle me-2"></i>
    <strong>View Only:</strong> This page shows all available services and their prices. To place an order, click the <strong>Pre-Order</strong> button or go to <a href="customer_preorder.php" style="color:#1565c0;">Pre-Order page</a>.
</div>

<!-- Pricing type legend -->
<div class="row g-3 mb-4">
    <?php foreach ($icons as $type => $meta): ?>
    <div class="col-md-4">
        <div class="card p-3" style="border-left:4px solid <?= $meta['text'] ?>;">
            <div class="d-flex align-items-center gap-3">
                <span style="font-size:1.6rem;"><?= $meta['icon'] ?></span>
                <div>
                    <div class="fw-bold" style="color:<?= $meta['text'] ?>; font-size:0.9rem;"><?= $meta['label'] ?></div>
                    <div style="font-size:0.78rem; color:#6c7a8a;">
                        <?php if($type==='per_kg'): ?>Total = Price × Weight (kg)
                        <?php elseif($type==='per_item'): ?>Total = Price × Number of items
                        <?php else: ?>Fixed price regardless of quantity<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Services Grid -->
<div class="service-grid">
<?php while ($s = $services->fetch_assoc()):
    $meta = $icons[$s['pricing_type']] ?? ['icon'=>'🧺','color'=>'#f5f5f5','text'=>'#333','label'=>$s['pricing_type']];
    $unit = $s['pricing_type']==='per_kg' ? '/kg' : ($s['pricing_type']==='per_item' ? '/item' : ' flat');
?>
    <div class="service-card">
        <div class="service-icon" style="background:<?= $meta['color'] ?>; font-size:1.4rem;">
            <?= $meta['icon'] ?>
        </div>
        <h6 class="fw-bold mb-1"><?= htmlspecialchars($s['service_name']) ?></h6>
        <?php if($s['description']): ?>
            <p style="font-size:0.78rem; color:#6c7a8a; margin-bottom:10px; min-height:32px;"><?= htmlspecialchars($s['description']) ?></p>
        <?php else: ?>
            <p style="min-height:32px;"></p>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="service-price">₱<?= number_format($s['price'], 2) ?><span style="font-size:0.75rem; font-weight:500; color:#6c7a8a;"><?= $unit ?></span></div>
            <span class="service-type-badge" style="background:<?= $meta['color'] ?>; color:<?= $meta['text'] ?>;">
                <?= $meta['label'] ?>
            </span>
        </div>
        <a href="customer_preorder.php?service_id=<?= $s['id'] ?>" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-plus-circle me-1"></i>Pre-Order This
        </a>
    </div>
<?php endwhile; ?>
</div>

<?php if ($services->num_rows === 0): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-tags" style="font-size:3rem; opacity:0.2; display:block; margin-bottom:12px;"></i>
        <p>No services available yet. Please check back later.</p>
    </div>
<?php endif; ?>

<?php include("customer_footer.php"); ?>