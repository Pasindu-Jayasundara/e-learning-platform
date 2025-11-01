<?php
// Stripe webhook endpoint to process payment events reliably
require_once __DIR__ . '/../includes/db.php';
$config = require __DIR__ . '/../includes/config.php';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!empty($config['stripe_webhook_secret'])) {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $config['stripe_webhook_secret']);
    } catch (\UnexpectedValueException $e) {
        http_response_code(400);
        echo 'Invalid payload';
        exit;
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        http_response_code(400);
        echo 'Invalid signature';
        exit;
    }
} else {
    // No webhook secret configured — try basic decode (less secure)
    $event = json_decode($payload);
    if (!$event) { http_response_code(400); echo 'Invalid payload'; exit; }
}

// handle relevant events
switch ($event->type ?? ($event['type'] ?? null)) {
    case 'checkout.session.completed':
        $session = $event->data->object ?? $event['data']['object'];
            // determine purpose (default enrollment)
            $purpose = $session->metadata->purpose ?? ($session->metadata['purpose'] ?? 'enrollment');
            // record payment and enroll or mark listing paid based on purpose
            $user_id = $session->metadata->user_id ?? $session->metadata['user_id'] ?? null;
            $course_id = $session->metadata->course_id ?? $session->metadata['course_id'] ?? null;
            $amount = isset($session->amount_total) ? ($session->amount_total / 100.0) : 0.0;
            $stripe_session_id = $session->id ?? null;
            $stripe_payment_intent_id = $session->payment_intent ?? null;
            $stripe_charge_id = null;
            // try to extract charge id from payment_intent if available
            if (!empty($stripe_payment_intent_id)) {
                try {
                    require_once __DIR__ . '/../vendor/autoload.php';
                    $stripe = new \Stripe\StripeClient($config['stripe_secret'] ?? null);
                    $pi = $stripe->paymentIntents->retrieve($stripe_payment_intent_id);
                    if (!empty($pi->charges->data) && !empty($pi->charges->data[0]->id)) {
                        $stripe_charge_id = $pi->charges->data[0]->id;
                    }
                } catch (Exception $e) {
                    // ignore
                }
            }

            if ($user_id && $course_id) {
                // idempotent insert or update stripe ids if payment exists
                try { $pdo->exec("ALTER TABLE payments ADD COLUMN purpose VARCHAR(32) NULL DEFAULT 'enrollment'"); } catch (Throwable $e) {}
                $q = $pdo->prepare('SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND status = ? AND (purpose = ? OR purpose IS NULL) LIMIT 1');
                $q->execute([$user_id,$course_id,'completed',$purpose]);
                if ($row = $q->fetch()) {
                    if ($stripe_session_id || $stripe_charge_id || $stripe_payment_intent_id) {
                        try {
                            $u = $pdo->prepare('UPDATE payments SET stripe_session_id = COALESCE(stripe_session_id, ?), stripe_charge_id = COALESCE(stripe_charge_id, ?), stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, ?), purpose = COALESCE(purpose, ?) WHERE id = ?');
                            $u->execute([$stripe_session_id,$stripe_charge_id,$stripe_payment_intent_id,$purpose,$row['id']]);
                        } catch (PDOException $e) {
                            // Column not found: ignore update if legacy schema
                            if ($e->getCode() !== '42S22') throw $e;
                        }
                    }
                } else {
                    try {
                        $ins = $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status,purpose,stripe_session_id,stripe_charge_id,stripe_payment_intent_id) VALUES (?,?,?,?,?,?,?,?)');
                        $ins->execute([$user_id,$course_id,$amount,'completed',$purpose,$stripe_session_id,$stripe_charge_id,$stripe_payment_intent_id]);
                    } catch (PDOException $e) {
                        if ($e->getCode() === '42S22') {
                            // Fallback minimal insert for legacy schema
                            $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status) VALUES (?,?,?,?)')->execute([$user_id,$course_id,$amount,'completed']);
                        } else {
                            throw $e;
                        }
                    }
                }
                if ($purpose === 'listing_fee') {
                    // mark course as listing paid; publish only if admin hasn't set a publish lock
                    try { $pdo->exec("ALTER TABLE courses ADD COLUMN listing_fee_paid TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
                    try { $pdo->exec("ALTER TABLE courses ADD COLUMN listing_fee_paid_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
                    try { $pdo->exec("ALTER TABLE courses ADD COLUMN admin_unpublished_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}
                    // Check if admin previously unpublished
                    $chk = $pdo->prepare('SELECT admin_unpublished_at FROM courses WHERE id = ? LIMIT 1');
                    $chk->execute([$course_id]);
                    $row = $chk->fetch();
                    if ($row && empty($row['admin_unpublished_at'])) {
                        $pdo->prepare('UPDATE courses SET listing_fee_paid=1, listing_fee_paid_at=NOW(), is_published=1 WHERE id=?')->execute([$course_id]);
                    } else {
                        $pdo->prepare('UPDATE courses SET listing_fee_paid=1, listing_fee_paid_at=NOW() WHERE id=?')->execute([$course_id]);
                    }
                } else {
                    // enroll student for regular course purchase
                    $q2 = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
                    $q2->execute([$user_id,$course_id]);
                    if (!$q2->fetch()) {
                        $pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)')->execute([$user_id,$course_id,0]);
                    }

                    // Send receipt email via webhook to ensure reliability even if user doesn't return to success page
                    try {
                        require_once __DIR__ . '/../includes/mailer.php';
                        // fetch minimal details
                        $uStmt = $pdo->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
                        $uStmt->execute([$user_id]);
                        $u = $uStmt->fetch();
                        $cStmt = $pdo->prepare('SELECT title FROM courses WHERE id = ? LIMIT 1');
                        $cStmt->execute([$course_id]);
                        $c = $cStmt->fetch();
                        $courseTitle = $c['title'] ?? 'Course';
                        // obtain a payment id to reference on receipt link
                        $pidStmt = $pdo->prepare('SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND status = ? ORDER BY id DESC LIMIT 1');
                        $pidStmt->execute([$user_id,$course_id,'completed']);
                        $prow = $pidStmt->fetch();
                        $payment_id = $prow['id'] ?? null;

                        $to = [$u['email'] ?? ''];
                        $subject = 'Payment receipt for ' . $courseTitle;
                        // Build base URL best-effort (HTTP_HOST may be empty on webhook)
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                        $host = $_SERVER['HTTP_HOST'] ?? '';
                        $baseUrl = $host ? ($scheme . $host) : '';
                        $html = '<h2>Payment Receipt</h2>' .
                                '<p>Thank you, ' . htmlspecialchars($u['name'] ?? 'Student') . '</p>' .
                                '<p>Course: ' . htmlspecialchars($courseTitle) . '</p>' .
                                '<p>Amount: $' . number_format($amount, 2) . '</p>';
                        if ($payment_id) {
                            $html .= '<p>Receipt ID: ' . htmlspecialchars($payment_id) . '</p>';
                            if ($baseUrl) { $html .= '<p><a href="' . $baseUrl . '/receipt.php?id=' . $payment_id . '">Download Receipt</a></p>'; }
                        }
                        $text = "Payment Receipt\n" .
                                'Course: ' . $courseTitle . "\n" .
                                'Amount: $' . number_format($amount, 2) . "\n" .
                                ($payment_id ? ('Receipt ID: ' . $payment_id . "\n") : '');
                        if ($payment_id && $baseUrl) { $text .= 'Receipt: ' . $baseUrl . '/receipt.php?id=' . $payment_id . "\n"; }
                        // only send if we have a valid email
                        if (!empty($u['email'])) {
                            @send_notification($to, $subject, ['html' => $html, 'text' => $text]);
                        }
                    } catch (Throwable $e) {
                        // ignore mail errors in webhook path
                    }
                }
            }
        break;
    case 'charge.refunded':
    case 'charge.dispute.closed':
        // handle refunds: the charge object may include metadata referencing course/user
        $charge = $event->data->object ?? $event['data']['object'];
        $metadata = $charge->metadata ?? ($charge['metadata'] ?? []);
        $user_id = $metadata->user_id ?? $metadata['user_id'] ?? null;
        $course_id = $metadata->course_id ?? $metadata['course_id'] ?? null;
        if ($user_id && $course_id) {
            // mark payments as refunded
            $u = $pdo->prepare('UPDATE payments SET status = ? WHERE user_id = ? AND course_id = ? AND status = ?');
            $u->execute(['refunded',$user_id,$course_id,'completed']);
        }
        break;
    default:
        // ignore
        break;
}

http_response_code(200);
echo 'OK';

?>
