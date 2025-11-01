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
			// record payment and enroll
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
				$q = $pdo->prepare('SELECT id FROM payments WHERE user_id = ? AND course_id = ? AND status = ? LIMIT 1');
				$q->execute([$user_id,$course_id,'completed']);
				if ($row = $q->fetch()) {
					if ($stripe_session_id || $stripe_charge_id || $stripe_payment_intent_id) {
						try {
							$u = $pdo->prepare('UPDATE payments SET stripe_session_id = COALESCE(stripe_session_id, ?), stripe_charge_id = COALESCE(stripe_charge_id, ?), stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, ?) WHERE id = ?');
							$u->execute([$stripe_session_id,$stripe_charge_id,$stripe_payment_intent_id,$row['id']]);
						} catch (PDOException $e) {
							if ($e->getCode() !== '42S22') throw $e;
						}
					}
				} else {
					try {
						$ins = $pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status,stripe_session_id,stripe_charge_id,stripe_payment_intent_id) VALUES (?,?,?,?,?,?,?)');
						$ins->execute([$user_id,$course_id,$amount,'completed',$stripe_session_id,$stripe_charge_id,$stripe_payment_intent_id]);
					} catch (PDOException $e) {
						if ($e->getCode() === '42S22') {
							$pdo->prepare('INSERT INTO payments (user_id,course_id,amount,status) VALUES (?,?,?,?)')->execute([$user_id,$course_id,$amount,'completed']);
						} else {
							throw $e;
						}
					}
				}
				// enroll if missing
				$q2 = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
				$q2->execute([$user_id,$course_id]);
				if (!$q2->fetch()) {
					$pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)')->execute([$user_id,$course_id,0]);
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
