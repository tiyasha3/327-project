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

$me = intval($_SESSION['user_id']);

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

$with    = intval($_GET['with']    ?? 0);
$job_id  = intval($_GET['job_id']  ?? 0);

if ($with <= 0 || $job_id <= 0) {
    echo json_encode(["success" => false, "error" => "missing_params"]);
    exit();
}

$allowed = $conn->prepare("
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
$allowed->bind_param("iiiii", $job_id, $me, $with, $with, $me);
$allowed->execute();
$allowed->store_result();
if ($allowed->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "not_authorized"]);
    $allowed->close();
    exit();
}
$allowed->close();

// Mark messages sent to me in this conversation as read
$markRead = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND job_id = ?");
$markRead->bind_param("iii", $me, $with, $job_id);
$markRead->execute();
$markRead->close();

// Fetch messages
$stmt = $conn->prepare("
    SELECT m.message_id, m.sender_id, m.content, m.sent_at, m.is_read,
           u.name AS sender_name, u.avatar AS sender_avatar
    FROM messages m
    JOIN users u ON u.user_id = m.sender_id
    WHERE m.job_id = ?
      AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
    ORDER BY m.sent_at ASC
    LIMIT 200
");
$stmt->bind_param("iiiii", $job_id, $me, $with, $with, $me);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Fetch the "other user" info + job title
$stmtU = $conn->prepare("SELECT user_id, name, avatar, role FROM users WHERE user_id = ? LIMIT 1");
$stmtU->bind_param("i", $with);
$stmtU->execute();
$other = $stmtU->get_result()->fetch_assoc();
$stmtU->close();

$stmtJ = $conn->prepare("SELECT job_id, title FROM jobs WHERE job_id = ? LIMIT 1");
$stmtJ->bind_param("i", $job_id);
$stmtJ->execute();
$job = $stmtJ->get_result()->fetch_assoc();
$stmtJ->close();

echo json_encode([
    "success"  => true,
    "messages" => $messages,
    "other"    => $other,
    "job"      => $job,
    "me"       => $me
]);

$conn->close();
?>
