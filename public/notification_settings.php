<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// Only instructors and admins can toggle instructor notification preference for themselves
if (!in_array($user['role'], ['instructor','admin'])) {
    set_flash('error','Access denied');
    header('Location: /dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }
  $val = isset($_POST['notify_on_report']) ? 1 : 0;
  try {
    $stmt = $pdo->prepare('UPDATE users SET notify_on_report = ? WHERE id = ?');
    $stmt->execute([$val, $user['id']]);
  } catch (PDOException $e) {
    // auto-migrate missing column for dev environments
    if ($e->getCode() === '42S22') {
      try {
        $pdo->exec("ALTER TABLE users ADD COLUMN notify_on_report TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
        $stmt = $pdo->prepare('UPDATE users SET notify_on_report = ? WHERE id = ?');
        $stmt->execute([$val, $user['id']]);
        set_flash('success','Notification column added and preference updated');
      } catch (PDOException $e2) {
        set_flash('error','Failed to update preference: ' . $e2->getMessage());
        header('Location: /dashboard.php');
        exit;
      }
    } else {
      set_flash('error','Failed to update preference: ' . $e->getMessage());
      header('Location: /dashboard.php');
      exit;
    }
  }
  if (!headers_sent()) {
    if (!get_flash('success')) set_flash('success','Notification preference updated');
    header('Location: /notification_settings.php');
  }
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT notify_on_report FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$user['id']]);
  $pref = $stmt->fetchColumn();
} catch (PDOException $e) {
  if ($e->getCode() === '42S22') {
    // auto-migrate the column then retry
    try {
      $pdo->exec("ALTER TABLE users ADD COLUMN notify_on_report TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
      $stmt = $pdo->prepare('SELECT notify_on_report FROM users WHERE id = ? LIMIT 1');
      $stmt->execute([$user['id']]);
      $pref = $stmt->fetchColumn();
      set_flash('success','Notification settings initialized');
    } catch (PDOException $e2) {
      $pref = 0; // fallback
      set_flash('error','Failed to initialize notification settings: ' . $e2->getMessage());
    }
  } else {
    $pref = 0;
    set_flash('error','Failed to load notification settings: ' . $e->getMessage());
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notification Settings - E-Learning Platform</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .settings-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
    .setting-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
    .setting-row:last-child { border-bottom: none; }
    .setting-label { font-weight: 600; }
    .muted { color: var(--text-secondary); font-size: 14px; }
  </style>
  </head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="page-header fade-in">
        <h1>Notification Settings</h1>
        <p class="muted">Control when we email you about activity on your courses.</p>
      </div>

      <div class="card fade-in" style="max-width: 760px;">
        <div class="card-header">
          <h2>Email preferences</h2>
        </div>
        <div class="card-body">
          <form method="post" style="margin:0;">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
            <div class="settings-grid">
              <div class="setting-row">
                <div>
                  <div class="setting-label">Reports on my courses</div>
                  <div class="muted">Receive an email when someone reports content or activity related to your courses.</div>
                </div>
                <label style="white-space:nowrap;">
                  <input type="checkbox" name="notify_on_report" <?php echo $pref ? 'checked' : ''; ?>>
                  <span>Enabled</span>
                </label>
              </div>
            </div>
            <div style="margin-top: 16px; display:flex; gap:12px;">
              <button type="submit" class="btn primary">Save changes</button>
              <a href="/dashboard.php" class="btn secondary">Back to Dashboard</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
