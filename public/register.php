<?php
require_once __DIR__ . '/../includes/auth.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
  $selectedRole = $_POST['role'] ?? 'student';

    if (!$name || !$email || !$password) {
        $errors[] = 'All fields are required';
    } else {
        // check existing
        if (find_user_by_email($email)) {
            $errors[] = 'Email already registered';
    } else {
      // Always register as student. If user selected instructor, flag request for approval.
      $id = register_user($name, $email, $password, 'student');
      // If instructor selected, set requested_instructor flag
      if ($selectedRole === 'instructor') {
        require_once __DIR__ . '/../includes/db.php';
        $q = $pdo->prepare('UPDATE users SET requested_instructor = 1, instructor_requested_at = NOW() WHERE id = ?');
        try { $q->execute([$id]); } catch (Throwable $e) { /* ignore */ }
        set_flash('success','Thanks! Your instructor application was submitted and is pending admin approval. You can start learning meanwhile.');
      }
      $user = ['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'student'];
      login_user($user);
      header('Location: /dashboard.php');
      exit;
    }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  
  <main>
    <div class="container-narrow">
      <div class="page-header fade-in" style="text-align: center; margin-top: 40px;">
        <h1>🎓 Create Your Account</h1>
        <p>Join thousands of learners already transforming their future</p>
      </div>

      <div class="fade-in" style="margin-top: 32px;">
        <?php foreach ($errors as $e): ?>
          <div class="flash error" style="max-width: 600px; margin: 0 auto 20px;">
            <?php echo htmlspecialchars($e); ?>
          </div>
        <?php endforeach; ?>

        <form method="post" style="margin: 0 auto;">
          <label>Full Name</label>
          <input type="text" name="name" placeholder="Enter your full name" required autofocus>

          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter your email" required>

          <label>Password</label>
          <input type="password" name="password" placeholder="Create a strong password" required>

          <label>I want to join as</label>
          <select name="role" style="margin-bottom: 24px;">
            <option value="student">🎓 Student - I want to learn</option>
            <option value="instructor">👨‍🏫 Instructor - I want to teach</option>
          </select>

          <button type="submit" class="btn primary" style="width: 100%; margin-top: 8px;">
            Create Account
          </button>
        </form>

        <div style="text-align: center; margin-top: 24px; padding-bottom: 48px;">
          <p style="color: var(--text-secondary);">
            Already have an account? 
            <a href="/login.php" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">
              Sign in here
            </a>
          </p>
        </div>
      </div>
    </div>
  </main>
</body>
</html>