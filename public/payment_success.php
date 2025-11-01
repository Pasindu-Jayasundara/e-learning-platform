<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$session_id = $_GET['session_id'] ?? null;
$course_id = intval($_GET['course_id'] ?? 0);
$config = require __DIR__ . '/../includes/config.php';

if (empty($config['stripe_secret']) || empty($config['stripe_publishable'])) {
    set_flash('error', 'Stripe not configured');
    header('Location: /dashboard.php');
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$stripe = new \Stripe\StripeClient($config['stripe_secret']);
try {
    $session = $stripe->checkout->sessions->retrieve($session_id);
} catch (Exception $e) {
    set_flash('error','Unable to verify payment: ' . $e->getMessage());
    header('Location: /dashboard.php');
    exit;
}

// verify payment
if (isset($session->payment_status) && $session->payment_status === 'paid') {
    // ensure idempotency: check if payment already recorded
    $stmt = $pdo->prepare('SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND status = ? LIMIT 1');
    $stmt->execute([$user['id'],$course_id,'completed']);
    if (!$stmt->fetch()) {
        $amount = 0.0;
        if (isset($session->amount_total)) {
            $amount = $session->amount_total / 100.0;
        }
        // attempt to get charge id and payment_intent from session
        $stripe_session_id = $session->id ?? null;
        $stripe_payment_intent_id = $session->payment_intent ?? null;
        $stripe_charge_id = null;
        if (!empty($stripe_payment_intent_id)) {
            try {
                $pi = $stripe->paymentIntents->retrieve($stripe_payment_intent_id);
                if (!empty($pi->charges->data) && !empty($pi->charges->data[0]->id)) {
                    $stripe_charge_id = $pi->charges->data[0]->id;
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        // Insert payment with Stripe metadata when schema supports it; fallback if columns missing
        try {
            $stmt = $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status,stripe_session_id,stripe_charge_id,stripe_payment_intent_id) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$user['id'],$course_id,$amount,'completed',$stripe_session_id,$stripe_charge_id,$stripe_payment_intent_id]);
            $payment_id = $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '42S22') {
                // Column not found -> insert minimal payment row
                $stmt = $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status) VALUES (?,?,?,?)');
                $stmt->execute([$user['id'],$course_id,$amount,'completed']);
                $payment_id = $pdo->lastInsertId();
            } else {
                throw $e;
            }
        }

        // send receipt email to student (HTML + text)
        try {
            require_once __DIR__ . '/../includes/mailer.php';
            // fetch course title for receipt (avoid undefined variable)
            $courseTitle = 'Course';
            try {
                $cstmt = $pdo->prepare('SELECT title FROM courses WHERE id = ? LIMIT 1');
                $cstmt->execute([$course_id]);
                $crow = $cstmt->fetch();
                if ($crow && !empty($crow['title'])) { $courseTitle = $crow['title']; }
            } catch (Throwable $ignore) {}
            $to = [$user['email']];
            $subject = "Payment receipt for {$courseTitle}";
            $html = "<h2>Payment Receipt</h2><p>Thank you, " . htmlspecialchars($user['name']) . "</p>";
            $html .= "<p>Course: " . htmlspecialchars($courseTitle) . "</p>";
            $html .= "<p>Amount: $" . number_format($amount,2) . "</p>";
            $html .= "<p>Receipt ID: " . htmlspecialchars($payment_id) . "</p>";
        // Build absolute URL respecting current scheme (http vs https)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $baseUrl = isset($_SERVER['HTTP_HOST']) ? ($scheme . $_SERVER['HTTP_HOST']) : '';
        $html .= "<p><a href=\"" . $baseUrl . "/receipt.php?id={$payment_id}\">Download Receipt</a></p>";
        $text = "Payment Receipt\n" .
            "Course: {$courseTitle}\n" .
            "Amount: $" . number_format($amount, 2) . "\n" .
            "Receipt ID: {$payment_id}\n" .
            (!empty($baseUrl) ? ("Receipt: " . $baseUrl . "/receipt.php?id={$payment_id}\n") : '');
            send_notification($to, $subject, ['html' => $html, 'text' => $text]);
        } catch (Exception $e) {
            // no-op
        }
    }

    // enroll if not already
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$user['id'],$course_id]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)');
        $stmt->execute([$user['id'],$course_id,0]);
    }

    set_flash('success','Payment successful and enrolled');
    header('Location: /dashboard.php');
    exit;
}

set_flash('error','Payment not completed');
header('Location: /dashboard.php');
exit;
