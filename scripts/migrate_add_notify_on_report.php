<?php
// Idempotent migration to add notify_on_report to users
// Usage: php scripts/migrate_add_notify_on_report.php

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->query("SELECT notify_on_report FROM users LIMIT 1");
    echo "• users.notify_on_report already exists\n";
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN notify_on_report TINYINT(1) NOT NULL DEFAULT 0 AFTER email");
        echo "✔ Added users.notify_on_report\n";
    } catch (PDOException $e2) {
        echo "! Failed to add notify_on_report: " . $e2->getMessage() . "\n";
    }
}

echo "\n✅ Migration complete.\n";
