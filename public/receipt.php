<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$payment_id = intval($_GET['id'] ?? 0);
if (!$payment_id) { header('Location: /dashboard.php'); exit; }

$stmt = $pdo->prepare('SELECT p.*, u.name AS student_name, u.email AS student_email, c.title AS course_title FROM payments p JOIN users u ON p.user_id = u.id LEFT JOIN courses c ON p.course_id = c.id WHERE p.id = ? LIMIT 1');
$stmt->execute([$payment_id]);
$p = $stmt->fetch();
if (!$p) { header('Location: /dashboard.php'); exit; }

// permission: owner or admin
if ($user['role'] !== 'admin' && intval($user['id']) !== intval($p['user_id'])) { set_flash('error','Access denied'); header('Location: /dashboard.php'); exit; }

// build simple receipt HTML
$html = '<h1>Payment Receipt</h1>';
$html .= '<p>Receipt #: ' . htmlspecialchars($p['id']) . '</p>';
$html .= '<p>Student: ' . htmlspecialchars($p['student_name']) . ' (' . htmlspecialchars($p['student_email']) . ')</p>';
$html .= '<p>Course: ' . htmlspecialchars($p['course_title']) . '</p>';
$html .= '<p>Amount: $' . htmlspecialchars($p['amount']) . '</p>';
$html .= '<p>Status: ' . htmlspecialchars($p['status']) . '</p>';
$html .= '<p>Date: ' . htmlspecialchars($p['created_at']) . '</p>';

// output PDF via Dompdf if available, else show HTML
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml('<html><body>' . $html . '</body></html>');
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        $dompdf->stream('receipt_' . $p['id'] . '.pdf', ['Attachment' => 1]);
        exit;
    } catch (Exception $e) {
        // fall through to HTML
    }
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Receipt</title></head><body>' . $html . '<p><a href="/dashboard.php">Back</a></p></body></html>';

?>
