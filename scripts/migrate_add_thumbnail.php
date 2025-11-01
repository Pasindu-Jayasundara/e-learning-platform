<?php
// Idempotent migration to add thumbnail_url to courses
// Usage: php scripts/migrate_add_thumbnail.php

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->query("SELECT thumbnail_url FROM courses LIMIT 1");
    echo "• courses.thumbnail_url already exists\n";
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE courses ADD COLUMN thumbnail_url VARCHAR(500) NULL AFTER description");
        echo "✔ Added courses.thumbnail_url\n";
    } catch (PDOException $e2) {
        echo "! Failed to add thumbnail_url: " . $e2->getMessage() . "\n";
    }
}

echo "\n✅ Migration complete.\n";
