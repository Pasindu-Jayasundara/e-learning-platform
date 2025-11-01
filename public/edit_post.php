<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $post_id = intval($_GET['post_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM forum_posts WHERE id = ? LIMIT 1');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post) { set_flash('error','Post not found'); header('Location: /dashboard.php'); exit; }
    // permission
    if (!($user['role'] === 'admin' || $user['id'] == $post['user_id'] || ($user['role'] === 'instructor' && $post['course_id'] && $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND instructor_id = ?')->execute([$post['course_id'],$user['id']]))) ) {
        set_flash('error','Not allowed'); header('Location: /post_forum.php?post_id=' . $post_id); exit;
    }
    ?>
    <!doctype html><html><head><meta charset="utf-8"><title>Edit Post</title></head><body>
    <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
    <h1>Edit Post</h1>
    <form method="post" action="/edit_post.php">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
      <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
      <label>Title<br><input name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required maxlength="200"></label><br>
      <label>Message<br><textarea name="message" required maxlength="2000"><?php echo htmlspecialchars($post['message']); ?></textarea></label><br>
      <button type="submit">Save</button>
    </form>
    </body></html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /dashboard.php'); exit; }
    $post_id = intval($_POST['post_id'] ?? 0);
    $title = strip_tags(trim($_POST['title'] ?? ''));
    $message = strip_tags(trim($_POST['message'] ?? ''));
    if (!$post_id || !$title || !$message) { set_flash('error','Missing data'); header('Location: /dashboard.php'); exit; }
    if (mb_strlen($title) > 200 || mb_strlen($message) > 2000) { set_flash('error','Limits exceeded'); header('Location: /edit_post.php?post_id=' . $post_id); exit; }
    $stmt = $pdo->prepare('SELECT * FROM forum_posts WHERE id = ? LIMIT 1');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    if (!$post) { set_flash('error','Post not found'); header('Location: /dashboard.php'); exit; }
    if (!($user['role'] === 'admin' || $user['id'] == $post['user_id'] || ($user['role'] === 'instructor' && $pdo->prepare('SELECT 1 FROM courses WHERE id = ? AND instructor_id = ?')->execute([$post['course_id'],$user['id']])))) {
        set_flash('error','Not allowed'); header('Location: /post_forum.php?post_id=' . $post_id); exit;
    }
    $stmt = $pdo->prepare('UPDATE forum_posts SET title = ?, message = ? WHERE id = ?');
    $stmt->execute([$title,$message,$post_id]);
    set_flash('success','Post updated');
    header('Location: /post_forum.php?post_id=' . $post_id);
    exit;
}

header('Location: /dashboard.php');
exit;
