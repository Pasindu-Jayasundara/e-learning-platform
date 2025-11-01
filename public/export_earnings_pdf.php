<?php
// Suppress deprecation notices and display output to keep PDF output clean
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
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

// build HTML
$html = '<h1>Instructor Earnings Report</h1>';
$html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,Helvetica,sans-serif">';
$html .= '<thead><tr><th>Course</th><th>Instructor</th><th>Payments</th><th>Total Earned</th></tr></thead><tbody>';
foreach ($rows as $r) {
    $html .= '<tr>';
    $html .= '<td>' . htmlspecialchars($r['course_title']) . '</td>';
    $html .= '<td>' . htmlspecialchars($r['instructor_name']) . '</td>';
    $html .= '<td>' . intval($r['payments_count']) . '</td>';
    $html .= '<td>$' . number_format($r['total_earned'],2) . '</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table>';

// render PDF via Dompdf
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
$dompdf = new Dompdf();
$dompdf->set_option('isHtml5ParserEnabled', true);
$dompdf->set_option('isRemoteEnabled', true);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','landscape');
$dompdf->render();
// clean any output buffering to avoid corrupting the PDF download
if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_end_clean();
}
// stream the PDF to the browser
$dompdf->stream('earnings_' . date('Ymd_His') . '.pdf', ['Attachment' => 1]);
exit;

