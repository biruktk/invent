<?php
require_once __DIR__ . '/index.php';
require_login();
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_set('error', 'Invalid CSRF token.');
        header('Location: /profile.php');
        exit;
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $update = [];
    if ($username !== '') $update['username'] = $username;
    if ($email !== '') $update['email'] = $email;
    if ($password !== '') $update['password'] = $password;
    if (!empty($update)) {
        update_user($user['id'], $update);
        flash_set('success', 'Profile updated.');
    }
    header('Location: /profile.php');
    exit;
}
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="p-3">
<div class="container">
    <h1>Profile</h1>
    <?php if ($m = flash_get('success')): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endif; ?>
    <?php if ($m = flash_get('error')): ?><div class="alert alert-danger"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <div class="form-group">
            <label>Username</label>
            <input class="form-control" name="username" value="<?php echo h($user['username']); ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input class="form-control" name="email" value="<?php echo h($user['email']); ?>">
        </div>
        <div class="form-group">
            <label>New password (leave blank to keep)</label>
            <input class="form-control" name="password" type="password">
        </div>
        <button class="btn btn-primary" type="submit">Save</button>
        <a class="btn btn-secondary" href="/">Back</a>
    </form>
</div>
</body>
</html>
