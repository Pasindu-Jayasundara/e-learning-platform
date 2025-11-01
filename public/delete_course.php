<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /manage_courses.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /manage_courses.php');
    exit;
}
if (!$id) {
    header('Location: /manage_courses.php');
    exit;
}

// fetch course
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: /manage_courses.php');
    exit;
}

// instructors may only delete their own courses
if ($user['role'] === 'instructor' && $course['instructor_id'] != $user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// prevent deletion if there are enrollments
$cnt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM enrollments WHERE course_id = ?');
$cnt->execute([$id]);
$row = $cnt->fetch();
if ($row && intval($row['cnt']) > 0) {
    set_flash('error','Cannot delete this course because students are enrolled. Unenroll all students first.');
    header('Location: /manage_courses.php');
    exit;
}

// delete course (materials cascade)
$stmt = $pdo->prepare('DELETE FROM courses WHERE id = ?');
$stmt->execute([$id]);
set_flash('success','Course deleted');
header('Location: /manage_courses.php');
exit;
