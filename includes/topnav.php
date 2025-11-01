<?php
// top navigation and flash messages
// expects session started and get_flash available
if (!isset($user)) {
    $user = function_exists('current_user') ? current_user() : null;
}
?>
<div class="topnav">
  <div class="container">
    <a class="brand" href="/">E-Learning</a>
    <nav>
      <style>
        /* Lightweight dropdown styles scoped to topnav */
        .topnav nav .dropdown { position: relative; display: inline-block; }
  .topnav nav .dropdown > a { display: inline-flex; align-items: center; gap: 6px; }
        .topnav nav .dropdown-menu { display: none; position: absolute; right: 0; top: 100%;
          background: var(--bg-primary, #fff); border: 1px solid var(--border-color); border-radius: var(--radius-sm);
          box-shadow: var(--shadow-md, 0 6px 24px rgba(0,0,0,0.08)); min-width: 180px; z-index: 20; }
        .topnav nav .dropdown-menu a { display: block; padding: 8px 12px; white-space: nowrap; color: var(--text-primary); }
        .topnav nav .dropdown-menu a:hover { background: var(--bg-secondary); color: var(--primary-color); }
        .topnav nav .dropdown:hover .dropdown-menu { display: block; }
      </style>
      <?php if ($user): ?>
        <a href="/dashboard.php">Dashboard</a>
      <?php endif; ?>
      
      <a href="/courses.php">Browse Courses</a>
      <?php if ($user && ($user['role'] === 'admin' || $user['role'] === 'instructor')): ?>
        <a href="/manage_courses.php">Manage Courses</a>
      <?php endif; ?>
      <a href="/announcements.php">Announcements</a>
      <?php if ($user): ?>
        <?php if ($user['role'] === 'student'): ?>
          <a href="/my_progress.php">My Progress</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
          <div class="dropdown">
            <a href="#">Manage Users ▾</a>
            <div class="dropdown-menu">
              <a href="/admin_manage_admins.php">Admins</a>
              <a href="/admin_manage_instructors.php">Instructors</a>
              <a href="/admin_manage_learners.php">Learners</a>
            </div>
          </div>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['instructor','admin'])): ?>
          <a href="/notification_settings.php">Notification Settings</a>
        <?php endif; ?>
        <a href="/logout.php">Logout</a>
      <?php else: ?>
        <a href="/login.php">Login</a>
        <a href="/register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</div>

<?php // flash messages
if (function_exists('get_flash')) {
    $msg = get_flash('success');
    if ($msg) echo '<div class="flash success">' . htmlspecialchars($msg) . '</div>';
    $err = get_flash('error');
    if ($err) echo '<div class="flash error">' . htmlspecialchars($err) . '</div>';
}
?>
