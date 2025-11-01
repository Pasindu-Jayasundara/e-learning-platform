<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role(['admin']);
$user = current_user();

// simple create form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /admin_cert_templates.php'); exit; }
    $name = trim($_POST['name'] ?? '');
    $html = trim($_POST['html_template'] ?? '');
    if (!$name || !$html) { set_flash('error','Name and template required'); header('Location: /admin_cert_templates.php'); exit; }
    $ins = $pdo->prepare('INSERT INTO certificate_templates (name,html_template,created_by) VALUES (?,?,?)');
    $ins->execute([$name,$html,$user['id']]);
    set_flash('success','Template created');
    header('Location: /admin_cert_templates.php');
    exit;
}

$rows = $pdo->query('SELECT * FROM certificate_templates ORDER BY created_at DESC')->fetchAll();
$csrf = generate_csrf_token();
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Certificate Templates</title></head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Certificate Templates</h1>
  <?php if ($m = get_flash('error')): ?><div style="color:red"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>
  <?php if ($m = get_flash('success')): ?><div style="color:green"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>

  <h2>Create Template</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <label>Name<br><input name="name" required></label><br>
    <label>HTML Template<br><textarea name="html_template" rows="10" cols="80" required></textarea></label><br>
    <p>Available placeholders: {{student_name}}, {{course_title}}, {{date}}, {{serial}}</p>
    <button type="submit">Create</button>
  </form>

  <h2>Existing Templates</h2>
  <?php if (empty($rows)): ?><p>No templates yet.</p><?php else: ?>
    <ul>
    <?php foreach ($rows as $r): ?>
      <li><strong><?php echo htmlspecialchars($r['name']); ?></strong> — created <?php echo htmlspecialchars($r['created_at']); ?>
        <div style="border:1px solid #ddd;padding:8px;margin:6px;background:#fafafa"><pre><?php echo htmlspecialchars($r['html_template']); ?></pre></div>
      </li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p><a href="/dashboard.php">Back</a></p>
</body>
</html>
