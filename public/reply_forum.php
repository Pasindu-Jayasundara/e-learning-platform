<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard.php');
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /dashboard.php');
    exit;
}

 $post_id = intval($_POST['post_id'] ?? 0);
 $message = trim($_POST['message'] ?? '');
 // sanitization and limit
 $message = strip_tags($message);
 $max_message = 2000;
 if (mb_strlen($message) > $max_message) {
     set_flash('error','Reply too long (max '.$max_message.' chars)');
     header('Location: /post_forum.php?post_id=' . $post_id);
     exit;
 }
 if (!$post_id || $message === '') {
     set_flash('error','Missing reply data');
     header('Location: /dashboard.php');
     exit;
 }

// ensure post exists
$stmt = $pdo->prepare('SELECT p.*, c.instructor_id FROM forum_posts p JOIN courses c ON p.course_id = c.id WHERE p.id = ? LIMIT 1');
$stmt->execute([$post_id]);
$post = $stmt->fetch();
if (!$post) {
    set_flash('error','Post not found');
    header('Location: /dashboard.php');
    exit;
}

// permission to reply: enrolled students, instructor of course, or admin
$can = false;
if ($user['role'] === 'admin') $can = true;
if ($user['role'] === 'instructor' && $post['instructor_id'] == $user['id']) $can = true;
if ($user['role'] === 'student') {
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$user['id'], $post['course_id']]);
    if ($stmt->fetch()) $can = true;
}
if (!$can) {
    set_flash('error','You are not allowed to reply to this post');
    header('Location: /post_forum.php?post_id=' . $post_id);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO forum_replies (post_id,user_id,message) VALUES (?,?,?)');
$stmt->execute([$post_id, $user['id'], $message]);
set_flash('success','Reply posted');
header('Location: /post_forum.php?post_id=' . $post_id . '#replies');
exit;
