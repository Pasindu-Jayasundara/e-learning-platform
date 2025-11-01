<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
if ($user['role'] !== 'student') {
    echo 'Only students can checkout';
    exit;
}

$course_id = intval($_GET['course_id'] ?? 0);
if (!$course_id) {
    header('Location: /dashboard.php');
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: /dashboard.php');
    exit;
}

$config = require __DIR__ . '/../includes/config.php';

// Require Stripe configuration for paid enrollments (no mock fallbacks)
if (empty($config['stripe_secret']) || empty($config['stripe_publishable'])) {
    set_flash('error','Payment gateway not configured. Please set your Stripe keys to proceed with checkout.');
    header('Location: /course.php?id=' . $course_id);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$stripe = new \Stripe\StripeClient($config['stripe_secret']);

// create a Checkout Session
$session = $stripe->checkout->sessions->create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => ['name' => $course['title']],
            'unit_amount' => intval(round($course['price'] * 100)),
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $_SERVER['HTTP_HOST'] . "/payment_success.php?session_id={CHECKOUT_SESSION_ID}&course_id={$course_id}",
    'cancel_url' => (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/dashboard.php',
    'metadata' => ['user_id' => $user['id'], 'course_id' => $course_id],
]);

// redirect to Stripe Checkout
header('Location: ' . $session->url);
exit;
