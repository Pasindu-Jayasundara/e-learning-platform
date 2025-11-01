<?php
// Simple email template helpers using localization
function t($key, $vars = []) {
    $lang = include __DIR__ . '/lang/en.php';
    $s = $lang[$key] ?? $key;
    foreach ($vars as $k => $v) { $s = str_replace('{' . $k . '}', $v, $s); }
    return $s;
}

function report_post_templates($post_id, $reporter, $reason) {
    $subject = t('email.report.post.subject', ['id' => $post_id]);
    $link = (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '') . "/post_forum.php?post_id={$post_id}";
    $intro = t('email.report.intro', ['reporter_name' => $reporter['name'], 'reporter_id' => $reporter['id']]);
    $reasonLine = t('email.report.reason', ['reason' => $reason]);
    $view = t('email.report.view_link', ['link' => $link]);

    $html = "<p>" . htmlspecialchars($intro) . "</p>";
    $html .= "<p><strong>" . htmlspecialchars($reasonLine) . "</strong></p>";
    $html .= "<p><a href=\"{$link}\">View post</a></p>";

    $text = $intro . "\n\n" . $reasonLine . "\n\n" . $link;

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function report_reply_templates($reply_id, $reporter, $reason, $post_id=null) {
    $subject = t('email.report.reply.subject', ['id' => $reply_id]);
    $link = (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '') . "/post_forum.php?post_id=" . ($post_id ?: '');
    $intro = t('email.report.intro', ['reporter_name' => $reporter['name'], 'reporter_id' => $reporter['id']]);
    $reasonLine = t('email.report.reason', ['reason' => $reason]);

    $html = "<p>" . htmlspecialchars($intro) . "</p>";
    $html .= "<p><strong>" . htmlspecialchars($reasonLine) . "</strong></p>";
    $html .= "<p><a href=\"{$link}\">View thread</a></p>";

    $text = $intro . "\n\n" . $reasonLine . "\n\n" . $link;

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

function report_digest_templates($reports) {
    // $reports: array of report rows with reporter_name, course_title, entity_type, entity_id, reason, id
    $subject = t('email.report.digest.subject');
    $html = "<h3>Open Reports Digest</h3>";
    $html .= "<ul>";
    $text = "Open Reports Digest\n\n";
    foreach ($reports as $r) {
        $link = (isset($_SERVER['HTTP_HOST']) ? ('https://' . $_SERVER['HTTP_HOST']) : '') . "/post_forum.php?post_id=" . ($r['entity_type']==='post' ? $r['entity_id'] : ($r['entity_type']==='reply' ? ($r['post_id'] ?? '') : ''));
        $html .= "<li>Report #".htmlspecialchars($r['id'])." - ".htmlspecialchars($r['entity_type'])." #".htmlspecialchars($r['entity_id'])." (".htmlspecialchars($r['course_title']??'').") - " . htmlspecialchars($r['reason']) . " - <a href=\"{$link}\">View</a></li>";
        $text .= "Report #{$r['id']} - {$r['entity_type']} #{$r['entity_id']} ({$r['course_title']}) - {$r['reason']} - {$link}\n";
    }
    $html .= "</ul>";

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}
