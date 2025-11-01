<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'student') {
    $course_id = intval($_POST['course_id'] ?? 0);
    if ($course_id) {
        // check existing
        $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
        $stmt->execute([$user['id'],$course_id]);
        if ($stmt->fetch()) {
            header('Location: /dashboard.php');
            exit;
        }

        // check course price; if >0 redirect to checkout, else enroll directly
        $stmt = $pdo->prepare('SELECT id, price, is_published FROM courses WHERE id = ? LIMIT 1');
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        if ($course && !$course['is_published']) {
            // cannot enroll into unpublished course
            set_flash('error','Cannot enroll: course not published');
            header('Location: /dashboard.php');
            exit;
        }

        if ($course && floatval($course['price']) > 0.0) {
            // redirect to checkout flow
            header('Location: /checkout.php?course_id=' . $course_id);
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO enrollments (user_id,course_id,progress) VALUES (?,?,?)');
        $stmt->execute([$user['id'],$course_id,0]);

        // Send enrollment confirmation email for free courses
        try {
            require_once __DIR__ . '/../includes/mailer.php';
            // fetch course title
            $title = 'Course';
            try {
                $cstmt = $pdo->prepare('SELECT title FROM courses WHERE id = ? LIMIT 1');
                $cstmt->execute([$course_id]);
                $crow = $cstmt->fetch();
                if ($crow && !empty($crow['title'])) { $title = $crow['title']; }
            } catch (Throwable $ignore) {}
            $to = [$user['email']];
            $subject = "You're enrolled in {$title}";
            // Build absolute base URL
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $baseUrl = isset($_SERVER['HTTP_HOST']) ? ($scheme . $_SERVER['HTTP_HOST']) : '';
            $courseUrl = $baseUrl ? ($baseUrl . '/course.php?id=' . $course_id) : '/course.php?id=' . $course_id;
            $html = '<h2>Enrollment confirmed</h2>' .
                    '<p>Hi ' . htmlspecialchars($user['name']) . ',</p>' .
                    '<p>You have been enrolled in <strong>' . htmlspecialchars($title) . '</strong>.</p>' .
                    '<p><a href="' . htmlspecialchars($courseUrl) . '">Start learning</a></p>';
            $text = "Enrollment confirmed\n" .
                    "Course: {$title}\n" .
                    ($baseUrl ? ("Start: " . $courseUrl . "\n") : '');
            @send_notification($to, $subject, ['html' => $html, 'text' => $text]);
        } catch (Throwable $e) {
            // ignore mail errors for free enrollments
        }
    }
}
header('Location: /dashboard.php');
exit;
