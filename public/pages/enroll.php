<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'student') {
	$course_id = intval($_POST['course_id'] ?? 0);
	if ($course_id) {
		// check existing
		$stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
		$stmt->execute([$user['id'],$course_id]);
		if ($stmt->fetch()) {
			header('Location: /dashboard.php');
			exit;
		}

		// check course price; if >0 redirect to checkout, else enroll directly
		$stmt = $pdo->prepare('SELECT id, price, is_published FROM courses WHERE id = ? LIMIT 1');
		$stmt->execute([$course_id]);
		$course = $stmt->fetch();
		if ($course && !$course['is_published']) {
			// cannot enroll into unpublished course
			set_flash('error','Cannot enroll: course not published');
			header('Location: /dashboard.php');
			exit;
		}

		if ($course && floatval($course['price']) > 0.0) {
			// redirect to checkout flow
			header('Location: /checkout.php?course_id=' . $course_id);
			exit;
		}

		$stmt = $pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)');
		$stmt->execute([$user['id'],$course_id,0]);
	}
}
header('Location: /dashboard.php');
exit;
