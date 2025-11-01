<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// show profile form
$csrf = generate_csrf_token();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>My Profile</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>My Profile</h1>
  <?php if ($msg = get_flash('error')): ?><div style="color:red"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <?php if ($msg = get_flash('success')): ?><div style="color:green"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

  <form method="post" action="/update_profile.php">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
    <label>Name<br><input name="name" required value="<?php echo htmlspecialchars($user['name']); ?>"></label><br>
    <label>Email<br><input name="email" type="email" required value="<?php echo htmlspecialchars($user['email']); ?>"></label><br>
    <label><input type="checkbox" name="notify_on_report" value="1" <?php echo !empty($user['notify_on_report']) ? 'checked' : ''; ?>> Receive email notifications for reports on my courses</label><br>

    <h3>Change password</h3>
    <p>Leave blank to keep current password.</p>
    <label>Current password (required to change)<br><input name="current_password" type="password"></label><br>
    <label>New password<br><input name="new_password" type="password"></label><br>
    <label>Confirm new password<br><input name="new_password_confirm" type="password"></label><br>

    <button type="submit">Update Profile</button>
  </form>

  <p><a href="/dashboard.php">Back</a></p>
</body>
</html>
