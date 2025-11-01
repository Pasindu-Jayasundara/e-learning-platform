<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['admin','instructor'])) { set_flash('error','Access denied'); header('Location: /dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /notifications.php'); exit; }
$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /notifications.php'); exit; }

$report_id = intval($_POST['report_id'] ?? 0);
if (!$report_id) { set_flash('error','Missing report'); header('Location: /notifications.php'); exit; }

$rstmt = $pdo->prepare('SELECT * FROM reports WHERE id = ? LIMIT 1');
$rstmt->execute([$report_id]);
$report = $rstmt->fetch();
if (!$report) { set_flash('error','Report not found'); header('Location: /notifications.php'); exit; }

// only the claimer or admin can release
if ($user['role'] !== 'admin' && intval($report['claimed_by']) !== intval($user['id'])) { set_flash('error','Not allowed'); header('Location: /notifications.php'); exit; }

// release
$q = $pdo->prepare('UPDATE reports SET claimed_by = NULL, claimed_at = NULL, status = ? WHERE id = ?');
$q->execute(['open',$report_id]);

// audit
$q2 = $pdo->prepare('INSERT INTO audit_log (actor_id,action,entity_type,entity_id,report_id,notes) VALUES (?,?,?,?,?,?)');
$q2->execute([$user['id'],'release_report',$report['entity_type'],$report['entity_id'],$report_id,'released via mailbox']);

set_flash('success','Report released');
header('Location: /notifications.php');
exit;

?>
