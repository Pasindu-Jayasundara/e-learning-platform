<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }

$reply_id = intval($_POST['reply_id'] ?? 0);
if (!$reply_id) { set_flash('error','Missing'); header('Location: /dashboard.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM forum_replies WHERE id = ? LIMIT 1');
$stmt->execute([$reply_id]);
$reply = $stmt->fetch();
if (!$reply) { set_flash('error','Reply not found'); header('Location: /dashboard.php'); exit; }

if (!($user['role'] === 'admin' || $user['id'] == $reply['user_id'])) { set_flash('error','Not allowed'); header('Location: /post_forum.php?post_id=' . $reply['post_id']); exit; }

$pdo->prepare('DELETE FROM forum_replies WHERE id = ?')->execute([$reply_id]);
set_flash('success','Reply deleted');
header('Location: /post_forum.php?post_id=' . $reply['post_id']);
exit;
