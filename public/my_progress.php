<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// fetch enrollments for current user
$stmt = $pdo->prepare('SELECT e.*, c.title, c.instructor_id, u.name as instructor_name FROM enrollments e JOIN courses c ON e.course_id = c.id LEFT JOIN users u ON c.instructor_id = u.id WHERE e.user_id = ?');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

// Compute progress consistently with certificate gating: use lessons when present, else enrollment.progress
$progressMap = []; // course_id => percent
if (!empty($rows)) {
  $courseIds = array_map(function($r){ return (int)$r['course_id']; }, $rows);
  $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

  // fetch lesson counts per course
  $lessonCount = [];
  $st = $pdo->prepare("SELECT course_id, COUNT(*) as cnt FROM lessons WHERE course_id IN ($placeholders) GROUP BY course_id");
  $st->execute($courseIds);
  foreach ($st->fetchAll() as $lc) { $lessonCount[(int)$lc['course_id']] = (int)$lc['cnt']; }

  // fetch completed lessons for this user per course
  $completedCount = [];
  $st2 = $pdo->prepare("SELECT course_id, COUNT(*) as cnt FROM lesson_progress WHERE user_id = ? AND status = 'completed' AND course_id IN ($placeholders) GROUP BY course_id");
  $st2->execute(array_merge([$user['id']], $courseIds));
  foreach ($st2->fetchAll() as $cc) { $completedCount[(int)$cc['course_id']] = (int)$cc['cnt']; }

  // compute percent
  foreach ($rows as $r) {
    $cid = (int)$r['course_id'];
    $total = $lessonCount[$cid] ?? 0;
    if ($total > 0) {
      $done = $completedCount[$cid] ?? 0;
      $pct = $total > 0 ? (int)floor(($done * 100) / $total) : 0;
      $progressMap[$cid] = $pct;
    } else {
      $progressMap[$cid] = (int)$r['progress'];
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Progress - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  
  <main>
    <div class="container">
      <div class="page-header fade-in">
        <h1>📊 My Learning Progress</h1>
        <p>Track your course completion and learning journey</p>
      </div>

      <div style="display: flex; gap: 12px; margin-bottom: 32px;" class="fade-in">
        <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">
          ← Back to Dashboard
        </a>
        <a href="/export_progress.php" class="btn secondary">
          📥 Download CSV Report
        </a>
      </div>

      <?php if (empty($rows)): ?>
        <div class="card fade-in" style="text-align: center; padding: 64px 32px;">
          <div style="font-size: 64px; margin-bottom: 16px;">📚</div>
          <h2 style="margin-bottom: 12px;">No Courses Yet</h2>
          <p style="color: var(--text-secondary); margin-bottom: 24px;">
            You haven't enrolled in any courses yet. Start your learning journey today!
          </p>
          <a href="/dashboard.php" class="btn primary">
            🚀 Browse Available Courses
          </a>
        </div>
      <?php else: ?>
        <!-- Stats Overview -->
        <div class="stats-grid fade-in" style="margin-bottom: 32px;">
          <?php
            $totalCourses = count($rows);
            $completedCourses = 0;
            $totalProgress = 0;
            foreach ($rows as $r) {
              $cid = (int)$r['course_id'];
              $prog = isset($progressMap[$cid]) ? (int)$progressMap[$cid] : (int)$r['progress'];
              $totalProgress += $prog;
              if ($prog >= 100) $completedCourses++;
            }
            $avgProgress = $totalCourses > 0 ? round($totalProgress / $totalCourses) : 0;
          ?>
          <div class="stat-card">
            <div class="stat-card-label">Enrolled Courses</div>
            <div class="stat-card-value"><?php echo $totalCourses; ?></div>
          </div>
          <div class="stat-card" style="border-left-color: var(--secondary-color);">
            <div class="stat-card-label">Completed Courses</div>
            <div class="stat-card-value"><?php echo $completedCourses; ?></div>
          </div>
          <div class="stat-card" style="border-left-color: var(--accent-color);">
            <div class="stat-card-label">Average Progress</div>
            <div class="stat-card-value"><?php echo $avgProgress; ?>%</div>
          </div>
        </div>

        <!-- Progress Table -->
        <div class="card fade-in">
          <div class="card-header">
            <h2>📖 Course Progress Details</h2>
          </div>
          <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table>
              <thead>
                <tr>
                  <th>Course</th>
                  <th>Instructor</th>
                  <th>Progress</th>
                  <th>Status</th>
                  <th>Enrolled Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                  <?php 
                    $cid = (int)$r['course_id'];
                    $progress = isset($progressMap[$cid]) ? (int)$progressMap[$cid] : (int)$r['progress'];
                    $isCompleted = $progress >= 100;
                  ?>
                  <tr>
                    <td>
                      <a href="/course.php?id=<?php echo $r['course_id']; ?>" style="font-weight: 600;">
                        <?php echo htmlspecialchars($r['title']); ?>
                      </a>
                    </td>
                    <td><?php echo htmlspecialchars($r['instructor_name']); ?></td>
                    <td>
                      <div style="display: flex; align-items: center; gap: 12px; min-width: 150px;">
                        <div class="progress-container" style="flex-grow: 1; margin: 0;">
                          <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                        <span style="font-weight: 700; color: var(--primary-color); min-width: 45px;">
                          <?php echo $progress; ?>%
                        </span>
                      </div>
                    </td>
                    <td>
                      <?php if ($isCompleted): ?>
                        <span class="badge success">✓ Completed</span>
                      <?php elseif ($progress >= 50): ?>
                        <span class="badge primary">In Progress</span>
                      <?php else: ?>
                        <span class="badge warning">Just Started</span>
                      <?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;">
                      <?php echo date('M j, Y', strtotime($r['enrolled_at'])); ?>
                    </td>
                    <td>
                      <div style="display: flex; gap: 8px;">
                        <a href="/course.php?id=<?php echo $r['course_id']; ?>" class="btn-sm btn primary">
                          Continue
                        </a>
                        <?php if ($isCompleted): ?>
                          <a href="/certificate.php?course_id=<?php echo $r['course_id']; ?>" class="btn-sm btn secondary">
                            🎓 Certificate
                          </a>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
