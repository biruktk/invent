<?php
require_once __DIR__ . '/index.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$token = $_POST['_csrf'] ?? '';
if (!verify_csrf_token($token)) {
    http_response_code(400);
    echo 'Invalid CSRF token.';
    exit;
}
if ($id <= 0) { http_response_code(400); echo 'Invalid id.'; exit; }
if (delete_user_by_id($id)) {
    flash_set('success', 'User deleted.');
} else {
    flash_set('error', 'Unable to delete user.');
}
header('Location: /a/index.php');
exit;
