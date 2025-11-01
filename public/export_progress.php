<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();

// Ensure CSV output isn't polluted by PHP warnings/notices
@ini_set('display_errors', '0');
@error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// If instructor + course_id provided, export that course's students (only if instructor owns it)
$course_id = intval($_GET['course_id'] ?? 0);

// Fetch rows depending on role
$rows = [];
$context = 'student'; // 'student' or 'course'
if ($user['role'] === 'student') {
    $stmt = $pdo->prepare('SELECT e.user_id, u.name as student_name, c.id as course_id, c.title as course_title, c.instructor_id, e.progress, e.enrolled_at, iu.name as instructor_name
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           JOIN users u ON e.user_id = u.id
                           LEFT JOIN users iu ON c.instructor_id = iu.id
                           WHERE e.user_id = ?');
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();
    $context = 'student';
} elseif (in_array($user['role'], ['instructor','admin']) && $course_id) {
    if ($user['role'] === 'instructor') {
        // verify ownership
        $stmt = $pdo->prepare('SELECT id FROM courses WHERE id = ? AND instructor_id = ? LIMIT 1');
        $stmt->execute([$course_id, $user['id']]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
    $stmt = $pdo->prepare('SELECT e.user_id, u.name as student_name, c.id as course_id, c.title as course_title, c.instructor_id, e.progress, e.enrolled_at, iu.name as instructor_name
                           FROM enrollments e
                           JOIN courses c ON e.course_id = c.id
                           JOIN users u ON e.user_id = u.id
                           LEFT JOIN users iu ON c.instructor_id = iu.id
                           WHERE e.course_id = ?');
    $stmt->execute([$course_id]);
    $rows = $stmt->fetchAll();
    $context = 'course';
} else {
    // default: student without rows or instructor without course_id
    set_flash('error','Nothing to export');
    header('Location: /dashboard.php');
    exit;
}

// Compute lesson-based progress consistently with UI/certificate gating
$computedProgress = []; // key depends on context
if (!empty($rows)) {
    if ($context === 'student') {
        $courseIds = array_values(array_unique(array_map(function($r){ return (int)$r['course_id']; }, $rows)));
        if (!empty($courseIds)) {
            $ph = implode(',', array_fill(0, count($courseIds), '?'));
            // lessons per course
            $st = $pdo->prepare("SELECT course_id, COUNT(*) as cnt FROM lessons WHERE course_id IN ($ph) GROUP BY course_id");
            $st->execute($courseIds);
            $lessonCount = [];
            foreach ($st->fetchAll() as $lc) { $lessonCount[(int)$lc['course_id']] = (int)$lc['cnt']; }
            // completed per course for this user
            $st2 = $pdo->prepare("SELECT course_id, COUNT(*) as cnt FROM lesson_progress WHERE user_id = ? AND status = 'completed' AND course_id IN ($ph) GROUP BY course_id");
            $st2->execute(array_merge([$user['id']], $courseIds));
            $completedCount = [];
            foreach ($st2->fetchAll() as $cc) { $completedCount[(int)$cc['course_id']] = (int)$cc['cnt']; }
            // compute
            foreach ($rows as $r) {
                $cid = (int)$r['course_id'];
                $total = $lessonCount[$cid] ?? 0;
                if ($total > 0) {
                    $done = $completedCount[$cid] ?? 0;
                    $computedProgress[$cid] = (int)floor(($done * 100) / $total);
                }
            }
        }
    } else { // context === 'course'
        $cid = (int)$rows[0]['course_id'];
        // lesson total for the course
        $st = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?');
        $st->execute([$cid]);
        $lessonTotal = (int)$st->fetchColumn();
        if ($lessonTotal > 0) {
            // completed lessons per user
            $st2 = $pdo->prepare("SELECT user_id, COUNT(*) as cnt FROM lesson_progress WHERE course_id = ? AND status = 'completed' GROUP BY user_id");
            $st2->execute([$cid]);
            $completedByUser = [];
            foreach ($st2->fetchAll() as $row) { $completedByUser[(int)$row['user_id']] = (int)$row['cnt']; }
            foreach ($rows as $r) {
                $uid = (int)$r['user_id'];
                $done = $completedByUser[$uid] ?? 0;
                $computedProgress[$uid] = (int)floor(($done * 100) / $lessonTotal);
            }
        }
    }
}

// output CSV
// Clear any active output buffers to avoid stray bytes before headers
if (function_exists('ob_get_level')) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
}

$filename = 'progress_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Pragma: no-cache');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Expires: 0');
// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'wb');
// Excel delimiter hint to avoid locale issues
fwrite($out, "sep=,\r\n");
// header
fputcsv($out, ['user_id','student_name','course_title','instructor_name','progress_percent','enrolled_at'], ',', '"', '\\', "\r\n");
foreach ($rows as $r) {
    // prefer lesson-based progress when available
    if ($context === 'student') {
        $cid = (int)$r['course_id'];
        $pct = isset($computedProgress[$cid]) ? $computedProgress[$cid] : (int)$r['progress'];
    } else {
        $uid = (int)$r['user_id'];
        $pct = isset($computedProgress[$uid]) ? $computedProgress[$uid] : (int)$r['progress'];
    }
    fputcsv($out, [
        $r['user_id'],
        $r['student_name'],
        $r['course_title'],
        $r['instructor_name'] ?? '',
        $pct,
        $r['enrolled_at'],
    ], ',', '"', '\\', "\r\n");
}
fclose($out);
exit;
