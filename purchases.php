<?php
require_once __DIR__ . '/index.php';
require_login();
$db = get_db();
$user = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) { flash_set('error','Invalid CSRF'); header('Location:/purchases.php'); exit; }
    $supplier = trim($_POST['supplier'] ?? ''); $product_id = (int)($_POST['product_id'] ?? 0); $qty = (int)($_POST['qty'] ?? 1); $unit = (int)($_POST['unit_price'] ?? 0); $total = $qty * $unit; $payment_type = $_POST['payment_type'] ?? 'cash';
    $st = $db->prepare('INSERT INTO purchases (company_id,supplier,payment_type,total_cents,status,created_at) VALUES (:cid,:sup,:pt,:total,:status,:now)');
    $st->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $st->bindValue(':sup',$supplier,SQLITE3_TEXT); $st->bindValue(':pt',$payment_type,SQLITE3_TEXT); $st->bindValue(':total',$total,SQLITE3_INTEGER); $st->bindValue(':status','paid',SQLITE3_TEXT); $st->bindValue(':now',date('c'),SQLITE3_TEXT); $st->execute();
    $pid = $db->lastInsertRowID();
    $si = $db->prepare('INSERT INTO purchase_items (purchase_id,product_id,name,qty,unit_price_cents,line_total_cents) VALUES (:pid,:prod,:name,:qty,:unit,:line)');
    $prod = $db->prepare('SELECT * FROM products WHERE id = :id'); $prod->bindValue(':id',$product_id,SQLITE3_INTEGER); $prodrow = $prod->execute()->fetchArray(SQLITE3_ASSOC);
    $si->bindValue(':pid',$pid,SQLITE3_INTEGER); $si->bindValue(':prod',$product_id,SQLITE3_INTEGER); $si->bindValue(':name',$prodrow['name'],SQLITE3_TEXT); $si->bindValue(':qty',$qty,SQLITE3_INTEGER); $si->bindValue(':unit',$unit,SQLITE3_INTEGER); $si->bindValue(':line',$total,SQLITE3_INTEGER); $si->execute();
    // increase product quantity
    $u = $db->prepare('UPDATE products SET quantity = quantity + :q WHERE id = :id'); $u->bindValue(':q',$qty,SQLITE3_INTEGER); $u->bindValue(':id',$product_id,SQLITE3_INTEGER); $u->execute();
    flash_set('success','Purchase recorded'); header('Location:/purchases.php'); exit;
}
$prods = []; $prs = $db->prepare('SELECT id,name FROM products WHERE company_id = :cid ORDER BY name'); $prs->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $res = $prs->execute(); while ($r = $res->fetchArray(SQLITE3_ASSOC)) $prods[] = $r;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Purchases</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"></head>
<body class="p-3">
<div class="container">
    <h1>Purchases</h1>
    <?php if ($m = flash_get('success')): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <div class="form-row align-items-end">
            <div class="col"><label>Product</label><select name="product_id" class="form-control"><?php foreach($prods as $p):?><option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['name']); ?></option><?php endforeach;?></select></div>
            <div class="col"><label>Qty</label><input name="qty" value="1" class="form-control" type="number"></div>
            <div class="col"><label>Unit price (cents)</label><input name="unit_price" class="form-control" type="number"></div>
            <div class="col"><label>Supplier</label><input name="supplier" class="form-control"></div>
            <div class="col"><button class="btn btn-primary">Record Purchase</button></div>
        </div>
    </form>
</div>
</body>
</html>
