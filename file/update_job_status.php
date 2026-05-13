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
$job_id = intval($_POST['job_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowed = ['open', 'closed', 'filled'];

if ($job_id <= 0 || !in_array($status, $allowed, true)) {
    echo "Invalid data.";
    exit();
}

$check = $conn->prepare("
    SELECT j.job_id
    FROM jobs j
    JOIN users u ON u.user_id = j.employer_id
    WHERE j.job_id = ? AND j.employer_id = ? AND u.role = 'employer'
    LIMIT 1
");
if (!$check) {
    echo "DB Error: " . $conn->error;
    exit();
}
$check->bind_param("ii", $job_id, $employer_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo "not_authorized";
    $check->close();
    exit();
}
$check->close();

$stmt = $conn->prepare("UPDATE jobs SET status = ? WHERE job_id = ? AND employer_id = ?");
if (!$stmt) {
    echo "DB Error: " . $conn->error;
    exit();
}
$stmt->bind_param("sii", $status, $job_id, $employer_id);
$stmt->execute();
echo $stmt->errno ? "error: " . $stmt->error : "success";
$stmt->close();
$conn->close();
?>
