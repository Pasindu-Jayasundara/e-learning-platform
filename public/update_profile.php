<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /profile.php');
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) {
    set_flash('error','Invalid CSRF token');
    header('Location: /profile.php');
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$notify = isset($_POST['notify_on_report']) ? 1 : 0;

if (!$name || !$email) {
    set_flash('error','Name and email are required');
    header('Location: /profile.php');
    exit;
}

// check email uniqueness
$q = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
$q->execute([$email, $user['id']]);
if ($q->fetch()) {
    set_flash('error','Email already in use');
    header('Location: /profile.php');
    exit;
}

$change_pw = false;
$new_pw = trim($_POST['new_password'] ?? '');
$confirm = trim($_POST['new_password_confirm'] ?? '');
$current_pw = trim($_POST['current_password'] ?? '');
if ($new_pw !== '') {
    if ($new_pw !== $confirm) {
        set_flash('error','New passwords do not match');
        header('Location: /profile.php');
        exit;
    }
    if ($current_pw === '') {
        set_flash('error','Current password required to change password');
        header('Location: /profile.php');
        exit;
    }
    // verify current password
    $s = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $s->execute([$user['id']]);
    $row = $s->fetch();
    if (!$row || !password_verify($current_pw, $row['password'])) {
        set_flash('error','Current password incorrect');
        header('Location: /profile.php');
        exit;
    }
    $change_pw = true;
}

if ($change_pw) {
    $hash = password_hash($new_pw, PASSWORD_DEFAULT);
    $u = $pdo->prepare('UPDATE users SET name = ?, email = ?, notify_on_report = ?, password = ? WHERE id = ?');
    $u->execute([$name, $email, $notify, $hash, $user['id']]);
} else {
    $u = $pdo->prepare('UPDATE users SET name = ?, email = ?, notify_on_report = ? WHERE id = ?');
    $u->execute([$name, $email, $notify, $user['id']]);
}

set_flash('success','Profile updated');
header('Location: /profile.php');
exit;
