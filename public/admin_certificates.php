<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['admin']);
$user = current_user();

// actions: issue (approve) or revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /admin_certificates.php'); exit; }
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    if (!$id) { set_flash('error','Missing id'); header('Location: /admin_certificates.php'); exit; }
    if ($action === 'issue') {
        $t = $_POST['template_id'] ? intval($_POST['template_id']) : null;
        $serial = bin2hex(random_bytes(6));
        $u = $pdo->prepare('UPDATE certificates SET status = ?, issued_by = ?, issued_at = NOW(), template_id = ?, serial = ? WHERE id = ?');
        $u->execute(['issued',$user['id'],$t,$serial,$id]);
        set_flash('success','Certificate issued');
    } elseif ($action === 'revoke') {
        $u = $pdo->prepare('UPDATE certificates SET status = ?, revoked_by = ?, revoked_at = NOW() WHERE id = ?');
        $u->execute(['revoked',$user['id'],$id]);
        set_flash('success','Certificate revoked');
    }
    header('Location: /admin_certificates.php');
    exit;
}

$certs = $pdo->query('SELECT certs.*, u.name as student_name, c.title as course_title, t.name as template_name FROM certificates certs JOIN users u ON certs.user_id = u.id JOIN courses c ON certs.course_id = c.id LEFT JOIN certificate_templates t ON certs.template_id = t.id ORDER BY certs.created_at DESC LIMIT 300')->fetchAll();
$templates = $pdo->query('SELECT id,name FROM certificate_templates ORDER BY created_at DESC')->fetchAll();
$csrf = generate_csrf_token();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Certificates</title></head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Certificates</h1>
  <?php if ($m = get_flash('error')): ?><div style="color:red"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>
  <?php if ($m = get_flash('success')): ?><div style="color:green"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>

  <table border="1" cellpadding="6" cellspacing="0">
    <thead><tr><th>ID</th><th>Student</th><th>Course</th><th>Status</th><th>Template</th><th>Serial</th><th>When</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($certs as $r): ?>
      <tr>
        <td><?php echo $r['id']; ?></td>
        <td><?php echo htmlspecialchars($r['student_name']); ?></td>
        <td><?php echo htmlspecialchars($r['course_title']); ?></td>
        <td><?php echo htmlspecialchars($r['status']); ?></td>
        <td><?php echo htmlspecialchars($r['template_name'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($r['serial'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($r['created_at']); ?></td>
        <td>
          <?php if ($r['status'] === 'requested'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
              <select name="template_id">
                <option value="">(no template)</option>
                <?php foreach ($templates as $t): ?>
                  <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <button name="action" value="issue">Issue</button>
            </form>
          <?php elseif ($r['status'] === 'issued'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
              <button name="action" value="revoke">Revoke</button>
            </form>
            <a href="/certificate.php?course_id=<?php echo $r['course_id']; ?>&user_id=<?php echo $r['user_id']; ?>">Download</a>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <p><a href="/dashboard.php">Back</a></p>
</body>
</html>
