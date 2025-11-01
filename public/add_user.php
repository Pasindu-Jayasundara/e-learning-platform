<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token';
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) $errors[] = 'Name required';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
    if (!$password) $errors[] = 'Password required';

    if (empty($errors)) {
        // ensure unique email
        if (find_user_by_email($email)) {
            $errors[] = 'Email already exists';
        } else {
            // use register_user to hash password
            $id = register_user($name, $email, $password, $role);
            // update is_active
            $stmt = $pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?');
            $stmt->execute([$is_active, $id]);
            set_flash('success','User created');
            header('Location: /admin_users.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Add User</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Add User</h1>
  <?php foreach ($errors as $e): ?>
    <p class="error"><?php echo htmlspecialchars($e); ?></p>
  <?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
    <label>Name<br><input type="text" name="name" required></label><br>
    <label>Email<br><input type="email" name="email" required></label><br>
    <label>Password<br><input type="password" name="password" required></label><br>
    <label>Role<br>
      <select name="role">
        <option value="student">Student</option>
        <option value="instructor">Instructor</option>
        <option value="admin">Admin</option>
      </select>
    </label><br>
    <label>Active? <input type="checkbox" name="is_active" value="1" checked></label><br>
    <button type="submit">Create</button>
  </form>
  <p><a href="/admin_users.php">Back</a></p>
</body>
</html>
