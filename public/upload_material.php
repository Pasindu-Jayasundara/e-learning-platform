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

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /manage_courses.php');
    exit;
}

$course_id = intval($_POST['course_id'] ?? 0);
if (!$course_id) {
    set_flash('error','Missing course id');
    header('Location: /manage_courses.php');
    exit;
}

// verify ownership
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) {
    set_flash('error','Course not found');
    header('Location: /manage_courses.php');
    exit;
}
if ($user['role'] === 'instructor' && $course['instructor_id'] != $user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (!isset($_FILES['material']) || $_FILES['material']['error'] !== UPLOAD_ERR_OK) {
    set_flash('error','File upload failed');
    header('Location: /manage_courses.php');
    exit;
}

$file = $_FILES['material'];
// basic validation
$allowed = [
    'application/pdf',
    'application/zip',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/png',
    'image/jpeg',
    'text/plain'
];
if (!in_array($file['type'], $allowed)) {
    set_flash('error','File type not allowed');
    header('Location: /manage_courses.php');
    exit;
}

$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);
$basename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file['name']));
$destRel = '/uploads/' . $basename;
$dest = $uploadsDir . '/' . $basename;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    set_flash('error','Failed to move uploaded file');
    header('Location: /manage_courses.php');
    exit;
}

// store record
$stmt = $pdo->prepare('INSERT INTO course_materials (course_id, filename, path) VALUES (?,?,?)');
$stmt->execute([$course_id, $basename, $destRel]);

set_flash('success','Material uploaded');
header('Location: /manage_courses.php');
exit;

?>
