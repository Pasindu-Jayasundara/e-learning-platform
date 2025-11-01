<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$user = current_user();
$course_id = intval($_GET['course_id'] ?? 0);
if (!$course_id) {
    echo 'Missing course id';
    exit;
}

// check enrollment
$stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
$stmt->execute([$user['id'],$course_id]);
$enr = $stmt->fetch();
if (!$enr) {
    http_response_code(403);
    echo 'Not enrolled in this course';
    exit;
}

// allow admin to specify user_id to download someone else's certificate
$for_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $user['id'];

// check enrollment for the target user
$stmt = $pdo->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
$stmt->execute([$for_user_id,$course_id]);
$enr = $stmt->fetch();
if (!$enr) {
    http_response_code(403);
    echo 'Not enrolled in this course';
    exit;
}

$stmt = $pdo->prepare('SELECT title FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) {
    echo 'Course not found';
    exit;
}

// Require course completion before issuing certificate
// Compute progress using lessons if present, otherwise use enrollments.progress
$progressPercent = 0;
try {
  // lessons-based computation
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?');
  $stmt->execute([$course_id]);
  $totalLessons = (int)$stmt->fetchColumn();
  if ($totalLessons > 0) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = ? AND status = 'completed'");
    $stmt->execute([$for_user_id, $course_id]);
    $completed = (int)$stmt->fetchColumn();
    $progressPercent = $totalLessons > 0 ? (int)floor(($completed * 100) / $totalLessons) : 0;
  } else {
    // fallback to enrollments.progress
    $stmt = $pdo->prepare('SELECT progress FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$for_user_id, $course_id]);
    $progressPercent = (int)($stmt->fetchColumn() ?: 0);
  }
} catch (Throwable $e) {
  // If anything goes wrong, fall back to stored progress
  try {
    $stmt = $pdo->prepare('SELECT progress FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$for_user_id, $course_id]);
    $progressPercent = (int)($stmt->fetchColumn() ?: 0);
  } catch (Throwable $e2) {
    $progressPercent = 0;
  }
}

if ($progressPercent < 100) {
  http_response_code(403);
  header('Content-Type: text/html; charset=UTF-8');
  echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/assets/css/style.css"></head><body>';
  echo '<div class="container" style="max-width: 720px; margin: 40px auto;">';
  echo '<div class="card"><div class="card-body" style="text-align:center;">';
  echo '<div style="font-size:48px;">⏳</div>';
  echo '<h2 style="margin:8px 0;">Complete the course to get your certificate</h2>';
  echo '<p>Your current progress is ' . htmlspecialchars((string)$progressPercent) . '%.</p>';
  echo '<a class="btn primary" href="/course.php?id=' . intval($course_id) . '">Go back to course</a>';
  echo '</div></div></div>';
  echo '</body></html>';
  exit;
}

$studentName = '';
if ($for_user_id === $user['id']) {
    $studentName = htmlspecialchars($user['name']);
} else {
    $s = $pdo->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $s->execute([$for_user_id]);
    $rr = $s->fetch();
    $studentName = htmlspecialchars($rr['name'] ?? '');
}
$courseTitle = htmlspecialchars($course['title']);
$date = date('F j, Y');

// Try to check for an issued certificate record (if table exists)
$certRec = null;
try {
    $certStmt = $pdo->prepare('SELECT certs.*, t.html_template FROM certificates certs LEFT JOIN certificate_templates t ON certs.template_id = t.id WHERE certs.user_id = ? AND certs.course_id = ? AND certs.status = "issued" LIMIT 1');
    $certStmt->execute([$for_user_id, $course_id]);
    $certRec = $certStmt->fetch();
} catch (PDOException $e) {
    // certificates table might not exist, use fallback
    $certRec = null;
}

if ($certRec && !empty($certRec['html_template'])) {
    // render using template placeholders
    $tpl = $certRec['html_template'];
    $replacements = [
        '{{student_name}}' => $studentName,
        '{{course_title}}' => $courseTitle,
        '{{date}}' => htmlspecialchars($certRec['issued_at'] ?? $date),
        '{{serial}}' => htmlspecialchars($certRec['serial'] ?? ''),
    ];
    $html = strtr($tpl, $replacements);
} elseif ($certRec) {
    // issued but no template: basic fallback with issuance date and serial
    $issuedOn = htmlspecialchars($certRec['issued_at'] ?? $date);
    $serial = htmlspecialchars($certRec['serial'] ?? '');
    $html = "<!doctype html>
<html>
<head>
  <meta charset=\"utf-8\">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; text-align:center; padding:50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .cert { background: white; border: 15px double #667eea; padding:40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    h1 { font-size:42px; color: #667eea; margin-bottom: 10px; }
    .name { font-size:32px; font-weight:700; margin-top:25px; color: #333; text-transform: uppercase; }
    .course { margin-top:25px; font-size:22px; color: #555; }
    .footer { margin-top:40px; font-size:14px; color: #888; }
  </style>
</head>
<body>
  <div class=\"cert\">
  <h1>Certificate of Completion</h1>
    <p style=\"font-size:18px; color: #666;\">This certifies that</p>
    <div class=\"name\">{$studentName}</div>
    <p class=\"course\">has successfully completed the course</p>
    <div class=\"course\" style=\"font-weight:700; color:#667eea;\">{$courseTitle}</div>
    <div class=\"footer\">
      <p>Issued on {$issuedOn}</p>
      <p>Certificate Serial: {$serial}</p>
    </div>
  </div>
</body>
</html>";
} else {
    // Generate a simple certificate for enrolled students (no approval needed)
    $serial = strtoupper(substr(md5($for_user_id . $course_id . time()), 0, 12));
    $html = "<!doctype html>
<html>
<head>
  <meta charset=\"utf-8\">
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; text-align:center; padding:50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .cert { background: white; border: 15px double #667eea; padding:40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
    h1 { font-size:42px; color: #667eea; margin-bottom: 10px; }
    .name { font-size:32px; font-weight:700; margin-top:25px; color: #333; text-transform: uppercase; }
    .course { margin-top:25px; font-size:22px; color: #555; }
    .footer { margin-top:40px; font-size:14px; color: #888; }
  </style>
</head>
<body>
  <div class=\"cert\">
  <h1>Certificate of Completion</h1>
    <p style=\"font-size:18px; color: #666;\">This certifies that</p>
    <div class=\"name\">{$studentName}</div>
    <p class=\"course\">has successfully completed the course</p>
    <div class=\"course\" style=\"font-weight:700; color:#667eea;\">{$courseTitle}</div>
    <div class=\"footer\">
      <p>Issued on {$date}</p>
      <p>Certificate Serial: {$serial}</p>
    </div>
  </div>
</body>
</html>";
}

// If preview=1, show HTML in browser for debugging
if (isset($_GET['preview']) && $_GET['preview'] == '1') {
  header('Content-Type: text/html; charset=UTF-8');
  echo $html;
  exit;
}

// Generate PDF (robust streaming)
try {
  // Dompdf options for better compatibility
  $options = new Options();
  $options->set('defaultFont', 'DejaVu Sans');
  $options->set('isRemoteEnabled', true);
  $options->set('isHtml5ParserEnabled', true);

  $dompdf = new Dompdf($options);
  $dompdf->loadHtml($html, 'UTF-8');
  $dompdf->setPaper('A4', 'landscape');
  $dompdf->render();

  $pdf = $dompdf->output();
  $filename = 'certificate_' . $course_id . '_' . $for_user_id . '.pdf';

  // Clean (disable) output buffering to avoid corrupt PDFs
  if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
  }
  // Some environments enable zlib.output_compression which can break PDF length
  if (function_exists('ini_get') && ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', '0');
  }

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="' . $filename . '"');
  header('Content-Length: ' . strlen($pdf));
  echo $pdf;
  exit;
} catch (Throwable $e) {
  // Fallback: show error text for quick diagnosis
  if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
  }
  header('Content-Type: text/plain; charset=UTF-8');
  http_response_code(500);
  echo 'Failed to generate PDF: ' . $e->getMessage();
  exit;
}

// no closing PHP tag to avoid accidental output
