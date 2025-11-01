<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request.'); header('Location: /messages.php'); exit;
}

if (!verify_csrf_token($_POST['csrf'] ?? '')) {
    set_flash('error', 'Invalid CSRF token.'); header('Location: /messages.php'); exit;
}

$orig_id = (int)($_POST['orig_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
if ($orig_id <= 0 || $body === '') {
    set_flash('error', 'Missing data.'); header('Location: /messages.php'); exit;
}

$stmt = $db->prepare('SELECT * FROM messages WHERE id = ? LIMIT 1');
$stmt->execute([$orig_id]);
$orig = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orig) { set_flash('error','Original message not found.'); header('Location: /messages.php'); exit; }

// only sender or recipient may reply; recipient replies to sender, sender replies to recipient
if ($orig['recipient_id'] != $user['id'] && $orig['sender_id'] != $user['id']) {
    set_flash('error', 'Access denied.'); header('Location: /messages.php'); exit;
}

// determine recipient (reply to the other user)
$recipient_id = ($orig['sender_id'] == $user['id']) ? $orig['recipient_id'] : $orig['sender_id'];
$subject = 'Re: ' . ($orig['subject'] ?: '(no subject)');

$ins = $db->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)');
$ok = $ins->execute([$user['id'], $recipient_id, $subject, $body]);
if ($ok) {
    // notify by email if possible
    $rStmt = $db->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $rStmt->execute([$recipient_id]);
    $r = $rStmt->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['email'])) {
        @send_notification([$r['email']], 'New message reply: ' . $subject, [
            'text' => "You have a new reply from {$user['name']}:\n\n" . strip_tags($body),
            'html' => "<p>You have a new reply from <strong>" . htmlspecialchars($user['name']) . "</strong></p><hr>" . nl2br(htmlspecialchars($body))
        ]);
    }
    set_flash('success', 'Reply sent.'); header('Location: /messages.php'); exit;
} else {
    set_flash('error', 'Failed to send reply.'); header('Location: /messages.php'); exit;
}
