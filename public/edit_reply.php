<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reply_id = intval($_GET['reply_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT r.*, u.name as author FROM forum_replies r JOIN users u ON r.user_id = u.id WHERE r.id = ? LIMIT 1');
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();
    if (!$reply) { set_flash('error','Reply not found'); header('Location: /dashboard.php'); exit; }
    if (!($user['role'] === 'admin' || $user['id'] == $reply['user_id'])) { set_flash('error','Not allowed'); header('Location: /post_forum.php?post_id=' . $reply['post_id']); exit; }
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Edit Reply</title></head><body>
    <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
    <h1>Edit Reply</h1>
    <form method="post" action="/edit_reply.php">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="reply_id" value="<?php echo $reply['id']; ?>">
      <label>Message<br><textarea name="message" required maxlength="2000"><?php echo htmlspecialchars($reply['message']); ?></textarea></label><br>
      <button type="submit">Save</button>
    </form>
    </body></html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }
    $reply_id = intval($_POST['reply_id'] ?? 0);
    $message = strip_tags(trim($_POST['message'] ?? ''));
    if (!$reply_id || $message === '') { set_flash('error','Missing'); header('Location: /dashboard.php'); exit; }
    if (mb_strlen($message) > 2000) { set_flash('error','Message too long'); header('Location: /edit_reply.php?reply_id=' . $reply_id); exit; }
    $stmt = $pdo->prepare('SELECT * FROM forum_replies WHERE id = ? LIMIT 1');
    $stmt->execute([$reply_id]);
    $reply = $stmt->fetch();
    if (!$reply) { set_flash('error','Reply not found'); header('Location: /dashboard.php'); exit; }
    if (!($user['role'] === 'admin' || $user['id'] == $reply['user_id'])) { set_flash('error','Not allowed'); header('Location: /post_forum.php?post_id=' . $reply['post_id']); exit; }
    $pdo->prepare('UPDATE forum_replies SET message = ? WHERE id = ?')->execute([$message,$reply_id]);
    set_flash('success','Reply updated');
    header('Location: /post_forum.php?post_id=' . $reply['post_id']);
    exit;
}

header('Location: /dashboard.php');
exit;
