<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// Feature deprecated: certificates are automatic at 100% completion. Redirect away.
set_flash('error','Certificate request queue is no longer used. Certificates are automatic upon full course completion.');
header('Location: /dashboard.php');
exit;

// Show certificate requests for courses the instructor owns, or all if admin
if ($user['role'] === 'admin') {
  // include course title
  $stmt = $db->query('SELECT c.*, u.name AS student_name, t.name AS template_name, co.title AS course_title FROM certificates c LEFT JOIN users u ON c.user_id=u.id LEFT JOIN certificate_templates t ON c.template_id=t.id LEFT JOIN courses co ON c.course_id = co.id WHERE c.status = "requested" ORDER BY c.created_at DESC');
  $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $stmt = $db->prepare('SELECT c.*, u.name AS student_name, t.name AS template_name, co.title AS course_title FROM certificates c LEFT JOIN users u ON c.user_id=u.id LEFT JOIN certificate_templates t ON c.template_id=t.id JOIN courses co ON c.course_id = co.id WHERE c.status = "requested" AND co.instructor_id = ? ORDER BY c.created_at DESC');
  $stmt->execute([$user['id']]);
  $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// handle actions: approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) { set_flash('error','Invalid CSRF'); header('Location:/instructor_cert_requests.php'); exit; }
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { set_flash('error','Missing id'); header('Location:/instructor_cert_requests.php'); exit; }

    // load request and verify permissions
    $q = $db->prepare('SELECT c.*, co.instructor_id FROM certificates c JOIN courses co ON c.course_id=co.id WHERE c.id = ? LIMIT 1');
    $q->execute([$id]);
    $req = $q->fetch(PDO::FETCH_ASSOC);
    if (!$req) { set_flash('error','Request not found'); header('Location:/instructor_cert_requests.php'); exit; }
    if ($user['role'] !== 'admin' && $req['instructor_id'] != $user['id']) { set_flash('error','Access denied'); header('Location:/instructor_cert_requests.php'); exit; }

  if ($action === 'approve') {
        // ensure student still enrolled
        $en = $db->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
        $en->execute([$req['user_id'], $req['course_id']]);
        if (!$en->fetch()) { set_flash('error','Student is not enrolled'); header('Location:/instructor_cert_requests.php'); exit; }

    $serial = strtoupper(substr(md5(uniqid('cert',true)),0,12));
    $u = $db->prepare('UPDATE certificates SET status = ?, issued_by = ?, issued_at = NOW(), serial = ? WHERE id = ?');
    $u->execute(['issued', $user['id'], $serial, $id]);

    // load updated certificate and related data
    $cstmt = $db->prepare('SELECT certs.*, u.email AS student_email, u.name AS student_name, co.title AS course_title, t.html_template FROM certificates certs LEFT JOIN users u ON certs.user_id = u.id LEFT JOIN courses co ON certs.course_id = co.id LEFT JOIN certificate_templates t ON certs.template_id = t.id WHERE certs.id = ? LIMIT 1');
    $cstmt->execute([$id]);
    $cert = $cstmt->fetch(PDO::FETCH_ASSOC);

    // prepare HTML for certificate
    $studentName = htmlspecialchars($cert['student_name'] ?? '');
    $courseTitle = htmlspecialchars($cert['course_title'] ?? '');
    $issuedOn = htmlspecialchars($cert['issued_at'] ?? date('F j, Y'));
    $serialOut = htmlspecialchars($cert['serial'] ?? $serial);
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

    // try to generate PDF and email it via PHPMailer if available; otherwise send notice with link
    $sent = false;
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $pdfString = null;
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
      } catch (Exception $e) { /* best-effort */ }
    }

    // Attempt PHPMailer attach/send
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
      } catch (Exception $e) {
        // fall back to notice
        $sent = false;
        // log email send failure to audit_log
        try {
            $a = $db->prepare('INSERT INTO audit_log (actor_id,action,entity_type,entity_id,notes) VALUES (?,?,?,?,?)');
            $a->execute([$user['id'],'certificate_email_failed','certificate',$id, $e->getMessage()]);
        } catch (Exception $ee) { /* ignore logging failure */ }
      }
    }

    if (!$sent) {
      // send a simple notification with a download link
      $link = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/certificate.php?course_id=' . urlencode($cert['course_id']) . '&user_id=' . urlencode($cert['user_id']);
      $body = [
        'text' => "Your certificate for {$courseTitle} has been issued. Download: {$link}",
        'html' => "<p>Your certificate for <strong>" . htmlspecialchars($courseTitle) . "</strong> has been issued.</p><p><a href=\"{$link}\">Download certificate</a></p>"
      ];
      $ok = send_notification([$cert['student_email']], 'Your certificate has been issued', $body);
      if (!$ok) {
          try {
              $a = $db->prepare('INSERT INTO audit_log (actor_id,action,entity_type,entity_id,notes) VALUES (?,?,?,?,?)');
              $a->execute([$user['id'],'certificate_notification_failed','certificate',$id, 'fallback notification returned false']);
          } catch (Exception $ee) { /* ignore logging failure */ }
      }
    }

    set_flash('success','Certificate issued.');
    header('Location:/instructor_cert_requests.php'); exit;
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        $meta = json_encode(['rejected_by' => $user['id'], 'reason' => $reason]);
        $u = $db->prepare('UPDATE certificates SET status = ?, revoked_by = ?, revoked_at = NOW(), metadata = ? WHERE id = ?');
        $u->execute(['revoked', $user['id'], $meta, $id]);
        set_flash('success','Certificate request rejected.');
        header('Location:/instructor_cert_requests.php'); exit;
    }
}

$csrf = generate_csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Certificate Requests</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Certificate Requests</h1>
  <?php if ($msg = get_flash('error')): ?><div style="color:red"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <?php if ($msg = get_flash('success')): ?><div style="color:green"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <?php if (empty($requests)): ?>
    <p>No pending requests.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>Student</th><th>Course</th><th>Template</th><th>Requested</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($requests as $r): ?>
        <tr>
          <td><?php echo $r['id']; ?></td>
          <td><?php echo htmlspecialchars($r['student_name']); ?></td>
          <td><?php echo htmlspecialchars($r['course_id']); ?></td>
          <td><?php echo htmlspecialchars($r['template_name'] ?? 'default'); ?></td>
          <td><?php echo htmlspecialchars($r['created_at']); ?></td>
          <td>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
              <button name="action" value="approve">Approve</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
              <input name="reason" placeholder="Optional reason">
              <button name="action" value="reject">Reject</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <p><a href="/dashboard.php">Back</a></p>
</body>
</html>
