<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('error', 'Message not found.');
    header('Location: /messages.php'); exit;
}

$stmt = $db->prepare('SELECT m.*, s.name as sender_name, s.email as sender_email, r.name as recipient_name, r.email as recipient_email FROM messages m JOIN users s ON m.sender_id = s.id JOIN users r ON m.recipient_id = r.id WHERE m.id = ? LIMIT 1');
$stmt->execute([$id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$m) {
    set_flash('error', 'Message not found.');
    header('Location: /messages.php'); exit;
}

// ensure only sender or recipient can view
if ($m['recipient_id'] != $user['id'] && $m['sender_id'] != $user['id']) {
    set_flash('error', 'Access denied.');
    header('Location: /messages.php'); exit;
}

// mark read if recipient is viewing
if ($m['recipient_id'] == $user['id'] && !$m['is_read']) {
    $u = $db->prepare('UPDATE messages SET is_read = 1 WHERE id = ?');
    $u->execute([$m['id']]);
    $m['is_read'] = 1;
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>View Message</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Message</h1>
  <p><strong>From:</strong> <?php echo htmlspecialchars($m['sender_name'] ?: $m['sender_email']); ?></p>
  <p><strong>To:</strong> <?php echo htmlspecialchars($m['recipient_name'] ?: $m['recipient_email']); ?></p>
  <p><strong>Subject:</strong> <?php echo htmlspecialchars($m['subject'] ?: '(no subject)'); ?></p>
  <p><strong>Date:</strong> <?php echo htmlspecialchars($m['created_at']); ?></p>
  <hr>
  <div style="white-space:pre-wrap"><?php echo htmlspecialchars($m['body']); ?></div>

  <p>
    <?php if ($user['id'] != $m['sender_id']): ?>
      <a href="/compose.php?reply_to=<?php echo $m['id']; ?>">Reply</a>
    <?php endif; ?>
    &nbsp; <a href="/messages.php">Back to inbox</a>
  </p>
</body>
</html>
