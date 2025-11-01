<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$like = '%' . str_replace('%','\\%',$q) . '%';
$stmt = $db->prepare('SELECT id, name, email FROM users WHERE (name LIKE ? OR email LIKE ?) AND id != ? LIMIT 20');
$stmt->execute([$like, $like, current_user()['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);
