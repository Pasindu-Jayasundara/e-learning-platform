<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// simple course fetch
// role-aware course fetch
if ($user['role'] === 'instructor') {
  // Only this instructor's courses + enrollment counts
  $stmt = $pdo->prepare('SELECT c.*, u.name AS instructor_name, COALESCE(COUNT(e.id),0) AS enroll_count
                         FROM courses c
                         LEFT JOIN users u ON c.instructor_id = u.id
                         LEFT JOIN enrollments e ON e.course_id = c.id
                         WHERE c.instructor_id = ?
                         GROUP BY c.id');
  $stmt->execute([$user['id']]);
  $courses = $stmt->fetchAll();
} else if ($user['role'] === 'admin') {
  // Admin: show all courses
  $sth = $pdo->query('SELECT c.*, u.name AS instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id');
  $courses = $sth->fetchAll();
} else {
  // Students (and other non-instructors): show only published courses
  $sth = $pdo->query('SELECT c.*, u.name AS instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.is_published = 1');
  $courses = $sth->fetchAll();
}
// fetch current user's enrollments for quick lookup
$enrolledCourseIds = [];
if ($user && $user['role'] === 'student') {
  $stmt = $pdo->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
  $stmt->execute([$user['id']]);
  $rows = $stmt->fetchAll();
  foreach ($rows as $r) $enrolledCourseIds[] = $r['course_id'];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  
  <main>
    <div class="container">
      <!-- Welcome Header -->
      <div class="page-header fade-in">
        <h1>Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
        <p>Here’s a quick overview of your account.</p>
      </div>

      <?php if ($user['role'] === 'admin'): ?>
        <?php
          $totUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
          $totCourses = (int)$pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
          $totEnrolls = (int)$pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn();
        ?>
        <div class="stats-grid fade-in" style="margin-bottom:24px;">
          <div class="stat-card"><div class="stat-card-label">Users</div><div class="stat-card-value"><?php echo $totUsers; ?></div></div>
          <div class="stat-card" style="border-left-color: var(--secondary-color);"><div class="stat-card-label">Courses</div><div class="stat-card-value"><?php echo $totCourses; ?></div></div>
          <div class="stat-card" style="border-left-color: var(--accent-color);"><div class="stat-card-label">Enrollments</div><div class="stat-card-value"><?php echo $totEnrolls; ?></div></div>
        </div>
      <?php elseif ($user['role'] === 'instructor'): ?>
        <?php
          // counts for instructor
          $stmtCnt = $pdo->prepare('SELECT COUNT(*) FROM courses WHERE instructor_id = ?');
          $stmtCnt->execute([$user['id']]);
          $myCourses = (int)$stmtCnt->fetchColumn();
          $stmtStu = $pdo->prepare('SELECT COUNT(DISTINCT e.user_id) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.instructor_id = ?');
          $stmtStu->execute([$user['id']]);
          $myStudents = (int)$stmtStu->fetchColumn();
          // total earnings for this instructor from completed payments
          $stmtEarn = $pdo->prepare('SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN courses c ON p.course_id = c.id WHERE c.instructor_id = ? AND p.status = ?');
          $stmtEarn->execute([$user['id'], 'completed']);
            $myEarnings = (float)$stmtEarn->fetchColumn();
        ?>
        <div class="stats-grid fade-in" style="margin-bottom:24px;">
          <div class="stat-card"><div class="stat-card-label">Your Courses</div><div class="stat-card-value"><?php echo $myCourses; ?></div></div>
          <div class="stat-card" style="border-left-color: var(--secondary-color);"><div class="stat-card-label">Total Students</div><div class="stat-card-value"><?php echo $myStudents; ?></div></div>
          <div class="stat-card" style="border-left-color: var(--accent-color);"><div class="stat-card-label">Earnings</div><div class="stat-card-value">$<?php echo number_format($myEarnings, 2); ?></div></div>
        </div>
      <?php endif; ?>

      <?php /* Quick Actions section removed per request */ ?>

      <?php if (in_array($user['role'], ['admin','instructor'])): ?>
        <!-- Reports Card -->
        <div class="card fade-in" style="margin-bottom: 32px; max-width: 920px;">
          <div class="card-header">
            <h2>📑 Reports</h2>
          </div>
          <div class="card-body">
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
              <?php if ($user['role'] === 'instructor'): ?>
                <a href="/earnings.php" class="btn secondary">View Earnings</a>
                <a href="/export_earnings_csv.php" class="btn primary">Download Earnings CSV</a>
                <a href="/export_earnings_pdf.php" class="btn secondary">Download Earnings PDF</a>
              <?php else: ?>
                <a href="/reports_platform.php" class="btn primary">Platform Reports</a>
                <a href="/earnings.php" class="btn secondary">Earnings (Filterable)</a>
                <a href="/export_earnings_csv.php" class="btn secondary">Earnings CSV</a>
                <a href="/export_earnings_pdf.php" class="btn secondary">Earnings PDF</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($user['role'] === 'student'): ?>
        <!-- Student Stats -->
        <div class="stats-grid fade-in">
          <div class="stat-card">
            <div class="stat-card-label">Enrolled Courses</div>
            <div class="stat-card-value"><?php echo count($enrolledCourseIds); ?></div>
          </div>
          <div class="stat-card" style="border-left-color: var(--secondary-color);">
            <div class="stat-card-label">Available Courses</div>
            <div class="stat-card-value"><?php echo count($courses); ?></div>
          </div>
          <div class="stat-card" style="border-left-color: var(--accent-color);">
            <div class="stat-card-label">Quick Links</div>
            <div style="margin-top: 8px;">
              <a href="/my_progress.php" class="btn-sm btn primary">View Progress</a>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Courses Section -->
      <div class="card-header" style="margin-bottom: 24px;">
        <?php if ($user['role'] === 'instructor'): ?>
          <h2>👨‍🏫 Your Courses (<?php echo count($courses); ?>)</h2>
        <?php elseif ($user['role'] === 'student'): ?>
          <h2><?php echo count($enrolledCourseIds) > 0 ? '📖 Continue Learning' : '🎯 Available Courses'; ?></h2>
        <?php else: ?>
          <h2>🎯 All Courses</h2>
        <?php endif; ?>
      </div>

      <div class="course-grid fade-in">
        <?php foreach ($courses as $c): ?>
          <?php
            $isEnrolled = in_array($c['id'], $enrolledCourseIds);
            $price = floatval($c['price'] ?? 0);
            $isFree = $price <= 0;
            // thumbnail: prefer course thumbnail_url, fallback to picsum stable seed
            $thumb = !empty($c['thumbnail_url']) ? $c['thumbnail_url'] : ('https://picsum.photos/seed/course-' . intval($c['id']) . '/600/360');
          ?>
          <div class="course-card">
            <div class="course-card-image">
              <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Course thumbnail">
              <?php if ($isEnrolled): ?>
                <div class="course-card-badge" style="background: var(--secondary-color);">Enrolled</div>
              <?php elseif ($isFree): ?>
                <div class="course-card-badge" style="background: var(--secondary-color);">Free</div>
              <?php else: ?>
                <div class="course-card-badge">$<?php echo htmlspecialchars($c['price']); ?></div>
              <?php endif; ?>
            </div>

            <div class="course-card-content">
              <h3 class="course-card-title">
                <a href="/course.php?id=<?php echo $c['id']; ?>">
                  <?php echo htmlspecialchars($c['title']); ?>
                </a>
              </h3>

              <div class="course-card-meta">
                <span>👨‍🏫 <?php echo htmlspecialchars($c['instructor_name'] ?? 'Instructor'); ?></span>
              </div>

              <?php if (!empty($c['description'])): ?>
                <div class="course-card-description">
                  <?php echo htmlspecialchars(substr($c['description'], 0, 100)); ?>
                  <?php if (strlen($c['description']) > 100): ?>...<?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="course-card-footer">
                <?php if ($user['role'] === 'student'): ?>
                  <?php if ($isEnrolled): ?>
                    <div class="enrolled-badge">Enrolled</div>
                    <a href="/certificate.php?course_id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">🎓 Certificate</a>
                  <?php else: ?>
                    <div class="course-price <?php echo $isFree ? 'free' : ''; ?>">
                      <?php echo $isFree ? 'Free' : '$' . htmlspecialchars($c['price']); ?>
                    </div>
                    <form method="post" action="/enroll.php" style="display:inline; margin:0;">
                      <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                      <button type="submit" class="btn-sm btn primary">Enroll Now</button>
                    </form>
                  <?php endif; ?>
                <?php elseif ($user['role'] === 'instructor' && $c['instructor_id'] == $user['id']): ?>
                  <a href="/edit_course.php?id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">✏️ Edit</a>
                  <a href="/edit_lessons.php?course_id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">🎞️ Lessons</a>
                  <?php $hasEnrollments = isset($c['enroll_count']) ? intval($c['enroll_count']) > 0 : false; ?>
                  <form method="post" action="/delete_course.php" style="display:inline; margin:0;"
                        onsubmit="return <?php echo $hasEnrollments ? 'alert(\'Cannot delete: students are enrolled in this course.\'), false' : 'confirm(\'Delete this course and all its materials?\')'; ?>;">
                    <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <button type="submit" class="btn-sm btn danger" <?php echo $hasEnrollments ? 'disabled title="Cannot delete: students enrolled"' : ''; ?>>🗑️ Delete</button>
                  </form>
                <?php else: ?>
                  <a href="/course.php?id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">View Details</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($courses)): ?>
          <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 48px;">
            <p style="font-size: 18px; color: var(--text-secondary);">
              📚 No courses available yet. Check back soon!
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</body>
</html>
