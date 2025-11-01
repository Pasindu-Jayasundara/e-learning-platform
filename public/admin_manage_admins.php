<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /admin_manage_admins.php'); exit; }
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) { $errors[] = 'All fields are required'; }
    if (empty($errors)) {
      $exists = find_user_by_email($email);
      if ($exists) { $errors[] = 'Email already exists'; }
      else {
        $id = register_user($name, $email, $password, 'admin');
        set_flash('success','Admin created');
        header('Location: /admin_manage_admins.php'); exit;
      }
    }
  }
}

$admins = $pdo->query("SELECT id,name,email,created_at FROM users WHERE role='admin' ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Admins - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style> table.datagrid{width:100%;border-collapse:collapse} table.datagrid th,table.datagrid td{border:1px solid var(--border-color);padding:10px;text-align:left} table.datagrid th{background:var(--bg-secondary)} </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header"><h1>👑 Manage Admins</h1><p>Create and review administrator accounts</p></div>

      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h2>Add Admin</h2></div>
        <div class="card-body">
          <?php foreach ($errors as $e): ?><div class="flash error"><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
          <form method="post" style="padding:0; box-shadow:none; max-width:none; display:grid; gap:12px;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <input type="hidden" name="action" value="create">
            <label>Name</label>
            <input type="text" name="name" required>
            <label>Email</label>
            <input type="email" name="email" required>
            <label>Password</label>
            <input type="password" name="password" required>
            <div><button type="submit" class="btn primary">➕ Create Admin</button></div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Admins (<?php echo count($admins); ?>)</h2></div>
        <div class="card-body">
          <?php if (empty($admins)): ?>
            <p class="text-muted">No admin accounts yet.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Created</th><th>Details</th></tr></thead>
              <tbody>
                <?php foreach ($admins as $a): ?>
                  <tr>
                    <td>#<?php echo (int)$a['id']; ?></td>
                    <td><?php echo htmlspecialchars($a['name']); ?></td>
                    <td><?php echo htmlspecialchars($a['email']); ?></td>
                    <td><?php echo htmlspecialchars($a['created_at']); ?></td>
                    <td><a class="btn-sm btn secondary" href="/admin_user_details.php?id=<?php echo (int)$a['id']; ?>">🔍 View</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</body>
</html>