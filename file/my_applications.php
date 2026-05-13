<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

include "db.php";

// Session is the source of truth. The GET user_id is ignored for safety.
$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    echo json_encode(["error" => "not_logged_in"]);
    exit();
}

$stmt = $conn->prepare("
    SELECT
        j.job_id, j.title, j.salary, j.category, j.location, j.duration,
        j.employer_id,
        a.application_id, a.status, a.message, a.apply_date AS applied_at
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE a.user_id = ?
    ORDER BY a.apply_date DESC
");

if (!$stmt) { echo json_encode(["error" => $conn->error]); exit(); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$stmt->close();
$conn->close();
