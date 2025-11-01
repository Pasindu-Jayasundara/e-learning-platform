<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /manage_enrollments.php');
    exit;
}
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /manage_enrollments.php');
    exit;
}
$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
if (!$enrollment_id) {
    header('Location: /manage_enrollments.php');
    exit;
}

// fetch enrollment and course
$stmt = $pdo->prepare('SELECT e.*, c.instructor_id FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.id = ? LIMIT 1');
$stmt->execute([$enrollment_id]);
$en = $stmt->fetch();
if (!$en) {
    set_flash('error','Enrollment not found');
    header('Location: /manage_enrollments.php');
    exit;
}
// permission: admin or instructor of course
if ($user['role'] === 'instructor' && $en['instructor_id'] != $user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$stmt = $pdo->prepare('DELETE FROM enrollments WHERE id = ?');
$stmt->execute([$enrollment_id]);
set_flash('success','Student unenrolled');
header('Location: /manage_enrollments.php?course_id=' . $en['course_id']);
exit;
