<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_login();
header('Content-Type: application/json');

$user = current_user();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function json_response($ok, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode(['ok' => $ok] + $data);
    exit;
}

if ($method === 'POST') {
    // CSRF check
    $csrf = $_POST['csrf'] ?? '';
    if (!verify_csrf_token($csrf)) {
        json_response(false, ['error' => 'Invalid CSRF token'], 400);
    }

    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    if ($lesson_id <= 0) {
        json_response(false, ['error' => 'Missing lesson_id'], 400);
    }

    // find lesson and course
    $stmt = $pdo->prepare('SELECT l.id, l.course_id FROM lessons l WHERE l.id = ? LIMIT 1');
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lesson) {
        json_response(false, ['error' => 'Lesson not found'], 404);
    }

    // ensure enrolled
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$user['id'], $lesson['course_id']]);
    $enroll = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$enroll) {
        json_response(false, ['error' => 'Not enrolled in this course'], 403);
    }

    // If position_seconds is provided, save progress (resume point)
    if (isset($_POST['position_seconds'])) {
        $pos = intval($_POST['position_seconds']);
        if ($pos < 0) { $pos = 0; }
        $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id, course_id, lesson_id, status, watched_seconds, last_watched_at) VALUES (?, ?, ?, 'in_progress', ?, NOW()) ON DUPLICATE KEY UPDATE status='in_progress', watched_seconds=VALUES(watched_seconds), last_watched_at=NOW()");
        $stmt->execute([$user['id'], $lesson['course_id'], $lesson['id'], $pos]);

        json_response(true, [
            'course_id' => (int)$lesson['course_id'],
            'lesson_id' => (int)$lesson['id'],
            'saved_seconds' => $pos,
        ]);
    }

    // Otherwise, mark lesson completed
    $stmt = $pdo->prepare('INSERT INTO lesson_progress (user_id, course_id, lesson_id, status, watched_seconds, last_watched_at) VALUES (?, ?, ?, \'completed\', 0, NOW()) ON DUPLICATE KEY UPDATE status=\'completed\', last_watched_at=NOW()');
    $stmt->execute([$user['id'], $lesson['course_id'], $lesson['id']]);

    // compute course progress
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM lessons WHERE course_id = ?');
    $stmt->execute([$lesson['course_id']]);
    $total = (int)$stmt->fetchColumn();

    $completed = 0;
    if ($total > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM lesson_progress WHERE user_id = ? AND course_id = ? AND status = 'completed'");
        $stmt->execute([$user['id'], $lesson['course_id']]);
        $completed = (int)$stmt->fetchColumn();
    }

    $percent = $total > 0 ? (int)floor(($completed * 100) / $total) : 0;

    // update enrollments.progress (best-effort)
    $stmt = $pdo->prepare('UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$percent, $user['id'], $lesson['course_id']]);

    json_response(true, [
        'course_id' => (int)$lesson['course_id'],
        'lesson_id' => (int)$lesson['id'],
        'progress_percent' => $percent,
        'completed_lessons' => $completed,
        'total_lessons' => $total,
    ]);
}

if ($method === 'GET') {
    $course_id = intval($_GET['course_id'] ?? 0);
    if ($course_id <= 0) {
        json_response(false, ['error' => 'Missing course_id'], 400);
    }

    // ensure enrollment
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$user['id'], $course_id]);
    if (!$stmt->fetch()) {
        json_response(false, ['error' => 'Not enrolled in this course'], 403);
    }

    // fetch lesson statuses
    $stmt = $pdo->prepare("SELECT l.id as lesson_id, COALESCE(lp.status, 'not_started') as status, COALESCE(lp.watched_seconds, 0) as watched_seconds FROM lessons l LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ? WHERE l.course_id = ? ORDER BY l.sort_order ASC, l.id ASC");
    $stmt->execute([$user['id'], $course_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // compute course progress
    $total = count($rows);
    $completed = 0;
    foreach ($rows as $r) {
        if ($r['status'] === 'completed') $completed++;
    }
    $percent = $total > 0 ? (int)floor(($completed * 100) / $total) : 0;

    json_response(true, [
        'lessons' => $rows,
        'progress_percent' => $percent,
        'completed_lessons' => $completed,
        'total_lessons' => $total,
    ]);
}

json_response(false, ['error' => 'Method not allowed'], 405);
