<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

include "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(["success" => false, "error" => "not_logged_in", "message" => "Please login first"]);
    exit();
}
$job_id  = intval($_POST['job_id'] ?? 0);
$message = trim($_POST['message']  ?? '');

if ($job_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid job ID."]);
    exit();
}

$role = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
$role->bind_param("i", $user_id);
$role->execute();
$role_row = $role->get_result()->fetch_assoc();
$role->close();
if (!$role_row || $role_row['role'] !== 'teen') {
    echo json_encode(["success" => false, "message" => "Only teen accounts can apply for jobs."]);
    exit();
}

$job = $conn->prepare("SELECT status FROM jobs WHERE job_id = ? LIMIT 1");
$job->bind_param("i", $job_id);
$job->execute();
$job_row = $job->get_result()->fetch_assoc();
$job->close();
if (!$job_row) {
    echo json_encode(["success" => false, "message" => "Job not found."]);
    exit();
}
if ($job_row['status'] !== 'open') {
    echo json_encode(["success" => false, "message" => "This job is no longer accepting applications."]);
    exit();
}

/* ── Check duplicate ── */
$check = $conn->prepare("SELECT application_id FROM applications WHERE user_id = ? AND job_id = ?");
if (!$check) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $conn->error]);
    exit();
}
$check->bind_param("ii", $user_id, $job_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "You have already applied for this job."]);
    $check->close();
    exit();
}
$check->close();

/* ── Insert ── */
$stmt = $conn->prepare("INSERT INTO applications (user_id, job_id, message, status) VALUES (?, ?, ?, 'pending')");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $conn->error]);
    exit();
}
$stmt->bind_param("iis", $user_id, $job_id, $message);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Applied Successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
}

$stmt->close();
$conn->close();
