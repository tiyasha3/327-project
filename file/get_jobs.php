<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

include "db.php";

$search = trim($_GET['q']   ?? '');
$cat    = trim($_GET['cat'] ?? '');
$limit  = intval($_GET['limit'] ?? 50);
if ($limit <= 0 || $limit > 100) $limit = 50;

/* ── Build query with optional filters ────────── */
$sql    = "SELECT j.job_id, j.title, j.description, j.salary, j.category, j.location, j.duration, j.status, j.created_at,
                  u.name AS employer_name
           FROM jobs j
           LEFT JOIN users u ON j.employer_id = u.user_id
           WHERE 1=1";
$params = [];
$types  = "";

if ($search !== '') {
    $sql     .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.category LIKE ?)";
    $like     = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= "sss";
}

if ($cat !== '') {
    $sql     .= " AND j.category LIKE ?";
    $params[] = "%$cat%";
    $types   .= "s";
}

$sql .= " ORDER BY j.created_at DESC LIMIT ?";
$params[] = $limit;
$types   .= "i";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $jobs[] = $row;
}

echo json_encode($jobs);

$stmt->close();
$conn->close();
?>
