<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }

$post_id = intval($_POST['post_id'] ?? 0);
$reason = trim(strip_tags($_POST['reason'] ?? ''));
if (!$post_id || $reason === '') { set_flash('error','Missing data'); header('Location: /dashboard.php'); exit; }

// insert report - reports table should exist (see db/reports.sql)
// determine course_id for this post (if possible)
$course_id = null;
$q = $pdo->prepare('SELECT course_id FROM forum_posts WHERE id = ? LIMIT 1');
$q->execute([$post_id]);
$course_id = $q->fetchColumn() ?: null;

$stmt = $pdo->prepare('INSERT INTO reports (reporter_id, entity_type, entity_id, reason, course_id) VALUES (?,?,?,?,?)');
$stmt->execute([$user['id'],'post',$post_id,$reason,$course_id]);

// notify moderators and course instructor (if configured)
$cfg = include __DIR__ . '/../includes/config.php';
$to = [];
if (!empty($cfg['moderator_emails']) && is_array($cfg['moderator_emails'])) {
	$to = array_merge($to, $cfg['moderator_emails']);
}
// also notify course instructor if available and opted-in
if ($course_id) {
	$q = $pdo->prepare('SELECT u.email, u.notify_on_report FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ? LIMIT 1');
	$q->execute([$course_id]);
	$row = $q->fetch();
	if ($row && !empty($row['email']) && (!isset($row['notify_on_report']) || $row['notify_on_report'])) $to[] = $row['email'];
}
// send notification using mailer wrapper
require_once __DIR__ . '/../includes/email_templates.php';
require_once __DIR__ . '/../includes/mailer.php';
$templates = report_post_templates($post_id, $user, $reason);
send_notification($to, $templates['subject'], ['html' => $templates['html'], 'text' => $templates['text']]);

set_flash('success','Report submitted');
header('Location: /post_forum.php?post_id=' . $post_id);
exit;
