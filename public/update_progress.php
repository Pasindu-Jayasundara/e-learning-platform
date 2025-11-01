<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}

$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$progress = intval($_POST['progress'] ?? 0);
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /dashboard.php');
    exit;
}
if ($enrollment_id <= 0 || $progress < 0 || $progress > 100) {
    set_flash('error','Invalid input');
    header('Location: /dashboard.php');
    exit;
}

// fetch enrollment and course
$stmt = $pdo->prepare('SELECT e.*, c.instructor_id FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.id = ? LIMIT 1');
$stmt->execute([$enrollment_id]);
$en = $stmt->fetch();
if (!$en) {
    set_flash('error','Enrollment not found');
    header('Location: /dashboard.php');
    exit;
}

// permission: admin or instructor of course
if ($user['role'] === 'instructor' && $en['instructor_id'] != $user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$stmt = $pdo->prepare('UPDATE enrollments SET progress = ? WHERE id = ?');
$stmt->execute([intval($progress), $enrollment_id]);
set_flash('success','Progress updated');
// redirect back to course page
header('Location: /course.php?id=' . $en['course_id']);
exit;
