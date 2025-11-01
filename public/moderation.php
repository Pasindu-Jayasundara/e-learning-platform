<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

require_login();
$user = current_user();

// allow admins to see everything; instructors see reports for their courses only
if (!in_array($user['role'], ['admin','instructor'])) { set_flash('error','Access denied'); header('Location: /dashboard.php'); exit; }

// Filters: status (open/all), reporter_id, course_id
$filter_status = $_GET['status'] ?? 'open';
$filter_reporter = intval($_GET['reporter_id'] ?? 0);
$filter_course = intval($_GET['course_id'] ?? 0);

$params = [];
$where = [];
if ($filter_status === 'open') { $where[] = "r.status = 'open'"; }
if ($filter_reporter) { $where[] = 'r.reporter_id = ?'; $params[] = $filter_reporter; }
if ($filter_course) { $where[] = 'r.course_id = ?'; $params[] = $filter_course; }

// if instructor, restrict to reports for courses they own
if ($user['role'] === 'instructor') {
  // select course ids owned by this instructor
  $c = $pdo->prepare('SELECT id FROM courses WHERE instructor_id = ?');
  $c->execute([$user['id']]);
  $courseIds = $c->fetchAll(PDO::FETCH_COLUMN);
  if (!$courseIds) { $where[] = '1=0'; }
  else { $where[] = 'r.course_id IN (' . implode(',', array_map('intval',$courseIds)) . ')'; }
}

$sql = 'SELECT r.*, u.name as reporter_name FROM reports r JOIN users u ON r.reporter_id = u.id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY r.created_at DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Moderation - Reports</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<?php require_once __DIR__ . '/../includes/topnav.php'; ?>
<h1>Moderation - Reports</h1>
<?php if (!$reports): ?>
  <p>No reports.</p>
<?php else: ?>
  <table border="1" cellpadding="6" cellspacing="0">
    <thead><tr><th>ID</th><th>Reporter</th><th>Type</th><th>Entity</th><th>Reason</th><th>When</th><th>Action</th></tr></thead>
    <tbody>
    <?php foreach ($reports as $r): ?>
      <tr>
        <td><?php echo $r['id']; ?></td>
        <td><?php echo htmlspecialchars($r['reporter_name']); ?></td>
        <td><?php echo htmlspecialchars($r['entity_type']); ?></td>
        <td>
          <?php if ($r['entity_type'] === 'post'): ?>
            <?php $q = $pdo->prepare('SELECT id,title,course_id FROM forum_posts WHERE id = ? LIMIT 1'); $q->execute([$r['entity_id']]); $item = $q->fetch(); ?>
            Post #<?php echo $r['entity_id']; ?>: <?php echo htmlspecialchars($item['title'] ?? '(deleted)'); ?>
            <div><a href="/post_forum.php?post_id=<?php echo $r['entity_id']; ?>">View post</a></div>
          <?php else: ?>
            <?php $q = $pdo->prepare('SELECT id,post_id,message FROM forum_replies WHERE id = ? LIMIT 1'); $q->execute([$r['entity_id']]); $item = $q->fetch(); ?>
            Reply #<?php echo $r['entity_id']; ?> (post <?php echo htmlspecialchars($item['post_id'] ?? ''); ?>)
            <div><a href="/post_forum.php?post_id=<?php echo $item['post_id'] ?? ''; ?>">View thread</a></div>
          <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($r['reason']); ?></td>
        <td><?php echo $r['created_at']; ?></td>
        <td>
          <form method="post" action="/moderate_report.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
            <input type="hidden" name="action" value="dismiss">
            <button type="submit">Dismiss</button>
          </form>
          <?php if ($r['entity_type'] === 'post'): ?>
            <form method="post" action="/moderate_report.php" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
              <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
              <input type="hidden" name="action" value="delete_post">
              <input type="hidden" name="entity_id" value="<?php echo $r['entity_id']; ?>">
              <button type="submit" onclick="return confirm('Delete this post and its replies?')">Delete Post</button>
            </form>
          <?php else: ?>
            <form method="post" action="/moderate_report.php" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
              <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
              <input type="hidden" name="action" value="delete_reply">
              <input type="hidden" name="entity_id" value="<?php echo $r['entity_id']; ?>">
              <button type="submit" onclick="return confirm('Delete this reply?')">Delete Reply</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</body></html>
