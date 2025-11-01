<?php
// Usage (PowerShell):
// php .\\scripts\\create_admin.php --email=admin@example.com --password=admin123 --name=Admin

require_once __DIR__ . '/../includes/db.php';

// Parse CLI args
$email = 'admin@example.com';
$passwordPlain = 'admin123';
$name = 'Admin';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--email=')) $email = substr($arg, 8);
    if (str_starts_with($arg, '--password=')) $passwordPlain = substr($arg, 11);
    if (str_starts_with($arg, '--name=')) $name = substr($arg, 7);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email provided\n");
    exit(1);
}
if (strlen($passwordPlain) < 6) {
    fwrite(STDERR, "Password must be at least 6 characters\n");
    exit(1);
}

try {
    $pdo->beginTransaction();

    // Check if user exists
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);

    if ($existing) {
        $id = $existing['id'];
        // Promote to admin and optionally update password and name
        $upd = $pdo->prepare('UPDATE users SET name = ?, password = ?, role = ? WHERE id = ?');
        $upd->execute([$name, $passwordHash, 'admin', $id]);
        $pdo->commit();
        echo "\n✅ Updated existing user to admin.\n";
    } else {
        $ins = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)');
        $ins->execute([$name, $email, $passwordHash, 'admin']);
        $pdo->commit();
        echo "\n✅ Created new admin user.\n";
    }

    echo "\nLogin credentials:\n";
    echo "  Email:    {$email}\n";
    echo "  Password: {$passwordPlain}\n";
    echo "\nYou can change these later from the UI.\n";

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "❌ Error: " . $e->getMessage() . "\n");
    exit(1);
}
