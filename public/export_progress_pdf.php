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

// permission check for instructors
if ($user['role'] === 'instructor' && $course_id) {
    $q = $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
    $q->execute([$course_id,$user['id']]);
    if (!$q->fetch()) { set_flash('error','Not allowed'); header('Location: /reports_enrollments.php'); exit; }
}

// build query similar to CSV export
$sql = 'SELECT e.id AS enrollment_id, e.user_id, u.name AS student_name, u.email, e.course_id, c.title AS course_title, e.progress, e.created_at FROM enrollments e JOIN users u ON e.user_id = u.id JOIN courses c ON e.course_id = c.id';
$where = [];
$params = [];
if ($course_id) { $where[] = 'e.course_id = ?'; $params[] = $course_id; }
if ($from) { $where[] = 'e.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'e.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($user['role'] === 'instructor' && !$course_id) {
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

// build HTML for PDF
$html = '<h1>Enrollment & Progress Report</h1>';
$html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,Helvetica,sans-serif">';
$html .= '<thead><tr><th>Enroll ID</th><th>Student</th><th>Email</th><th>Course</th><th>Progress</th><th>Enrolled At</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($r['enrollment_id']) . '</td>';
    $html .= '<td>' . htmlspecialchars($r['student_name']) . '</td>';
    $html .= '<td>' . htmlspecialchars($r['email']) . '</td>';
    $html .= '<td>' . htmlspecialchars($r['course_title']) . '</td>';
    $html .= '<td>' . intval($r['progress']) . '%</td>';
    $html .= '<td>' . htmlspecialchars($r['created_at']) . '</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table>';

// generate PDF using Dompdf
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$filename = 'enrollments_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ['Attachment' => 1]);
exit;

?>
