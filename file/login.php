<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$email = trim($_POST['email']    ?? '');
$pw    = trim($_POST['password'] ?? '');

if (empty($email) || empty($pw)) {
    echo json_encode(["success" => false, "message" => "Email and password are required."]);
    exit();
}

$stmt = $conn->prepare("SELECT user_id, name, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "No account found with that email address."]);
    $stmt->close();
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$passwordMatches = false;

if (password_verify($pw, $user['password'])) {
    $passwordMatches = true;
} elseif ($user['password'] === $pw) {
    $passwordMatches = true;
    $newHash = password_hash($pw, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $upd->bind_param("si", $newHash, $user['user_id']);
    $upd->execute();
    $upd->close();
}

if (!$passwordMatches) {
    echo json_encode(["success" => false, "message" => "Incorrect password. Please try again."]);
    exit();
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['name']    = $user['name'];
$_SESSION['role']    = $user['role'] ?? 'teen';

echo json_encode([
    "success" => true,
    "user_id" => $user['user_id'],
    "name"    => $user['name'],
    "role"    => $user['role'] ?? 'teen',
    "message" => "Login successful"
]);

$conn->close();
