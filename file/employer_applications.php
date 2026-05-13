<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "not_logged_in", "jobs" => [], "applications" => []]);
    exit();
}

$employer_id = intval($_SESSION['user_id']);
$job_id_filter = intval($_GET['job_id'] ?? 0);

$role_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ? LIMIT 1");
$role_stmt->bind_param("i", $employer_id);
$role_stmt->execute();
$role_row = $role_stmt->get_result()->fetch_assoc();
$role_stmt->close();

if (!$role_row || $role_row['role'] !== 'employer') {
    echo json_encode(["success" => false, "error" => "not_authorized", "jobs" => [], "applications" => []]);
    exit();
}

$jobs_stmt = $conn->prepare("
    SELECT j.job_id, j.title, j.description, j.salary,
           j.category, j.location, j.duration, j.status, j.created_at,
           COUNT(a.application_id) AS app_count
    FROM jobs j
    LEFT JOIN applications a ON j.job_id = a.job_id
    WHERE j.employer_id = ?
    GROUP BY j.job_id
    ORDER BY j.created_at DESC
");
if (!$jobs_stmt) {
    echo json_encode(["success" => false, "error" => $conn->error, "jobs" => [], "applications" => []]);
    exit();
}
$jobs_stmt->bind_param("i", $employer_id);
$jobs_stmt->execute();
$jobs_result = $jobs_stmt->get_result();
$jobs = [];
while ($row = $jobs_result->fetch_assoc()) {
    $jobs[] = $row;
}
$jobs_stmt->close();

if ($job_id_filter > 0) {
    $apps_stmt = $conn->prepare("
        SELECT a.application_id AS app_id, a.status, a.message, a.apply_date AS applied_at,
               u.user_id, u.name, u.phone, u.age, u.avatar, u.location AS user_location,
               j.title AS job_title, j.job_id
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        JOIN jobs j ON a.job_id = j.job_id
        WHERE j.employer_id = ? AND j.job_id = ?
        ORDER BY a.apply_date DESC
    ");
    if (!$apps_stmt) {
        echo json_encode(["success" => false, "error" => $conn->error, "jobs" => $jobs, "applications" => []]);
        exit();
    }
    $apps_stmt->bind_param("ii", $employer_id, $job_id_filter);
} else {
    $apps_stmt = $conn->prepare("
        SELECT a.application_id AS app_id, a.status, a.message, a.apply_date AS applied_at,
               u.user_id, u.name, u.phone, u.age, u.avatar, u.location AS user_location,
               j.title AS job_title, j.job_id
        FROM applications a
        JOIN users u ON a.user_id = u.user_id
        JOIN jobs j ON a.job_id = j.job_id
        WHERE j.employer_id = ?
        ORDER BY a.apply_date DESC
    ");
    if (!$apps_stmt) {
        echo json_encode(["success" => false, "error" => $conn->error, "jobs" => $jobs, "applications" => []]);
        exit();
    }
    $apps_stmt->bind_param("i", $employer_id);
}

$apps_stmt->execute();
$apps_result = $apps_stmt->get_result();
$applications = [];
while ($row = $apps_result->fetch_assoc()) {
    $applications[] = $row;
}
$apps_stmt->close();
$conn->close();

echo json_encode(["success" => true, "jobs" => $jobs, "applications" => $applications]);
?>
