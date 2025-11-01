<?php
// Idempotent migration for course_messages (student <-> instructor per-course messaging)
// Usage: php scripts/migrate_course_messages.php
require_once __DIR__ . '/../includes/db.php';

function execSafe(PDO $pdo, string $sql, string $label) {
    try { $pdo->exec($sql); echo "✔ $label\n"; }
    catch (PDOException $e) {
        $m = $e->getMessage();
        if (stripos($m, 'exists') !== false || stripos($m, 'Duplicate') !== false || stripos($m, 'already') !== false) {
            echo "• $label (already)\n";
        } else {
            echo "! $label FAILED: $m\n";
        }
    }
}

execSafe($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS course_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  sender_id INT NOT NULL,
  recipient_id INT NOT NULL,
  body TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cm_course (course_id),
  INDEX idx_cm_sender (sender_id),
  INDEX idx_cm_recipient (recipient_id),
  CONSTRAINT fk_cm_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_recipient FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL, 'Create course_messages table');

echo "\n✅ Course messages migration complete.\n";
