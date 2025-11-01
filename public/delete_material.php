<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /manage_courses.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /manage_courses.php');
    exit;
}

if (!$id) {
    header('Location: /manage_courses.php');
    exit;
}

$stmt = $pdo->prepare('SELECT m.*, c.instructor_id FROM course_materials m JOIN courses c ON m.course_id = c.id WHERE m.id = ? LIMIT 1');
stmt->execute([$id]);
$mat = $stmt->fetch();
if (!$mat) {
    set_flash('error','Material not found');
    header('Location: /manage_courses.php');
    exit;
}

// instructor may only delete own course materials
if ($user['role'] === 'instructor' && $mat['instructor_id'] != $user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$path = __DIR__ . $mat['path'];
if (file_exists($path)) {
    @unlink($path);
}

$stmt = $pdo->prepare('DELETE FROM course_materials WHERE id = ?');
$stmt->execute([$id]);
set_flash('success','Material deleted');
header('Location: /manage_courses.php');
exit;
