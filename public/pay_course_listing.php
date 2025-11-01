<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['instructor']);
$user = current_user();
$config = require __DIR__ . '/../includes/config.php';

$course_id = (int)($_GET['course_id'] ?? 0);
if ($course_id <= 0) { set_flash('error','Missing course'); header('Location:/manage_courses.php'); exit; }

// verify ownership
$stmt = $pdo->prepare('SELECT id, title, instructor_id, listing_fee_paid FROM courses WHERE id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course || (int)$course['instructor_id'] !== (int)$user['id']) { set_flash('error','Course not found'); header('Location:/manage_courses.php'); exit; }

if (!empty($course['listing_fee_paid'])) { set_flash('success','Listing fee already paid'); header('Location:/manage_courses.php'); exit; }

$amount = isset($config['listing_fee_amount']) ? (float)$config['listing_fee_amount'] : 10.00; // default $10
$currency = isset($config['listing_fee_currency']) ? strtolower($config['listing_fee_currency']) : 'usd';

if (empty($config['stripe_secret']) || empty($config['stripe_publishable'])) {
  set_flash('error','Stripe not configured. Contact admin.');
  header('Location:/manage_courses.php'); exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
$stripe = new \Stripe\StripeClient($config['stripe_secret']);

try {
  $session = $stripe->checkout->sessions->create([
    'mode' => 'payment',
    'payment_method_types' => ['card'],
    'success_url' => (isset($_SERVER['HTTP_HOST']) ? 'http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'s':'').'://' . $_SERVER['HTTP_HOST'] : '') . '/listing_payment_success.php?session_id={CHECKOUT_SESSION_ID}&course_id='.$course_id,
    'cancel_url' => (isset($_SERVER['HTTP_HOST']) ? 'http'.((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'s':'').'://' . $_SERVER['HTTP_HOST'] : '') . '/manage_courses.php',
    'line_items' => [[
      'price_data' => [
        'currency' => $currency,
        'unit_amount' => (int)round($amount * 100),
        'product_data' => [
          'name' => 'Course Listing Fee',
          'description' => 'Listing fee for: ' . $course['title'],
        ],
      ],
      'quantity' => 1,
    ]],
    'metadata' => [
      'purpose' => 'listing_fee',
      'user_id' => (string)$user['id'],
      'course_id' => (string)$course_id,
    ],
  ]);
} catch (Exception $e) {
  set_flash('error','Unable to start payment: '.$e->getMessage());
  header('Location:/manage_courses.php'); exit;
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pay Listing Fee</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header"><h1>💳 Pay Listing Fee</h1><p>Complete the one-time listing fee to activate your course</p></div>
      <div class="card">
        <div class="card-body">
          <p><strong>Course:</strong> <?php echo htmlspecialchars($course['title']); ?></p>
          <p><strong>Amount:</strong> $<?php echo number_format($amount,2); ?> <?php echo strtoupper($currency); ?></p>
          <button id="checkout" class="btn primary">Proceed to Checkout</button>
          <a class="btn secondary" href="/manage_courses.php">Cancel</a>
        </div>
      </div>
    </div>
  </main>
  <script>
    (function(){
      var stripe = Stripe('<?php echo htmlspecialchars($config['stripe_publishable']); ?>');
      document.getElementById('checkout').addEventListener('click', function(){
        stripe.redirectToCheckout({ sessionId: '<?php echo htmlspecialchars($session->id); ?>' });
      });
    })();
  </script>
</body>
</html>
