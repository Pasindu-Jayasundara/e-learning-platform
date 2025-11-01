<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);
$user = current_user();

// Handle publish/unpublish actions first
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /publish_courses.php'); exit; }
    $id = intval($_POST['course_id'] ?? 0);
    $action = $_POST['action'] ?? '';
  // Ensure admin_unpublished_at column exists for audit/lock semantics
  try { $pdo->exec("ALTER TABLE courses ADD COLUMN admin_unpublished_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) { /* ignore if exists */ }
  if ($id && $action === 'publish') {
    $q = $pdo->prepare('UPDATE courses SET is_published = 1, admin_unpublished_at = NULL WHERE id = ?');
    $q->execute([$id]);
        set_flash('success','Course published');
    } elseif ($id && $action === 'unpublish') {
    // Mark as unpublished by admin and lock auto-publishers
    $q = $pdo->prepare('UPDATE courses SET is_published = 0, admin_unpublished_at = NOW() WHERE id = ?');
        $q->execute([$id]);
        set_flash('success','Course unpublished');
    }
    header('Location: /publish_courses.php');
    exit;
}

// fetch unpublished courses with instructor names
$stmt = $pdo->prepare('SELECT c.*, u.name as instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.is_published = 0 ORDER BY c.created_at DESC');
$stmt->execute();
$courses = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Publish Courses - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    table.datagrid { width: 100%; border-collapse: collapse; }
    table.datagrid th, table.datagrid td { border: 1px solid var(--border-color); padding: 10px; text-align: left; }
    table.datagrid th { background: var(--bg-secondary); }
    .muted { color: var(--text-secondary); }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header fade-in" style="margin-bottom: 20px;">
        <h1>✅ Approve & Publish Courses</h1>
        <p class="muted">Review newly created courses and publish them so students can discover and enroll.</p>
      </div>

      <div class="card fade-in">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
          <h2>Unpublished Courses</h2>
          <span class="badge <?php echo empty($courses) ? 'warning' : 'info'; ?>"><?php echo count($courses); ?></span>
        </div>
        <div class="card-body">
          <?php if (empty($courses)): ?>
            <p class="muted" style="margin: 12px 0;">No courses are awaiting publication.</p>
            <div style="margin-top: 12px;">
              <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border:2px solid var(--primary-color);">← Back to Dashboard</a>
            </div>
          <?php else: ?>
            <div style="overflow:auto;">
              <table class="datagrid">
                <thead>
                  <tr>
                    <th style="width:80px;">ID</th>
                    <th>Title</th>
                    <th style="width:220px;">Instructor</th>
                    <th style="width:160px;">Created</th>
                    <th style="width:260px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($courses as $c): ?>
                    <tr>
                      <td>#<?php echo (int)$c['id']; ?></td>
                      <td><?php echo htmlspecialchars($c['title']); ?></td>
                      <td><?php echo htmlspecialchars($c['instructor_name'] ?? '—'); ?></td>
                      <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                      <td>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                          <a class="btn-sm btn secondary" href="/admin_course_details.php?id=<?php echo (int)$c['id']; ?>">🔍 View Details</a>
                          <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('Publish this course now?');">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <input type="hidden" name="course_id" value="<?php echo (int)$c['id']; ?>">
                            <input type="hidden" name="action" value="publish">
                            <button type="submit" class="btn-sm btn primary">🚀 Publish</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div style="margin-top: 16px;">
              <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border:2px solid var(--primary-color);">← Back to Dashboard</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
