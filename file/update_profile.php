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
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$location = trim($_POST['location'] ?? '');
$bio = trim($_POST['bio'] ?? '');
$skills = trim($_POST['skills'] ?? '');

if ($name === '') {
    echo json_encode(["success" => false, "message" => "Name is required."]);
    exit();
}

if (strlen($bio) > 600 || strlen($skills) > 300) {
    echo json_encode(["success" => false, "message" => "Bio or skills text is too long."]);
    exit();
}

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

if (!column_exists($conn, "users", "skills")) {
    $conn->query("ALTER TABLE users ADD COLUMN skills TEXT NULL AFTER bio");
}

$avatar_path = null;
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "message" => "Photo upload failed."]);
        exit();
    }

    if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        echo json_encode(["success" => false, "message" => "Photo must be under 2MB."]);
        exit();
    }

    $tmp = $_FILES['avatar']['tmp_name'];
    $mime = mime_content_type($tmp);
    $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

    if (!isset($allowed[$mime])) {
        echo json_encode(["success" => false, "message" => "Upload a JPG, PNG, or WebP image."]);
        exit();
    }

    $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . "frontend" . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "avatars";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

    $filename = "user_" . $user_id . "_" . time() . "." . $allowed[$mime];
    $target = $upload_dir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($tmp, $target)) {
        echo json_encode(["success" => false, "message" => "Could not save uploaded photo."]);
        exit();
    }

    $avatar_path = "uploads/avatars/" . $filename;
}

if ($avatar_path) {
    $stmt = $conn->prepare("
        UPDATE users
        SET name = ?, phone = ?, location = ?, bio = ?, skills = ?, avatar = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("ssssssi", $name, $phone, $location, $bio, $skills, $avatar_path, $user_id);
} else {
    $stmt = $conn->prepare("
        UPDATE users
        SET name = ?, phone = ?, location = ?, bio = ?, skills = ?
        WHERE user_id = ?
    ");
    $stmt->bind_param("sssssi", $name, $phone, $location, $bio, $skills, $user_id);
}

if (!$stmt) {
    echo json_encode(["success" => false, "message" => $conn->error]);
    exit();
}

if ($stmt->execute()) {
    $_SESSION['name'] = $name;
    echo json_encode(["success" => true, "message" => "Profile updated successfully.", "avatar" => $avatar_path]);
} else {
    echo json_encode(["success" => false, "message" => "Profile update failed."]);
}

$stmt->close();
$conn->close();
?>
