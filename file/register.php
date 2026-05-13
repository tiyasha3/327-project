<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$name  = trim($_POST['name']  ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$pw    = $_POST['password']   ?? '';
$age   = intval($_POST['age'] ?? 0);
$role  = $_POST['role']       ?? 'teen';

/* ── Validate ─────────────────────────────────── */
if (empty($name) || empty($email) || empty($pw)) {
    echo json_encode(["success" => false, "message" => "Name, email, and password are required."]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email address."]);
    exit();
}

if (strlen($pw) < 8) {
    echo json_encode(["success" => false, "message" => "Password must be at least 8 characters."]);
    exit();
}

$role = in_array($role, ['teen', 'employer']) ? $role : 'teen';

/* ── Check duplicate email (prepared statement) ── */
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "This email is already registered. Please sign in."]);
    $check->close();
    exit();
}
$check->close();

/* ── Hash password & insert ──────────────────── */
$hashed = password_hash($pw, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, age, role) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssis", $name, $email, $phone, $hashed, $age, $role);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "User Registered Successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Registration failed: " . $conn->error]);
}

$stmt->close();
$conn->close();
?>
