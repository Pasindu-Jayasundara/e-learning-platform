<?php
// Idempotent migration to add instructor request columns to users
// Usage: php scripts/migrate_instructor_request.php

require_once __DIR__ . '/../includes/db.php';

function addColumn(PDO $pdo, string $sql, string $label) {
    try { $pdo->exec($sql); echo "✔ $label\n"; }
    catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg,'Duplicate')!==false || stripos($msg,'exists')!==false || stripos($msg,'already')!==false) {
            echo "• $label (already)\n";
        } else { echo "! $label FAILED: $msg\n"; }
    }
}

try {
    $pdo->query("SELECT requested_instructor FROM users LIMIT 1");
    echo "• users.requested_instructor already exists\n";
} catch (PDOException $e) {
    addColumn($pdo, "ALTER TABLE users ADD COLUMN requested_instructor TINYINT(1) NOT NULL DEFAULT 0 AFTER role", 'Add users.requested_instructor');
}

try {
    $pdo->query("SELECT instructor_requested_at FROM users LIMIT 1");
    echo "• users.instructor_requested_at already exists\n";
} catch (PDOException $e) {
    addColumn($pdo, "ALTER TABLE users ADD COLUMN instructor_requested_at TIMESTAMP NULL DEFAULT NULL AFTER requested_instructor", 'Add users.instructor_requested_at');
}

try {
    $pdo->query("SELECT instructor_approved_at FROM users LIMIT 1");
    echo "• users.instructor_approved_at already exists\n";
} catch (PDOException $e) {
    addColumn($pdo, "ALTER TABLE users ADD COLUMN instructor_approved_at TIMESTAMP NULL DEFAULT NULL AFTER instructor_requested_at", 'Add users.instructor_approved_at');
}

echo "\n✅ Migration complete.\n";