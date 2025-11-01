<?php
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $user = verify_and_get_user($email, $password);
    if ($user) {
        login_user($user);
        header('Location: /dashboard.php');
        exit;
    } else {
        $errors[] = 'Invalid credentials';
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  
  <main>
    <div class="container-narrow">
      <div class="page-header fade-in" style="text-align: center; margin-top: 40px;">
        <h1>🔐 Welcome Back</h1>
        <p>Sign in to continue your learning journey</p>
      </div>

      <div class="fade-in" style="margin-top: 32px;">
        <?php foreach ($errors as $e): ?>
          <div class="flash error" style="max-width: 600px; margin: 0 auto 20px;">
            <?php echo htmlspecialchars($e); ?>
          </div>
        <?php endforeach; ?>

        <form method="post" style="margin: 0 auto;">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your email" required autofocus>

          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>

          <button type="submit" class="btn primary" style="width: 100%; margin-top: 8px;">
            Sign In
          </button>
        </form>

        <div style="text-align: center; margin-top: 24px; padding-bottom: 48px;">
          <p style="color: var(--text-secondary);">
            Don't have an account? 
            <a href="/register.php" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
              Create one here
            </a>
          </p>
        </div>
      </div>
    </div>
  </main>
</body>
</html>