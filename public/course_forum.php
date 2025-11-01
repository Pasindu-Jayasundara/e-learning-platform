<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);
if (!$course_id) {
    header('Location: /dashboard.php');
    exit;
}

// fetch course
$stmt = $pdo->prepare('SELECT id,title,instructor_id FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) { header('Location: /dashboard.php'); exit; }

// fetch posts
$stmt = $pdo->prepare('SELECT p.*, u.name as author FROM forum_posts p JOIN users u ON p.user_id = u.id WHERE p.course_id = ? ORDER BY p.created_at DESC');
$stmt->execute([$course_id]);
$posts = $stmt->fetchAll();

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forum - <?php echo htmlspecialchars($course['title']); ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  </head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>

  <main>
    <div class="container">
      <!-- Header / Hero -->
      <div class="card fade-in" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color: #fff; margin-bottom: 24px;">
        <div style="padding: 28px 24px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap: wrap;">
          <div>
            <div style="opacity:0.9; font-weight:600; letter-spacing: .2px; margin-bottom:6px;">Course Forum</div>
            <h1 style="margin:0; font-size: 28px; line-height:1.2;">💬 <?php echo htmlspecialchars($course['title']); ?></h1>
          </div>
          <div>
            <a href="/course.php?id=<?php echo $course_id; ?>" class="btn light" style="border:2px solid #fff; color:#fff;">← Back to Course</a>
          </div>
        </div>
      </div>

      <!-- Grid: New Post (left) | Posts (right) for larger screens -->
      <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items:start;">
        <!-- New Post -->
        <div class="card fade-in">
          <div class="card-header"><h2>📝 Start a New Discussion</h2></div>
          <div class="card-body">
            <form method="post" action="/post_forum.php" style="display:flex; flex-direction:column; gap:12px;">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
              <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
              <div>
                <label for="title" style="font-weight:600; color: var(--text-primary);">Title</label>
                <input id="title" type="text" name="title" required placeholder="Enter a clear, descriptive title" style="width:100%; padding:10px; margin-top:6px;">
              </div>
              <div>
                <label for="message" style="font-weight:600; color: var(--text-primary);">Message</label>
                <textarea id="message" name="message" rows="6" required placeholder="Describe your question, share context, and what you've tried" style="width:100%; padding:10px; margin-top:6px; resize: vertical;"></textarea>
              </div>
              <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="submit" class="btn primary">Post Discussion</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Posts List -->
        <div class="card fade-in">
          <div class="card-header"><h2>📚 Recent Posts</h2></div>
          <div class="card-body">
            <?php if (empty($posts)): ?>
              <p class="muted">No posts yet. Be the first to start the conversation!</p>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($posts as $p): ?>
                  <div style="padding:14px; border:1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
                      <div style="min-width:0;">
                        <a href="/post_forum.php?post_id=<?php echo $p['id']; ?>" style="font-weight:700; color: var(--text-primary); text-decoration:none; display:inline-block; max-width:100%; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                          <?php echo htmlspecialchars($p['title']); ?>
                        </a>
                        <div class="muted" style="font-size: 13px; margin-top:4px;">By <?php echo htmlspecialchars($p['author']); ?> • <?php echo date('M j, Y g:i A', strtotime($p['created_at'])); ?></div>
                      </div>
                      <div>
                        <a href="/post_forum.php?post_id=<?php echo $p['id']; ?>" class="btn-sm btn secondary">View / Reply</a>
                      </div>
                    </div>
                    <div style="margin-top:8px; color: var(--text-secondary); font-size: 14px; max-height: 3.2em; overflow: hidden;">
                      <?php echo nl2br(htmlspecialchars(mb_strimwidth($p['message'], 0, 220, '…'))); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div style="margin-top: 24px;">
        <a href="/course.php?id=<?php echo $course_id; ?>" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Course</a>
      </div>
    </div>
  </main>
</body>
</html>
