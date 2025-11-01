<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// fetch recent payments for this user
$stmt = $pdo->prepare('SELECT p.*, c.title as course_title FROM payments p LEFT JOIN courses c ON p.course_id = c.id WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 200');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>My Payments</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>My Payments</h1>
  <p><a href="/dashboard.php">Back</a></p>
  <?php if (empty($rows)): ?>
    <p>No payments found.</p>
  <?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
      <thead><tr><th>ID</th><th>Course</th><th>Amount</th><th>Status</th><th>When</th><th>Receipt</th><th>Refund</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['id']); ?></td>
            <td><?php echo htmlspecialchars($r['course_title'] ?? ''); ?></td>
            <td><?php echo htmlspecialchars($r['amount']); ?></td>
            <td><?php echo htmlspecialchars($r['status']); ?></td>
            <td><?php echo htmlspecialchars($r['created_at']); ?></td>
            <td><a href="/receipt.php?id=<?php echo $r['id']; ?>">Download</a></td>
            <td><?php echo htmlspecialchars($r['stripe_refund_id'] ?? ''); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
