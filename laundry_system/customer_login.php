<?php
// ── Start session only if not already started ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Already logged in → go to dashboard ───────────────────────────────────
if (isset($_SESSION['customer'])) {
    header("Location: customer_dashboard.php");
    exit();
}

// ── DB connection (inline — no dependency on external sanitize()) ──────────
$conn = new mysqli("localhost", "root", "", "laundry_system");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$error = "";

if (isset($_POST['login'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = md5(trim($_POST['password']));

    $result = $conn->query("
        SELECT ca.id, ca.customer_id, ca.username, ca.is_active,
               c.fullname, c.contact_number, c.address, c.email
        FROM customer_accounts ca
        INNER JOIN customers c ON ca.customer_id = c.id
        WHERE ca.username = '$username'
          AND ca.password = '$password'
        LIMIT 1
    ");

    if ($result === false) {
        $error = "DB error: " . $conn->error . " — Make sure you ran database_patch.sql first.";
    } elseif ($result->num_rows === 0) {
        $error = "Invalid username or password.";
    } else {
        $acc = $result->fetch_assoc();
        if ((int)$acc['is_active'] !== 1) {
            $error = "Your account is deactivated. Please contact the shop.";
        } else {
            $_SESSION['customer'] = [
                'id'             => (int)$acc['id'],
                'customer_id'    => (int)$acc['customer_id'],
                'username'       => $acc['username'],
                'fullname'       => $acc['fullname'],
                'contact_number' => $acc['contact_number'],
                'address'        => $acc['address'],
                'email'          => $acc['email'],
            ];
            $conn->query("UPDATE customer_accounts SET last_login = NOW() WHERE id = " . (int)$acc['id']);
            header("Location: customer_dashboard.php");
            exit();
        }
    }
}
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
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    background: linear-gradient(135deg, #0d47a1 0%, #1976d2 50%, #42a5f5 100%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Segoe UI', sans-serif;
}
.wrap { width: 420px; }
.card {
    background: #fff; border-radius: 20px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.3);
    overflow: hidden; border: none;
}
.card-head {
    background: linear-gradient(135deg, #1565c0, #1e88e5);
    padding: 36px 40px 28px; text-align: center; color: #fff;
}
.avatar {
    width: 68px; height: 68px; background: rgba(255,255,255,0.2);
    border: 2px solid rgba(255,255,255,0.35); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.9rem; margin: 0 auto 14px;
}
.card-head h4 { font-weight: 700; font-size: 1.4rem; margin: 0; }
.card-head p  { opacity: 0.75; font-size: 0.83rem; margin: 4px 0 0; }
.card-body-pad { padding: 30px 36px 34px; }
.form-label { font-size: 0.83rem; font-weight: 600; color: #4a5568; }
.input-group-text {
    background: #f0f5ff; border: 1.5px solid #e2e8f0;
    border-right: none; border-radius: 10px 0 0 10px !important; color: #1e88e5;
}
.form-control {
    border: 1.5px solid #e2e8f0; border-left: none;
    border-radius: 0 10px 10px 0 !important;
    padding: 10px 14px; font-size: 0.93rem;
}
.form-control:focus { border-color: #1e88e5; box-shadow: 0 0 0 3px rgba(30,136,229,0.12); }
.btn-go {
    width: 100%; padding: 12px;
    background: linear-gradient(135deg, #1565c0, #1e88e5);
    color: #fff; border: none; border-radius: 10px;
    font-size: 0.97rem; font-weight: 700; cursor: pointer; transition: opacity 0.2s;
}
.btn-go:hover { opacity: 0.9; }
.err-box {
    background: #fff0f0; border-left: 4px solid #e53935;
    border-radius: 8px; padding: 10px 14px;
    font-size: 0.86rem; color: #b71c1c; margin-bottom: 18px;
}
.note { text-align: center; color: #9aacbe; font-size: 0.79rem; margin-top: 16px; }
.staff-link {
    display: block; text-align: center; margin-top: 14px;
    color: rgba(255,255,255,0.75); font-size: 0.81rem; text-decoration: none;
}
.staff-link:hover { color: #fff; }
.copy { text-align: center; color: rgba(255,255,255,0.5); font-size: 0.74rem; margin-top: 8px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="card-head">
            <div class="avatar"><i class="fas fa-soap"></i></div>
            <h4>Customer Portal</h4>
            <p>Pre-order &amp; Track Your Laundry</p>
        </div>
        <div class="card-body-pad">
            <?php if ($error): ?>
                <div class="err-box"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user fa-sm"></i></span>
                        <input type="text" name="username" class="form-control"
                               placeholder="Enter your username" required autofocus
                               value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock fa-sm"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="Enter your password" required>
                    </div>
                </div>
                <button type="submit" name="login" class="btn-go">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to My Account
                </button>
            </form>
            <div class="note">No account? Ask the shop staff to register you.</div>
        </div>
    </div>
    <a href="login.php" class="staff-link"><i class="fas fa-user-shield me-1"></i>Staff / Admin Login</a>
    <p class="copy">&copy; <?= date("Y") ?> LaundryHub</p>
</div>
</body>
</html>