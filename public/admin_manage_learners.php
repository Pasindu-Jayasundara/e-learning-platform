<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);

$learners = $pdo->query("SELECT id,name,email,created_at FROM users WHERE role='student' ORDER BY created_at DESC")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Learners - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style> table.datagrid{width:100%;border-collapse:collapse} table.datagrid th,table.datagrid td{border:1px solid var(--border-color);padding:10px;text-align:left} table.datagrid th{background:var(--bg-secondary)} </style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header"><h1>🎓 Manage Learners</h1><p>Review and explore learner profiles</p></div>

      <div class="card">
        <div class="card-header"><h2>Learners (<?php echo count($learners); ?>)</h2></div>
        <div class="card-body">
          <?php if (empty($learners)): ?>
            <p class="text-muted">No learners found.</p>
          <?php else: ?>
            <table class="datagrid">
              <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Joined</th><th>Details</th></tr></thead>
              <tbody>
                <?php foreach ($learners as $l): ?>
                  <tr>
                    <td>#<?php echo (int)$l['id']; ?></td>
                    <td><?php echo htmlspecialchars($l['name']); ?></td>
                    <td><?php echo htmlspecialchars($l['email']); ?></td>
                    <td><?php echo htmlspecialchars($l['created_at']); ?></td>
                    <td><a class="btn-sm btn secondary" href="/admin_user_details.php?id=<?php echo (int)$l['id']; ?>">🔍 View</a></td>
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