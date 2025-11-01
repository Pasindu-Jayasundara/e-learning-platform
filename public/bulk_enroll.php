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
if (!$course_id) {
    set_flash('error','Missing course id');
    header('Location: /manage_enrollments.php');
    exit;
}

// permission check for instructor
if ($user['role'] === 'instructor') {
    $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
    $stmt->execute([$course_id,$user['id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error','CSV upload failed');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}

$path = $_FILES['csv']['tmp_name'];
$handle = fopen($path,'r');
if (!$handle) {
    set_flash('error','Unable to read CSV');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}

$header = fgetcsv($handle);
if (!$header) {
    set_flash('error','CSV appears empty');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}
$colIndex = array_search('email', array_map('strtolower', $header));
if ($colIndex === false) {
    set_flash('error','CSV must contain an email column');
    header('Location: /manage_enrollments.php?course_id=' . $course_id);
    exit;
}

$imported = 0;
$errors = [];
while (($row = fgetcsv($handle)) !== false) {
    $email = trim($row[$colIndex] ?? '');
    if (!$email) continue;
    // find user
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) {
        $errors[] = "No user: $email";
        continue;
    }
    $user_id = $u['id'];
    // check existing
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$user_id,$course_id]);
    if ($stmt->fetch()) continue;
    $stmt = $pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)');
    $stmt->execute([$user_id,$course_id,0]);
    $imported++;
}
fclose($handle);
$msg = "Imported: $imported";
if (!empty($errors)) $msg .= '; Errors: ' . implode('; ', array_slice($errors,0,5));
set_flash('success',$msg);
header('Location: /manage_enrollments.php?course_id=' . $course_id);
exit;
