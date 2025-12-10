<?php
require_once __DIR__ . '/index.php';
require_admin();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo 'Invalid id.'; exit; }
$user = fetch_user_by_id($id);
if (!$user) { echo 'User not found.'; exit; }
$db = get_db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_set('error', 'Invalid CSRF token.');
        header('Location: /edit_user.php?id=' . $id);
        exit;
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : null;
    $update = ['username' => $username, 'email' => $email, 'role_id' => $role_id];
    if ($password !== '') $update['password'] = $password;
    update_user($id, $update);
    flash_set('success', 'User updated.');
    header('Location: /a/index.php');
    exit;
}
$roles_res = $db->query('SELECT id, name FROM roles ORDER BY name');
$roles = [];
while ($r = $roles_res->fetchArray(SQLITE3_ASSOC)) $roles[] = $r;
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="p-3">
<div class="container">
    <h1>Edit User</h1>
    <?php if ($m = flash_get('error')): ?><div class="alert alert-danger"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <div class="form-group">
            <label>Username</label>
            <input name="username" class="form-control" value="<?php echo h($user['username']); ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input name="email" class="form-control" value="<?php echo h($user['email']); ?>">
        </div>
        <div class="form-group">
            <label>New password (leave blank to keep)</label>
            <input name="password" class="form-control" type="password">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role_id" class="form-control">
                <option value="">(default)</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo h($r['id']); ?>" <?php if ($user['role_id'] == $r['id']) echo 'selected'; ?>><?php echo h($r['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary">Save</button>
        <a class="btn btn-secondary" href="/a/index.php">Cancel</a>
    </form>
</div>
</body>
</html>
