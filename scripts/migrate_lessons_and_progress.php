<?php
// Idempotent migration to add lessons and lesson_progress tables
// Usage: php scripts/migrate_lessons_and_progress.php

require_once __DIR__ . '/../includes/db.php';

function execSQL(PDO $pdo, string $sql, string $label) {
    try {
        $pdo->exec($sql);
        echo "✔ $label\n";
    } catch (PDOException $e) {
        // If duplicate/exists errors, still report as ok
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'exists') !== false || stripos($msg, 'already') !== false) {
            echo "• $label (already)\n";
        } else {
            echo "! $label FAILED: " . $msg . "\n";
        }
    }
}

// lessons table
execSQL($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  video_url VARCHAR(500) NOT NULL,
  duration_seconds INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lessons_course (course_id),
  INDEX idx_lessons_sort (sort_order),
  CONSTRAINT fk_lessons_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL, 'Create lessons table');

// lesson_progress table
execSQL($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS lesson_progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  course_id INT NOT NULL,
  lesson_id INT NOT NULL,
    status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  watched_seconds INT NOT NULL DEFAULT 0,
  last_watched_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_lesson (user_id, lesson_id),
  KEY idx_lp_user (user_id),
  KEY idx_lp_course (course_id),
  KEY idx_lp_lesson (lesson_id),
  CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL, 'Create lesson_progress table');

// Ensure enrollments.progress column exists (already in base schema)
try {
    $pdo->query("SELECT progress FROM enrollments LIMIT 1");
    echo "• enrollments.progress present\n";
} catch (PDOException $e) {
    // add column if missing
    execSQL($pdo, "ALTER TABLE enrollments ADD COLUMN progress INT NOT NULL DEFAULT 0", 'Add enrollments.progress');
}

echo "\n✅ Lessons and progress migration complete.\n";
