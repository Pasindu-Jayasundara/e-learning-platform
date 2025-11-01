<?php
// Seed sample lessons (videos) for existing courses
// Usage: php scripts/seed_lessons.php
require_once __DIR__ . '/../includes/db.php';

echo "=== Seeding sample lessons ===\n";

// ensure lessons table exists
try {
    $pdo->query('SELECT COUNT(*) FROM lessons');
} catch (PDOException $e) {
    echo "Lessons table not found. Please run: php scripts/migrate_lessons_and_progress.php\n";
    exit(1);
}

// fetch some courses
$courses = $pdo->query('SELECT id, title FROM courses ORDER BY id ASC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
if (!$courses) {
    echo "No courses found to seed.\n";
    exit(0);
}

$sampleVideos = [
    [
        'title' => 'Welcome & Overview',
        'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        'duration_seconds' => 60*2 + 30,
    ],
    [
        'title' => 'Core Concepts',
        'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ElephantsDream.mp4',
        'duration_seconds' => 60*3 + 10,
    ],
    [
        'title' => 'Hands-on Demo',
        'video_url' => 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/Sintel.mp4',
        'duration_seconds' => 60*5 + 0,
    ],
];

$insert = $pdo->prepare('INSERT INTO lessons (course_id, title, description, video_url, duration_seconds, sort_order) VALUES (?, ?, ?, ?, ?, ?)');

$total = 0;
foreach ($courses as $c) {
    // skip if already has lessons
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lessons WHERE course_id = ?');
    $stmt->execute([$c['id']]);
    $has = (int)$stmt->fetchColumn();
    if ($has > 0) {
        echo "• Skipping '{$c['title']}' (already has $has lessons)\n";
        continue;
    }

    echo "+ Adding lessons to '{$c['title']}'\n";
    $order = 1;
    foreach ($sampleVideos as $sv) {
        $insert->execute([$c['id'], $sv['title'], null, $sv['video_url'], $sv['duration_seconds'], $order++]);
        $total++;
    }
}

echo "\n✅ Seeded $total lessons.\n";
