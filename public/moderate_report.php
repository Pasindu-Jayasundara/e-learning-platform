<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['admin','instructor'])) { set_flash('error','Access denied'); header('Location: /dashboard.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /moderation.php'); exit; }

$csrf = $_POST['csrf'] ?? '';
if (!verify_csrf_token($csrf)) { set_flash('error','Invalid CSRF'); header('Location: /moderation.php'); exit; }

$report_id = intval($_POST['report_id'] ?? 0);
$action = $_POST['action'] ?? '';
$entity_id = intval($_POST['entity_id'] ?? 0);

if (!$report_id) { set_flash('error','Missing report'); header('Location: /moderation.php'); exit; }

// mark report handled helper
$mark_handled = function($pdo,$report_id,$user_id,$note='') {
    $q = $pdo->prepare('UPDATE reports SET status = ?, handled_by = ?, handled_at = NOW() WHERE id = ?');
    $q->execute(['actioned',$user_id,$report_id]);
};

// record audit log
$record_audit = function($pdo,$actor_id,$action,$entity_type=null,$entity_id=null,$report_id=null,$notes=null){
    $q = $pdo->prepare('INSERT INTO audit_log (actor_id,action,entity_type,entity_id,report_id,notes) VALUES (?,?,?,?,?,?)');
    $q->execute([$actor_id,$action,$entity_type,$entity_id,$report_id,$notes]);
};

// helper to check instructor owns the course for a given entity
$instructor_allowed = function($pdo,$user_id,$entity_type,$entity_id){
    if ($entity_type === 'post') {
        $q = $pdo->prepare('SELECT c.instructor_id FROM forum_posts p JOIN courses c ON p.course_id = c.id WHERE p.id = ? LIMIT 1');
        $q->execute([$entity_id]);
        $instr = $q->fetchColumn();
        return $instr && intval($instr) === intval($user_id);
    }
    if ($entity_type === 'reply') {
        $q = $pdo->prepare('SELECT c.instructor_id FROM forum_replies r JOIN forum_posts p ON r.post_id = p.id JOIN courses c ON p.course_id = c.id WHERE r.id = ? LIMIT 1');
        $q->execute([$entity_id]);
        $instr = $q->fetchColumn();
        return $instr && intval($instr) === intval($user_id);
    }
    return false;
};

// fetch report to know entity/course
$rstmt = $pdo->prepare('SELECT * FROM reports WHERE id = ? LIMIT 1');
$rstmt->execute([$report_id]);
$report = $rstmt->fetch();
if (!$report) { set_flash('error','Report not found'); header('Location: /moderation.php'); exit; }

// permission: admin OR instructor who owns the course (if report has course_id)
if ($user['role'] !== 'admin') {
    if (!$report['course_id'] || !$instructor_allowed($pdo,$user['id'],$report['entity_type'],$report['entity_id'])) {
        set_flash('error','Not allowed'); header('Location: /moderation.php'); exit;
    }
}

if ($action === 'dismiss') {
    $mark_handled($pdo,$report_id,$user['id']);
    $record_audit($pdo,$user['id'],'dismiss_report',$report['entity_type'],$report['entity_id'],$report_id,'dismissed by moderator');
    set_flash('success','Report dismissed');
    header('Location: /moderation.php'); exit;
}

if ($action === 'delete_post' && $entity_id) {
    // delete replies then post
    $pdo->prepare('DELETE FROM forum_replies WHERE post_id = ?')->execute([$entity_id]);
    $pdo->prepare('DELETE FROM forum_posts WHERE id = ?')->execute([$entity_id]);
    $mark_handled($pdo,$report_id,$user['id']);
    $record_audit($pdo,$user['id'],'delete_post','post',$entity_id,$report_id,'deleted via moderation');
    set_flash('success','Post deleted');
    header('Location: /moderation.php'); exit;
}

if ($action === 'delete_reply' && $entity_id) {
    $pdo->prepare('DELETE FROM forum_replies WHERE id = ?')->execute([$entity_id]);
    $mark_handled($pdo,$report_id,$user['id']);
    $record_audit($pdo,$user['id'],'delete_reply','reply',$entity_id,$report_id,'deleted via moderation');
    set_flash('success','Reply deleted');
    header('Location: /moderation.php'); exit;
}

set_flash('error','Unknown action');
header('Location: /moderation.php');
exit;
