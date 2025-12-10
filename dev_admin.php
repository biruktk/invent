<?php
require_once __DIR__ . '/index.php';
// Dev-only admin tasks. Only execute if in non-production or user is a site admin.
if (ENV === 'production' && !is_admin()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
require_admin();
// Example: ability to load schema
if (isset($_GET['action']) && $_GET['action'] === 'load_schema') {
    $sql = file_get_contents(__DIR__ . '/full_schema.sql');
    if ($sql === false) { echo 'Schema file not found.'; exit; }
    $db = get_db();
    try {
        $db->exec('BEGIN');
        $db->exec($sql);
        $db->exec('COMMIT');
        echo 'Schema loaded.';
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo 'Error: ' . htmlspecialchars($e->getMessage());
    }
    exit;
}
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dev Admin</title>
</head>
<body>
    <h1>Dev Admin</h1>
    <p><a href="?action=load_schema">Load schema (careful)</a></p>
</body>
</html>
