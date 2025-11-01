<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
// Browsing is allowed without login, but we adapt UX if logged in
$user = current_user();

$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'new'; // new|price_asc|price_desc|title

$params = [];
// Instructors see only their own courses; everyone else sees published courses
if ($user && ($user['role'] ?? null) === 'instructor') {
  $sql = "SELECT c.*, u.name as instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.instructor_id = ?";
  $params[] = $user['id'];
} else {
  $sql = "SELECT c.*, u.name as instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.is_published = 1";
}
if ($q !== '') {
  $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
  $like = '%' . $q . '%';
  $params[] = $like; $params[] = $like;
}
switch ($sort) {
  case 'price_asc': $sql .= ' ORDER BY c.price ASC, c.id DESC'; break;
  case 'price_desc': $sql .= ' ORDER BY c.price DESC, c.id DESC'; break;
  case 'title': $sql .= ' ORDER BY c.title ASC'; break;
  default: $sql .= ' ORDER BY c.id DESC';
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// If logged in, map enrollments to flag Continue
$enrolled = [];
if ($user) {
  $sth = $pdo->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
  $sth->execute([$user['id']]);
  foreach ($sth->fetchAll() as $row) { $enrolled[(int)$row['course_id']] = true; }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Courses - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header fade-in">
        <?php if ($user && ($user['role'] ?? null) === 'instructor'): ?>
          <h1>👨‍🏫 Your Courses</h1>
          <p>These are the courses you teach. Use Manage Courses for full editing.</p>
        <?php else: ?>
          <h1>🔎 Browse Courses</h1>
          <p>Explore our latest published courses</p>
        <?php endif; ?>
      </div>

      <div class="card fade-in" style="margin-bottom: 16px;">
        <form method="get" class="compact" style="display:flex; gap:12px; align-items:end; padding:0; box-shadow:none; max-width:none;">
          <div style="flex:1;">
            <label for="q">Search</label>
            <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($q); ?>" placeholder="Course title or keywords">
          </div>
          <div>
            <label for="sort">Sort by</label>
            <select id="sort" name="sort">
              <option value="new" <?php if($sort==='new') echo 'selected'; ?>>Newest</option>
              <option value="title" <?php if($sort==='title') echo 'selected'; ?>>Title</option>
              <option value="price_asc" <?php if($sort==='price_asc') echo 'selected'; ?>>Price: Low to High</option>
              <option value="price_desc" <?php if($sort==='price_desc') echo 'selected'; ?>>Price: High to Low</option>
            </select>
          </div>
          <div>
            <label>&nbsp;</label>
            <button class="btn secondary" type="submit">Filter</button>
          </div>
        </form>
      </div>

      <?php if (empty($courses)): ?>
        <div class="card fade-in" style="text-align:center; padding: 48px;">
          <div style="font-size:56px;">🧐</div>
          <h2 style="margin-top:8px;">No courses found</h2>
          <p class="muted">Try adjusting your search or check back later.</p>
        </div>
      <?php else: ?>
        <div class="course-grid fade-in">
          <?php foreach ($courses as $c): ?>
                      <?php
                        $price = (float)($c['price'] ?? 0);
                        $isFree = $price <= 0;
                        // thumbnail: prefer course thumbnail_url, fallback to stable picsum seed
                        $thumb = !empty($c['thumbnail_url']) ? $c['thumbnail_url'] : ('https://picsum.photos/seed/course-' . intval($c['id']) . '/600/360');
                      ?>
                      <div class="course-card">
                        <div class="course-card-image">
                          <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Course thumbnail">
                          <div class="course-card-badge">
                            <?php echo $isFree ? 'Free' : ('$' . number_format($price, 2)); ?>
                          </div>
                        </div>
              <div class="course-card-content">
                <div class="course-card-title">
                  <a href="/course.php?id=<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['title']); ?></a>
                </div>
                <div class="course-card-meta">
                  <span>👨‍🏫 <?php echo htmlspecialchars($c['instructor_name'] ?? 'Instructor'); ?></span>
                  <span>💰 <?php echo ($c['price'] > 0) ? ('$' . number_format((float)$c['price'], 2)) : 'Free'; ?></span>
                </div>
                <?php if (!empty($c['description'])): ?>
                  <div class="course-card-description">
                    <?php echo htmlspecialchars(mb_strimwidth($c['description'], 0, 140, '…')); ?>
                  </div>
                <?php endif; ?>
                <div class="course-card-footer">
                  <div class="course-price <?php echo ($c['price'] == 0) ? 'free' : ''; ?>">
                    <?php echo ($c['price'] > 0) ? ('$' . number_format((float)$c['price'], 2)) : 'Free'; ?>
                  </div>
                  <div>
                    <?php if ($user && isset($enrolled[(int)$c['id']])): ?>
                      <a class="btn primary" href="/course.php?id=<?php echo $c['id']; ?>">Continue</a>
                    <?php else: ?>
                      <a class="btn secondary" href="/course.php?id=<?php echo $c['id']; ?>">View</a>
                    <?php endif; ?>
                  </div>
                </div>
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
