<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['instructor']);
$user = current_user();
$config = require __DIR__ . '/../includes/config.php';

$session_id = $_GET['session_id'] ?? null;
$course_id = (int)($_GET['course_id'] ?? 0);
if ($course_id <= 0 || !$session_id) { set_flash('error','Invalid payment session'); header('Location:/manage_courses.php'); exit; }

// verify ownership
$st = $pdo->prepare('SELECT id, instructor_id, listing_fee_paid, admin_unpublished_at FROM courses WHERE id = ? LIMIT 1');
$st->execute([$course_id]);
$c = $st->fetch();
if (!$c || (int)$c['instructor_id'] !== (int)$user['id']) { set_flash('error','Course not found'); header('Location:/manage_courses.php'); exit; }

if (!empty($c['listing_fee_paid'])) { set_flash('success','Listing fee already recorded'); header('Location:/manage_courses.php'); exit; }

if (empty($config['stripe_secret']) || empty($config['stripe_publishable'])) {
    set_flash('error', 'Stripe not configured');
    header('Location: /manage_courses.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$stripe = new \Stripe\StripeClient($config['stripe_secret']);
try {
    $session = $stripe->checkout->sessions->retrieve($session_id);
} catch (Exception $e) {
    set_flash('error','Unable to verify payment: ' . $e->getMessage());
    header('Location: /manage_courses.php');
    exit;
}

if (isset($session->payment_status) && $session->payment_status === 'paid') {
    // determine amount
    $amount = isset($session->amount_total) ? ($session->amount_total / 100.0) : 0.0;
    $stripe_session_id = $session->id ?? null;
    $stripe_payment_intent_id = $session->payment_intent ?? null;
    $stripe_charge_id = null;
    if (!empty($stripe_payment_intent_id)) {
        try {
            $pi = $stripe->paymentIntents->retrieve($stripe_payment_intent_id);
            if (!empty($pi->charges->data) && !empty($pi->charges->data[0]->id)) {
                $stripe_charge_id = $pi->charges->data[0]->id;
            }
        } catch (Exception $e) {}
    }

    // ensure payments.purpose exists
    try { $pdo->exec("ALTER TABLE payments ADD COLUMN purpose VARCHAR(32) NULL DEFAULT 'enrollment'"); } catch (Throwable $e) {}

    // Idempotency: check if already recorded
    $q = $pdo->prepare("SELECT id FROM payments WHERE user_id=? AND course_id=? AND purpose='listing_fee' AND status='completed' LIMIT 1");
    $q->execute([$user['id'],$course_id]);
    if (!$q->fetch()) {
        try {
            $ins = $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status,purpose,stripe_session_id,stripe_charge_id,stripe_payment_intent_id) VALUES (?,?,?,?,?,?,?,?)');
            $ins->execute([$user['id'],$course_id,$amount,'completed','listing_fee',$stripe_session_id,$stripe_charge_id,$stripe_payment_intent_id]);
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status) VALUES (?,?,?,?)')->execute([$user['id'],$course_id,$amount,'completed']);
            } else { throw $e; }
        }
    }

    // ensure admin_unpublished_at exists
    try { $pdo->exec("ALTER TABLE courses ADD COLUMN admin_unpublished_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
    // mark course as listing paid and publish only if admin hasn't explicitly unpublished it
    if (empty($c['admin_unpublished_at'])) {
        $u = $pdo->prepare('UPDATE courses SET listing_fee_paid=1, listing_fee_paid_at=NOW(), is_published=1 WHERE id=?');
    } else {
        $u = $pdo->prepare('UPDATE courses SET listing_fee_paid=1, listing_fee_paid_at=NOW() WHERE id=?');
    }
    $u->execute([$course_id]);

    set_flash('success','Listing fee paid. You can continue setting up your course.');
    header('Location:/manage_courses.php'); exit;
}

set_flash('error','Payment not completed');
header('Location:/manage_courses.php');
exit;
