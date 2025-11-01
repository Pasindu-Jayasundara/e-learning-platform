<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);
$user = current_user();

$stmt = $pdo->query('SELECT p.*, u.name as student_name, u.email as student_email, c.title as course_title FROM payments p JOIN users u ON p.user_id = u.id LEFT JOIN courses c ON p.course_id = c.id ORDER BY p.created_at DESC');
$payments = $stmt->fetchAll();

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Payments</title>
<link rel="stylesheet" href="/assets/css/style.css"></head><body>
<?php require_once __DIR__ . '/../includes/topnav.php'; ?>
<h1>Payments</h1>
<table border="1" cellpadding="6" cellspacing="0">
  <thead><tr><th>ID</th><th>User</th><th>Course</th><th>Amount</th><th>Status</th><th>Stripe IDs</th><th>Refund ID</th><th>When</th><th>Action</th></tr></thead>
  <tbody>
  <?php foreach ($payments as $p): ?>
    <tr>
      <td><?php echo $p['id']; ?></td>
      <td><?php echo htmlspecialchars($p['student_name']) . ' (' . htmlspecialchars($p['student_email']) . ')'; ?></td>
      <td><?php echo htmlspecialchars($p['course_title']); ?></td>
  <td><?php echo htmlspecialchars($p['amount']); ?></td>
  <td><?php echo htmlspecialchars($p['status']); ?></td>
  <td><?php echo htmlspecialchars($p['stripe_session_id'] ?? '') . '<br/>' . htmlspecialchars($p['stripe_payment_intent_id'] ?? '') . '<br/>' . htmlspecialchars($p['stripe_charge_id'] ?? ''); ?></td>
  <td><?php echo htmlspecialchars($p['stripe_refund_id'] ?? ''); ?></td>
  <td><?php echo $p['created_at']; ?></td>
      <td>
        <a href="/receipt.php?id=<?php echo $p['id']; ?>">Receipt</a>
        <?php if ($p['status'] === 'completed'): ?>
          <form method="post" action="/refund_payment.php" style="display:inline">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
            <button type="submit">Refund</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body></html>
