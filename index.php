<?php
// Centralized helpers moved from bootstrap.php
// This file is safe to include from other scripts and also acts as the public index if accessed directly.
if (!defined('INVENT_INIT')) {
    define('INVENT_INIT', true);
    session_start();

    // Database filename (SQLite)
    define('DB_FILE', __DIR__ . '/db.sqlite');

    // Basic environment flags
    define('ENV', getenv('INVENT_ENV') ?: 'production');

    function get_db() {
        static $db = null;
        if ($db === null) {
            if (!file_exists(DB_FILE)) {
                // Create empty file; schema can be loaded from full_schema.sql
                touch(DB_FILE);
            }
            $db = new SQLite3(DB_FILE);
            $db->busyTimeout(5000);
            $db->exec('PRAGMA foreign_keys = ON;');
        }
        return $db;
    }

    // CSRF helpers
    function generate_csrf_token() {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    function verify_csrf_token($token) {
        return !empty($token) && !empty($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
    }

    // Flash messages
    function flash_set($key, $message) {
        $_SESSION['_flash'][$key] = $message;
    }
    function flash_get($key) {
        if (!empty($_SESSION['_flash'][$key])) {
            $m = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $m;
        }
        return null;
    }

    // Authentication helpers
    function current_user() {
        if (!empty($_SESSION['user_id'])) {
            return fetch_user_by_id($_SESSION['user_id']);
        }
        return null;
    }

    function require_login() {
        if (empty($_SESSION['user_id'])) {
            flash_set('error', 'Please log in to continue.');
            header('Location: /login.php');
            exit;
        }
    }

    function is_admin() {
        $u = current_user();
        if (!$u) return false;
        // Role can be stored as role_name or role_id; normalized schema uses roles.
        return !empty($u['role_name']) && $u['role_name'] === 'admin';
    }

    function require_admin() {
        if (!is_admin()) {
            http_response_code(403);
            echo "Access denied.";
            exit;
        }
    }

    // Sanitization + helpers for outputs
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Simple query helpers
    function fetch_user_by_id($id) {
        $db = get_db();
        $st = $db->prepare('SELECT u.*, r.name AS role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = :id LIMIT 1');
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        $res = $st->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        return $row ?: null;
    }

    function create_user($username, $email, $password, $role_id = null) {
        $db = get_db();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $db->prepare('INSERT INTO users (username, email, password_hash, role_id, created_at) VALUES (:username, :email, :hash, :role_id, :now)');
        $st->bindValue(':username', $username, SQLITE3_TEXT);
        $st->bindValue(':email', $email, SQLITE3_TEXT);
        $st->bindValue(':hash', $hash, SQLITE3_TEXT);
        $st->bindValue(':role_id', $role_id, SQLITE3_INTEGER);
        $st->bindValue(':now', date('c'), SQLITE3_TEXT);
        return $st->execute();
    }

    function update_user($id, $data = []) {
        $db = get_db();
        $fields = [];
        $params = [':id' => $id];
        if (isset($data['username'])) { $fields[] = 'username = :username'; $params[':username'] = $data['username']; }
        if (isset($data['email'])) { $fields[] = 'email = :email'; $params[':email'] = $data['email']; }
        if (!empty($data['password'])) { $fields[] = 'password_hash = :hash'; $params[':hash'] = password_hash($data['password'], PASSWORD_DEFAULT); }
        if (isset($data['role_id'])) { $fields[] = 'role_id = :role_id'; $params[':role_id'] = $data['role_id']; }
        if (empty($fields)) return false;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $st = $db->prepare($sql);
        foreach ($params as $k => $v) {
            // basic binding type inference
            $st->bindValue($k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
        }
        return $st->execute();
    }

    function delete_user_by_id($id) {
        $db = get_db();
        $st = $db->prepare('DELETE FROM users WHERE id = :id');
        $st->bindValue(':id', $id, SQLITE3_INTEGER);
        return $st->execute();
    }

    // If requested directly show minimal welcome / redirect
    if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
        $u = current_user();
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <title>Invent</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
        </head>
        <body class="p-3">
        <div class="container">
            <h1>Invent</h1>
            <?php if ($u): ?>
                <p>Welcome, <?php echo h($u['username']); ?>.</p>
                <p><a href="/profile.php">Profile</a> | <a href="/a/index.php">Admin</a></p>
            <?php else: ?>
                <p><a href="/login.php">Login</a> or contact the administrator to create an account.</p>
            <?php endif; ?>
            <hr>
            <p>See <code>full_schema.sql</code> for database structure.</p>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}
