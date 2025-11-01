<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare('SELECT m.*, u.name as sender_name, u.email as sender_email FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.recipient_id = ? ORDER BY m.created_at DESC LIMIT ? OFFSET ?');
$stmt->bindValue(1, $user['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $db->prepare('SELECT COUNT(*) FROM messages WHERE recipient_id = ?');
$countStmt->execute([$user['id']]);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Inbox</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Inbox</h1>
  <p><a href="/compose.php">Compose</a></p>
  <?php if (empty($messages)): ?>
    <p>No messages.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>From</th><th>Subject</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach ($messages as $m): ?>
        <tr style="<?php echo $m['is_read'] ? '' : 'font-weight:bold'; ?>">
          <td><?php echo htmlspecialchars($m['sender_name'] ?: $m['sender_email']); ?></td>
          <td><a href="/message_view.php?id=<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['subject'] ?: '(no subject)'); ?></a></td>
          <td><?php echo htmlspecialchars($m['created_at']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <p>Page <?php echo $page; ?> of <?php echo $pages; ?></p>
    <p>
      <?php if ($page > 1): ?><a href="/messages.php?page=<?php echo $page - 1; ?>">Prev</a><?php endif; ?>
      <?php if ($page < $pages): ?><a href="/messages.php?page=<?php echo $page + 1; ?>">Next</a><?php endif; ?>
    </p>
  <?php endif; ?>
  <p><a href="/dashboard.php">Back</a></p>
</body>
</html>
