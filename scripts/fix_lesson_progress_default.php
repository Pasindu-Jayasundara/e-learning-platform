<?php
// Adjust default of lesson_progress.status to 'not_started' if it's currently 'completed'
require_once __DIR__ . '/../includes/db.php';

try {
    $row = $pdo->query("SHOW COLUMNS FROM lesson_progress LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($row && isset($row['Default']) && $row['Default'] === 'completed') {
        $pdo->exec("ALTER TABLE lesson_progress MODIFY COLUMN status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started'");
        echo "✔ Updated lesson_progress.status default to 'not_started'\n";
    } else {
        echo "• lesson_progress.status default already OK\n";
    }
} catch (Throwable $e) {
    echo "! Could not update lesson_progress default: " . $e->getMessage() . "\n";
}
