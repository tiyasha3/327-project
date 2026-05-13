<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");

include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "not_logged_in"]);
    exit();
}

$conn->query("
    CREATE TABLE IF NOT EXISTS reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        reviewer_id INT NOT NULL,
        reviewed_id INT NOT NULL,
        job_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reviewed (reviewed_id),
        INDEX idx_reviewer (reviewer_id),
        INDEX idx_review_job (job_id),
        UNIQUE KEY uniq_review (reviewer_id, reviewed_id, job_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$viewer_id = intval($_SESSION['user_id']);
$profile_id = intval($_GET['user_id'] ?? $viewer_id);
if ($profile_id <= 0) $profile_id = $viewer_id;

function column_exists($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($row['total'] ?? 0) > 0;
}

$skills_select = column_exists($conn, "users", "skills") ? ", u.skills" : ", '' AS skills";

$stmt = $conn->prepare("
    SELECT u.user_id, u.name, u.email, u.phone, u.age, u.role,
           u.avatar, u.bio, u.location, u.verified, u.created_at
           $skills_select,
           COALESCE(rv.rating, 0) AS rating,
           COALESCE(rv.review_count, 0) AS review_count
    FROM users u
    LEFT JOIN (
        SELECT reviewed_id, ROUND(AVG(rating), 1) AS rating, COUNT(review_id) AS review_count
        FROM reviews
        GROUP BY reviewed_id
    ) rv ON rv.reviewed_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode(["success" => false, "message" => $conn->error]);
    exit();
}

$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "profile_not_found"]);
    $stmt->close();
    $conn->close();
    exit();
}

$profile = $result->fetch_assoc();
$stmt->close();

echo json_encode([
    "success" => true,
    "viewer_id" => $viewer_id,
    "is_owner" => $viewer_id === intval($profile['user_id']),
    "profile" => $profile
]);

$conn->close();
?>
