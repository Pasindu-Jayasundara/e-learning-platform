<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /announcements.php');
    exit;
}
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /announcements.php');
    exit;
}
$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$course_id = $_POST['course_id'] ?? null;
if ($course_id === '') $course_id = null;
if (!$title || !$message) {
    set_flash('error','Title and message required');
    header('Location: /announcements.php');
    exit;
}
// if instructor, ensure course belongs to them when course_id provided
if ($user['role'] === 'instructor' && $course_id) {
    $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
    $stmt->execute([$course_id,$user['id']]);
    if (!$stmt->fetch()) {
        set_flash('error','Invalid course selection');
        header('Location: /announcements.php');
        exit;
    }
}
$stmt = $pdo->prepare('INSERT INTO announcements (user_id,course_id,title,message) VALUES (?,?,?,?)');
$stmt->execute([$user['id'],$course_id,$title,$message]);
set_flash('success','Announcement posted');
header('Location: /announcements.php');
exit;
