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

// Get latest message per (job_id + other_user) pair
$stmt = $conn->prepare("
    SELECT
        m.job_id,
        j.title AS job_title,
        IF(m.sender_id = ?, m.receiver_id, m.sender_id) AS other_id,
        MAX(m.sent_at) AS last_time
    FROM messages m
    JOIN jobs j ON j.job_id = m.job_id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY m.job_id, other_id
    ORDER BY last_time DESC
");
$stmt->bind_param("iii", $me, $me, $me);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

$conversations = [];
foreach ($rows as $row) {
    $otherId = intval($row['other_id']);
    $jobId   = intval($row['job_id']);

    // Get other user info
    $uStmt = $conn->prepare("SELECT user_id, name, avatar, role FROM users WHERE user_id = ? LIMIT 1");
    $uStmt->bind_param("i", $otherId);
    $uStmt->execute();
    $other = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    // Get last message content
    $lStmt = $conn->prepare("
        SELECT content FROM messages
        WHERE job_id = ? AND ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
        ORDER BY sent_at DESC LIMIT 1
    ");
    $lStmt->bind_param("iiiii", $jobId, $me, $otherId, $otherId, $me);
    $lStmt->execute();
    $lMsg = $lStmt->get_result()->fetch_assoc();
    $lStmt->close();

    // Count unread
    $uCount = $conn->prepare("SELECT COUNT(*) AS c FROM messages WHERE job_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0");
    $uCount->bind_param("iii", $jobId, $otherId, $me);
    $uCount->execute();
    $unread = intval($uCount->get_result()->fetch_assoc()['c']);
    $uCount->close();

    $conversations[] = [
        'job_id'        => $jobId,
        'job_title'     => $row['job_title'],
        'other_id'      => $otherId,
        'other_name'    => $other['name']   ?? 'Unknown',
        'other_avatar'  => $other['avatar'] ?? '',
        'other_role'    => $other['role']   ?? '',
        'last_time'     => $row['last_time'],
        'last_message'  => $lMsg['content'] ?? '',
        'unread_count'  => $unread,
    ];
}

echo json_encode(["success" => true, "conversations" => $conversations]);
$conn->close();
?>
