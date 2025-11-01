<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }

$post_id = intval($_POST['post_id'] ?? 0);
if (!$post_id) { set_flash('error','Missing'); header('Location: /dashboard.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM forum_posts WHERE id = ? LIMIT 1');
$stmt->execute([$post_id]);
$post = $stmt->fetch();
if (!$post) { set_flash('error','Post not found'); header('Location: /dashboard.php'); exit; }

// permission: admin or author or instructor of the course
$allowed = false;
if ($user['role'] === 'admin') $allowed = true;
if ($user['id'] == $post['user_id']) $allowed = true;
if ($user['role'] === 'instructor') {
    $q = $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND instructor_id = ?');
    $q->execute([$post['course_id'],$user['id']]);
    if ($q->fetch()) $allowed = true;
}
if (!$allowed) { set_flash('error','Not allowed'); header('Location: /post_forum.php?post_id=' . $post_id); exit; }

// delete replies then post
$pdo->prepare('DELETE FROM forum_replies WHERE post_id = ?')->execute([$post_id]);
$pdo->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([$post_id]);
set_flash('success','Post deleted');
header('Location: /course_forum.php?course_id=' . $post['course_id']);
exit;
