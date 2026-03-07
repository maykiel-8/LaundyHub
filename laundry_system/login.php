<?php
session_start();
include("config/db.php");

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";
if(isset($_POST['login'])){
    $username = sanitize($conn, $_POST['username']);
    $password = md5($_POST['password']);
    $check = $conn->query("SELECT * FROM users WHERE username='$username' AND password='$password'");
    if($check->num_rows > 0){
        $_SESSION['user'] = $check->fetch_assoc();
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LaundryHub &mdash; Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(135deg, #1565c0 0%, #42a5f5 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Segoe UI', sans-serif;
}
.login-wrap {
    width: 420px;
}
.login-card {
    background: white;
    border-radius: 18px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    overflow: hidden;
}
.login-header {
    background: linear-gradient(135deg, #1565c0, #1e88e5);
    padding: 36px 40px 28px;
    text-align: center;
    color: white;
}
.login-header i { font-size: 2.5rem; opacity: 0.9; margin-bottom: 10px; }
.login-header h4 { margin: 0; font-weight: 700; font-size: 1.4rem; }
.login-header p { margin: 4px 0 0; opacity: 0.75; font-size: 0.85rem; }
.login-body { padding: 32px 40px 36px; }
.form-label { font-size: 0.85rem; font-weight: 600; color: #4a5568; }
.form-control {
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    padding: 10px 14px;
    font-size: 0.95rem;
}
.form-control:focus {
    border-color: #1e88e5;
    box-shadow: 0 0 0 3px rgba(30,136,229,0.12);
}
.input-group-text {
    background: #f5f8ff;
    border: 1.5px solid #e2e8f0;
    border-radius: 10px 0 0 10px !important;
    color: #1e88e5;
}
.input-group .form-control { border-radius: 0 10px 10px 0 !important; border-left: none; }
.btn-login {
    background: linear-gradient(135deg, #1565c0, #1e88e5);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px;
    font-size: 1rem;
    font-weight: 600;
    width: 100%;
    transition: opacity 0.2s;
}
.btn-login:hover { opacity: 0.9; color: white; }
.alert { border-radius: 10px; font-size: 0.9rem; }
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-header">
            <div><i class="fas fa-soap"></i></div>
            <h4>LaundryHub</h4>
            <p>Laundry Shop Management System</p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                </div>
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Login
                </button>
            </form>
        </div>
    </div>
    <p class="text-center text-white mt-3" style="opacity:0.7; font-size:0.82rem;">
        <a href="customer_login.php" style="color:rgba(255,255,255,0.8); font-size:0.82rem; text-decoration:none; display:block; text-align:center; margin-bottom:8px;"><i class="fas fa-user me-1"></i>Customer Portal Login</a>
    &copy; <?= date("Y") ?> LaundryHub &mdash; All Rights Reservedcopy; <?= date("Y") ?> LaundryHub &mdash; All Rights Reserved
    </p>
</div>
</body>
</html>