<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['instructor']);
$user = current_user();

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /manage_courses.php');
    exit;
}

// fetch course
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: /manage_courses.php');
    exit;
}

// Only instructors may edit their own courses
if ($user['role'] !== 'instructor' || $course['instructor_id'] != $user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token';
    }

  $title = trim($_POST['title'] ?? '');
  $description = $_POST['description'] ?? '';
  $thumbnail_url = trim($_POST['thumbnail_url'] ?? '');
  $price = $_POST['price'] ?? 0;
  // Instructors cannot change publish status or instructor assignment here
  $is_published = $course['is_published'];
  $instructor_id = $user['id'];

    if (!$title) $errors[] = 'Title required';
    if (empty($errors)) {
    // Ensure empty thumbnail_url stored as NULL
    $thumbParam = $thumbnail_url !== '' ? $thumbnail_url : null;
    $stmt = $pdo->prepare('UPDATE courses SET title=?, description=?, thumbnail_url=?, price=?, is_published=?, instructor_id=? WHERE id=?');
    $stmt->execute([$title,$description,$thumbParam,$price,$is_published,$instructor_id,$id]);
        set_flash('success','Course updated');
        header('Location: /manage_courses.php');
        exit;
    }
}

// No instructor assignment UI here (admin editing disabled)
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Course - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } }
  </style>
  </head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>

  <main>
    <div class="container">
      <div class="page-header fade-in" style="margin-bottom: 20px;">
        <h1>✏️ Edit Course</h1>
        <p>Update details for "<?php echo htmlspecialchars($course['title']); ?>"</p>
        <div style="margin-top: 8px;">
          <a href="/manage_courses.php" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Manage Courses</a>
          <a href="/course.php?id=<?php echo $course['id']; ?>" class="btn secondary">View Course</a>
        </div>
      </div>

      <div class="card fade-in">
        <div class="card-header">
          <h2>Course Details</h2>
        </div>
        <div class="card-body">
          <?php foreach ($errors as $e): ?>
            <div class="flash error" style="margin-bottom: 12px;"><?php echo htmlspecialchars($e); ?></div>
          <?php endforeach; ?>

          <form method="post" style="padding: 0; box-shadow: none; max-width: none;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">

            <div class="form-grid">
              <div>
                <label>Course Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($course['title']); ?>" placeholder="Enter course title" required>
              </div>
              <div>
                <label>Price ($)</label>
                <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($course['price']); ?>" placeholder="0.00">
              </div>
            </div>

            <label>Thumbnail URL</label>
            <input type="url" name="thumbnail_url" value="<?php echo htmlspecialchars($course['thumbnail_url'] ?? ''); ?>" placeholder="https://…/image.jpg">
            <div style="margin:8px 0 16px 0;">
              <?php
                $preview = !empty($course['thumbnail_url']) ? $course['thumbnail_url'] : ('https://picsum.photos/seed/course-' . intval($course['id']) . '/600/360');
              ?>
              <img src="<?php echo htmlspecialchars($preview); ?>" alt="Thumbnail preview" style="max-width: 480px; border-radius: var(--radius-md); border:1px solid var(--border-color);">
            </div>

            <label>Course Description</label>
            <textarea name="description" rows="6" placeholder="Describe what students will learn in this course..."><?php echo htmlspecialchars($course['description']); ?></textarea>

            <div style="display:flex; gap: 8px; align-items: center; margin-top: 8px;">
              <span class="badge <?php echo $course['is_published'] ? 'success' : 'warning'; ?>">
                <?php echo $course['is_published'] ? 'Published' : 'Draft'; ?>
              </span>
              <?php if (isset($course['listing_fee_paid']) && (int)$course['listing_fee_paid'] !== 1): ?>
                <span class="badge danger" title="Requires one-time listing fee">Unpaid Listing Fee</span>
                <a class="btn-sm btn primary" href="/pay_course_listing.php?course_id=<?php echo (int)$course['id']; ?>">Pay Listing Fee</a>
              <?php endif; ?>
            </div>

            <div style="margin-top: 16px; display:flex; gap: 12px;">
              <button type="submit" class="btn primary">✓ Save Changes</button>
              <a href="/manage_courses.php" class="btn secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>

      <div style="height: 24px;"></div>
    </div>
  </main>
</body>
</html>
