<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_login();
$user = current_user();

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf'] ?? '')) {
        set_flash('error', 'Invalid CSRF token.');
        header('Location: /compose.php'); exit;
    }

    $recipient_email = trim($_POST['recipient_email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($recipient_email === '' || $body === '') {
        set_flash('error', 'Recipient email and message body are required.');
        header('Location: /compose.php'); exit;
    }

    // find recipient by email
    $stmt = $db->prepare('SELECT id, email, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$recipient_email]);
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$recipient) {
        set_flash('error', 'Recipient not found.');
        header('Location: /compose.php'); exit;
    }

    $ins = $db->prepare('INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)');
    $ok = $ins->execute([$user['id'], $recipient['id'], $subject, $body]);
    if ($ok) {
        // notify recipient (best-effort)
        $mailBody = [
            'text' => "You have a new message from {$user['name']} ({$user['email']}):\n\n" . strip_tags($body),
            'html' => "<p>You have a new message from <strong>" . htmlspecialchars($user['name']) . "</strong> (" . htmlspecialchars($user['email']) . ")</p><hr>" . nl2br(htmlspecialchars($body))
        ];
        @send_notification([$recipient['email']], 'New message on the platform: ' . ($subject ?: '(no subject)'), $mailBody);
        set_flash('success', 'Message sent.');
        header('Location: /messages.php'); exit;
    } else {
        set_flash('error', 'Failed to send message.');
        header('Location: /compose.php'); exit;
    }
}

$csrf = generate_csrf_token();
// prefill when replying
$prefill_recipient = '';
if (!empty($_GET['reply_to'])) {
    $rid = (int)$_GET['reply_to'];
    if ($rid > 0) {
        $q = $db->prepare('SELECT m.*, u.email as sender_email FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.id = ? LIMIT 1');
        $q->execute([$rid]);
        if ($orig = $q->fetch(PDO::FETCH_ASSOC)) {
            // reply to sender
            $prefill_recipient = $orig['sender_email'];
            // prefill subject for convenience
            $_POST['subject'] = 'Re: ' . ($orig['subject'] ?: '(no subject)');
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Compose Message</title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>.wide{width:100%;max-width:800px}</style>
</head>
<body>
  <?php require_once __DIR__ . '/../includes/topnav.php'; ?>
  <h1>Compose Message</h1>
  <?php if ($msg = get_flash('error')): ?><div style="color:red"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
  <?php if ($msg = get_flash('success')): ?><div style="color:green"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

    <form method="post" class="wide" action="/compose.php">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <label>Recipient<br>
            <input id="recipient_input" name="recipient_email" type="email" required value="<?php echo htmlspecialchars($prefill_recipient); ?>" placeholder="Start typing name or email...">
            <div id="recipient_suggestions" style="border:1px solid #ccc;display:none;position:relative;background:#fff;max-height:200px;overflow:auto"></div>
        </label><br>
        <label>Subject<br><input name="subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"></label><br>
        <label>Message<br><textarea name="body" rows="10" required><?php echo htmlspecialchars($_POST['body'] ?? ''); ?></textarea></label><br>
        <button type="submit">Send</button>
    </form>

  <p><a href="/messages.php">Back to inbox</a></p>
    <script>
    (function(){
        const input = document.getElementById('recipient_input');
        const box = document.getElementById('recipient_suggestions');
        let timer = null;
        input.addEventListener('input', function(){
            const q = input.value.trim();
            if (timer) clearTimeout(timer);
            if (q.length < 2) { box.style.display='none'; return; }
            timer = setTimeout(()=>{
                fetch('/user_search.php?q=' + encodeURIComponent(q))
                    .then(r=>r.json())
                    .then(list=>{
                        box.innerHTML='';
                        if (!list.length) { box.style.display='none'; return; }
                        list.forEach(u=>{
                            const el = document.createElement('div');
                            el.style.padding='6px'; el.style.cursor='pointer';
                            el.textContent = (u.name ? u.name + ' <' + u.email + '>' : u.email);
                            el.addEventListener('click', ()=>{ input.value = u.email; box.style.display='none'; });
                            box.appendChild(el);
                        });
                        box.style.display = 'block';
                    })
                    .catch(()=>{ box.style.display='none'; });
            }, 250);
        });
        document.addEventListener('click', function(e){ if (!box.contains(e.target) && e.target !== input) box.style.display='none'; });
    })();
    </script>
</body>
</html>
