<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");

include "db.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "not_logged_in"]);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "error" => "invalid_method"]);
    exit();
}

$sender_id   = intval($_SESSION['user_id']);
$receiver_id = intval($_POST['receiver_id'] ?? 0);
$job_id      = intval($_POST['job_id']      ?? 0);
$content     = trim($_POST['content']       ?? '');

if ($receiver_id <= 0 || $job_id <= 0 || $content === '') {
    echo json_encode(["success" => false, "error" => "missing_fields"]);
    exit();
}

if (strlen($content) > 2000) {
    echo json_encode(["success" => false, "error" => "message_too_long"]);
    exit();
}

if ($sender_id === $receiver_id) {
    echo json_encode(["success" => false, "error" => "cannot_message_yourself"]);
    exit();
}

// Verify the conversation is allowed: check application exists between these parties for this job
// (employer or teen can message the other)
$checkStmt = $conn->prepare("
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
$checkStmt->bind_param("iiiii", $job_id, $sender_id, $receiver_id, $receiver_id, $sender_id);
$checkStmt->execute();
$checkStmt->store_result();
if ($checkStmt->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "not_authorized"]);
    $checkStmt->close();
    exit();
}
$checkStmt->close();

// Ensure messages table exists
$conn->query("CREATE TABLE IF NOT EXISTS messages (
    message_id   INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT NOT NULL,
    receiver_id  INT NOT NULL,
    job_id       INT NOT NULL,
    content      TEXT NOT NULL,
    sent_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read      TINYINT(1) DEFAULT 0,
    FOREIGN KEY (sender_id)   REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id)      REFERENCES jobs(job_id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, job_id, content) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiis", $sender_id, $receiver_id, $job_id, $content);

if ($stmt->execute()) {
    echo json_encode([
        "success"    => true,
        "message_id" => $stmt->insert_id,
        "sent_at"    => date("Y-m-d H:i:s")
    ]);
} else {
    echo json_encode(["success" => false, "error" => "db_error"]);
}

$stmt->close();
$conn->close();
?>
