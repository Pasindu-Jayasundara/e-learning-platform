<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$stmt = $pdo->prepare('SELECT m.*, c.instructor_id, c.is_published FROM course_materials m JOIN courses c ON m.course_id = c.id WHERE m.id = ? LIMIT 1');
$stmt->execute([$id]);
$mat = $stmt->fetch();
if (!$mat) {
    http_response_code(404);
    echo 'Material not found';
    exit;
}

// permission checks: admin or instructor of course or enrolled student if course published
$allowed = false;
if ($user['role'] === 'admin') $allowed = true;
if ($user['role'] === 'instructor' && $mat['instructor_id'] == $user['id']) $allowed = true;
if ($user['role'] === 'student') {
    // check enrollment
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$user['id'],$mat['course_id']]);
    if ($stmt->fetch()) $allowed = true;
}

if (!$allowed) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$path = __DIR__ . $mat['path'];
if (!file_exists($path)) {
    http_response_code(404);
    echo 'File not found on disk';
    exit;
}

// stream file
$filename = $mat['filename'];
$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
