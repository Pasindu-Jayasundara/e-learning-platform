<?php
session_start();
require_once __DIR__ . '/db.php';

function current_user() {
    if (isset($_SESSION['user'])) {
        // Keep session user minimal. Optionally refresh from DB.
        $u = $_SESSION['user'];
        if (!empty($u['id'])) {
            global $pdo;
            $stmt = $pdo->prepare('SELECT id,name,email,role FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$u['id']]);
            $fresh = $stmt->fetch();
            if ($fresh) {
                return $fresh;
            }
        }
        return $u;
    }
    return null;
}

function is_logged_in() {
    return current_user() !== null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(array $roles) {
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles)) {
        http_response_code(403);
        echo 'Forbidden - insufficient permissions';
        exit;
    }
}

function login_user($user) {
    // $user should be an associative array (id, name, email, role)
    // store minimal data
    $_SESSION['user'] = [
        'id' => $user['id'] ?? $user['ID'] ?? null,
        'name' => $user['name'] ?? $user['Name'] ?? '',
        'email' => $user['email'] ?? $user['EMAIL'] ?? '',
        'role' => $user['role'] ?? 'student',
    ];
}

function logout_user() {
    session_unset();
    session_destroy();
}

function find_user_by_email($email) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function register_user($name, $email, $password, $role = 'student') {
    global $pdo;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)');
    $stmt->execute([$name,$email,$hash,$role]);
    return $pdo->lastInsertId();
}

function verify_and_get_user($email, $password) {
    $user = find_user_by_email($email);
    if (!$user) return false;
    if (password_verify($password, $user['password'])) {
        // remove password before returning
        unset($user['password']);
        return $user;
    }
    return false;
}

// Flash messaging
function set_flash($key, $message) {
    if (!isset($_SESSION['_flash'])) $_SESSION['_flash'] = [];
    $_SESSION['_flash'][$key] = $message;
}

function get_flash($key) {
    if (isset($_SESSION['_flash']) && isset($_SESSION['_flash'][$key])) {
        $m = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $m;
    }
    return null;
}

// Simple CSRF token helpers for forms
function generate_csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf_token'];
}

function verify_csrf_token($token) {
    return !empty($token) && !empty($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}
