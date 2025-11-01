<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);
$user = current_user();

// fetch all users
$sth = $pdo->query('SELECT id,name,email,role,is_active,created_at FROM users ORDER BY id DESC');
$users = $sth->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Manage Users</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Manage Users</h1>
  <p><a href="/add_user.php">Add New User</a> | <a href="/dashboard.php">Back</a></p>

  <?php if ($msg = get_flash('success')): ?>
    <p class="success"><?php echo htmlspecialchars($msg); ?></p>
  <?php endif; ?>
  <?php if ($err = get_flash('error')): ?>
    <p class="error"><?php echo htmlspecialchars($err); ?></p>
  <?php endif; ?>

  <table border="1" cellpadding="6" cellspacing="0">
    <thead>
      <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th>Created</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?php echo $u['id']; ?></td>
          <td><?php echo htmlspecialchars($u['name']); ?></td>
          <td><?php echo htmlspecialchars($u['email']); ?></td>
          <td><?php echo htmlspecialchars($u['role']); ?></td>
          <td><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
          <td><?php echo $u['created_at']; ?></td>
          <td>
            <a href="/edit_user.php?id=<?php echo $u['id']; ?>">edit</a>
            <?php if ($u['id'] != $user['id']): ?>
              <form method="post" action="/delete_user.php" style="display:inline" onsubmit="return confirm('Delete user?');">
                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <button type="submit">delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
