<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
if ($user['role'] !== 'admin') { set_flash('error','Access denied'); header('Location: /dashboard.php'); exit; }

$stmt = $pdo->query('SELECT a.*, u.name as actor_name FROM audit_log a JOIN users u ON a.actor_id = u.id ORDER BY a.created_at DESC LIMIT 500');
$logs = $stmt->fetchAll();

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Audit Log</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php require_once __DIR__ . '/../includes/topnav.php'; ?>
<h1>Audit Log</h1>
<table border="1" cellpadding="6" cellspacing="0">
  <thead><tr><th>ID</th><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Report ID</th><th>Notes</th></tr></thead>
  <tbody>
  <?php foreach($logs as $l): ?>
    <tr>
      <td><?php echo $l['id']; ?></td>
      <td><?php echo $l['created_at']; ?></td>
      <td><?php echo htmlspecialchars($l['actor_name']) . ' ('.$l['actor_id'].')'; ?></td>
      <td><?php echo htmlspecialchars($l['action']); ?></td>
      <td><?php echo htmlspecialchars($l['entity_type']).' #'.htmlspecialchars($l['entity_id']); ?></td>
      <td><?php echo htmlspecialchars($l['report_id']); ?></td>
      <td><?php echo htmlspecialchars($l['notes']); ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body></html>
