<?php
// ── Session guard — never call session_start() twice ──────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Auth check ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['customer'])) {
    header("Location: customer_login.php");
    exit();
}

$cust      = $_SESSION['customer'];
$cust_id   = (int)$cust['customer_id'];
$cust_name = htmlspecialchars($cust['fullname']);
$current   = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LaundryHub — Customer Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#1e88e5; --primary-dark:#1565c0; --bg:#f0f4f9; }
* { box-sizing:border-box; margin:0; padding:0; }
body { background:var(--bg); font-family:'Segoe UI',sans-serif; }

.cust-navbar {
    height:64px; background:linear-gradient(135deg,#1565c0,#1e88e5);
    display:flex; align-items:center; padding:0 28px; gap:20px;
    position:sticky; top:0; z-index:100;
    box-shadow:0 2px 12px rgba(0,0,0,0.18);
}
.cust-brand { color:#fff; font-size:1.15rem; font-weight:700; text-decoration:none; display:flex; align-items:center; gap:8px; }
.cust-brand:hover { color:#fff; }
.nav-links { display:flex; gap:4px; }
.nav-link-item {
    color:rgba(255,255,255,0.8); text-decoration:none;
    padding:8px 13px; border-radius:8px; font-size:0.86rem; font-weight:500;
    display:flex; align-items:center; gap:6px; transition:all 0.2s;
}
.nav-link-item:hover, .nav-link-item.active { background:rgba(255,255,255,0.2); color:#fff; }
.user-area { margin-left:auto; display:flex; align-items:center; gap:10px; color:rgba(255,255,255,0.9); font-size:0.84rem; }
.avatar {
    width:33px; height:33px; border-radius:50%;
    background:rgba(255,255,255,0.25); border:2px solid rgba(255,255,255,0.4);
    display:flex; align-items:center; justify-content:center;
    font-weight:700; font-size:0.88rem; color:#fff; flex-shrink:0;
}
.btn-logout {
    color:rgba(255,255,255,0.75); font-size:0.78rem; text-decoration:none;
    padding:5px 11px; border:1px solid rgba(255,255,255,0.3); border-radius:7px;
    display:flex; align-items:center; gap:5px; transition:all 0.2s;
}
.btn-logout:hover { background:rgba(255,255,255,0.15); color:#fff; }

.cust-main { max-width:1100px; margin:0 auto; padding:28px 20px; }

.page-header { margin-bottom:22px; padding-bottom:14px; border-bottom:2px solid #e3eaf3; }
.page-header h3 { margin:0; font-weight:700; color:#1a2a3a; }
.page-header p  { margin:4px 0 0; color:#6c7a8a; font-size:0.9rem; }

.card { border:none; border-radius:14px; box-shadow:0 2px 12px rgba(0,0,0,0.07); }
.card-header-custom {
    padding:14px 20px; border-bottom:1px solid #eef2f7;
    font-weight:600; color:#1a2a3a;
    display:flex; align-items:center; gap:10px; border-radius:14px 14px 0 0;
}
.card-header-custom i { color:var(--primary); }

.stat-card { border-radius:14px; padding:22px; color:#fff; position:relative; overflow:hidden; }
.stat-card .stat-icon { position:absolute; right:18px; top:50%; transform:translateY(-50%); font-size:3rem; opacity:0.2; }
.stat-card h6 { margin:0; font-size:0.78rem; opacity:0.85; text-transform:uppercase; letter-spacing:0.5px; }
.stat-card h2 { margin:6px 0 0; font-weight:700; font-size:1.9rem; }
.bg-blue   { background:linear-gradient(135deg,#1e88e5,#42a5f5); }
.bg-green  { background:linear-gradient(135deg,#43a047,#66bb6a); }
.bg-orange { background:linear-gradient(135deg,#fb8c00,#ffa726); }
.bg-purple { background:linear-gradient(135deg,#8e24aa,#ba68c8); }

.table th { font-size:0.78rem; text-transform:uppercase; letter-spacing:0.5px; color:#7a8a9a; border-bottom:2px solid #eef2f7; }
.table td { vertical-align:middle; font-size:0.88rem; color:#2a3a4a; }
.table-hover tbody tr:hover { background:#f5f8ff; }

.badge-status { padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; display:inline-block; }
.status-Received   { background:#e3f2fd; color:#1565c0; }
.status-Processing { background:#fff3e0; color:#e65100; }
.status-Finishing  { background:#f3e5f5; color:#6a1b9a; }
.status-Ready      { background:#e8f5e9; color:#1b5e20; }
.status-Released   { background:#eceff1; color:#455a64; }

.btn-primary { background:var(--primary); border:none; }
.btn-primary:hover { background:var(--primary-dark); }
.form-label { font-size:0.84rem; font-weight:600; color:#4a5568; margin-bottom:4px; }
.form-control, .form-select { border-radius:8px; border:1.5px solid #e2e8f0; font-size:0.88rem; }
.form-control:focus, .form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(30,136,229,0.12); }
.alert { border-radius:10px; font-size:0.9rem; border:none; }

.service-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px,1fr)); gap:16px; }
.service-card {
    background:#fff; border-radius:14px; padding:20px;
    box-shadow:0 2px 12px rgba(0,0,0,0.07); border:1.5px solid #eef2f7; transition:all 0.2s;
}
.service-card:hover { box-shadow:0 6px 20px rgba(30,136,229,0.15); border-color:#90caf9; transform:translateY(-2px); }
</style>
</head>
<body>

<nav class="cust-navbar">
    <a href="customer_dashboard.php" class="cust-brand">
        <i class="fas fa-soap"></i> LaundryHub
    </a>
    <div class="nav-links">
        <a href="customer_dashboard.php"  class="nav-link-item <?= $current==='customer_dashboard.php'?'active':'' ?>"><i class="fas fa-home"></i> Home</a>
        <a href="customer_services.php"   class="nav-link-item <?= $current==='customer_services.php'?'active':'' ?>"><i class="fas fa-tags"></i> Services</a>
        <a href="customer_preorder.php"   class="nav-link-item <?= $current==='customer_preorder.php'?'active':'' ?>"><i class="fas fa-plus-circle"></i> Pre-Order</a>
        <a href="customer_myorders.php"   class="nav-link-item <?= $current==='customer_myorders.php'?'active':'' ?>"><i class="fas fa-list"></i> My Orders</a>
    </div>
    <div class="user-area">
        <div class="avatar"><?= strtoupper(substr($cust['fullname'], 0, 1)) ?></div>
        <span><?= $cust_name ?></span>
        <a href="customer_logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>

<div class="cust-main">