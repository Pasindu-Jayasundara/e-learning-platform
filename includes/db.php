<?php
$config = require __DIR__ . '/config.php';

$driver = $config['db_driver'] ?? 'mysql';

try {
    if ($driver === 'sqlsrv') {
        // Azure SQL (SQL Server) via PDO_SQLSRV
        $server = $config['db_server'] ?? 'tcp:localhost,1433';
        $dbName = $config['db_name_sqlsrv'] ?? ($config['db_name'] ?? '');
        $encrypt = isset($config['db_encrypt']) && $config['db_encrypt'] ? 'yes' : 'no';
        $trust = isset($config['db_trust_cert']) && $config['db_trust_cert'] ? 'yes' : 'no';
        $timeout = (int)($config['db_login_timeout'] ?? 30);

        $dsn = "sqlsrv:Server={$server};Database={$dbName};Encrypt={$encrypt};TrustServerCertificate={$trust};LoginTimeout={$timeout}";
        $user = $config['db_user_sqlsrv'] ?? $config['db_user'] ?? '';
        $pass = $config['db_pass_sqlsrv'] ?? $config['db_pass'] ?? '';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        // MySQL (default)
        $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
} catch (PDOException $e) {
    if (!empty($config['display_errors'])) {
        echo 'DB Connection failed: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}
