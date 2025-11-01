<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

// build query similar to earnings page
$sql = 'SELECT c.id as course_id, c.title as course_title, u.id as instructor_id, u.name as instructor_name, SUM(p.amount) as total_earned, COUNT(p.id) as payments_count FROM payments p JOIN courses c ON p.course_id = c.id JOIN users u ON c.instructor_id = u.id WHERE p.status = "completed"';
$params = [];
if ($course_id) { $sql .= ' AND c.id = ?'; $params[] = $course_id; }
if ($from) { $sql .= ' AND p.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $sql .= ' AND p.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($user['role'] === 'instructor') { $sql .= ' AND c.instructor_id = ?'; $params[] = $user['id']; }
$sql .= ' GROUP BY c.id, u.id ORDER BY total_earned DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Ensure clean output for CSV downloads
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
}
ini_set('display_errors', '0');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="earnings_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output','w');
// Write UTF-8 BOM for Excel compatibility
fprintf($out, "\xEF\xBB\xBF");

$sep = ','; $enclosure = '"'; $escape = '\\';
fputcsv($out, ['course_id','course_title','instructor_id','instructor_name','payments_count','total_earned'], $sep, $enclosure, $escape);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['course_id'],
        $r['course_title'],
        $r['instructor_id'],
        $r['instructor_name'],
        $r['payments_count'],
        $r['total_earned']
    ], $sep, $enclosure, $escape);
}
fclose($out);
exit;

?>
