<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['post_id'])) {
  $post_id = intval($_GET['post_id']);
  $stmt = $pdo->prepare('SELECT p.*, u.name as author, c.title as course_title FROM forum_posts p JOIN users u ON p.user_id = u.id JOIN courses c ON p.course_id = c.id WHERE p.id = ? LIMIT 1');
  $stmt->execute([$post_id]);
  $post = $stmt->fetch();
  if (!$post) { header('Location: /dashboard.php'); exit; }

  // pagination for replies
  $page = max(1, intval($_GET['page'] ?? 1));
  $per_page = 10;
  $offset = ($page - 1) * $per_page;

  $countStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM forum_replies WHERE post_id = ?');
  $countStmt->execute([$post_id]);
  $total = (int)$countStmt->fetchColumn();
  $total_pages = max(1, ceil($total / $per_page));

  $stmt = $pdo->prepare('SELECT r.*, u.name as author FROM forum_replies r JOIN users u ON r.user_id = u.id WHERE r.post_id = ? ORDER BY r.created_at ASC LIMIT ? OFFSET ?');
  // PDO requires integers bound explicitly
  $stmt->bindValue(1, $post_id, PDO::PARAM_INT);
  $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
  $stmt->bindValue(3, $offset, PDO::PARAM_INT);
  $stmt->execute();
  $replies = $stmt->fetchAll();
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Post - <?php echo htmlspecialchars($post['title']); ?></title>
      <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
      <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
      <main>
        <div class="container">
          <!-- Header -->
          <div class="card fade-in" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%); color:#fff; margin-bottom: 20px;">
            <div style="padding: 22px 20px; display:flex; align-items:center; justify-content:space-between; gap: 12px; flex-wrap: wrap;">
              <div style="min-width:0;">
                <div style="opacity:0.9; font-weight:600; margin-bottom:6px;">Forum Thread</div>
                <h1 style="margin:0; font-size: 26px; line-height:1.2; max-width: 900px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">🧵 <?php echo htmlspecialchars($post['title']); ?></h1>
                <div style="opacity:0.9; margin-top:6px; font-size: 13px;">By <strong><?php echo htmlspecialchars($post['author']); ?></strong> in <strong><?php echo htmlspecialchars($post['course_title']); ?></strong> • <?php echo date('M j, Y g:i A', strtotime($post['created_at'])); ?></div>
              </div>
              <div>
                <a href="/course_forum.php?course_id=<?php echo $post['course_id']; ?>" class="btn light" style="border:2px solid #fff; color:#fff;">← Back to Forum</a>
              </div>
            </div>
          </div>

          <!-- Original Post -->
          <div class="card fade-in" style="margin-bottom:16px;">
            <div class="card-header"><h2>📝 Original Post</h2></div>
            <div class="card-body" style="line-height:1.7; color: var(--text-primary);">
              <?php echo nl2br(htmlspecialchars($post['message'])); ?>
            </div>
            <div class="card-footer" style="display:flex; justify-content: space-between; align-items:center; gap: 12px; flex-wrap: wrap;">
              <div class="muted">Seen something inappropriate? Report it.</div>
              <form method="post" action="/report_post.php" class="inline compact" style="display:flex; gap:8px; align-items:center;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <input name="reason" required maxlength="250" placeholder="Reason for report" style="width:280px; padding:8px;">
                <button class="btn danger" type="submit">Report</button>
              </form>
            </div>
          </div>

          <!-- Replies -->
          <div class="card fade-in">
            <div class="card-header"><h2>💬 Replies (<?php echo $total; ?>)</h2></div>
            <div id="replies" class="card-body" style="display:flex; flex-direction:column; gap:10px;">
              <?php if (empty($replies)): ?>
                <p class="muted">No replies yet. Join the discussion below.</p>
              <?php else: ?>
                <?php foreach ($replies as $r): ?>
                  <?php $mine = ((int)$r['user_id'] === (int)$user['id']); ?>
                  <div style="align-self: <?php echo $mine ? 'flex-end' : 'flex-start'; ?>; max-width: 90%; min-width: 40%;">
                    <div style="padding:10px 12px; border-radius: 12px; background: <?php echo $mine ? 'var(--primary-color)' : 'var(--bg-secondary)'; ?>; color: <?php echo $mine ? '#fff' : 'var(--text-primary)'; ?>; border:1px solid var(--border-color);">
                      <div style="font-size:12px; opacity:0.9; margin-bottom:6px; display:flex; justify-content:space-between; gap:10px;">
                        <span><strong><?php echo htmlspecialchars($r['author']); ?></strong> • <?php echo date('M j, Y g:i A', strtotime($r['created_at'])); ?></span>
                        <span>
                          <?php if ($user['id'] === $r['user_id'] || $user['role'] === 'admin') : ?>
                            <a href="/edit_reply.php?reply_id=<?php echo $r['id']; ?>" style="margin-right:8px; color: inherit;">Edit</a>
                            <form action="/delete_reply.php" method="post" class="inline compact" style="display:inline">
                              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                              <input type="hidden" name="reply_id" value="<?php echo $r['id']; ?>">
                              <button class="btn-sm btn danger" type="submit" onclick="return confirm('Delete this reply?')">Delete</button>
                            </form>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div><?php echo nl2br(htmlspecialchars($r['message'])); ?></div>
                      <div style="margin-top:8px;">
                        <form method="post" action="/report_reply.php" class="inline compact" style="display:flex; gap:8px; align-items:center;">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                          <input type="hidden" name="reply_id" value="<?php echo $r['id']; ?>">
                          <input name="reason" required maxlength="250" placeholder="Report reason" style="flex:1; min-width:180px; padding:8px; background: #fff;">
                          <button class="btn-sm btn danger" type="submit">Report</button>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="card-footer" style="display:flex; justify-content:space-between; align-items:center;">
              <div class="muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></div>
              <div style="display:flex; gap:8px;">
                <?php if ($page > 1) : ?>
                  <a class="btn secondary" href="/post_forum.php?post_id=<?php echo $post_id; ?>&page=<?php echo $page-1; ?>">← Prev</a>
                <?php endif; ?>
                <?php if ($page < $total_pages) : ?>
                  <a class="btn secondary" href="/post_forum.php?post_id=<?php echo $post_id; ?>&page=<?php echo $page+1; ?>">Next →</a>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Reply Composer -->
          <div class="card fade-in" style="margin-top:16px;">
            <div class="card-header"><h3>✍️ Add a reply</h3></div>
            <div class="card-body">
              <form method="post" action="/reply_forum.php" style="display:flex; flex-direction:column; gap:10px;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                <textarea name="message" rows="5" required placeholder="Write your reply…" style="width:100%; padding:10px; resize: vertical;"></textarea>
                <div style="display:flex; justify-content:flex-end;">
                  <button class="btn primary" type="submit">Reply</button>
                </div>
              </form>
            </div>
          </div>

          <div style="margin-top: 20px;">
            <a href="/course_forum.php?course_id=<?php echo $post['course_id']; ?>" class="btn light" style="color: var(--primary-color); border: 2px solid var(--primary-color);">← Back to Forum</a>
          </div>
        </div>
      </main>
  </body>
  </html>
  <?php
    exit;
}

// Handle new post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF token'); header('Location: /dashboard.php'); exit; }
  $course_id = intval($_POST['course_id'] ?? 0);
  $title = trim($_POST['title'] ?? '');
  $message = trim($_POST['message'] ?? '');
  // basic sanitization and limits
  $title = strip_tags($title);
  $message = strip_tags($message);
  $max_title = 200;
  $max_message = 2000;
  if (mb_strlen($title) > $max_title) { set_flash('error','Title too long (max '.$max_title.' chars)'); header('Location: /course_forum.php?course_id=' . $course_id); exit; }
  if (mb_strlen($message) > $max_message) { set_flash('error','Message too long (max '.$max_message.' chars)'); header('Location: /course_forum.php?course_id=' . $course_id); exit; }
  if (!$course_id || !$title || !$message) { set_flash('error','Missing data'); header('Location: /course_forum.php?course_id=' . $course_id); exit; }
    // verify course access (must be published or instructor/admin or enrolled)
    $stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) { set_flash('error','Course not found'); header('Location: /dashboard.php'); exit; }
    // permission: anyone can post if enrolled or instructor/admin
    $can = false;
    if ($user['role'] === 'admin') $can = true;
    if ($user['role'] === 'instructor' && $course['instructor_id'] == $user['id']) $can = true;
    if ($user['role'] === 'student') {
        $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
        $stmt->execute([$user['id'],$course_id]);
        if ($stmt->fetch()) $can = true;
    }
    if (!$can) { set_flash('error','You are not allowed to post in this forum'); header('Location: /course_forum.php?course_id=' . $course_id); exit; }

    $stmt = $pdo->prepare('INSERT INTO forum_posts (course_id,user_id,title,message) VALUES (?,?,?,?)');
    $stmt->execute([$course_id,$user['id'],$title,$message]);
    set_flash('success','Post created');
    header('Location: /course_forum.php?course_id=' . $course_id);
    exit;
}

header('Location: /dashboard.php');
exit;
