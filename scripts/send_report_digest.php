<?php
// Cronable script to send a digest of open reports to moderators and instructors
chdir(__DIR__ . '/../');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/email_templates.php';
require_once __DIR__ . '/../includes/mailer.php';

$cfg = include __DIR__ . '/../includes/config.php';

// fetch open or claimed reports (not dismissed/resolved)
$stmt = $pdo->prepare("SELECT r.*, u.name as reporter_name, c.title as course_title, p.post_id FROM reports r JOIN users u ON r.reporter_id = u.id LEFT JOIN courses c ON r.course_id = c.id LEFT JOIN forum_replies p ON (r.entity_type='reply' AND r.entity_id=p.id) WHERE r.status IN ('open','claimed') ORDER BY r.created_at DESC LIMIT 200");
$stmt->execute();
$reports = $stmt->fetchAll();

if (!$reports) {
    echo "No open reports.\n";
    exit(0);
}

$templates = report_digest_templates($reports);

// send to moderators
if (!empty($cfg['moderator_emails']) && is_array($cfg['moderator_emails'])) {
    send_notification($cfg['moderator_emails'], $templates['subject'], ['html'=>$templates['html'],'text'=>$templates['text']]);
    echo "Digest sent to moderators.\n";
}

// per-instructor digests: group reports by course -> instructor
$byCourse = [];
foreach ($reports as $r) {
    if (empty($r['course_id'])) continue;
    $byCourse[$r['course_id']][] = $r;
}

foreach ($byCourse as $course_id => $rpts) {
    // find instructor email and preference
    $q = $pdo->prepare('SELECT u.email, u.notify_on_report FROM courses c JOIN users u ON c.instructor_id = u.id WHERE c.id = ? LIMIT 1');
    $q->execute([$course_id]);
    $row = $q->fetch();
    if (!$row) continue;
    if (empty($row['email']) || (isset($row['notify_on_report']) && !$row['notify_on_report'])) continue;
    $t = report_digest_templates($rpts);
    send_notification([$row['email']], $t['subject'], ['html'=>$t['html'],'text'=>$t['text']]);
    echo "Digest sent to instructor for course {$course_id}\n";
}

// record audit log entry for digest send
$a = $pdo->prepare('INSERT INTO audit_log (actor_id,action,notes) VALUES (?,?,?)');
$a->execute([0,'send_digest','Digest sent via cron']);

echo "Done.\n";

?>
