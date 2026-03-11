<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "laundry_system";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Helper: Sanitize input to prevent SQL injection (basic escaping)
function sanitize($conn, $val) {
    return $conn->real_escape_string(trim($val));
}

// ─── Role-Based Permissions ─────────────────────────────────────────────────
// Define which pages/modules each role can access
$ROLE_PERMISSIONS = [
    'admin' => [
        'dashboard.php', 'customers.php', 'orders.php', 'services.php', 
        'payment.php', 'delivery.php', 'reports.php', 'inventory.php', 
        'users.php', 'receipt.php'
    ],
    'cashier' => [
        'dashboard.php', 'customers.php', 'orders.php', 
        'payment.php', 'reports.php', 'receipt.php'
    ],
    'operator' => [
        'dashboard.php', 'orders.php', 'services.php', 'inventory.php', 'receipt.php'
    ],
    'delivery' => [
        'dashboard.php', 'delivery.php', 'receipt.php'
    ]
];

// Check if user role has access to a page
function hasAccessToPage($role, $page) {
    global $ROLE_PERMISSIONS;
    $basename = basename($page);
    return isset($ROLE_PERMISSIONS[$role]) && in_array($basename, $ROLE_PERMISSIONS[$role]);
}

// Check permission and redirect if unauthorized
function requirePermission($allowed_roles) {
    if (!isset($_SESSION['user'])) {
        header("Location: login.php");
        exit();
    }
    
    $current_role = $_SESSION['user']['role'];
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($current_role, $allowed_roles)) {
        http_response_code(403);
        die("<div style='margin-top:50px; text-align:center;'>" .
            "<h2 style='color:#d32f2f;'>Access Denied</h2>" .
            "<p>You do not have permission to access this page.</p>" .
            "<a href='dashboard.php' style='color:#1e88e5;'>Go to Dashboard</a>" .
            "</div>");
    }
}
?>