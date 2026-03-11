<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$role     = $_SESSION['user']['role'];
$fullname = $_SESSION['user']['fullname'];
$current  = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LaundryHub &mdash; Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
    --primary: #1e88e5;
    --primary-dark: #1565c0;
    --accent: #42a5f5;
    --bg: #f0f4f9;
    --sidebar-w: 240px;
}
body { background: var(--bg); margin: 0; font-family: 'Segoe UI', sans-serif; }

.sidebar {
    width: var(--sidebar-w);
    height: 100vh;
    position: fixed;
    top: 0; left: 0;
    background: linear-gradient(180deg, #1565c0 0%, #1e88e5 100%);
    display: flex;
    flex-direction: column;
    z-index: 100;
    box-shadow: 3px 0 15px rgba(0,0,0,0.15);
    overflow-y: auto;
}
.sidebar-brand {
    padding: 22px 20px 16px;
    color: white;
    font-size: 1.2rem;
    font-weight: 700;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    flex-shrink: 0;
}
.sidebar-brand i { margin-right: 8px; }
.sidebar-user {
    padding: 12px 20px;
    color: rgba(255,255,255,0.85);
    font-size: 0.82rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.1);
    flex-shrink: 0;
}
.sidebar-user span { font-weight: 600; color: #fff; }
.nav-menu { flex: 1; padding: 10px 0; }
.nav-item a {
    display: flex;
    align-items: center;
    padding: 10px 22px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    font-size: 0.88rem;
    transition: all 0.2s;
    gap: 12px;
}
.nav-item a:hover, .nav-item a.active {
    background: rgba(255,255,255,0.18);
    color: #fff;
    border-left: 3px solid #fff;
}
.nav-item a i { width: 18px; text-align: center; }
.nav-label {
    padding: 8px 22px 3px;
    color: rgba(255,255,255,0.45);
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 4px;
}
.sidebar-footer {
    padding: 14px 22px;
    border-top: 1px solid rgba(255,255,255,0.12);
    flex-shrink: 0;
}
.sidebar-footer a {
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    font-size: 0.88rem;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: color 0.2s;
}
.sidebar-footer a:hover { color: #fff; }

.main-content {
    margin-left: var(--sidebar-w);
    padding: 28px;
    min-height: 100vh;
}
.page-header {
    margin-bottom: 22px;
    padding-bottom: 14px;
    border-bottom: 2px solid #e3eaf3;
}
.page-header h3 { margin: 0; font-weight: 700; color: #1a2a3a; }
.page-header p { margin: 4px 0 0; color: #6c7a8a; font-size: 0.9rem; }

.card {
    border: none;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    transition: box-shadow 0.2s;
}
.card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.11); }
.card-header-custom {
    padding: 14px 20px;
    border-bottom: 1px solid #eef2f7;
    font-weight: 600;
    color: #1a2a3a;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-header-custom i { color: var(--primary); }

.stat-card {
    border-radius: 14px;
    padding: 22px;
    color: white;
    position: relative;
    overflow: hidden;
}
.stat-card .stat-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 3rem;
    opacity: 0.2;
}
.stat-card h6 { margin: 0; font-size: 0.78rem; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px; }
.stat-card h2 { margin: 6px 0 0; font-weight: 700; font-size: 1.9rem; }
.stat-card small { opacity: 0.8; font-size: 0.78rem; }
.bg-sales     { background: linear-gradient(135deg, #1e88e5, #42a5f5); }
.bg-orders    { background: linear-gradient(135deg, #43a047, #66bb6a); }
.bg-pending   { background: linear-gradient(135deg, #fb8c00, #ffa726); }
.bg-customers { background: linear-gradient(135deg, #8e24aa, #ba68c8); }
.bg-delivery  { background: linear-gradient(135deg, #00838f, #26c6da); }

.table th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; color: #7a8a9a; border-bottom: 2px solid #eef2f7; }
.table td { vertical-align: middle; font-size: 0.88rem; color: #2a3a4a; }
.table-hover tbody tr:hover { background: #f5f8ff; }

.badge-status { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.status-Received   { background:#e3f2fd; color:#1565c0; }
.status-Processing { background:#fff3e0; color:#e65100; }
.status-Finishing  { background:#f3e5f5; color:#6a1b9a; }
.status-Ready      { background:#e8f5e9; color:#1b5e20; }
.status-Released   { background:#eceff1; color:#455a64; }

/* Delivery status badges */
.ds-Pending         { background:#fff3e0; color:#e65100; }
.ds-Assigned        { background:#e3f2fd; color:#1565c0; }
.ds-OutforPickup    { background:#ede7f6; color:#4527a0; }
.ds-PickedUp        { background:#e8eaf6; color:#283593; }
.ds-OutforDelivery  { background:#e0f7fa; color:#006064; }
.ds-Delivered       { background:#e8f5e9; color:#1b5e20; }
.ds-Failed          { background:#ffebee; color:#b71c1c; }

.btn-primary { background: var(--primary); border: none; }
.btn-primary:hover { background: var(--primary-dark); }

.form-label { font-size: 0.84rem; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
.form-control, .form-select { border-radius: 8px; border: 1.5px solid #e2e8f0; font-size: 0.88rem; }
.form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(30,136,229,0.12); }

.alert { border-radius: 10px; font-size: 0.9rem; border: none; }

/* Service type toggle */
.service-type-btn { cursor:pointer; border:2px solid #e2e8f0; border-radius:10px; padding:12px 16px; text-align:center; transition:all 0.2s; }
.service-type-btn:hover { border-color:var(--primary); background:#f0f8ff; }
.service-type-btn.selected { border-color:var(--primary); background:linear-gradient(135deg,#e3f2fd,#f0f8ff); }
.service-type-btn i { font-size:1.4rem; display:block; margin-bottom:6px; color:var(--primary); }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-soap"></i> LaundryHub
    </div>
    <div class="sidebar-user">
        Logged in as:<br>
        <span><?php echo htmlspecialchars($fullname); ?></span>
        <span class="badge bg-light text-primary ms-1" style="font-size:0.68rem;"><?php echo ucfirst($role); ?></span>
    </div>
    <nav class="nav-menu">
        <div class="nav-label">Main</div>
        <div class="nav-item">
            <a href="dashboard.php" class="<?= $current=='dashboard.php'?'active':'' ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
        </div>

        <?php if(in_array($role, ['admin', 'cashier'])): ?>
        <div class="nav-label">Operations</div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'cashier'])): ?>
        <div class="nav-item">
            <a href="customers.php" class="<?= $current=='customers.php'?'active':'' ?>">
                <i class="fas fa-users"></i> Customers
            </a>
        </div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'cashier', 'operator'])): ?>
        <div class="nav-item">
            <a href="orders.php" class="<?= $current=='orders.php'?'active':'' ?>">
                <i class="fas fa-shopping-basket"></i> Orders
            </a>
        </div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'operator'])): ?>
        <div class="nav-item">
            <a href="services.php" class="<?= $current=='services.php'?'active':'' ?>">
                <i class="fas fa-tags"></i> Services
            </a>
        </div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'cashier'])): ?>
        <div class="nav-item">
            <a href="payment.php" class="<?= $current=='payment.php'?'active':'' ?>">
                <i class="fas fa-cash-register"></i> Payments
            </a>
        </div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'delivery'])): ?>
        <div class="nav-label">Delivery</div>
        <div class="nav-item">
            <a href="delivery.php" class="<?= $current=='delivery.php'?'active':'' ?>">
                <i class="fas fa-motorcycle"></i> Pickup & Delivery
            </a>
        </div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'cashier'])): ?>
        <div class="nav-label">Reports</div>
        <div class="nav-item">
            <a href="reports.php" class="<?= $current=='reports.php'?'active':'' ?>">
                <i class="fas fa-file-alt"></i> Sales Reports
            </a>
        </div>
        <?php endif; ?>

        <?php if(in_array($role, ['admin', 'operator'])): ?>
        <div class="nav-item">
            <a href="inventory.php" class="<?= $current=='inventory.php'?'active':'' ?>">
                <i class="fas fa-boxes"></i> Inventory
            </a>
        </div>
        <?php endif; ?>

        <?php if($role === 'admin'): ?>
        <div class="nav-label">Admin</div>
        <div class="nav-item">
            <a href="users.php" class="<?= $current=='users.php'?'active':'' ?>">
                <i class="fas fa-user-shield"></i> Users
            </a>
        </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="main-content">