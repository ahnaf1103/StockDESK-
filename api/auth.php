<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Helper for UUID
function uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// CSRF check for mutations
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $action !== 'login') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch']);
        exit;
    }
}

// Get current user from session token
function getCurrentUser() {
    $token = $_COOKIE['sd_token'] ?? '';
    if (!$token) return null;

    $session = DB::fetch("SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()", [$token]);
    if (!$session) return null;

    $user = DB::fetch("SELECT id, name, email, role, color, avatar_url, permissions, linked_google, linked_facebook FROM users WHERE id = ?", [$session['user_id']]);
    if ($user) {
        $user['permissions'] = json_decode($user['permissions'], true);
    }
    return $user;
}

switch ($action) {
    case 'login':
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // For demo purposes, if user doesn't exist, we might create them or check hardcoded
        // In real app, check password_verify
        $user = DB::fetch("SELECT * FROM users WHERE email = ?", [$email]);
        error_log("Login attempt for email: " . $email);
        if (!$user) error_log("User not found in DB");
        
        // Demo logic: if no users exist, create admin
        if (!$user && $email === 'admin@stockdesk.app') {
            $id = uuid();
            $perms = json_encode(['addItem'=>true, 'editItem'=>true, 'deleteItem'=>true, 'clearHistory'=>true, 'manageUsers'=>true, 'emptyTrash'=>true]);
            DB::query("INSERT INTO users (id, name, email, pass_hash, role, permissions) VALUES (?, ?, ?, ?, ?, ?)", 
                [$id, 'Admin', $email, password_hash('admin', PASSWORD_DEFAULT), 'Super Admin', $perms]);
            $user = DB::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        }

        if ($user && password_verify($password, $user['pass_hash'])) {
            error_log("Password verified for user: " . $user['id']);
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            DB::query("INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, ?)", [$token, $user['id'], $expires]);
            
            setcookie('sd_token', $token, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            // Set CSRF token in session
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));

            unset($user['pass_hash']);
            $user['permissions'] = json_decode($user['permissions'], true);
            echo json_encode(['ok' => true, 'data' => $user, 'csrf' => $_SESSION['csrf_token']]);
        } else {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
        }
        break;

    case 'logout':
        $token = $_COOKIE['sd_token'] ?? '';
        if ($token) {
            DB::query("DELETE FROM sessions WHERE token = ?", [$token]);
            setcookie('sd_token', '', time() - 3600, '/');
        }
        session_destroy();
        echo json_encode(['ok' => true]);
        break;

    case 'me':
        $user = getCurrentUser();
        if ($user) {
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            }
            echo json_encode(['ok' => true, 'data' => $user, 'csrf' => $_SESSION['csrf_token']]);
        } else {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        }
        break;

    case 'verifyPin':
        $data = json_decode(file_get_contents('php://input'), true);
        $pin = $data['pin'] ?? '';
        // Specification says PIN is '1234' server-side
        if ($pin === '1234') {
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Invalid PIN']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        break;
}
?>
