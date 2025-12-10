<?php
require_once __DIR__ . '/index.php';
require_login();
$db = get_db();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) { flash_set('error','Invalid CSRF'); header('Location: /sales.php'); exit; }
    $customer_name = trim($_POST['customer_name'] ?? 'Walk-in');
    $item_id = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    // fetch product
    $p = $db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1'); $p->bindValue(':id',$item_id,SQLITE3_INTEGER); $prod = $p->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$prod) { flash_set('error','Product not found'); header('Location:/sales.php'); exit; }
    $total = $prod['sale_price_cents'] * $qty;
    // create or fetch customer
    $c = $db->prepare('INSERT INTO customers (company_id,name,created_at) VALUES (:cid,:name,:now)'); $c->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $c->bindValue(':name',$customer_name,SQLITE3_TEXT); $c->bindValue(':now',date('c'),SQLITE3_TEXT); $c->execute();
    $customer_id = $db->lastInsertRowID();
    // insert sale
    $s = $db->prepare('INSERT INTO sales (company_id,customer_id,payment_method,total_cents,status,created_at) VALUES (:cid,:cust,:pm,:total,:status,:now)');
    $status = ($payment_method === 'credit') ? 'pending' : 'paid';
    $s->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $s->bindValue(':cust',$customer_id,SQLITE3_INTEGER); $s->bindValue(':pm',$payment_method,SQLITE3_TEXT); $s->bindValue(':total',$total,SQLITE3_INTEGER); $s->bindValue(':status',$status,SQLITE3_TEXT); $s->bindValue(':now',date('c'),SQLITE3_TEXT); $s->execute();
    $sale_id = $db->lastInsertRowID();
    // insert sale item
    $si = $db->prepare('INSERT INTO sale_items (sale_id,product_id,name,qty,unit_price_cents,line_total_cents) VALUES (:sid,:pid,:name,:qty,:unit,:line)');
    $si->bindValue(':sid',$sale_id,SQLITE3_INTEGER); $si->bindValue(':pid',$item_id,SQLITE3_INTEGER); $si->bindValue(':name',$prod['name'],SQLITE3_TEXT); $si->bindValue(':qty',$qty,SQLITE3_INTEGER); $si->bindValue(':unit',$prod['sale_price_cents'],SQLITE3_INTEGER); $si->bindValue(':line',$total,SQLITE3_INTEGER); $si->execute();
    // decrement stock simple (reduce quantity in products table)
    $u = $db->prepare('UPDATE products SET quantity = quantity - :q WHERE id = :id'); $u->bindValue(':q',$qty,SQLITE3_INTEGER); $u->bindValue(':id',$item_id,SQLITE3_INTEGER); $u->execute();
    // record payment if not credit
    if ($payment_method !== 'credit') {
        $pm = $db->prepare('INSERT INTO payments (company_id, related_type, related_id, amount_cents, method, recorded_by, recorded_at) VALUES (:cid,:type,:rid,:amt,:m,:by,:now)');
        $pm->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $pm->bindValue(':type','sale',SQLITE3_TEXT); $pm->bindValue(':rid',$sale_id,SQLITE3_INTEGER); $pm->bindValue(':amt',$total,SQLITE3_INTEGER); $pm->bindValue(':m',$payment_method,SQLITE3_TEXT); $pm->bindValue(':by',$user['id'],SQLITE3_INTEGER); $pm->bindValue(':now',date('c'),SQLITE3_TEXT); $pm->execute();
    }
    flash_set('success','Sale recorded'); header('Location:/sales.php'); exit;
}
// fetch products
$prods = []; $prs = $db->prepare('SELECT id,name,sku,sale_price_cents,quantity FROM products WHERE company_id = :cid ORDER BY name'); $prs->bindValue(':cid', current_user()['company_id'], SQLITE3_INTEGER); $r = $prs->execute(); while ($row = $r->fetchArray(SQLITE3_ASSOC)) $prods[] = $row;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Sales</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"></head>
<body class="p-3">
<div class="container">
    <h1>Sales</h1>
    <?php if ($m = flash_get('success')): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <div class="form-row align-items-end">
            <div class="col"><label>Product</label><select name="product_id" class="form-control"><?php foreach($prods as $p):?><option value="<?php echo (int)$p['id']; ?>"><?php echo h($p['name']); ?> (<?php echo number_format($p['sale_price_cents']/100,2); ?>)</option><?php endforeach;?></select></div>
            <div class="col"><label>Qty</label><input name="qty" value="1" class="form-control" type="number"></div>
            <div class="col"><label>Customer name</label><input name="customer_name" class="form-control" value="Walk-in"></div>
            <div class="col"><label>Payment</label><select name="payment_method" class="form-control"><option value="cash">Cash</option><option value="bank">Bank</option><option value="prepaid">Prepaid</option><option value="credit">Credit</option></select></div>
            <div class="col"><button class="btn btn-primary">Record Sale</button></div>
        </div>
    </form>
</div>
</body>
</html>
