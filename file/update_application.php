<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: text/plain");

include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo "not_logged_in";
    exit();
}

$employer_id = intval($_SESSION['user_id']);
$app_id = intval($_POST['app_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowed = ['pending', 'reviewed', 'accepted', 'rejected'];

if ($app_id <= 0 || !in_array($status, $allowed, true)) {
    echo "Invalid data.";
    exit();
}

$check = $conn->prepare("
    SELECT a.application_id
    FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    JOIN users u ON u.user_id = j.employer_id
    WHERE a.application_id = ? AND j.employer_id = ? AND u.role = 'employer'
    LIMIT 1
");
if (!$check) {
    echo "DB Error: " . $conn->error;
    exit();
}
$check->bind_param("ii", $app_id, $employer_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo "not_authorized";
    $check->close();
    exit();
}
$check->close();

$stmt = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
if (!$stmt) {
    echo "DB Error: " . $conn->error;
    exit();
}
$stmt->bind_param("si", $status, $app_id);
echo $stmt->execute() ? "success" : "error: " . $conn->error;
$stmt->close();
$conn->close();
?>
