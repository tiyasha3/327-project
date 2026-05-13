<?php
session_start();
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST, GET");

include "db.php";

// Admin auth check — using a simple session flag set by this file's own login
$ADMIN_PASSWORD = "karmoAdmin2025!"; // change this

/* ── POST /admin.php?action=login ─────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_GET['action'] ?? '';

    if ($action === 'login') {
        $pw = trim($_POST['password'] ?? '');
        $em = trim($_POST['email']    ?? '');
        if ($em === 'admin@karmobd.com' && $pw === $ADMIN_PASSWORD) {
            $_SESSION['admin'] = true;
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "wrong_credentials"]);
        }
        exit();
    }

    if (!isset($_SESSION['admin'])) {
        echo json_encode(["success" => false, "error" => "not_admin"]);
        exit();
    }

    if ($action === 'delete_job') {
        $id = intval($_POST['id'] ?? 0);
        $s = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
        $s->bind_param("i", $id);
        echo json_encode(["success" => $s->execute()]);
        $s->close(); exit();
    }

    if ($action === 'delete_user') {
        $id = intval($_POST['id'] ?? 0);
        $s = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $s->bind_param("i", $id);
        echo json_encode(["success" => $s->execute()]);
        $s->close(); exit();
    }

    if ($action === 'toggle_verified') {
        $id  = intval($_POST['id']  ?? 0);
        $val = intval($_POST['val'] ?? 0);
        $s = $conn->prepare("UPDATE users SET verified = ? WHERE user_id = ?");
        $s->bind_param("ii", $val, $id);
        echo json_encode(["success" => $s->execute()]);
        $s->close(); exit();
    }

    echo json_encode(["success" => false, "error" => "unknown_action"]);
    exit();
}

/* ── GET: data endpoints ─────────────────────── */
if (!isset($_SESSION['admin'])) {
    echo json_encode(["success" => false, "error" => "not_admin"]);
    exit();
}

$action = $_GET['action'] ?? 'stats';

if ($action === 'stats') {
    $users = $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
    $jobs  = $conn->query("SELECT COUNT(*) AS c FROM jobs")->fetch_assoc()['c'];
    $apps  = $conn->query("SELECT COUNT(*) AS c FROM applications")->fetch_assoc()['c'];
    $teens = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='teen'")->fetch_assoc()['c'];
    $emps  = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='employer'")->fetch_assoc()['c'];
    $open  = $conn->query("SELECT COUNT(*) AS c FROM jobs WHERE status='open'")->fetch_assoc()['c'];
    echo json_encode(["success" => true, "users" => $users, "jobs" => $jobs, "applications" => $apps, "teens" => $teens, "employers" => $emps, "open_jobs" => $open]);
    exit();
}

if ($action === 'users') {
    $search = '%' . trim($_GET['q'] ?? '') . '%';
    $role   = trim($_GET['role'] ?? '');
    $sql = "SELECT user_id, name, email, phone, age, role, location, verified, created_at FROM users";
    $params = []; $types = '';
    $conditions = [];
    if ($search !== '%%') { $conditions[] = "(name LIKE ? OR email LIKE ?)"; $params[] = $search; $params[] = $search; $types .= 'ss'; }
    if ($role)             { $conditions[] = "role = ?"; $params[] = $role; $types .= 's'; }
    if ($conditions) $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " ORDER BY created_at DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["success" => true, "users" => $rows]);
    exit();
}

if ($action === 'jobs') {
    $search = '%' . trim($_GET['q'] ?? '') . '%';
    $status = trim($_GET['status'] ?? '');
    $sql = "SELECT j.job_id, j.title, j.category, j.location, j.salary, j.status, j.created_at, u.name AS employer_name FROM jobs j LEFT JOIN users u ON u.user_id = j.employer_id";
    $params = []; $types = ''; $conditions = [];
    if ($search !== '%%')  { $conditions[] = "(j.title LIKE ? OR u.name LIKE ?)"; $params[] = $search; $params[] = $search; $types .= 'ss'; }
    if ($status)           { $conditions[] = "j.status = ?"; $params[] = $status; $types .= 's'; }
    if ($conditions) $sql .= " WHERE " . implode(" AND ", $conditions);
    $sql .= " ORDER BY j.created_at DESC LIMIT 100";
    $stmt = $conn->prepare($sql);
    if ($params) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode(["success" => true, "jobs" => $rows]);
    exit();
}

echo json_encode(["success" => false, "error" => "unknown_action"]);
$conn->close();
?>
