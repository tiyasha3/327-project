<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, GET");

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

$reviewer_id = intval($_SESSION['user_id']);

/* ── GET: fetch reviews for a user ─────────────── */
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $reviewed_id = intval($_GET['user_id'] ?? 0);
    if ($reviewed_id <= 0) {
        echo json_encode(["success" => false, "error" => "missing_user_id"]);
        exit();
    }
    $stmt = $conn->prepare("
        SELECT r.review_id, r.rating, r.comment, r.created_at,
               u.name AS reviewer_name, u.avatar AS reviewer_avatar, u.role AS reviewer_role,
               j.title AS job_title
        FROM reviews r
        JOIN users u ON u.user_id = r.reviewer_id
        JOIN jobs  j ON j.job_id  = r.job_id
        WHERE r.reviewed_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $reviewed_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
    $stmt->close();

    // Average rating
    $avgStmt = $conn->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total FROM reviews WHERE reviewed_id = ?");
    $avgStmt->bind_param("i", $reviewed_id);
    $avgStmt->execute();
    $avg = $avgStmt->get_result()->fetch_assoc();
    $avgStmt->close();

    echo json_encode([
        "success"    => true,
        "reviews"    => $reviews,
        "avg_rating" => $avg['avg_rating'] ?? 0,
        "total"      => $avg['total'] ?? 0,
    ]);
    exit();
}

/* ── POST: submit a review ──────────────────────── */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "invalid_method"]);
    exit();
}

$reviewed_id = intval($_POST['reviewed_id'] ?? 0);
$job_id      = intval($_POST['job_id']      ?? 0);
$rating      = intval($_POST['rating']      ?? 0);
$comment     = trim($_POST['comment']       ?? '');

if ($reviewed_id <= 0 || $job_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(["success" => false, "error" => "invalid_fields"]);
    exit();
}
if ($reviewer_id === $reviewed_id) {
    echo json_encode(["success" => false, "error" => "cannot_review_yourself"]);
    exit();
}
if (strlen($comment) > 500) {
    echo json_encode(["success" => false, "error" => "comment_too_long"]);
    exit();
}

// Check not already reviewed
$dup = $conn->prepare("SELECT review_id FROM reviews WHERE reviewer_id = ? AND reviewed_id = ? AND job_id = ? LIMIT 1");
$dup->bind_param("iii", $reviewer_id, $reviewed_id, $job_id);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    echo json_encode(["success" => false, "error" => "already_reviewed"]);
    $dup->close();
    exit();
}
$dup->close();

// Verify they have a valid connection on this job
$check = $conn->prepare("
    SELECT a.application_id FROM applications a
    JOIN jobs j ON j.job_id = a.job_id
    WHERE a.job_id = ?
      AND (
        (a.user_id = ? AND j.employer_id = ?)
        OR
        (a.user_id = ? AND j.employer_id = ?)
      )
      AND a.status = 'accepted'
    LIMIT 1
");
$check->bind_param("iiiii", $job_id, $reviewer_id, $reviewed_id, $reviewed_id, $reviewer_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "not_authorized"]);
    $check->close();
    exit();
}
$check->close();

$stmt = $conn->prepare("INSERT INTO reviews (reviewer_id, reviewed_id, job_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiis", $reviewer_id, $reviewed_id, $job_id, $rating, $comment);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "review_id" => $stmt->insert_id]);
} else {
    echo json_encode(["success" => false, "error" => "db_error"]);
}

$stmt->close();
$conn->close();
?>
