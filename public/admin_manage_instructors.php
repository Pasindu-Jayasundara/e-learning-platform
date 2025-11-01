<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_login();
require_role(['admin']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /admin_manage_instructors.php'); exit; }
  $action = $_POST['action'] ?? '';
  if ($action === 'approve') {
    $id = intval($_POST['user_id'] ?? 0);
    if ($id) {
      $stmt = $pdo->prepare("SELECT id,name,email FROM users WHERE id=? LIMIT 1");
      $stmt->execute([$id]);
      $u = $stmt->fetch();
      if ($u) {
        $upd = $pdo->prepare("UPDATE users SET role='instructor', requested_instructor=0, instructor_approved_at=NOW() WHERE id=?");
        $upd->execute([$id]);
        set_flash('success','Instructor approved');
        // send email
  $subject = 'Your instructor application has been approved';
  // Build absolute base URL for helpful links
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
  $baseUrl = isset($_SERVER['HTTP_HOST']) ? ($scheme . $_SERVER['HTTP_HOST']) : '';
  $manageUrl = $baseUrl ? ($baseUrl . '/manage_courses.php') : '/manage_courses.php';
  $body = [
    'html' => '<p>Hi '.htmlspecialchars($u['name']).',</p>'
      . '<p>Your instructor application has been approved. You can now create and manage courses.</p>'
      . '<p><a href="'.htmlspecialchars($manageUrl).'">Go to Manage Courses</a></p>'
      . '<p>— E-Learning Team</p>',
    'text' => 'Hi '.$u['name']."\nYour instructor application has been approved. You can now create and manage courses.\n"
      . 'Manage Courses: ' . $manageUrl . "\n— E-Learning Team"
  ];
        @send_notification([$u['email']], $subject, $body);
      }
    }
    header('Location: /admin_manage_instructors.php'); exit;
  }
}

// Ensure columns exist for requested/approved instructor fields to avoid runtime errors
function ensure_instructor_request_columns(PDO $pdo): void {
  try { $pdo->exec("ALTER TABLE users ADD COLUMN requested_instructor TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* ignore if exists */ }
  try { $pdo->exec("ALTER TABLE users ADD COLUMN instructor_requested_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) { /* ignore if exists */ }
  try { $pdo->exec("ALTER TABLE users ADD COLUMN instructor_approved_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) { /* ignore if exists */ }
}
ensure_instructor_request_columns($pdo);

$pending = $pdo->query("SELECT id,name,email,instructor_requested_at FROM users WHERE requested_instructor=1 AND role='student' ORDER BY instructor_requested_at DESC")->fetchAll();
$instructors = $pdo->query("SELECT id,name,email,COALESCE(instructor_approved_at, created_at) AS approved_at, created_at FROM users WHERE role='instructor' ORDER BY approved_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Instructors - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style> table.datagrid{width:100%;border-collapse:collapse} table.datagrid th,table.datagrid td{border:1px solid var(--border-color);padding:10px;text-align:left} table.datagrid th{background:var(--bg-secondary)} </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header"><h1>👨‍🏫 Manage Instructors</h1><p>Approve new instructors and view existing ones</p></div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
          <h2>Pending Applications</h2>
          <span class="badge <?php echo empty($pending) ? 'warning' : 'info'; ?>"><?php echo count($pending); ?></span>
        </div>
        <div class="card-body">
          <?php if (empty($pending)): ?>
            <p class="text-muted">No pending instructor applications.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Requested</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($pending as $p): ?>
                  <tr>
                    <td>#<?php echo (int)$p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['email']); ?></td>
                    <td><?php echo htmlspecialchars($p['instructor_requested_at'] ?? ''); ?></td>
                    <td>
                      <form method="post" style="display:inline;" onsubmit="return confirm('Approve this instructor?');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="user_id" value="<?php echo (int)$p['id']; ?>">
                        <button type="submit" class="btn-sm btn primary">✓ Approve</button>
                      </form>
                      <a class="btn-sm btn secondary" href="/admin_user_details.php?id=<?php echo (int)$p['id']; ?>">🔍 View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Current Instructors</h2></div>
        <div class="card-body">
          <?php if (empty($instructors)): ?>
            <p class="text-muted">No instructors yet.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Approved</th><th>Details</th></tr></thead>
              <tbody>
                <?php foreach ($instructors as $i): ?>
                  <tr>
                    <td>#<?php echo (int)$i['id']; ?></td>
                    <td><?php echo htmlspecialchars($i['name']); ?></td>
                    <td><?php echo htmlspecialchars($i['email']); ?></td>
                    <td><?php echo htmlspecialchars($i['approved_at'] ?? ''); ?></td>
                    <td><a class="btn-sm btn secondary" href="/admin_user_details.php?id=<?php echo (int)$i['id']; ?>">🔍 View</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>