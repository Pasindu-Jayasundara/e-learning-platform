<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /admin_users.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id,name,email,role,is_active FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$userRow = $stmt->fetch();
if (!$userRow) {
    header('Location: /admin_users.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        $errors[] = 'Invalid CSRF token';
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if (!$name) $errors[] = 'Name required';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';

    if (empty($errors)) {
        // check email uniqueness
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already in use';
        } else {
            if ($password) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=?, is_active=?, password=? WHERE id=?');
                $stmt->execute([$name,$email,$role,$is_active,$hash,$id]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name=?, email=?, role=?, is_active=? WHERE id=?');
                $stmt->execute([$name,$email,$role,$is_active,$id]);
            }
            set_flash('success','User updated');
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
  <title>Edit User</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Edit User</h1>
  <?php foreach ($errors as $e): ?>
    <p class="error"><?php echo htmlspecialchars($e); ?></p>
  <?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
    <label>Name<br><input type="text" name="name" value="<?php echo htmlspecialchars($userRow['name']); ?>" required></label><br>
    <label>Email<br><input type="email" name="email" value="<?php echo htmlspecialchars($userRow['email']); ?>" required></label><br>
    <label>Password (leave blank to keep)<br><input type="password" name="password"></label><br>
    <label>Role<br>
      <select name="role">
        <option value="student" <?php echo ($userRow['role']=='student')? 'selected':''; ?>>Student</option>
        <option value="instructor" <?php echo ($userRow['role']=='instructor')? 'selected':''; ?>>Instructor</option>
        <option value="admin" <?php echo ($userRow['role']=='admin')? 'selected':''; ?>>Admin</option>
      </select>
    </label><br>
    <label>Active? <input type="checkbox" name="is_active" value="1" <?php echo $userRow['is_active']? 'checked':''; ?>></label><br>
    <button type="submit">Save</button>
  </form>
  <p><a href="/admin_users.php">Back</a></p>
</body>
</html>
