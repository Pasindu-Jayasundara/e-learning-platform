<?php
/**
 * Idempotent migration to add course listing fee tracking and payment purpose.
 */
require_once __DIR__ . '/../includes/db.php';

function column_exists(PDO $pdo, string $table, string $column): bool {
    $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() > 0;
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
        echo "Added column $column to $table\n";
    } else {
        echo "Column $column already exists on $table\n";
    }
}

function add_index_if_missing(PDO $pdo, string $table, string $column): void {
    $sql = "SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $column]);
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE `$table` ADD KEY (`$column`)");
        echo "Added index on $table($column)\n";
    } else {
        echo "Index on $table($column) already exists\n";
    }
}

try {
    // courses: listing fee flags
    add_column_if_missing($pdo, 'courses', 'listing_fee_paid', "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_published`");
    add_column_if_missing($pdo, 'courses', 'listing_fee_paid_at', "DATETIME NULL DEFAULT NULL AFTER `listing_fee_paid`");

    // payments: purpose to distinguish student vs instructor fees
    add_column_if_missing($pdo, 'payments', 'purpose', "VARCHAR(32) NULL DEFAULT 'enrollment' AFTER `status`");
    add_index_if_missing($pdo, 'payments', 'purpose');

    echo "\n✅ Course listing fee migration complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
