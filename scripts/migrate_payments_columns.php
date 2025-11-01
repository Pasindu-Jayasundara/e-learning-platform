<?php
/**
 * Idempotent migration to ensure payments table has Stripe columns & indexes.
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
    add_column_if_missing($pdo, 'payments', 'stripe_session_id', "`stripe_session_id` VARCHAR(255) NULL AFTER `status`");
    add_column_if_missing($pdo, 'payments', 'stripe_charge_id', "`stripe_charge_id` VARCHAR(255) NULL AFTER `stripe_session_id`");
    add_index_if_missing($pdo, 'payments', 'stripe_session_id');
    add_index_if_missing($pdo, 'payments', 'stripe_charge_id');

    add_column_if_missing($pdo, 'payments', 'stripe_payment_intent_id', "`stripe_payment_intent_id` VARCHAR(255) NULL AFTER `stripe_charge_id`");
    add_column_if_missing($pdo, 'payments', 'stripe_refund_id', "`stripe_refund_id` VARCHAR(255) NULL AFTER `stripe_payment_intent_id`");
    add_index_if_missing($pdo, 'payments', 'stripe_payment_intent_id');
    add_index_if_missing($pdo, 'payments', 'stripe_refund_id');

    echo "\n✅ Payments table migration complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
