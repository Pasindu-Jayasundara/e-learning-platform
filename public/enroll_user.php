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
$course_id = intval($_POST['course_id'] ?? 0);
$email = trim($_POST['email'] ?? '');
if (!$course_id || !$email) {
    set_flash('error','Missing data');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}

// verify permission for course
if ($user['role'] === 'instructor') {
    $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
    $stmt->execute([$course_id, $user['id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// find user by email
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$u = $stmt->fetch();
if (!$u) {
    set_flash('error','No user with that email');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}
$user_id = $u['id'];

// check existing
$stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
$stmt->execute([$user_id,$course_id]);
if ($stmt->fetch()) {
    set_flash('error','User already enrolled');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)');
$stmt->execute([$user_id,$course_id,0]);
set_flash('success','User enrolled');
header('Location: /manage_enrollments.php?course_id=' . $course_id);
exit;
