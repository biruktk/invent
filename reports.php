<?php
require_once __DIR__ . '/index.php';
require_admin();
$db = get_db();
// Example simple reports
$users_count = $db->querySingle('SELECT COUNT(*) FROM users');
$items_count = $db->querySingle('SELECT COUNT(*) FROM items');
$categories_count = $db->querySingle('SELECT COUNT(*) FROM categories');
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reports</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="p-3">
<div class="container">
    <h1>Reports</h1>
    <ul class="list-group">
        <li class="list-group-item">Users: <?php echo h($users_count); ?></li>
        <li class="list-group-item">Items: <?php echo h($items_count); ?></li>
        <li class="list-group-item">Categories: <?php echo h($categories_count); ?></li>
    </ul>
    <p class="mt-3"><a href="/a/index.php" class="btn btn-secondary">Back</a></p>
</div>
</body>
</html>
