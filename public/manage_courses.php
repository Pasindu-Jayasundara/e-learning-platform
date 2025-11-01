<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
// Friendly redirect: students should browse, not manage
if (!in_array($user['role'], ['admin','instructor'])) {
  header('Location: /courses.php');
  exit;
}

$errors = [];
// Only instructors may create courses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['role'] !== 'instructor') {
        http_response_code(403);
        set_flash('error','Only instructors can create courses');
        header('Location: /manage_courses.php');
        exit;
    }
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
  // Instructors' courses remain unpublished until approved.
  $is_published = 0;
  $instructor_id = $user['id'];

    if (!$title) $errors[] = 'Title required';
  if (empty($errors)) {
    // Ensure listing fee columns exist (idempotent)
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN listing_fee_paid TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) { /* ignore if exists */ }
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN listing_fee_paid_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) { /* ignore if exists */ }

    $stmt = $pdo->prepare('INSERT INTO courses (title,description,price,is_published,instructor_id,listing_fee_paid) VALUES (?,?,?,?,?,0)');
    $stmt->execute([$title,$description,$price,$is_published,$instructor_id]);
    $newCourseId = $pdo->lastInsertId();
    // Redirect instructor to pay the one-time listing fee for this course
    header('Location: /pay_course_listing.php?course_id=' . urlencode($newCourseId));
    exit;
  }
}

// fetch courses for admin or instructor's own courses
if ($user['role'] === 'admin') {
  $sth = $pdo->query('SELECT c.*, u.name as instructor_name, COALESCE(ec.enroll_count,0) AS enroll_count
            FROM courses c
            LEFT JOIN users u ON c.instructor_id = u.id
            LEFT JOIN (
              SELECT course_id, COUNT(*) AS enroll_count
              FROM enrollments
              GROUP BY course_id
            ) ec ON ec.course_id = c.id');
  $courses = $sth->fetchAll();
  // Also fetch unpublished courses for quick publishing table
  $up = $pdo->query("SELECT c.*, u.name as instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.is_published = 0 ORDER BY c.created_at DESC");
  $unpublishedCourses = $up->fetchAll();
} else {
  $stmt = $pdo->prepare('SELECT c.*, u.name as instructor_name, COALESCE(ec.enroll_count,0) AS enroll_count
               FROM courses c
               LEFT JOIN users u ON c.instructor_id = u.id
               LEFT JOIN (
                 SELECT course_id, COUNT(*) AS enroll_count
                 FROM enrollments
                 GROUP BY course_id
               ) ec ON ec.course_id = c.id
               WHERE instructor_id = ?');
  $stmt->execute([$user['id']]);
  $courses = $stmt->fetchAll();
}

// Instructors list no longer needed for admin here (admins cannot create courses)

// fetch materials for courses shown (map course_id -> materials[])
$materialsMap = [];
if (!empty($courses)) {
   $ids = array_map(function($c){ return $c['id']; }, $courses);
   $placeholders = implode(',', array_fill(0, count($ids), '?'));
   $stmt = $pdo->prepare("SELECT * FROM course_materials WHERE course_id IN ($placeholders) ORDER BY uploaded_at DESC");
   $stmt->execute($ids);
   $mrows = $stmt->fetchAll();
   foreach ($mrows as $m) {
      $materialsMap[$m['course_id']][] = $m;
   }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Courses - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  
  <main>
    <div class="container">
      <div class="page-header fade-in">
        <h1>📚 Manage Courses</h1>
        <p>Create and manage your courses, upload materials, and track performance</p>
      </div>

      <?php if ($user['role'] === 'admin'): ?>
        <!-- Admin: Quick Publish Table -->
        <div class="card fade-in" style="margin-bottom: 24px;">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h2>🚀 Publish Courses</h2>
            <span class="badge <?php echo empty($unpublishedCourses) ? 'warning' : 'info'; ?>"><?php echo isset($unpublishedCourses) ? count($unpublishedCourses) : 0; ?></span>
          </div>
          <div class="card-body">
            <?php if (empty($unpublishedCourses)): ?>
              <p class="text-muted">No courses are awaiting publication.</p>
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
                    <?php foreach ($unpublishedCourses as $uc): ?>
                      <tr>
                        <td>#<?php echo (int)$uc['id']; ?></td>
                        <td><?php echo htmlspecialchars($uc['title']); ?></td>
                        <td><?php echo htmlspecialchars($uc['instructor_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($uc['created_at']); ?></td>
                        <td>
                          <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <a class="btn-sm btn secondary" href="/admin_course_details.php?id=<?php echo (int)$uc['id']; ?>">🔍 View Details</a>
                            <form method="post" action="/publish_courses.php" style="display:inline; margin:0;" onsubmit="return confirm('Publish this course now?');">
                              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                              <input type="hidden" name="course_id" value="<?php echo (int)$uc['id']; ?>">
                              <input type="hidden" name="action" value="publish">
                              <button type="submit" class="btn-sm btn primary">Publish</button>
                            </form>
                          </div>
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

      <!-- Create Course Form (Instructors only) -->
      <?php if ($user['role'] === 'instructor'): ?>
        <div class="card fade-in" style="margin-bottom: 32px;">
          <div class="card-header">
            <h2>➕ Create New Course</h2>
          </div>
          <div class="card-body">
            <?php foreach ($errors as $e): ?>
              <div class="flash error" style="margin-bottom: 16px;">
                <?php echo htmlspecialchars($e); ?>
              </div>
            <?php endforeach; ?>

            <form method="post" style="padding: 0; box-shadow: none; max-width: none;">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                  <label>Course Title</label>
                  <input type="text" name="title" placeholder="Enter course title" required>
                </div>

                <div>
                  <label>Price ($)</label>
                  <input type="number" step="0.01" name="price" value="0.00" placeholder="0.00">
                </div>
              </div>

              <label>Course Description</label>
              <textarea name="description" placeholder="Describe what students will learn in this course..." rows="4"></textarea>

              <button type="submit" class="btn primary" style="margin-top: 8px;">
                ✓ Create Course
              </button>
            </form>
          </div>
        </div>
      <?php endif; ?>

      <!-- Courses List -->
      <div class="card-header fade-in" style="margin-bottom: 24px;">
        <h2>📖 <?php echo $user['role']==='admin' ? 'All Courses' : 'Your Courses'; ?> (<?php echo count($courses); ?>)</h2>
      </div>

      <?php if (empty($courses)): ?>
        <div class="card fade-in" style="text-align: center; padding: 48px;">
          <p style="font-size: 18px; color: var(--text-secondary);">
            📚 No courses yet. Create your first course above!
          </p>
        </div>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 24px;" class="fade-in">
          <?php foreach ($courses as $c): ?>
            <div class="card">
              <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                <div style="flex-grow: 1;">
                  <h3 style="margin: 0 0 8px 0; font-size: 20px;">
                    <a href="/course.php?id=<?php echo $c['id']; ?>" style="color: var(--text-primary); text-decoration: none;">
                      <?php echo htmlspecialchars($c['title']); ?>
                    </a>
                  </h3>
                  <div style="display: flex; gap: 16px; font-size: 14px; color: var(--text-secondary);">
                    <span>👨‍🏫 <?php echo htmlspecialchars($c['instructor_name'] ?? 'Instructor'); ?></span>
                    <span>💰 $<?php echo htmlspecialchars($c['price']); ?></span>
                    <span class="badge <?php echo $c['is_published'] ? 'success' : 'warning'; ?>">
                      <?php echo $c['is_published'] ? 'Published' : 'Draft'; ?>
                    </span>
                    <?php if (!$c['is_published'] && !empty($c['admin_unpublished_at'] ?? null)): ?>
                      <span class="badge danger" title="This course was unpublished by an admin">Unpublished by Admin</span>
                    <?php endif; ?>
                    <?php if (empty($c['listing_fee_paid']) || (int)$c['listing_fee_paid'] !== 1): ?>
                      <span class="badge danger" title="Requires one-time listing fee">Unpaid Listing Fee</span>
                    <?php else: ?>
                      <span class="badge success" title="Listing fee paid">Listing Fee Paid</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                  <?php if ($user['role'] === 'instructor' && $c['instructor_id'] == $user['id']): ?>
                    <a href="/edit_course.php?id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">✏️ Edit</a>
                    <a href="/edit_lessons.php?course_id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">🎞️ Manage Lessons</a>
                    <?php if (empty($c['listing_fee_paid']) || (int)$c['listing_fee_paid'] !== 1): ?>
                      <a href="/pay_course_listing.php?course_id=<?php echo $c['id']; ?>" class="btn-sm btn primary">💳 Pay Listing Fee</a>
                    <?php endif; ?>
                    <?php
                      $adminLocked = !$c['is_published'] && !empty($c['admin_unpublished_at'] ?? null);
                      $feeUnpaid = empty($c['listing_fee_paid']) || (int)$c['listing_fee_paid'] !== 1;
                    ?>
                    <?php if ((int)$c['is_published'] === 1): ?>
                      <form method="post" action="/toggle_publish.php" style="display:inline; margin:0;" onsubmit="return confirm('Unpublish this course? It will be hidden from students.');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="action" value="unpublish">
                        <button type="submit" class="btn-sm btn warning">⏸️ Unpublish</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="/toggle_publish.php" style="display:inline; margin:0;" onsubmit="return <?php echo ($adminLocked || $feeUnpaid) ? 'false' : 'confirm(\'Publish this course now?\')'; ?>;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="action" value="publish">
                        <button type="submit" class="btn-sm btn primary" <?php
                          echo $adminLocked ? 'disabled title="Admin has locked publishing"' : '';
                        ?> <?php echo $feeUnpaid ? 'disabled title="Pay the listing fee first"' : ''; ?>>🚀 Publish</button>
                      </form>
                    <?php endif; ?>
                    <?php $hasEnrollments = isset($c['enroll_count']) ? intval($c['enroll_count']) > 0 : false; ?>
                    <form method="post" action="/delete_course.php" style="display:inline; margin:0;" onsubmit="return <?php echo $hasEnrollments ? 'alert(\'Cannot delete: students are enrolled in this course.\'), false' : 'confirm(\'Delete this course and all its materials?\')'; ?>;">
                      <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                      <button type="submit" class="btn-sm btn danger" <?php echo $hasEnrollments ? 'disabled title="Cannot delete: students enrolled"' : ''; ?>>🗑️ Delete</button>
                    </form>
                  <?php endif; ?>

                  <?php if ($user['role'] === 'admin'): ?>
                    <a href="/admin_course_details.php?id=<?php echo $c['id']; ?>" class="btn-sm btn secondary">🔍 View Details</a>
                    <?php if ((int)$c['is_published'] === 1): ?>
                      <form method="post" action="/publish_courses.php" style="display:inline; margin:0;" onsubmit="return confirm('Unpublish this course? It will be hidden from students.');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="action" value="unpublish">
                        <button type="submit" class="btn-sm btn warning">⏸️ Unpublish</button>
                      </form>
                    <?php else: ?>
                      <form method="post" action="/publish_courses.php" style="display:inline; margin:0;" onsubmit="return confirm('Publish this course now?');">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                        <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                        <input type="hidden" name="action" value="publish">
                        <button type="submit" class="btn-sm btn primary">🚀 Publish</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>

              <?php if (!empty($c['description'])): ?>
                <p style="color: var(--text-secondary); margin-bottom: 16px; line-height: 1.6;">
                  <?php echo htmlspecialchars(substr($c['description'], 0, 200)); ?>
                  <?php if (strlen($c['description']) > 200): ?>...<?php endif; ?>
                </p>
              <?php endif; ?>

              <!-- Materials Section -->
              <?php if ($user['role'] === 'instructor' && $c['instructor_id'] == $user['id']): ?>
                <div style="border-top: 1px solid var(--border-color); padding-top: 16px; margin-top: 16px;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                    <strong style="color: var(--text-primary);">📎 Course Materials</strong>
                  </div>

                  <!-- Upload Material Form -->
                  <form method="post" action="/upload_material.php" enctype="multipart/form-data" style="display: flex; gap: 8px; padding: 0; box-shadow: none; margin-bottom: 16px;">
                    <input type="file" name="material" required style="flex-grow: 1; padding: 8px; font-size: 13px; margin: 0;">
                    <input type="hidden" name="course_id" value="<?php echo $c['id']; ?>">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <button type="submit" class="btn-sm btn primary">📤 Upload</button>
                  </form>

                  <?php if (!empty($materialsMap[$c['id']])): ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                      <?php foreach ($materialsMap[$c['id']] as $mat): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-secondary); border-radius: var(--radius-sm);">
                          <div style="display: flex; align-items: center; gap: 8px;">
                            <span>📄</span>
                            <a href="/download_material.php?id=<?php echo $mat['id']; ?>" style="font-weight: 500; color: var(--primary-color); text-decoration: none;">
                              <?php echo htmlspecialchars($mat['filename']); ?>
                            </a>
                            <span style="font-size: 12px; color: var(--text-secondary);">
                              (<?php echo date('M j, Y', strtotime($mat['uploaded_at'])); ?>)
                            </span>
                          </div>
                          <form method="post" action="/delete_material.php" style="display:inline; margin:0;">
                            <input type="hidden" name="id" value="<?php echo $mat['id']; ?>">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                            <button type="submit" class="btn-sm btn danger">🗑️</button>
                          </form>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <p style="color: var(--text-secondary); font-size: 13px; font-style: italic;">No materials uploaded yet</p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top: 32px; padding-bottom: 48px;">
        <a href="/dashboard.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">
          ← Back to Dashboard
        </a>
      </div>
    </div>
  </main>
</body>
</html>