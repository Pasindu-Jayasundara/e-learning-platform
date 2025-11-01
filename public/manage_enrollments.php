<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin','instructor']);
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);

// fetch courses the user can manage
if ($user['role'] === 'admin') {
    $courses = $pdo->query('SELECT id,title FROM courses ORDER BY title')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT id,title FROM courses WHERE instructor_id = ? ORDER BY title');
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
}

$students = [];
if ($course_id) {
    $stmt = $pdo->prepare('SELECT e.*, u.name, u.email FROM enrollments e JOIN users u ON e.user_id = u.id WHERE e.course_id = ?');
    $stmt->execute([$course_id]);
    $students = $stmt->fetchAll();
}

$errors = get_flash('error');
$success = get_flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Enrollments - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 12px; border-bottom: 1px solid var(--border-color); text-align: left; }
    .table th { font-weight: 600; color: var(--text-secondary); }
  </style>
  </head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>

  <main>
    <div class="container">
      <div class="page-header fade-in">
        <h1>👥 Manage Enrollments</h1>
        <p>Enroll students individually or in bulk, and manage existing enrollments.</p>
        <div style="margin-top: 8px;">
          <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Dashboard</a>
        </div>
      </div>

      <div class="card fade-in" style="margin-bottom: 20px;">
        <div class="card-header"><h2>Select Course</h2></div>
        <div class="card-body">
          <form method="get" style="display:flex; gap: 12px; align-items:center; padding:0; box-shadow:none; max-width:none;">
            <label style="margin:0;">Course</label>
            <select name="course_id" onchange="this.form.submit()" style="min-width: 320px;">
              <option value="">-- choose --</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($c['id']==$course_id)?'selected':''; ?>><?php echo htmlspecialchars($c['title']); ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($course_id): ?>
              <a class="btn secondary" href="/course.php?id=<?php echo $course_id; ?>">View Course</a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <?php if ($course_id): ?>
        <div class="grid-2">
          <div class="card fade-in">
            <div class="card-header"><h2>Enroll a Student</h2></div>
            <div class="card-body">
              <form method="post" action="/enroll_user.php" style="display:grid; gap: 12px; padding:0; box-shadow:none; max-width:none;">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <div>
                  <label>Student Email</label>
                  <input type="email" name="email" placeholder="student@example.com" required>
                </div>
                <div>
                  <button type="submit" class="btn primary">➕ Enroll</button>
                </div>
              </form>
            </div>
          </div>

          <div class="card fade-in">
            <div class="card-header"><h2>Bulk Enroll (CSV)</h2></div>
            <div class="card-body">
              <p style="color: var(--text-secondary); margin-top:0;">CSV must have a header and an <code>email</code> column. Only existing users will be enrolled.</p>
              <form method="post" action="/bulk_enroll.php" enctype="multipart/form-data" style="display:grid; gap: 12px; padding:0; box-shadow:none; max-width:none;">
                <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <input type="file" name="csv" accept="text/csv" required>
                <div>
                  <button type="submit" class="btn secondary">📤 Upload and Enroll</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="card fade-in" style="margin-top: 20px;">
          <div class="card-header"><h2>Enrolled Students (<?php echo count($students); ?>)</h2></div>
          <div class="card-body">
            <?php if (empty($students)): ?>
              <p style="color: var(--text-secondary);">No students enrolled yet.</p>
            <?php else: ?>
              <div style="overflow:auto;">
                <table class="table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Progress</th>
                      <th style="width: 160px;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($students as $s): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($s['name']); ?></td>
                      <td><?php echo htmlspecialchars($s['email']); ?></td>
                      <td><?php echo intval($s['progress']); ?>%</td>
                      <td>
                        <form method="post" action="/unenroll_user.php" onsubmit="return confirm('Unenroll this student?');" style="display:inline; margin-right:6px;">
                          <input type="hidden" name="enrollment_id" value="<?php echo $s['id']; ?>">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                          <button type="submit" class="btn-sm btn danger">🗑️ Unenroll</button>
                        </form>
                        <form method="post" action="/update_progress.php" style="display:inline;">
                          <input type="hidden" name="enrollment_id" value="<?php echo $s['id']; ?>">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                          <input type="number" name="progress" min="0" max="100" value="<?php echo intval($s['progress']); ?>" style="width:80px;">
                          <button type="submit" class="btn-sm btn secondary">💾 Save</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
