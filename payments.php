<?php
require_once __DIR__ . '/index.php';
require_login();
$db = get_db();
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) { flash_set('error','Invalid CSRF'); header('Location:/payments.php'); exit; }
    $related_type = $_POST['related_type'] ?? 'sale'; $related_id = (int)($_POST['related_id'] ?? 0); $amount = (int)($_POST['amount_cents'] ?? 0); $method = $_POST['method'] ?? 'cash';
    $st = $db->prepare('INSERT INTO payments (company_id, related_type, related_id, amount_cents, method, recorded_by, recorded_at) VALUES (:cid,:rt,:rid,:amt,:m,:by,:now)');
    $st->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $st->bindValue(':rt',$related_type,SQLITE3_TEXT); $st->bindValue(':rid',$related_id,SQLITE3_INTEGER); $st->bindValue(':amt',$amount,SQLITE3_INTEGER); $st->bindValue(':m',$method,SQLITE3_TEXT); $st->bindValue(':by',$user['id'],SQLITE3_INTEGER); $st->bindValue(':now',date('c'),SQLITE3_TEXT); $st->execute();
    flash_set('success','Payment recorded'); header('Location:/payments.php'); exit;
}
// simple list of payments
$rows = [];
$res = $db->query('SELECT * FROM payments ORDER BY recorded_at DESC LIMIT 200'); while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Payments</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"></head>
<body class="p-3">
<div class="container">
    <h1>Payments</h1>
    <?php if ($m = flash_get('success')): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post" class="mb-3">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <div class="form-row">
            <div class="col"><label>Related type</label><select name="related_type" class="form-control"><option value="sale">Sale</option><option value="purchase">Purchase</option></select></div>
            <div class="col"><label>Related ID</label><input name="related_id" class="form-control" type="number"></div>
            <div class="col"><label>Amount (cents)</label><input name="amount_cents" class="form-control" type="number"></div>
            <div class="col"><label>Method</label><select name="method" class="form-control"><option>cash</option><option>bank</option><option>stripe</option></select></div>
            <div class="col"><button class="btn btn-primary">Record</button></div>
        </div>
    </form>
    <table class="table table-sm"><thead><tr><th>id</th><th>type</th><th>related</th><th>amount</th><th>method</th><th>at</th></tr></thead><tbody><?php foreach($rows as $p): ?><tr><td><?php echo h($p['id']); ?></td><td><?php echo h($p['related_type']); ?></td><td><?php echo h($p['related_id']); ?></td><td><?php echo number_format($p['amount_cents']/100,2); ?></td><td><?php echo h($p['method']); ?></td><td><?php echo h($p['recorded_at']); ?></td></tr><?php endforeach; ?></tbody></table>
</div>
</body>
</html>
