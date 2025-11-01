<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /reports_enrollments.php'); exit; }
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /reports_enrollments.php'); exit; }

$course_id = intval($_POST['course_id'] ?? 0);
$from = trim($_POST['from'] ?? '');
$to = trim($_POST['to'] ?? '');

// permission: instructors limited to their courses
if ($user['role'] === 'instructor' && $course_id) {
    $q = $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
    $q->execute([$course_id,$user['id']]);
    if (!$q->fetch()) { set_flash('error','Not allowed'); header('Location: /reports_enrollments.php'); exit; }
}

// build query
$sql = 'SELECT e.id AS enrollment_id, e.user_id, u.name AS student_name, u.email, e.course_id, c.title AS course_title, e.progress, e.created_at FROM enrollments e JOIN users u ON e.user_id = u.id JOIN courses c ON e.course_id = c.id';
$where = [];
$params = [];
if ($course_id) { $where[] = 'e.course_id = ?'; $params[] = $course_id; }
if ($from) { $where[] = 'e.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'e.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($user['role'] === 'instructor' && !$course_id) {
    // limit to instructor's courses
    $c = $pdo->prepare('SELECT id FROM courses WHERE instructor_id = ?');
    $c->execute([$user['id']]);
    $ids = $c->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        $where[] = 'e.course_id IN (' . implode(',', array_map('intval',$ids)) . ')';
    } else {
        $where[] = '1=0';
    }
}
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY e.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// send CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="enrollments_' . date('Ymd_His') . '.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['enrollment_id','user_id','student_name','email','course_id','course_title','progress','enrolled_at']);
foreach ($rows as $r) {
    fputcsv($out, [$r['enrollment_id'],$r['user_id'],$r['student_name'],$r['email'],$r['course_id'],$r['course_title'],$r['progress'],$r['created_at']]);
}
fclose($out);
exit;

?>
