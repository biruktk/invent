<?php
// Admin landing page
require_once __DIR__ . '/../index.php';
require_admin();
$db = get_db();
$users_res = $db->query('SELECT u.id, u.username, u.email, r.name AS role_name, u.created_at FROM users u LEFT JOIN roles r ON u.role_id = r.id ORDER BY u.id DESC');
$users = [];
while ($r = $users_res->fetchArray(SQLITE3_ASSOC)) $users[] = $r;
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <script src="/index.js"></script>
</head>
<body class="p-3">
<div class="container">
    <h1>Admin</h1>
    <?php if ($m = flash_get('success')): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endif; ?>
    <?php if ($m = flash_get('error')): ?><div class="alert alert-danger"><?php echo h($m); ?></div><?php endif; ?>
    <p><a href="/add_user.php" class="btn btn-primary">Add user</a> <a href="/reports.php" class="btn btn-info">Reports</a></p>
    <table class="table table-sm table-striped">
        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo h($u['id']); ?></td>
                <td><?php echo h($u['username']); ?></td>
                <td><?php echo h($u['email']); ?></td>
                <td><?php echo h($u['role_name']); ?></td>
                <td><?php echo h($u['created_at']); ?></td>
                <td>
                    <a class="btn btn-sm btn-secondary" href="/edit_user.php?id=<?php echo h($u['id']); ?>">Edit</a>
                    <form method="post" action="/delete_user.php" style="display:inline" onsubmit="return confirm('Delete user?');">
                        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
                        <input type="hidden" name="id" value="<?php echo h($u['id']); ?>">
                        <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
