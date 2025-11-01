<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard.php'); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }

$reply_id = intval($_POST['reply_id'] ?? 0);
$reason = trim(strip_tags($_POST['reason'] ?? ''));
if (!$reply_id || $reason === '') { set_flash('error','Missing data'); header('Location: /dashboard.php'); exit; }

// determine course_id if possible
$course_id = null;
$q = $pdo->prepare('SELECT r.post_id, p.course_id FROM forum_replies r JOIN forum_posts p ON r.post_id = p.id WHERE r.id = ? LIMIT 1');
$q->execute([$reply_id]);
$row = $q->fetch();
if ($row) { $post_id = $row['post_id']; $course_id = $row['course_id']; }

$stmt = $pdo->prepare('INSERT INTO reports (reporter_id, entity_type, entity_id, reason, course_id) VALUES (?,?,?,?,?)');
$stmt->execute([$user['id'],'reply',$reply_id,$reason,$course_id]);

// notify moderators and course instructor
$cfg = include __DIR__ . '/../includes/config.php';
$to = [];
if (!empty($cfg['moderator_emails']) && is_array($cfg['moderator_emails'])) {
	$to = array_merge($to, $cfg['moderator_emails']);
}
if ($course_id) {
	$q2 = $pdo->prepare('SELECT u.email, u.notify_on_report FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ? LIMIT 1');
	$q2->execute([$course_id]);
	$row = $q2->fetch();
	if ($row && !empty($row['email']) && (!isset($row['notify_on_report']) || $row['notify_on_report'])) $to[] = $row['email'];
}

$templates = null;
require_once __DIR__ . '/../includes/email_templates.php';
require_once __DIR__ . '/../includes/mailer.php';
$templates = report_reply_templates($reply_id, $user, $reason, $post_id ?? null);
send_notification($to, $templates['subject'], ['html' => $templates['html'], 'text' => $templates['text']]);

set_flash('success','Report submitted');
header('Location: ' . ($post_id ? '/post_forum.php?post_id=' . $post_id : '/dashboard.php'));
exit;
