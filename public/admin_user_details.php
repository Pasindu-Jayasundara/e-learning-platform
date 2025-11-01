<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /admin_manage_learners.php'); exit; }

// Attempt to query extended user fields; if columns are missing, add them and retry, else fall back.
function ensure_instructor_request_columns(PDO $pdo): void {
  try { $pdo->exec("ALTER TABLE users ADD COLUMN requested_instructor TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* ignore if exists */ }
  try { $pdo->exec("ALTER TABLE users ADD COLUMN instructor_requested_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) { /* ignore if exists */ }
  try { $pdo->exec("ALTER TABLE users ADD COLUMN instructor_approved_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) { /* ignore if exists */ }
}

try {
  $stmt = $pdo->prepare("SELECT id,name,email,role,requested_instructor,instructor_requested_at,instructor_approved_at,created_at FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$id]);
  $user = $stmt->fetch();
} catch (Throwable $e) {
  // Likely missing columns; try to add them and retry once.
  ensure_instructor_request_columns($pdo);
  try {
    $stmt = $pdo->prepare("SELECT id,name,email,role,requested_instructor,instructor_requested_at,instructor_approved_at,created_at FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
  } catch (Throwable $e2) {
    // Final fallback without the new columns
    $stmt = $pdo->prepare("SELECT id,name,email,role,created_at FROM users WHERE id=? LIMIT 1");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if ($user) {
      $user['requested_instructor'] = 0;
      $user['instructor_requested_at'] = null;
      $user['instructor_approved_at'] = null;
    }
  }
}
if (!$user) { set_flash('error','User not found'); header('Location: /admin_manage_learners.php'); exit; }

// Enrollments
$enr = $pdo->prepare("SELECT e.*, c.title FROM enrollments e JOIN courses c ON e.course_id=c.id WHERE e.user_id=? ORDER BY e.enrolled_at DESC");
$enr->execute([$id]);
$enrollments = $enr->fetchAll();

// Payments
$pay = $pdo->prepare("SELECT p.*, c.title FROM payments p LEFT JOIN courses c ON p.course_id=c.id WHERE p.user_id=? ORDER BY p.created_at DESC");
$pay->execute([$id]);
$payments = $pay->fetchAll();

// Authored courses if instructor
$courses = [];
if ($user['role'] === 'instructor') {
  $c = $pdo->prepare("SELECT id,title,is_published,created_at FROM courses WHERE instructor_id=? ORDER BY created_at DESC");
  $c->execute([$id]);
  $courses = $c->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Details - E-Learning</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style> table.datagrid{width:100%;border-collapse:collapse} table.datagrid th,table.datagrid td{border:1px solid var(--border-color);padding:10px;text-align:left} table.datagrid th{background:var(--bg-secondary)} .mono{font-family:ui-monospace,Consolas,Menlo,monospace} </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header"><h1>🔍 User Details</h1><p>Comprehensive view of user data</p></div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>Profile</h2></div>
        <div class="card-body">
          <p><strong>ID:</strong> <span class="mono">#<?php echo (int)$user['id']; ?></span></p>
          <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
          <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
          <p><strong>Role:</strong> <span class="badge"><?php echo htmlspecialchars($user['role']); ?></span></p>
          <p><strong>Joined:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>
          <p><strong>Instructor request:</strong> <?php echo (int)($user['requested_instructor'] ?? 0) ? 'Requested at '.htmlspecialchars($user['instructor_requested_at']) : 'No'; ?></p>
          <?php if (!empty($user['instructor_approved_at'])): ?>
            <p><strong>Instructor approved at:</strong> <?php echo htmlspecialchars($user['instructor_approved_at']); ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>Enrollments (<?php echo count($enrollments); ?>)</h2></div>
        <div class="card-body">
          <?php if (empty($enrollments)): ?>
            <p class="text-muted">No enrollments.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>Course</th><th>Progress</th><th>Enrolled At</th></tr></thead>
              <tbody>
                <?php foreach ($enrollments as $e): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($e['title']); ?></td>
                    <td><?php echo (int)$e['progress']; ?>%</td>
                    <td><?php echo htmlspecialchars($e['enrolled_at']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>Payments (<?php echo count($payments); ?>)</h2></div>
        <div class="card-body">
          <?php if (empty($payments)): ?>
            <p class="text-muted">No payments.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>ID</th><th>Course</th><th>Status</th><th>Amount</th><th>Created</th></tr></thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td class="mono">#<?php echo (int)$p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['title'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['status']); ?></td>
                    <td>$<?php echo number_format((float)$p['amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($p['created_at']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!empty($courses)): ?>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><h2>Authored Courses (<?php echo count($courses); ?>)</h2></div>
          <div class="card-body">
            <table class="datagrid">
              <thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Created</th></tr></thead>
              <tbody>
                <?php foreach ($courses as $c): ?>
                  <tr>
                    <td>#<?php echo (int)$c['id']; ?></td>
                    <td><?php echo htmlspecialchars($c['title']); ?></td>
                    <td><span class="badge <?php echo (int)$c['is_published'] ? 'success' : 'warning'; ?>"><?php echo (int)$c['is_published'] ? 'Published' : 'Draft'; ?></span></td>
                    <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <div style="margin-top:12px; padding-bottom:24px;"><a href="/dashboard.php" class="btn light" style="color:var(--primary-color); border:2px solid var(--primary-color);">← Back to Dashboard</a></div>
    </div>
  </main>
</body>
</html>