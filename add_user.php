<?php
require_once __DIR__ . '/index.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) {
        flash_set('error', 'Invalid CSRF token.');
        header('Location: /add_user.php');
        exit;
    }
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : null;
    if ($username === '' || $email === '' || $password === '') {
        flash_set('error', 'Username, email and password are required.');
        header('Location: /add_user.php');
        exit;
    }
    create_user($username, $email, $password, $role_id);
    flash_set('success', 'User created.');
    header('Location: /a/index.php');
    exit;
}
$db = get_db();
$roles_res = $db->query('SELECT id, name FROM roles ORDER BY name');
$roles = [];
while ($r = $roles_res->fetchArray(SQLITE3_ASSOC)) $roles[] = $r;
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Add User</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="p-3">
<div class="container">
    <h1>Add User</h1>
    <?php if ($m = flash_get('error')): ?><div class="alert alert-danger"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <div class="form-group">
            <label>Username</label>
            <input name="username" class="form-control">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input name="email" class="form-control" type="email">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input name="password" class="form-control" type="password">
        </div>
        <div class="form-group">
            <label>Role</label>
            <select name="role_id" class="form-control">
                <option value="">(default)</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?php echo h($r['id']); ?>"><?php echo h($r['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary">Create</button>
        <a class="btn btn-secondary" href="/a/index.php">Cancel</a>
    </form>
</div>
</body>
</html>
