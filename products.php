<?php
require_once __DIR__ . '/index.php';
require_login();
$db = get_db();
$user = current_user();

// Handle posts for add/edit/delete/adjust
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    if (!verify_csrf_token($token)) { flash_set('error','Invalid CSRF'); header('Location: /products.php'); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $cost = (int)($_POST['cost'] ?? 0);
        $price = (int)($_POST['price'] ?? 0);
        $reorder = (int)($_POST['reorder'] ?? 0);
        $st = $db->prepare('INSERT INTO products (company_id, sku, name, description, cost_price_cents, sale_price_cents, reorder_level, created_at) VALUES (:cid, :sku, :name, :desc, :cost, :price, :reorder, :now)');
        $st->bindValue(':cid', $user['company_id'], SQLITE3_INTEGER);
        $st->bindValue(':sku', $sku, SQLITE3_TEXT);
        $st->bindValue(':name', $name, SQLITE3_TEXT);
        $st->bindValue(':desc', trim($_POST['description'] ?? ''), SQLITE3_TEXT);
        $st->bindValue(':cost', $cost, SQLITE3_INTEGER);
        $st->bindValue(':price', $price, SQLITE3_INTEGER);
        $st->bindValue(':reorder', $reorder, SQLITE3_INTEGER);
        $st->bindValue(':now', date('c'), SQLITE3_TEXT);
        $st->execute();
        flash_set('success','Product added'); header('Location: /products.php'); exit;
    }
    if ($action === 'adjust') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $loc = trim($_POST['location'] ?? 'default');
        // upsert stock_levels
        $s1 = $db->prepare('SELECT id, quantity FROM stock_levels WHERE product_id = :pid AND location_id = (SELECT id FROM locations WHERE company_id = :cid AND name = :loc LIMIT 1) LIMIT 1');
        $s1->bindValue(':pid',$product_id,SQLITE3_INTEGER); $s1->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $s1->bindValue(':loc',$loc,SQLITE3_TEXT);
        $r = $s1->execute()->fetchArray(SQLITE3_ASSOC);
        // ensure location exists
        $sL = $db->prepare('INSERT OR IGNORE INTO locations (company_id,name,created_at) VALUES (:cid,:name,:now)');
        $sL->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $sL->bindValue(':name',$loc,SQLITE3_TEXT); $sL->bindValue(':now',date('c'),SQLITE3_TEXT); $sL->execute();
        $locId = $db->querySingle('SELECT id FROM locations WHERE company_id = ' . (int)$user['company_id'] . ' AND name = ' . $db->escapeString($loc) . ' LIMIT 1');
        if ($r) {
            // simple update
            $newQ = $r['quantity'] + $qty;
            $u = $db->prepare('UPDATE stock_levels SET quantity = :q, updated_at = :now WHERE id = :id');
            $u->bindValue(':q',$newQ,SQLITE3_INTEGER); $u->bindValue(':now',date('c'),SQLITE3_TEXT); $u->bindValue(':id',$r['id'],SQLITE3_INTEGER); $u->execute();
        } else {
            $i = $db->prepare('INSERT INTO stock_levels (product_id, location_id, quantity, updated_at) VALUES (:pid, (SELECT id FROM locations WHERE company_id = :cid AND name = :loc LIMIT 1), :qty, :now)');
            $i->bindValue(':pid',$product_id,SQLITE3_INTEGER); $i->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $i->bindValue(':loc',$loc,SQLITE3_TEXT); $i->bindValue(':qty',$qty,SQLITE3_INTEGER); $i->bindValue(':now',date('c'),SQLITE3_TEXT); $i->execute();
        }
        // record stock movement
        $m = $db->prepare('INSERT INTO stock_movements (company_id, product_id, from_location_id, to_location_id, quantity, type, performed_by, created_at) VALUES (:cid, :pid, NULL, (SELECT id FROM locations WHERE company_id = :cid AND name = :loc LIMIT 1), :qty, :type, :user, :now)');
        $m->bindValue(':cid',$user['company_id'],SQLITE3_INTEGER); $m->bindValue(':pid',$product_id,SQLITE3_INTEGER); $m->bindValue(':loc',$loc,SQLITE3_TEXT); $m->bindValue(':qty',$qty,SQLITE3_INTEGER); $m->bindValue(':type','adjustment',SQLITE3_TEXT); $m->bindValue(':user',$user['id'],SQLITE3_INTEGER); $m->bindValue(':now',date('c'),SQLITE3_TEXT); $m->execute();
        flash_set('success','Stock adjusted'); header('Location: /products.php'); exit;
    }
}

// Fetch products for user's company
$rows = [];
$st = $db->prepare('SELECT * FROM products WHERE company_id = :cid ORDER BY name');
$st->bindValue(':cid', $user['company_id'], SQLITE3_INTEGER);
$res = $st->execute();
while ($r = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Products</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css"></head>
<body class="p-3">
<div class="container">
    <h1>Products</h1>
    <?php if ($m = flash_get('success')): ?><div class="alert alert-success"><?php echo h($m); ?></div><?php endif; ?>
    <form method="post" class="mb-3">
        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="col"><input name="sku" class="form-control" placeholder="SKU"></div>
            <div class="col"><input name="name" class="form-control" placeholder="Name"></div>
            <div class="col"><input name="price" class="form-control" placeholder="Price cents" type="number"></div>
            <div class="col"><button class="btn btn-primary">Add</button></div>
        </div>
    </form>
    <table class="table table-sm">
        <thead><tr><th>SKU</th><th>Name</th><th>Qty</th><th>Price</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
            <tr>
                <td><?php echo h($p['sku']); ?></td>
                <td><?php echo h($p['name']); ?></td>
                <td><?php echo (int)$p['quantity']; ?></td>
                <td><?php echo number_format($p['sale_price_cents']/100,2); ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="_csrf" value="<?php echo h(generate_csrf_token()); ?>">
                        <input type="hidden" name="action" value="adjust">
                        <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                        <input name="quantity" value="1" class="form-control d-inline-block" style="width:80px">
                        <input name="location" value="default" type="hidden">
                        <button class="btn btn-sm btn-secondary">Adjust</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
