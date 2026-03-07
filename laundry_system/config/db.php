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
?>