<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['instructor']);
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);
if (!$course_id) { header('Location: /manage_courses.php'); exit; }

// Load course
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) { set_flash('error','Course not found'); header('Location: /manage_courses.php'); exit; }

// Only the owning instructor may manage lessons
if ($user['role'] !== 'instructor' || $course['instructor_id'] != $user['id']) { http_response_code(403); echo 'Forbidden'; exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!verify_csrf_token($csrf)) { $errors[] = 'Invalid CSRF token'; }
  $action = $_POST['action'] ?? '';
  if (empty($errors)) {
    if ($action === 'create') {
      $title = trim($_POST['title'] ?? '');
      $video_url = trim($_POST['video_url'] ?? '');
      $description = $_POST['description'] ?? null;
      $duration = $_POST['duration_seconds'] !== '' ? intval($_POST['duration_seconds']) : null;
      $sort_order = $_POST['sort_order'] !== '' ? intval($_POST['sort_order']) : 0;
      if (!$title) $errors[] = 'Title required';
      if (!$video_url) $errors[] = 'Video URL required';
      if (empty($errors)) {
        $ins = $pdo->prepare('INSERT INTO lessons (course_id, title, description, video_url, duration_seconds, sort_order) VALUES (?,?,?,?,?,?)');
        $ins->execute([$course_id, $title, $description, $video_url, $duration, $sort_order]);
        set_flash('success','Lesson created');
        header('Location: /edit_lessons.php?course_id='.$course_id); exit;
      }
    } elseif ($action === 'update') {
      $lesson_id = intval($_POST['lesson_id'] ?? 0);
      $q = $pdo->prepare('SELECT * FROM lessons WHERE id = ? AND course_id = ? LIMIT 1');
      $q->execute([$lesson_id, $course_id]);
      $lesson = $q->fetch();
      if (!$lesson) { $errors[] = 'Lesson not found'; }
      $title = trim($_POST['title'] ?? '');
      $video_url = trim($_POST['video_url'] ?? '');
      $description = $_POST['description'] ?? null;
      $duration = $_POST['duration_seconds'] !== '' ? intval($_POST['duration_seconds']) : null;
      $sort_order = $_POST['sort_order'] !== '' ? intval($_POST['sort_order']) : 0;
      if (empty($errors)) {
        $u = $pdo->prepare('UPDATE lessons SET title=?, description=?, video_url=?, duration_seconds=?, sort_order=? WHERE id=? AND course_id=?');
        $u->execute([$title, $description, $video_url, $duration, $sort_order, $lesson_id, $course_id]);
        set_flash('success','Lesson updated');
        header('Location: /edit_lessons.php?course_id='.$course_id); exit;
      }
    } elseif ($action === 'delete') {
      $lesson_id = intval($_POST['lesson_id'] ?? 0);
      $d = $pdo->prepare('DELETE FROM lessons WHERE id = ? AND course_id = ?');
      $d->execute([$lesson_id, $course_id]);
      set_flash('success','Lesson deleted');
      header('Location: /edit_lessons.php?course_id='.$course_id); exit;
    }
  }
}

// Fetch lessons
$ls = $pdo->prepare('SELECT * FROM lessons WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
$ls->execute([$course_id]);
$lessons = $ls->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Lessons - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .lessons-table { width:100%; border-collapse: collapse; }
    .lessons-table th, .lessons-table td { border-bottom: 1px solid var(--border-color); padding: 10px; text-align: left; }
    .inline-input { width: 100%; box-sizing: border-box; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header fade-in" style="margin-bottom: 20px;">
        <h1>🎞️ Manage Lessons</h1>
        <p>Course: <?php echo htmlspecialchars($course['title']); ?></p>
        <div style="margin-top: 8px; display:flex; gap:8px;">
          <a href="/manage_courses.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Manage</a>
          <a href="/edit_course.php?id=<?php echo $course_id; ?>" class="btn secondary">✏️ Edit Course</a>
          <a href="/course.php?id=<?php echo $course_id; ?>" class="btn secondary">View Course</a>
        </div>
      </div>

      <?php foreach ($errors as $e): ?>
        <div class="flash error" style="margin-bottom: 12px;"><?php echo htmlspecialchars($e); ?></div>
      <?php endforeach; ?>

      <div class="card fade-in" style="margin-bottom: 24px;">
        <div class="card-header"><h2>Add Lesson</h2></div>
        <div class="card-body">
          <form method="post" style="padding:0; box-shadow:none; max-width:none; display:grid; gap:12px;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="action" value="create">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div>
                <label>Title</label>
                <input type="text" name="title" placeholder="Lesson title" required>
              </div>
              <div>
                <label>Video URL</label>
                <input type="url" name="video_url" placeholder="https://..." required>
              </div>
            </div>
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Optional"></textarea>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 12px;">
              <div>
                <label>Duration (seconds)</label>
                <input type="number" name="duration_seconds" min="0" placeholder="e.g., 600">
              </div>
              <div>
                <label>Sort Order</label>
                <input type="number" name="sort_order" placeholder="0">
              </div>
            </div>
            <div>
              <button type="submit" class="btn primary">➕ Add Lesson</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card fade-in">
        <div class="card-header"><h2>Lessons (<?php echo count($lessons); ?>)</h2></div>
        <div class="card-body">
          <?php if (empty($lessons)): ?>
            <p style="color: var(--text-secondary);">No lessons yet. Add your first lesson above.</p>
          <?php else: ?>
            <div style="overflow:auto;">
              <table class="lessons-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Video URL</th>
                    <th>Duration</th>
                    <th>Sort</th>
                    <th style="width: 160px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($lessons as $i => $l): ?>
                  <tr>
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                      <input type="hidden" name="action" value="update">
                      <input type="hidden" name="lesson_id" value="<?php echo $l['id']; ?>">
                      <td><?php echo $i+1; ?></td>
                      <td><input class="inline-input" type="text" name="title" value="<?php echo htmlspecialchars($l['title']); ?>" required></td>
                      <td><input class="inline-input" type="url" name="video_url" value="<?php echo htmlspecialchars($l['video_url']); ?>" required></td>
                      <td style="max-width:110px;"><input class="inline-input" type="number" name="duration_seconds" min="0" value="<?php echo htmlspecialchars((string)($l['duration_seconds'] ?? '')); ?>"></td>
                      <td style="max-width:90px;"><input class="inline-input" type="number" name="sort_order" value="<?php echo htmlspecialchars((string)($l['sort_order'] ?? 0)); ?>"></td>
                      <td>
                        <div style="display:flex; gap:6px;">
                          <button type="submit" class="btn-sm btn secondary">💾 Save</button>
                        </div>
                      </td>
                    </form>
                    <td style="border:0; padding:0 10px 10px 10px;" colspan="6">
                      <?php if (!empty($l['description'])): ?>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">Desc: <?php echo htmlspecialchars($l['description']); ?></div>
                      <?php endif; ?>
                      <form method="post" onsubmit="return confirm('Delete this lesson?');" style="margin-top:6px;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="lesson_id" value="<?php echo $l['id']; ?>">
                        <button type="submit" class="btn-sm btn danger">🗑️ Delete Lesson</button>
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
    </div>
  </main>
</body>
</html>
