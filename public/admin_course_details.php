<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);
$user = current_user();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: /manage_courses.php'); exit; }

// Load core course and aggregates
$stmt = $pdo->prepare("SELECT c.*, u.name AS instructor_name, u.email AS instructor_email,
  COALESCE(enr.cnt,0) AS students_count,
  COALESCE(pay.sum_completed,0) AS total_earned
  FROM courses c
  LEFT JOIN users u ON c.instructor_id = u.id
  LEFT JOIN (
    SELECT course_id, COUNT(DISTINCT user_id) AS cnt FROM enrollments WHERE course_id = ?
  ) enr ON enr.course_id = c.id
  LEFT JOIN (
    SELECT course_id, SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) AS sum_completed
    FROM payments WHERE course_id = ?
  ) pay ON pay.course_id = c.id
  WHERE c.id = ? LIMIT 1");
$stmt->execute([$id,$id,$id]);
$course = $stmt->fetch();
if (!$course) { set_flash('error','Course not found'); header('Location: /manage_courses.php'); exit; }

// Lessons
$lessons = $pdo->prepare("SELECT * FROM lessons WHERE course_id=? ORDER BY sort_order ASC, id ASC");
$lessons->execute([$id]);
$lessons = $lessons->fetchAll();

// Enrollments with users
$enrollStmt = $pdo->prepare("SELECT e.*, u.name, u.email FROM enrollments e JOIN users u ON e.user_id=u.id WHERE e.course_id=? ORDER BY e.enrolled_at DESC");
$enrollStmt->execute([$id]);
$enrollments = $enrollStmt->fetchAll();

// Payments
$payStmt = $pdo->prepare("SELECT p.*, u.name, u.email FROM payments p JOIN users u ON p.user_id=u.id WHERE p.course_id=? ORDER BY p.created_at DESC");
$payStmt->execute([$id]);
$payments = $payStmt->fetchAll();

// Announcements
$annStmt = $pdo->prepare("SELECT a.*, u.name AS author_name FROM announcements a LEFT JOIN users u ON a.user_id=u.id WHERE a.course_id=? ORDER BY a.created_at DESC");
$annStmt->execute([$id]);
$announcements = $annStmt->fetchAll();

// Forum counts and latest posts
$forumCountPosts = $pdo->prepare("SELECT COUNT(*) FROM forum_posts WHERE course_id=?");
$forumCountPosts->execute([$id]);
$forumPostsCount = (int)$forumCountPosts->fetchColumn();
$forumLatest = $pdo->prepare("SELECT fp.*, u.name FROM forum_posts fp JOIN users u ON fp.user_id=u.id WHERE fp.course_id=? ORDER BY fp.created_at DESC LIMIT 5");
$forumLatest->execute([$id]);
$forumLatest = $forumLatest->fetchAll();
$repStmt = $pdo->prepare("SELECT COUNT(*) FROM forum_replies WHERE post_id IN (SELECT id FROM forum_posts WHERE course_id=?)");
$repStmt->execute([$id]);
$forumRepliesCount = (int)$repStmt->fetchColumn();

// Materials
$matStmt = $pdo->prepare("SELECT * FROM course_materials WHERE course_id=? ORDER BY uploaded_at DESC");
$matStmt->execute([$id]);
$materials = $matStmt->fetchAll();

// Certificates count
$certCountStmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE course_id=?");
$certCountStmt->execute([$id]);
$certCount = (int)$certCountStmt->fetchColumn();

// Lesson progress summary
$lpStmt = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM lesson_progress WHERE course_id=? GROUP BY status");
try { $lpStmt->execute([$id]); $lp = $lpStmt->fetchAll(PDO::FETCH_KEY_PAIR); }
catch (Throwable $e) { $lp = []; }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Course Details (Admin) - E-Learning</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 900px){ .grid-2 { grid-template-columns: 1fr; } }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    table.datagrid { width:100%; border-collapse: collapse; }
    table.datagrid th, table.datagrid td { border: 1px solid var(--border-color); padding: 8px; }
    table.datagrid th { background: var(--bg-secondary); text-align: left; }
  </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header" style="margin-bottom: 16px;">
        <h1>🔍 Course Details</h1>
        <p>Full information for "<?php echo htmlspecialchars($course['title']); ?>"</p>
        <div style="margin-top:8px;">
          <a href="/manage_courses.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Manage Courses</a>
          <a href="/course.php?id=<?php echo $course['id']; ?>" class="btn secondary">View Public Page</a>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><h2>Course</h2></div>
          <div class="card-body">
            <div style="display:flex; gap:16px; align-items:flex-start;">
              <div>
                <?php
                  $thumb = $course['thumbnail_url'] ?? '';
                  if (!$thumb) { $thumb = 'https://picsum.photos/seed/course-'.intval($course['id']).'/480/280'; }
                ?>
                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="thumbnail" style="max-width: 320px; border-radius: var(--radius-md); border:1px solid var(--border-color);">
              </div>
              <div style="flex:1;">
                <p><strong>ID:</strong> <span class="mono">#<?php echo $course['id']; ?></span></p>
                <p><strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor_name'] ?? '—'); ?> (<?php echo htmlspecialchars($course['instructor_email'] ?? ''); ?>)</p>
                <p><strong>Price:</strong> $<?php echo number_format((float)$course['price'],2); ?></p>
                <p><strong>Status:</strong> <span class="badge <?php echo $course['is_published'] ? 'success' : 'warning'; ?>"><?php echo $course['is_published'] ? 'Published' : 'Draft'; ?></span></p>
                <p><strong>Students:</strong> <?php echo (int)$course['students_count']; ?></p>
                <p><strong>Total Earned:</strong> $<?php echo number_format((float)$course['total_earned'],2); ?></p>
                <p><strong>Created at:</strong> <?php echo htmlspecialchars($course['created_at']); ?></p>
              </div>
            </div>
            <?php if (!empty($course['description'])): ?>
              <div style="margin-top:12px;">
                <label>Description</label>
                <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($course['description']); ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h2>Summary</h2></div>
          <div class="card-body">
            <div style="display:grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
              <div class="stat">
                <div class="stat-label">Lessons</div>
                <div class="stat-value"><?php echo count($lessons); ?></div>
              </div>
              <div class="stat">
                <div class="stat-label">Materials</div>
                <div class="stat-value"><?php echo count($materials); ?></div>
              </div>
              <div class="stat">
                <div class="stat-label">Enrollments</div>
                <div class="stat-value"><?php echo count($enrollments); ?></div>
              </div>
              <div class="stat">
                <div class="stat-label">Payments</div>
                <div class="stat-value"><?php echo count($payments); ?></div>
              </div>
              <div class="stat">
                <div class="stat-label">Forum Posts</div>
                <div class="stat-value"><?php echo $forumPostsCount; ?></div>
              </div>
              <div class="stat">
                <div class="stat-label">Certificates</div>
                <div class="stat-value"><?php echo $certCount; ?></div>
              </div>
            </div>
            <div style="margin-top: 12px;">
              <div>Progress: 
                <span class="badge">not_started: <?php echo (int)($lp['not_started'] ?? 0); ?></span>
                <span class="badge">in_progress: <?php echo (int)($lp['in_progress'] ?? 0); ?></span>
                <span class="badge">completed: <?php echo (int)($lp['completed'] ?? 0); ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:16px;">
        <div class="card-header"><h2>Lessons</h2></div>
        <div class="card-body">
          <?php if (empty($lessons)): ?>
            <p class="text-muted">No lessons added.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>#</th><th>Title</th><th>Duration</th><th>Video URL</th></tr></thead>
              <tbody>
                <?php foreach ($lessons as $ls): ?>
                  <tr>
                    <td><?php echo (int)$ls['sort_order']; ?></td>
                    <td><?php echo htmlspecialchars($ls['title']); ?></td>
                    <td><?php echo $ls['duration_seconds'] !== null ? (int)$ls['duration_seconds'].'s' : '—'; ?></td>
                    <td class="mono"><?php echo htmlspecialchars($ls['video_url']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <div class="grid-2" style="margin-top:16px;">
        <div class="card">
          <div class="card-header"><h2>Enrollments</h2></div>
          <div class="card-body">
            <?php if (empty($enrollments)): ?>
              <p class="text-muted">No students enrolled.</p>
            <?php else: ?>
              <table class="datagrid">
                <thead><tr><th>User</th><th>Email</th><th>Progress</th><th>Enrolled At</th></tr></thead>
                <tbody>
                  <?php foreach ($enrollments as $e): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($e['name']); ?></td>
                      <td class="mono"><?php echo htmlspecialchars($e['email']); ?></td>
                      <td><?php echo (int)$e['progress']; ?>%</td>
                      <td><?php echo htmlspecialchars($e['enrolled_at']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h2>Payments</h2></div>
          <div class="card-body">
            <?php if (empty($payments)): ?>
              <p class="text-muted">No payments yet.</p>
            <?php else: ?>
              <table class="datagrid">
                <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Status</th><th>Amount</th><th>Created</th></tr></thead>
                <tbody>
                  <?php foreach ($payments as $p): ?>
                    <tr>
                      <td class="mono">#<?php echo (int)$p['id']; ?></td>
                      <td><?php echo htmlspecialchars($p['name']); ?></td>
                      <td class="mono"><?php echo htmlspecialchars($p['email']); ?></td>
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
      </div>

      <div class="grid-2" style="margin-top:16px;">
        <div class="card">
          <div class="card-header"><h2>Announcements</h2></div>
          <div class="card-body">
            <?php if (empty($announcements)): ?>
              <p class="text-muted">No announcements.</p>
            <?php else: ?>
              <ul>
                <?php foreach ($announcements as $a): ?>
                  <li>
                    <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                    <span class="text-muted"> by <?php echo htmlspecialchars($a['author_name'] ?? '—'); ?> on <?php echo htmlspecialchars($a['created_at']); ?></span>
                    <div style="white-space: pre-wrap; margin-top:4px;"><?php echo htmlspecialchars($a['message']); ?></div>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h2>Forum</h2></div>
          <div class="card-body">
            <p>Posts: <?php echo $forumPostsCount; ?>, Replies: <?php echo (int)$forumRepliesCount; ?></p>
            <?php if (!empty($forumLatest)): ?>
              <ul>
                <?php foreach ($forumLatest as $fp): ?>
                  <li>
                    <strong><?php echo htmlspecialchars($fp['title']); ?></strong>
                    <span class="text-muted"> by <?php echo htmlspecialchars($fp['name']); ?> on <?php echo htmlspecialchars($fp['created_at']); ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="text-muted">No forum posts yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:16px;">
        <div class="card-header"><h2>Materials</h2></div>
        <div class="card-body">
          <?php if (empty($materials)): ?>
            <p class="text-muted">No materials uploaded.</p>
          <?php else: ?>
            <ul>
              <?php foreach ($materials as $m): ?>
                <li>
                  <a href="/download_material.php?id=<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['filename']); ?></a>
                  <span class="text-muted"> (<?php echo htmlspecialchars($m['uploaded_at']); ?>)</span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <div style="height:24px;"></div>
    </div>
  </main>
</body>
</html>
