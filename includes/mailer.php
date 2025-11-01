<?php
// Simple mailer wrapper: prefers PHPMailer (if installed), then mail(), then file-based fallback.
function send_notification(array $to, string $subject, $body) : bool {
    // $body may be string (text) or array with keys 'html' and/or 'text'
    if (empty($to)) return false;
    $cfg = include __DIR__ . '/config.php';
    // Normalize recipients
    $recipients = array_filter(array_map('trim', $to));
    if (empty($recipients)) return false;

    $html = null; $text = null;
    if (is_array($body)) {
        $html = $body['html'] ?? null;
        $text = $body['text'] ?? null;
    } else {
        $text = (string)$body;
    }

    // Try PHPMailer via Composer
    $autoload = __DIR__ . '/../vendor/autoload.php';
    $mailerError = null;
    if (file_exists($autoload)) {
        try {
            require_once $autoload;
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                // SMTP config if provided
                if (!empty($cfg['smtp']['host'])) {
                    $mail->isSMTP();
                    $mail->Host = $cfg['smtp']['host'];
                    $mail->Port = $cfg['smtp']['port'] ?? 587;
                    $mail->SMTPAuth = !empty($cfg['smtp']['username']);
                    if (!empty($cfg['smtp']['username'])) { $mail->Username = $cfg['smtp']['username']; }
                    if (!empty($cfg['smtp']['password'])) { $mail->Password = $cfg['smtp']['password']; }
                    if (!empty($cfg['smtp']['encryption'])) { $mail->SMTPSecure = $cfg['smtp']['encryption']; }
                }
                $mail->setFrom($cfg['mail_from'] ?? 'no-reply@localhost');
                foreach ($recipients as $r) { if (filter_var($r,FILTER_VALIDATE_EMAIL)) $mail->addAddress($r); }
                $mail->Subject = $subject;
                if ($html) {
                    $mail->isHTML(true);
                    $mail->Body = $html;
                    $mail->AltBody = $text ?? strip_tags($html);
                } else {
                    $mail->Body = $text ?? '';
                }
                $mail->send();
                return true;
            }
        } catch (Exception $e) {
            // capture error and fallthrough to other methods
            $mailerError = $e->getMessage();
        }
    }

    // Try PHP mail()
    $headers = 'From: ' . ($cfg['mail_from'] ?? 'no-reply@localhost') . "\r\n";
    if ($html) {
        // send as HTML
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $payload = $html;
    } else {
        $payload = $text ?? '';
    }
    foreach ($recipients as $r) {
        if (filter_var($r,FILTER_VALIDATE_EMAIL)) {
            $ok = @mail($r, $subject, $payload, $headers);
            if ($ok) return true;
        }
    }

    // File-based fallback: append to notification log
    $logPath = $cfg['notification_log_path'] ?? __DIR__ . '/../storage/notifications.log';
    $entry = "-----\nTo: " . implode(',', $recipients) . "\nSubject: $subject\nTime: " . date('c') . "\n";
    if ($mailerError) { $entry .= "MailerError: " . $mailerError . "\n"; }
    $entry .= "\n";
    if ($text) $entry .= "Text:\n" . $text . "\n\n";
    if ($html) $entry .= "HTML:\n" . $html . "\n\n";
    try {
        file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
