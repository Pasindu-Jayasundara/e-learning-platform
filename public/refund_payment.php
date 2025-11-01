<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /payments_admin.php'); exit; }
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /payments_admin.php'); exit; }

$payment_id = intval($_POST['payment_id'] ?? 0);
if (!$payment_id) { set_flash('error','Missing payment id'); header('Location: /payments_admin.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM payments WHERE id = ? LIMIT 1');
$stmt->execute([$payment_id]);
$p = $stmt->fetch();
if (!$p) { set_flash('error','Payment not found'); header('Location: /payments_admin.php'); exit; }
if ($p['status'] !== 'completed') { set_flash('error','Payment not refundable'); header('Location: /payments_admin.php'); exit; }

$config = require __DIR__ . '/../includes/config.php';
if (!empty($config['stripe_secret']) && !empty($p['stripe_charge_id'])) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $stripe = new \Stripe\StripeClient($config['stripe_secret']);
    try {
    // create refund using stripe_charge_id
    $refund = $stripe->refunds->create(['charge' => $p['stripe_charge_id']]);
    // update local record with refund id
    $u = $pdo->prepare('UPDATE payments SET status = ?, stripe_refund_id = ? WHERE id = ?');
    $u->execute(['refunded', $refund->id ?? null, $payment_id]);
    $a = $pdo->prepare('INSERT INTO audit_log (actor_id,action,entity_type,entity_id,report_id,notes) VALUES (?,?,?,?,?,?)');
    $a->execute([$user['id'],'refund_payment','payment',$payment_id,null,'refunded via Stripe: ' . ($refund->id ?? '')]);
    set_flash('success','Payment refunded via Stripe and marked refunded locally');
    } catch (Exception $e) {
        set_flash('error','Refund failed: ' . $e->getMessage());
    }
} else {
    // no stripe charge id available or stripe not configured: just mark refunded
    $u = $pdo->prepare('UPDATE payments SET status = ? WHERE id = ?');
    $u->execute(['refunded',$payment_id]);
    $a = $pdo->prepare('INSERT INTO audit_log (actor_id,action,entity_type,entity_id,report_id,notes) VALUES (?,?,?,?,?,?)');
    $a->execute([$user['id'],'refund_payment','payment',$payment_id,null,'refunded by admin (no stripe charge id or stripe not configured)']);
    set_flash('success','Payment marked refunded');
}

header('Location: /payments_admin.php');
exit;

?>
