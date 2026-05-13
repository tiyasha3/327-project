<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

include "db.php";

// STRICT: PHP session required — no localStorage fallback
if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
    echo json_encode(["success" => false, "message" => "not_logged_in"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request."]);
    exit();
}

$employer_id = intval($_SESSION['user_id']);

// Verify employer role from DB — never trust client
$roleStmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
$roleStmt->bind_param("i", $employer_id);
$roleStmt->execute();
$roleRow = $roleStmt->get_result()->fetch_assoc();
$roleStmt->close();

if (!$roleRow || $roleRow['role'] !== 'employer') {
    echo json_encode(["success" => false, "message" => "Only employers can post jobs."]);
    exit();
}

$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$salary      = floatval($_POST['salary']  ?? 0);
$category    = trim($_POST['category']    ?? 'General');
$location    = trim($_POST['location']    ?? '');
$duration    = trim($_POST['duration']    ?? 'Flexible');

if (empty($title) || empty($description) || $salary <= 0) {
    echo json_encode(["success" => false, "message" => "Please fill in all required fields."]);
    exit();
}

$stmt = $conn->prepare(
    "INSERT INTO jobs (employer_id, title, description, salary, category, location, duration, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'open')"
);
$stmt->bind_param("issdsss", $employer_id, $title, $description, $salary, $category, $location, $duration);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "job_id" => $stmt->insert_id, "message" => "Job posted successfully!"]);
} else {
    echo json_encode(["success" => false, "message" => "DB Error: " . $conn->error]);
}
$stmt->close();
$conn->close();
?>
