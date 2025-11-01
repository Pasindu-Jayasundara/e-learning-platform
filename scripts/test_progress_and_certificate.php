<?php
// Quick sanity test for lessons progress and certificate gating logic
// Usage: php scripts/test_progress_and_certificate.php

require_once __DIR__ . '/../includes/db.php';

echo "=== Progress Tracking & Certificate Gating Test ===\n\n";

function ensure_user(PDO $pdo, string $email, string $name = 'Test Student') : int {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $hash = password_hash('student123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'student')");
    $stmt->execute([$name, $email, $hash]);
    return (int)$pdo->lastInsertId();
}

function ensure_course(PDO $pdo, string $title, int $instructorId = null) : int {
    $stmt = $pdo->prepare('SELECT id FROM courses WHERE title = ? LIMIT 1');
    $stmt->execute([$title]);
    $id = $stmt->fetchColumn();
    if ($id) return (int)$id;
    $stmt = $pdo->prepare('INSERT INTO courses (title, description, price, is_published, instructor_id) VALUES (?, ?, 0.00, 1, ?)');
    $stmt->execute([$title, 'Test course for progress tracking', $instructorId]);
    return (int)$pdo->lastInsertId();
}

function ensure_lessons(PDO $pdo, int $courseId, int $count = 3) : array {
    $stmt = $pdo->prepare('SELECT id FROM lessons WHERE course_id = ? ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$courseId]);
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($existing && count($existing) >= $count) return array_map('intval', $existing);

    $ins = $pdo->prepare('INSERT INTO lessons (course_id, title, description, video_url, duration_seconds, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
    $ids = [];
    for ($i=1; $i<=$count; $i++) {
        $ins->execute([$courseId, "Test Lesson $i", null, 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4', 120, $i]);
        $ids[] = (int)$pdo->lastInsertId();
    }
    return $ids;
}

function ensure_enrollment(PDO $pdo, int $userId, int $courseId) : void {
    $stmt = $pdo->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$userId, $courseId]);
    if ($stmt->fetchColumn()) return;
    $stmt = $pdo->prepare('INSERT INTO enrollments (user_id, course_id, progress, enrolled_at) VALUES (?,?,0,NOW())');
    $stmt->execute([$userId, $courseId]);
}

function compute_progress(PDO $pdo, int $userId, int $courseId) : int {
    // If lessons exist, compute from lesson_progress; else fallback to enrollments.progress
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?');
    $stmt->execute([$courseId]);
    $total = (int)$stmt->fetchColumn();
    if ($total > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND course_id = ? AND status = 'completed'");
        $stmt->execute([$userId, $courseId]);
        $completed = (int)$stmt->fetchColumn();
        return $total > 0 ? (int)floor(($completed * 100) / $total) : 0;
    }
    $stmt = $pdo->prepare('SELECT progress FROM enrollments WHERE user_id = ? AND course_id = ? LIMIT 1');
    $stmt->execute([$userId, $courseId]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function set_progress_bar(PDO $pdo, int $userId, int $courseId, int $percent) : void {
    $stmt = $pdo->prepare('UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?');
    $stmt->execute([$percent, $userId, $courseId]);
}

function mark_completed(PDO $pdo, int $userId, int $courseId, int $lessonId) : void {
    $stmt = $pdo->prepare("INSERT INTO lesson_progress (user_id,course_id,lesson_id,status,watched_seconds,last_watched_at) VALUES (?,?,?,?,0,NOW()) ON DUPLICATE KEY UPDATE status='completed', last_watched_at=NOW()");
    $stmt->execute([$userId, $courseId, $lessonId, 'completed']);
}

// Arrange
$studentId = ensure_user($pdo, 'test.student@example.com');
$courseId  = ensure_course($pdo, 'Test Course — Progress & Cert');
$lessons   = ensure_lessons($pdo, $courseId, 3);
ensure_enrollment($pdo, $studentId, $courseId);

// Reset any prior progress for a clean test
$pdo->prepare('DELETE FROM lesson_progress WHERE user_id = ? AND course_id = ?')->execute([$studentId, $courseId]);
set_progress_bar($pdo, $studentId, $courseId, 0);

// Assert initial state
$p0 = compute_progress($pdo, $studentId, $courseId);
$ok0 = ($p0 === 0);
printf("Initial progress: %d%% — %s\n", $p0, $ok0 ? 'OK' : 'FAIL');

// Complete first lesson
mark_completed($pdo, $studentId, $courseId, $lessons[0]);
$p1 = compute_progress($pdo, $studentId, $courseId);
set_progress_bar($pdo, $studentId, $courseId, $p1);
$ok1 = ($p1 === 33 || $p1 === 34); // floor may vary with division
printf("After 1/3 lessons: %d%% — %s\n", $p1, $ok1 ? 'OK' : 'CHECK');

// Complete second
mark_completed($pdo, $studentId, $courseId, $lessons[1]);
$p2 = compute_progress($pdo, $studentId, $courseId);
set_progress_bar($pdo, $studentId, $courseId, $p2);
$ok2 = ($p2 === 66 || $p2 === 67);
printf("After 2/3 lessons: %d%% — %s\n", $p2, $ok2 ? 'OK' : 'CHECK');

// Complete third
mark_completed($pdo, $studentId, $courseId, $lessons[2]);
$p3 = compute_progress($pdo, $studentId, $courseId);
set_progress_bar($pdo, $studentId, $courseId, $p3);
$ok3 = ($p3 === 100);
printf("After 3/3 lessons: %d%% — %s\n", $p3, $ok3 ? 'OK' : 'FAIL');

// Gate check (expected behavior of certificate page)
$gateBefore = ($p0 >= 100) ? 'ALLOW' : 'DENY';
$gateAfter  = ($p3 >= 100) ? 'ALLOW' : 'DENY';

printf("Certificate gate before completion: %s (expected DENY)\n", $gateBefore);
printf("Certificate gate after completion:  %s (expected ALLOW)\n", $gateAfter);

$final = ($ok0 && $ok1 && $ok2 && $ok3 && $gateBefore==='DENY' && $gateAfter==='ALLOW');
echo "\nResult: " . ($final ? '✅ PASS' : '❌ FAIL') . "\n";
