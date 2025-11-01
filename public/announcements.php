<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// show announcements: global (course_id IS NULL) or course-specific
$course_id = intval($_GET['course_id'] ?? 0);
if ($course_id) {
    $stmt = $pdo->prepare('SELECT a.*, u.name as author FROM announcements a LEFT JOIN users u ON a.user_id = u.id WHERE a.course_id = ? ORDER BY a.created_at DESC');
    $stmt->execute([$course_id]);
} else {
    $stmt = $pdo->query('SELECT a.*, u.name as author, c.title as course_title FROM announcements a LEFT JOIN users u ON a.user_id = u.id LEFT JOIN courses c ON a.course_id = c.id ORDER BY a.created_at DESC');
}
$ann = $stmt->fetchAll();

// fetch courses for instructor/admin when posting
$courses = [];
if ($user['role'] === 'admin') {
    $courses = $pdo->query('SELECT id,title FROM courses ORDER BY title')->fetchAll();
} elseif ($user['role'] === 'instructor') {
    $stmt = $pdo->prepare('SELECT id,title FROM courses WHERE instructor_id = ? ORDER BY title');
    $stmt->execute([$user['id']]);
    $courses = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Announcements</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  </head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header fade-in">
        <h1>📣 Announcements</h1>
        <p>Stay up to date with platform and course updates</p>
        <?php if ($course_id): ?>
          <div style="margin-top:8px;">
            <span class="badge primary">Filtered by course</span>
            <a class="btn light" href="/announcements.php" style="margin-left:8px; color:#fff;">Clear filter</a>
          </div>
        <?php endif; ?>
      </div>

      <div style="display:flex; gap: 12px; margin-bottom: 24px;" class="fade-in">
        <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Dashboard</a>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'instructor'): ?>
          <a href="#composer" class="btn secondary">✍️ New Announcement</a>
        <?php endif; ?>
      </div>

      <?php if ($user['role'] === 'admin' || $user['role'] === 'instructor'): ?>
        <div id="composer" class="card fade-in" style="margin-bottom: 24px;">
          <div class="card-header">
            <h2>✍️ Post Announcement</h2>
          </div>
          <div class="card-body">
            <form method="post" action="/create_announcement.php">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
              <label for="title">Title</label>
              <input id="title" type="text" name="title" required placeholder="e.g., Platform maintenance on Friday">
              <label for="message">Message</label>
              <textarea id="message" name="message" required placeholder="Share details for learners…"></textarea>
              <?php if (!empty($courses)): ?>
                <label for="course_id">Course (optional)</label>
                <select id="course_id" name="course_id">
                  <option value="">-- Global announcement --</option>
                  <?php foreach ($courses as $c): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
              <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="submit" class="btn primary">Post Announcement</button>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <?php if (empty($ann)): ?>
        <div class="card fade-in" style="text-align:center; padding: 48px 24px;">
          <div style="font-size:56px;">📰</div>
          <h2 style="margin-top:8px;">No announcements yet</h2>
          <p class="muted">When announcements are posted, they will appear here.</p>
        </div>
      <?php else: ?>
        <div class="fade-in" style="display:flex; flex-direction:column; gap:16px;">
          <?php foreach ($ann as $a): ?>
            <div class="card">
              <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div style="display:flex; align-items:center; gap:10px;">
                  <h3 style="margin:0;"><?php echo htmlspecialchars($a['title']); ?></h3>
                  <?php if (!empty($a['course_title'])): ?>
                    <span class="badge primary" title="Course-specific">Course: <?php echo htmlspecialchars($a['course_title']); ?></span>
                  <?php else: ?>
                    <span class="badge success" title="Global announcement">Global</span>
                  <?php endif; ?>
                </div>
                <div class="muted" style="font-size: 13px;">
                  By <strong><?php echo htmlspecialchars($a['author'] ?? 'System'); ?></strong>
                  • <?php echo date('M j, Y g:i A', strtotime($a['created_at'])); ?>
                </div>
              </div>
              <div class="card-body" style="line-height:1.7; color: var(--text-primary);">
                <?php echo nl2br(htmlspecialchars($a['message'])); ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top: 20px;">
        <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Dashboard</a>
      </div>
    </div>
  </main>
</body>
</html>
