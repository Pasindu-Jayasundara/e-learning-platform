<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo 'Missing id'; exit; }

$stmt = $db->prepare('SELECT c.*, t.html_template, u.name as student_name, co.title as course_title, co.instructor_id FROM certificates c LEFT JOIN certificate_templates t ON c.template_id = t.id LEFT JOIN users u ON c.user_id = u.id LEFT JOIN courses co ON c.course_id = co.id WHERE c.id = ? LIMIT 1');
$stmt->execute([$id]);
$cert = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cert) { echo 'Not found'; exit; }

// permission: admin or instructor owning the course or the student themselves
if (!($user['role'] === 'admin' || $user['id'] == $cert['user_id'] || ($user['role'] === 'instructor' && $cert['instructor_id'] == $user['id']))) {
    http_response_code(403); echo 'Access denied'; exit;
}

$issuedNowAllowed = ($user['role'] === 'admin' || ($user['role'] === 'instructor' && $cert['instructor_id'] == $user['id']));

// Handle inline issue action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue') {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) { set_flash('error','Invalid CSRF token'); header('Location: /preview_certificate.php?id=' . $id); exit; }
    if (!$issuedNowAllowed) { set_flash('error','Access denied'); header('Location: /preview_certificate.php?id=' . $id); exit; }

    // ensure student still enrolled
    $en = $db->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $en->execute([$cert['user_id'], $cert['course_id']]);
    if (!$en->fetch()) { set_flash('error','Student is not enrolled'); header('Location: /preview_certificate.php?id=' . $id); exit; }

    // update certificate to issued
    $serial = strtoupper(substr(md5(uniqid('cert',true)),0,12));
    $u = $db->prepare('UPDATE certificates SET status = ?, issued_by = ?, issued_at = NOW(), serial = ? WHERE id = ?');
    $u->execute(['issued', $user['id'], $serial, $id]);

    // reload cert
    $stmt = $db->prepare('SELECT certs.*, u.email AS student_email, u.name AS student_name, co.title AS course_title, t.html_template FROM certificates certs LEFT JOIN users u ON certs.user_id = u.id LEFT JOIN courses co ON certs.course_id = co.id LEFT JOIN certificate_templates t ON certs.template_id = t.id WHERE certs.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $cert = $stmt->fetch(PDO::FETCH_ASSOC);

    $studentName = htmlspecialchars($cert['student_name'] ?? '');
    $courseTitle = htmlspecialchars($cert['course_title'] ?? '');
    $issuedOn = htmlspecialchars($cert['issued_at'] ?? date('F j, Y'));
    $serialOut = htmlspecialchars($cert['serial'] ?? $serial);

    // build HTML from template or use fallback
    if (!empty($cert['html_template'])) {
        $tpl = $cert['html_template'];
        $replacements = [
            '{{student_name}}' => $studentName,
            '{{course_title}}' => $courseTitle,
            '{{date}}' => $issuedOn,
            '{{serial}}' => $serialOut,
        ];
        $html = strtr($tpl, $replacements);
    } else {
        $html = "<!doctype html><html><head><meta charset=\"utf-8\"><style>body{font-family:DejaVu Sans,Arial,sans-serif;text-align:center;padding:50px}.cert{border:10px double #ccc;padding:30px}h1{font-size:36px}.name{font-size:28px;font-weight:700;margin-top:20px}.course{margin-top:20px;font-size:20px}</style></head><body><div class=\"cert\"><h1>Certificate of Completion</h1><p>This certifies that</p><div class=\"name\">{$studentName}</div><p class=\"course\">has completed the course</p><div class=\"course\">{$courseTitle}</div><p style=\"margin-top:30px\">Issued on {$issuedOn}</p><p>Serial: {$serialOut}</p></div></body></html>";
    }

    // try to generate PDF
    $pdfString = null;
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        try {
            require_once $autoload;
            if (class_exists('Dompdf\\Dompdf')) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4','landscape');
                $dompdf->render();
                $pdfString = $dompdf->output();
            }
        } catch (Exception $e) { /* ignore */ }
    }

    $sent = false;
    if ($pdfString && file_exists($autoload)) {
        try {
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $cfg = include __DIR__ . '/../includes/config.php';
                if (!empty($cfg['smtp']['host'])) {
                    $mail->isSMTP();
                    $mail->Host = $cfg['smtp']['host'];
                    $mail->Port = $cfg['smtp']['port'] ?? 587;
                    $mail->SMTPAuth = !empty($cfg['smtp']['username']);
                    if (!empty($cfg['smtp']['username'])) $mail->Username = $cfg['smtp']['username'];
                    if (!empty($cfg['smtp']['password'])) $mail->Password = $cfg['smtp']['password'];
                    if (!empty($cfg['smtp']['encryption'])) $mail->SMTPSecure = $cfg['smtp']['encryption'];
                }
                $from = $cfg['mail_from'] ?? 'no-reply@localhost';
                $mail->setFrom($from);
                $mail->addAddress($cert['student_email']);
                $mail->isHTML(true);
                $mail->Subject = 'Your certificate for ' . ($courseTitle ?: 'the course');
                $mail->Body = '<p>Dear ' . $studentName . ',</p><p>Your certificate has been issued. See attached PDF or download it from your account.</p><p><a href="' . (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/certificate.php?course_id=' . urlencode($cert['course_id']) . '&user_id=' . urlencode($cert['user_id']) . '">Download certificate</a></p>';
                $mail->addStringAttachment($pdfString, 'certificate.pdf', 'base64', 'application/pdf');
                $mail->send();
                $sent = true;
            }
        } catch (Exception $e) { /* fallthrough */ }
    }

    if (!$sent) {
        $link = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/certificate.php?course_id=' . urlencode($cert['course_id']) . '&user_id=' . urlencode($cert['user_id']);
        $body = [
            'text' => "Your certificate for {$courseTitle} has been issued. Download: {$link}",
            'html' => "<p>Your certificate for <strong>" . htmlspecialchars($courseTitle) . "</strong> has been issued.</p><p><a href=\"{$link}\">Download certificate</a></p>"
        ];
        @send_notification([$cert['student_email']], 'Your certificate has been issued', $body);
    }

    set_flash('success','Certificate issued and student notified.');
    header('Location: /preview_certificate.php?id=' . $id); exit;
}

$studentName = htmlspecialchars($cert['student_name'] ?? '');
$courseTitle = htmlspecialchars($cert['course_title'] ?? '');
$date = date('F j, Y');

if (!empty($cert['html_template'])) {
    $tpl = $cert['html_template'];
    $replacements = [
        '{{student_name}}' => $studentName,
        '{{course_title}}' => $courseTitle,
        '{{date}}' => htmlspecialchars($cert['issued_at'] ?? $date),
        '{{serial}}' => htmlspecialchars($cert['serial'] ?? ''),
    ];
    $html = strtr($tpl, $replacements);
} else {
    $issuedOn = htmlspecialchars($cert['issued_at'] ?? $date);
    $serial = htmlspecialchars($cert['serial'] ?? '');
    $html = "<!doctype html><html><head><meta charset=\"utf-8\"><style>body{font-family:DejaVu Sans,Arial,sans-serif;text-align:center;padding:50px}.cert{border:10px double #ccc;padding:30px}h1{font-size:36px}.name{font-size:28px;font-weight:700;margin-top:20px}.course{margin-top:20px;font-size:20px}</style></head><body><div class=\"cert\"><h1>Certificate of Completion</h1><p>This certifies that</p><div class=\"name\">{$studentName}</div><p class=\"course\">has completed the course</p><div class=\"course\">{$courseTitle}</div><p style=\"margin-top:30px\">Issued on {$issuedOn}</p><p>Serial: {$serial}</p></div></body></html>";
}

// if format=pdf requested, attempt to render PDF
if (isset($_GET['format']) && $_GET['format'] === 'pdf') {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4','landscape');
            $dompdf->render();
            $dompdf->stream('certificate_preview_' . $id . '.pdf', ['Attachment' => 1]);
            exit;
        }
    }
    // fallback: output HTML as download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="certificate_preview_' . $id . '.html"');
    echo $html; exit;
}

// otherwise show HTML preview with download link
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Certificate Preview</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Certificate Preview</h1>
  <p><a href="/preview_certificate.php?id=<?php echo $id; ?>&format=pdf">Download PDF</a></p>
  <div><?php echo $html; ?></div>
    <p><a href="/dashboard.php">Back</a></p>
</body>
</html>
