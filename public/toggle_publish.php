<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['instructor']);

$user = current_user();
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location:/manage_courses.php'); exit; }

$course_id = (int)($_POST['course_id'] ?? 0);
$action = $_POST['action'] ?? '';
if ($course_id <= 0 || !in_array($action, ['publish','unpublish'], true)) { set_flash('error','Invalid request'); header('Location:/manage_courses.php'); exit; }

// Ensure admin lock column exists
try { $pdo->exec("ALTER TABLE courses ADD COLUMN admin_unpublished_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}

$stmt = $pdo->prepare('SELECT id, instructor_id, is_published, listing_fee_paid, admin_unpublished_at FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course || (int)$course['instructor_id'] !== (int)$user['id']) { set_flash('error','Course not found'); header('Location:/manage_courses.php'); exit; }

if ($action === 'publish') {
  // Respect admin lock
  if (!empty($course['admin_unpublished_at'])) {
    set_flash('error','This course was unpublished by an admin. Contact an administrator to publish.');
    header('Location:/manage_courses.php'); exit;
  }
  // Require listing fee payment
  if (empty($course['listing_fee_paid'])) {
    set_flash('error','Please pay the one-time listing fee before publishing.');
    header('Location:/pay_course_listing.php?course_id=' . $course_id); exit;
  }
  $q = $pdo->prepare('UPDATE courses SET is_published = 1 WHERE id = ?');
  $q->execute([$course_id]);
  set_flash('success','Course published');
} else if ($action === 'unpublish') {
  $q = $pdo->prepare('UPDATE courses SET is_published = 0 WHERE id = ?');
  $q->execute([$course_id]);
  set_flash('success','Course unpublished');
}

header('Location:/manage_courses.php');
exit;
