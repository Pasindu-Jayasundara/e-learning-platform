<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
require_role(['admin']);
$current = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin_users.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /admin_users.php');
    exit;
}
if (!$id) {
    header('Location: /admin_users.php');
    exit;
}
if ($id == $current['id']) {
    set_flash('error','You cannot delete your own account');
    header('Location: /admin_users.php');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
$stmt->execute([$id]);
set_flash('success','User deleted');
header('Location: /admin_users.php');
exit;
