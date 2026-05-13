<?php
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");

include "db.php";

$results = [];

function add_result(&$results, $ok, $label, $detail = '') {
    $prefix = $ok ? 'OK' : 'ERROR';
    $results[] = $detail === '' ? "$prefix: $label" : "$prefix: $label - $detail";
}

function run_sql($conn, &$results, $label, $sql) {
    $ok = $conn->query($sql);
    add_result($results, (bool)$ok, $label, $ok ? '' : $conn->error);
    return $ok;
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

function index_exists($conn, $table, $index) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $index);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($row['total'] ?? 0) > 0;
}

function ensure_column($conn, &$results, $table, $column, $definition) {
    if (column_exists($conn, $table, $column)) {
        add_result($results, true, "$table.$column already exists");
        return true;
    }

    return run_sql($conn, $results, "Added $table.$column", "ALTER TABLE $table ADD COLUMN $column $definition");
}

run_sql($conn, $results, "users table ready", "
    CREATE TABLE IF NOT EXISTS users (
        user_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        age INT NULL,
        phone VARCHAR(20) NULL,
        role ENUM('teen','employer','admin') DEFAULT 'teen',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        avatar VARCHAR(255) NULL,
        bio TEXT NULL,
        skills TEXT NULL,
        location VARCHAR(120) NULL,
        verified TINYINT(1) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

ensure_column($conn, $results, "users", "avatar", "VARCHAR(255) NULL AFTER created_at");
ensure_column($conn, $results, "users", "bio", "TEXT NULL AFTER avatar");
ensure_column($conn, $results, "users", "skills", "TEXT NULL AFTER bio");
ensure_column($conn, $results, "users", "location", "VARCHAR(120) NULL AFTER skills");
ensure_column($conn, $results, "users", "verified", "TINYINT(1) DEFAULT 0 AFTER location");

run_sql($conn, $results, "jobs table ready", "
    CREATE TABLE IF NOT EXISTS jobs (
        job_id INT AUTO_INCREMENT PRIMARY KEY,
        employer_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        salary DECIMAL(10,2) DEFAULT 0.00,
        category VARCHAR(80) DEFAULT 'General',
        location VARCHAR(120) NULL,
        duration VARCHAR(80) DEFAULT 'Flexible',
        status ENUM('open','closed','filled') DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_employer (employer_id),
        INDEX idx_status (status),
        CONSTRAINT jobs_employer_fk FOREIGN KEY (employer_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

ensure_column($conn, $results, "jobs", "status", "ENUM('open','closed','filled') DEFAULT 'open' AFTER duration");

run_sql($conn, $results, "applications table ready", "
    CREATE TABLE IF NOT EXISTS applications (
        application_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        job_id INT NOT NULL,
        message TEXT NULL,
        status ENUM('pending','reviewed','accepted','rejected') DEFAULT 'pending',
        apply_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_job (job_id),
        CONSTRAINT applications_user_fk FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT applications_job_fk FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

if (index_exists($conn, "applications", "uniq_application_user_job")) {
    add_result($results, true, "applications duplicate guard already exists");
} else {
    run_sql($conn, $results, "applications duplicate guard ready", "
        ALTER TABLE applications ADD UNIQUE KEY uniq_application_user_job (user_id, job_id)
    ");
}

$reviews_sql = "
    CREATE TABLE IF NOT EXISTS reviews (
        review_id INT AUTO_INCREMENT PRIMARY KEY,
        reviewer_id INT NOT NULL,
        reviewed_id INT NOT NULL,
        job_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reviewed (reviewed_id),
        INDEX idx_reviewer (reviewer_id),
        INDEX idx_review_job (job_id),
        UNIQUE KEY uniq_review (reviewer_id, reviewed_id, job_id),
        CONSTRAINT reviews_reviewer_fk FOREIGN KEY (reviewer_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT reviews_reviewed_fk FOREIGN KEY (reviewed_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT reviews_job_fk FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
if (!run_sql($conn, $results, "reviews table ready", $reviews_sql) && stripos($conn->error, "Tablespace") !== false) {
    run_sql($conn, $results, "reviews table ready with local MySQL fallback", "
        CREATE TABLE IF NOT EXISTS reviews (
            review_id INT AUTO_INCREMENT PRIMARY KEY,
            reviewer_id INT NOT NULL,
            reviewed_id INT NOT NULL,
            job_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reviewed (reviewed_id),
            INDEX idx_reviewer (reviewer_id),
            INDEX idx_review_job (job_id),
            UNIQUE KEY uniq_review (reviewer_id, reviewed_id, job_id)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

run_sql($conn, $results, "messages table ready", "
    CREATE TABLE IF NOT EXISTS messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        job_id INT NOT NULL,
        content TEXT NOT NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0,
        INDEX idx_job (job_id),
        INDEX idx_sender (sender_id),
        INDEX idx_receiver (receiver_id),
        CONSTRAINT messages_sender_fk FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT messages_receiver_fk FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
        CONSTRAINT messages_job_fk FOREIGN KEY (job_id) REFERENCES jobs(job_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

$demo_accounts = [
    [
        'name' => 'Demo Teen',
        'email' => 'demo.teen@karmobd.com',
        'password' => password_hash('demo1234', PASSWORD_DEFAULT),
        'role' => 'teen',
        'age' => 17,
        'phone' => '01700000001',
        'location' => 'Dhaka',
        'bio' => 'I am a teen looking for part-time work in Dhaka. Skilled in tutoring, photography, and social media.',
        'skills' => 'Tutoring, Photography, Social Media, Graphic Design',
    ],
    [
        'name' => 'Demo Employer',
        'email' => 'demo.employer@karmobd.com',
        'password' => password_hash('demo1234', PASSWORD_DEFAULT),
        'role' => 'employer',
        'age' => 35,
        'phone' => '01700000002',
        'location' => 'Gulshan, Dhaka',
        'bio' => 'We are a small Dhaka business looking for reliable young people for flexible, supervised work.',
        'skills' => '',
    ],
];

foreach ($demo_accounts as $acc) {
    $stmt = $conn->prepare("
        INSERT INTO users (name, email, password, role, age, phone, location, bio, skills, verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            password = VALUES(password),
            role = VALUES(role),
            age = VALUES(age),
            phone = VALUES(phone),
            location = VALUES(location),
            bio = VALUES(bio),
            skills = VALUES(skills),
            verified = 1
    ");
    $stmt->bind_param(
        "ssssissss",
        $acc['name'], $acc['email'], $acc['password'],
        $acc['role'], $acc['age'], $acc['phone'],
        $acc['location'], $acc['bio'], $acc['skills']
    );
    $ok = $stmt->execute();
    add_result($results, $ok, "Demo account ready: " . $acc['email'], $ok ? '' : $conn->error);
    $stmt->close();
}

function find_user_id($conn, $email) {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($row['user_id'] ?? 0);
}

$demo_employer_id = find_user_id($conn, 'demo.employer@karmobd.com');
$demo_teen_id = find_user_id($conn, 'demo.teen@karmobd.com');
$sample_job_ids = [];

$sample_jobs = [
    ['Social Media Helper', 'Help a local bakery schedule posts, reply to simple comments, and take product photos after school.', 1200, 'Tech', 'Uttara, Dhaka', 'Part-time'],
    ['SSC Math Tutor', 'Tutor a younger student twice a week for algebra and geometry practice. Guardian will be present.', 900, 'Teaching', 'Dhanmondi, Dhaka', 'Recurring'],
    ['Event Photography Assistant', 'Assist with taking candid photos at a small family event. Camera experience preferred.', 1800, 'Photography', 'Gulshan, Dhaka', 'Weekend'],
    ['Poster Designer', 'Create simple Canva posters for a neighborhood clothing shop.', 700, 'Design', 'Chattogram', 'Flexible'],
];

if ($demo_employer_id > 0) {
    foreach ($sample_jobs as $job) {
        [$title, $description, $salary, $category, $location, $duration] = $job;
        $check = $conn->prepare("SELECT job_id FROM jobs WHERE employer_id = ? AND title = ? LIMIT 1");
        $check->bind_param("is", $demo_employer_id, $title);
        $check->execute();
        $existing = $check->get_result()->fetch_assoc();
        $check->close();

        if ($existing) {
            $sample_job_ids[$title] = intval($existing['job_id']);
            add_result($results, true, "Sample job already exists: $title");
            continue;
        }

        $stmt = $conn->prepare("
            INSERT INTO jobs (employer_id, title, description, salary, category, location, duration, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
        ");
        $stmt->bind_param("issdsss", $demo_employer_id, $title, $description, $salary, $category, $location, $duration);
        $ok = $stmt->execute();
        if ($ok) $sample_job_ids[$title] = $stmt->insert_id;
        add_result($results, $ok, "Sample job ready: $title", $ok ? '' : $conn->error);
        $stmt->close();
    }
}

$accepted_job_id = intval($sample_job_ids['Social Media Helper'] ?? 0);
if ($demo_teen_id > 0 && $demo_employer_id > 0 && $accepted_job_id > 0) {
    $message = 'I can help with posts and product photos after school.';
    $check = $conn->prepare("SELECT application_id FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1");
    $check->bind_param("ii", $demo_teen_id, $accepted_job_id);
    $check->execute();
    $existing_app = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing_app) {
        $app_id = intval($existing_app['application_id']);
        $stmt = $conn->prepare("UPDATE applications SET status = 'accepted', message = ? WHERE application_id = ?");
        $stmt->bind_param("si", $message, $app_id);
        $ok = $stmt->execute();
        add_result($results, $ok, "Demo accepted application ready", $ok ? '' : $conn->error);
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO applications (user_id, job_id, message, status) VALUES (?, ?, ?, 'accepted')");
        $stmt->bind_param("iis", $demo_teen_id, $accepted_job_id, $message);
        $ok = $stmt->execute();
        add_result($results, $ok, "Demo accepted application ready", $ok ? '' : $conn->error);
        $stmt->close();
    }

    $msg_count = $conn->prepare("
        SELECT COUNT(*) AS total FROM messages
        WHERE job_id = ? AND (
            (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)
        )
    ");
    $msg_count->bind_param("iiiii", $accepted_job_id, $demo_teen_id, $demo_employer_id, $demo_employer_id, $demo_teen_id);
    $msg_count->execute();
    $msg_total = intval($msg_count->get_result()->fetch_assoc()['total'] ?? 0);
    $msg_count->close();

    if ($msg_total === 0) {
        $messages = [
            [$demo_teen_id, $demo_employer_id, 'Hi! Thanks for accepting my application. I can start this weekend.'],
            [$demo_employer_id, $demo_teen_id, 'Great. Please bring a phone with a good camera and a parent contact number.'],
        ];
        foreach ($messages as $msg) {
            [$sender, $receiver, $content] = $msg;
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, job_id, content) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $sender, $receiver, $accepted_job_id, $content);
            $ok = $stmt->execute();
            add_result($results, $ok, "Demo message ready", $ok ? '' : $conn->error);
            $stmt->close();
        }
    } else {
        add_result($results, true, "Demo message thread already exists");
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KarmoBD Setup</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', sans-serif; background: #0f1c15; color: white; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .box { background: #1a2e20; border: 1px solid rgba(30,138,94,0.3); border-radius: 16px; padding: 36px 40px; max-width: 720px; width: 100%; }
    h1 { font-size: 22px; font-weight: 800; margin-bottom: 6px; }
    h1 span { color: #7EFFC5; }
    .sub { font-size: 14px; color: rgba(255,255,255,0.66); margin-bottom: 24px; line-height: 1.5; }
    ul { list-style: none; display: grid; gap: 10px; margin-bottom: 24px; max-height: 360px; overflow: auto; }
    li { font-size: 13px; background: rgba(0,0,0,0.25); padding: 10px 12px; border-radius: 8px; line-height: 1.45; }
    code { background: rgba(126,255,197,0.15); color: #7EFFC5; padding: 2px 7px; border-radius: 5px; font-size: 13px; }
    .warn { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.3); border-radius: 10px; padding: 14px 16px; font-size: 13px; color: #FDE68A; margin-bottom: 20px; line-height: 1.55; }
    .links { display: flex; gap: 10px; flex-wrap: wrap; }
    a { display: inline-block; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; transition: opacity 0.2s; }
    a:hover { opacity: 0.85; }
    .btn-g { background: #1E8A5E; color: white; }
    .btn-o { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
  </style>
</head>
<body>
<div class="box">
  <h1>Karmo<span>BD</span> Setup</h1>
  <p class="sub">Database schema, demo accounts, sample jobs, and demo message data have been checked.</p>

  <ul>
    <?php foreach ($results as $r): ?>
      <li><?= htmlspecialchars($r, ENT_QUOTES, 'UTF-8') ?></li>
    <?php endforeach; ?>
  </ul>

  <div class="warn">
    Local demo credentials:
    <br>Teen: <code>demo.teen@karmobd.com</code> / <code>demo1234</code>
    <br>Employer: <code>demo.employer@karmobd.com</code> / <code>demo1234</code>
    <br>Admin: <code>admin@karmobd.com</code> / <code>karmoAdmin2025!</code>
    <br><br>Keep this setup script for localhost only. Remove or restrict it before any public deployment.
  </div>

  <div class="links">
    <a class="btn-g" href="../frontend/login.html">Go to Login</a>
    <a class="btn-o" href="../frontend/jobs.html">Browse Jobs</a>
    <a class="btn-o" href="../frontend/admin_dashboard.html">Admin Panel</a>
  </div>
</div>
</body>
</html>
