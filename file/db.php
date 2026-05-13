<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

$conn = new mysqli("localhost", "root", "");

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Database connection failed: " . $conn->connect_error]));
}

$conn->query("CREATE DATABASE IF NOT EXISTS karmobd CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

if (!$conn->select_db("karmobd")) {
    http_response_code(500);
    die(json_encode(["success" => false, "message" => "Could not select karmobd database."]));
}

$conn->set_charset("utf8mb4");
?>
