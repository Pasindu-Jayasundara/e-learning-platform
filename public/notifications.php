<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['admin','instructor'])) { set_flash('error','Access denied'); header('Location: /dashboard.php'); exit; }

$filter_status = $_GET['status'] ?? 'open';
$filter_reporter = intval($_GET['reporter_id'] ?? 0);
$filter_course = intval($_GET['course_id'] ?? 0);

$params = [];
$where = [];
if ($filter_status === 'open') { $where[] = "r.status = 'open'"; }
if ($filter_reporter) { $where[] = 'r.reporter_id = ?'; $params[] = $filter_reporter; }
if ($filter_course) { $where[] = 'r.course_id = ?'; $params[] = $filter_course; }

if ($user['role'] === 'instructor') {
    $c = $pdo->prepare('SELECT id FROM courses WHERE instructor_id = ?');
    $c->execute([$user['id']]);
    $courseIds = $c->fetchAll(PDO::FETCH_COLUMN);
    if (!$courseIds) { $where[] = '1=0'; }
    else { $where[] = 'r.course_id IN (' . implode(',', array_map('intval',$courseIds)) . ')'; }
}

$sql = 'SELECT r.*, u.name as reporter_name, c.title as course_title FROM reports r JOIN users u ON r.reporter_id = u.id LEFT JOIN courses c ON r.course_id = c.id';
if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY r.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Moderator Mailbox</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php require_once __DIR__ . '/../includes/topnav.php'; ?>
<h1>Moderator Mailbox</h1>
<form method="get">
  <label>Status:
    <select name="status"><option value="open" <?php echo $filter_status==='open'?'selected':''; ?>>Open</option><option value="all" <?php echo $filter_status==='all'?'selected':''; ?>>All</option></select>
  </label>
  <label>Course ID: <input name="course_id" value="<?php echo htmlspecialchars($filter_course); ?>"></label>
  <label>Reporter ID: <input name="reporter_id" value="<?php echo htmlspecialchars($filter_reporter); ?>"></label>
  <button>Filter</button>
</form>
<table border="1" cellpadding="6" cellspacing="0">
  <thead><tr><th>ID</th><th>When</th><th>Reporter</th><th>Type</th><th>Course</th><th>Reason</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach($reports as $r): ?>
    <tr>
      <td><?php echo $r['id']; ?></td>
      <td><?php echo $r['created_at']; ?></td>
      <td><?php echo htmlspecialchars($r['reporter_name']) . ' ('.$r['reporter_id'].')'; ?></td>
      <td><?php echo htmlspecialchars($r['entity_type']); ?> #<?php echo $r['entity_id']; ?></td>
      <td><?php echo htmlspecialchars($r['course_title'] ?? ''); ?></td>
      <td><?php echo htmlspecialchars($r['reason']); ?></td>
      <td>
        <a href="/post_forum.php?post_id=<?php echo $r['entity_type']==='post' ? $r['entity_id'] : ($r['entity_type']==='reply' ? (int)$pdo->query('SELECT post_id FROM forum_replies WHERE id='.intval($r['entity_id']).' LIMIT 1')->fetchColumn() : ''); ?>">View</a>
        <form method="post" action="/moderate_report.php" style="display:inline">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
          <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
          <input type="hidden" name="action" value="dismiss">
          <button type="submit">Dismiss</button>
        </form>
        <?php if (!empty($r['claimed_by']) && intval($r['claimed_by'])): ?>
          <?php if (intval($r['claimed_by']) === intval($user['id'])): ?>
            <form method="post" action="/release_report.php" style="display:inline">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
              <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
              <button type="submit">Release</button>
            </form>
          <?php else: ?>
            <span>Claimed by <?php echo intval($r['claimed_by']); ?></span>
          <?php endif; ?>
        <?php else: ?>
          <form method="post" action="/claim_report.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
            <button type="submit">Claim</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body></html>
