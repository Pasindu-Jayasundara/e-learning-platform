<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

$course_id = intval($_GET['course_id'] ?? 0);
if ($course_id <= 0) {
  header('Location: /dashboard.php');
  exit;
}

// load course and instructor
$stmt = $pdo->prepare('SELECT c.*, u.id as instructor_user_id, u.name as instructor_name, u.email as instructor_email FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.id = ? LIMIT 1');
$stmt->execute([$course_id]);
$course = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$course) { set_flash('error','Course not found'); header('Location: /dashboard.php'); exit; }

// permissions: students must be enrolled; instructors must own the course; admins allowed
$role = $user['role'];
$enrolled = false;
if ($role === 'student') {
  $st = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
  $st->execute([$user['id'], $course_id]);
  $enrolled = (bool)$st->fetchColumn();
  if (!$enrolled) { set_flash('error','Enroll to message your instructor.'); header('Location: /course.php?id='.$course_id); exit; }
}
if ($role === 'instructor' && (int)$course['instructor_id'] !== (int)$user['id']) {
  set_flash('error','You do not have access to this course messages.'); header('Location: /dashboard.php'); exit;
}

// Determine other participant for thread
// - For student: the instructor
// - For instructor: chosen student via ?user_id, default = first enrolled student with messages
$peer_user_id = 0;
$peer_name = '';

if ($role === 'student') {
  $peer_user_id = (int)$course['instructor_user_id'];
  $peer_name = $course['instructor_name'] ?: 'Instructor';
} else if ($role === 'instructor' || $role === 'admin') {
  $requested_user_id = intval($_GET['user_id'] ?? 0);
  if ($requested_user_id <= 0) {
    // default to first student with messages or first enrolled student
    $st = $pdo->prepare('SELECT DISTINCT u.id, u.name FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY u.name ASC LIMIT 1');
    $st->execute([$course_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) { $requested_user_id = (int)$row['id']; }
  }
  $peer_user_id = $requested_user_id;
  if ($peer_user_id > 0) {
    $st = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $st->execute([$peer_user_id]);
    $peer_name = $st->fetchColumn() ?: 'Student';
  }
}

// POST: send message
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $csrf = $_POST['csrf'] ?? '';
  if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /course_messages.php?course_id='.$course_id.($peer_user_id?('&user_id='.$peer_user_id):'')); exit; }

  $body = trim((string)($_POST['body'] ?? ''));
  if ($body === '') { set_flash('error','Message cannot be empty.'); header('Location: /course_messages.php?course_id='.$course_id.($peer_user_id?('&user_id='.$peer_user_id):'')); exit; }

  // determine recipient
  $recipient_id = 0;
  if ($role === 'student') {
    $recipient_id = (int)$course['instructor_user_id'];
  } else {
    $recipient_id = $peer_user_id;
  }
  if ($recipient_id <= 0) { set_flash('error','No recipient selected.'); header('Location: /course_messages.php?course_id='.$course_id); exit; }

  $ins = $pdo->prepare('INSERT INTO course_messages (course_id, sender_id, recipient_id, body, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
  $ins->execute([$course_id, $user['id'], $recipient_id, $body]);

  set_flash('success','Message sent.');
  header('Location: /course_messages.php?course_id='.$course_id.($role!=='student' && $peer_user_id?('&user_id='.$peer_user_id):''));
  exit;
}

// Mark peer->me messages as read
if ($peer_user_id > 0) {
  $upd = $pdo->prepare('UPDATE course_messages SET is_read = 1 WHERE course_id = ? AND recipient_id = ? AND sender_id = ?');
  $upd->execute([$course_id, $user['id'], $peer_user_id]);
}

// Fetch thread messages
$messages = [];
if ($peer_user_id > 0) {
  $st = $pdo->prepare('SELECT m.*, su.name AS sender_name, ru.name AS recipient_name FROM course_messages m JOIN users su ON su.id = m.sender_id JOIN users ru ON ru.id = m.recipient_id WHERE m.course_id = ? AND ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?)) ORDER BY m.created_at ASC, m.id ASC');
  $st->execute([$course_id, $user['id'], $peer_user_id, $peer_user_id, $user['id']]);
  $messages = $st->fetchAll(PDO::FETCH_ASSOC);
}

// For instructor/admin: fetch list of enrolled students to pick thread
$students = [];
if ($role === 'instructor' || $role === 'admin') {
  $st = $pdo->prepare('SELECT u.id, u.name, u.email FROM enrollments e JOIN users u ON u.id = e.user_id WHERE e.course_id = ? ORDER BY u.name ASC');
  $st->execute([$course_id]);
  $students = $st->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Course Messages - <?php echo htmlspecialchars($course['title']); ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <main>
    <div class="container">
      <div class="card fade-in" style="margin-bottom:16px;">
        <div class="card-header">
          <h2>📫 Messages — <?php echo htmlspecialchars($course['title']); ?></h2>
        </div>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 2fr; gap: 16px; align-items:start;">
        <div>
          <div class="card">
            <div class="card-header">
              <h3><?php echo ($role==='student') ? 'Instructor' : 'Students'; ?></h3>
            </div>
            <div class="card-body">
              <?php if ($role==='student'): ?>
                <div style="display:flex; align-items:center; gap:8px;">
                  <div style="font-weight:600;">👨‍🏫 <?php echo htmlspecialchars($course['instructor_name'] ?: 'Instructor'); ?></div>
                </div>
              <?php else: ?>
                <?php if (empty($students)): ?>
                  <p class="muted">No enrolled students yet.</p>
                <?php else: ?>
                  <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:6px;">
                    <?php foreach ($students as $stu): ?>
                      <li>
                        <a class="btn light" href="/course_messages.php?course_id=<?php echo $course_id; ?>&user_id=<?php echo (int)$stu['id']; ?>" style="display:flex; justify-content:space-between;">
                          <span>👤 <?php echo htmlspecialchars($stu['name']); ?></span>
                          <?php if ($peer_user_id == (int)$stu['id']): ?><span class="badge success">Selected</span><?php endif; ?>
                        </a>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          <div style="margin-top:16px;">
            <a href="/course.php?id=<?php echo $course_id; ?>" class="btn light">← Back to course</a>
          </div>
        </div>

        <div>
          <div class="card">
            <div class="card-header">
              <h3>Conversation with <?php echo htmlspecialchars($peer_name ?: ''); ?></h3>
            </div>
            <div class="card-body" id="messages-scroll" style="max-height: 460px; overflow:auto; display:flex; flex-direction:column; gap:10px;">
              <?php if ($peer_user_id <= 0): ?>
                <p class="muted">Select a student to start messaging.</p>
              <?php elseif (empty($messages)): ?>
                <p class="muted">No messages yet. Say hello!</p>
              <?php else: ?>
                <?php foreach ($messages as $m): ?>
                  <?php $mine = ((int)$m['sender_id'] === (int)$user['id']); ?>
                  <div style="align-self: <?php echo $mine ? 'flex-end' : 'flex-start'; ?>; max-width: 80%;">
                    <div style="padding:10px 12px; border-radius: 12px; background: <?php echo $mine ? 'var(--primary-color)' : 'var(--bg-secondary)'; ?>; color: <?php echo $mine ? '#fff' : 'var(--text-primary)'; ?>;">
                      <div style="font-size:13px; opacity:0.9; margin-bottom:4px;">
                        <?php echo htmlspecialchars($m['sender_name']); ?> • <?php echo htmlspecialchars($m['created_at']); ?>
                      </div>
                      <div><?php echo nl2br(htmlspecialchars($m['body'])); ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="card-footer">
              <form id="composer" method="post" action="/course_messages.php?course_id=<?php echo $course_id . ($role!=='student' && $peer_user_id?('&user_id='.$peer_user_id):''); ?>" style="display:flex; flex-direction: column; gap: 8px; align-items: stretch; width: 100%;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <textarea id="message-body" name="body" rows="5" placeholder="Write a message… (Enter to send, Shift+Enter for newline)" style="width:100%; box-sizing:border-box; padding:12px; resize: vertical; min-height:120px; font-size:15px;" required></textarea>
                <button class="btn primary" type="submit" style="align-self:flex-end;">➤ Send</button>
              </form>
              <script>
                (function(){
                  const scroller = document.getElementById('messages-scroll');
                  if (scroller) { scroller.scrollTop = scroller.scrollHeight; }
                  const textarea = document.getElementById('message-body');
                  if (textarea) {
                    textarea.addEventListener('keydown', function(e){
                      if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        const form = document.getElementById('composer');
                        if (form && textarea.value.trim().length > 0) { form.submit(); }
                      }
                    });
                  }
                })();
              </script>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
