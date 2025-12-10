<?php
// echo 'ss'
// inventory_app.php — Multi-user Inventory Management with Auth
// Adds user isolation to the original single-file SPA
// --------------------------------------------------------------
session_start();
// var_dump($_SESSION);
// $_SESSION['role'] = 'admin';
// function checkPermission($role, $module)
// {
// $permissions = [
// 'admin' => ['sales', 'inventory', 'finance', 'banks', 'purchases', 'misc', 'users'],
// 'manager' => ['sales', 'inventory', 'purchases'],
// 'sales' => ['sales'],
// 'inventory' => ['invento ry', 'purchases'],
// 'finance' => ['finance', 'banks', 'misc'],
// 'employee' => ['finance']
// ];
// return in_array($module, $permissions[$role] ?? []);
// }
// if ($_SESSION['role'] !== 'admin') {
// ---------- BOOTSTRAP DB ----------
const DB_PATH = __DIR__ . '/inventory2.sqlite';
$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
date_default_timezone_set('Africa/Addis_Ababa');
// ---------- USER AUTHENTICATION ----------
// session_start();
// Create users table
// $pdo->exec(<<<SQL
// CREATE TABLE IF NOT EXISTS users (
// id INTEGER PRIMARY KEY AUTOINCREMENT,
// username TEXT UNIQUE NOT NULL,
// email TEXT UNIQUE NOT NULL,
// pstatus TEXT DEFAULT 'unpaid',
// password_hash TEXT NOT NULL,
// txn TEXT,
// pkg TEXT,
// created_at TEXT NOT NULL DEFAULT (datetime('now'))
// );
// SQL);
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS cust (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    phone TEXT UNIQUE NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL);
$sql = "
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    pstatus TEXT DEFAULT 'inactive',
    password_hash TEXT NOT NULL,
    txn TEXT,
    pkg TEXT,
    company_id INTEGER,
    role TEXT NOT NULL DEFAULT 'employee',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
);
    ";
// Execute SQL
$pdo->exec($sql);
$sql = "
    CREATE TABLE IF NOT EXISTS companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        owner_id INTEGER,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ";
// Execute SQL
$pdo->exec($sql);

// // $pdo->exec(<<<SQL
// // -- CREATE TABLE companies (
// // -- id INTEGER PRIMARY KEY AUTOINCREMENT,
// // -- name TEXT NOT NULL
// // -- );
// // -- SQL);
// $pdo->exec("DROP TABLE IF EXISTS banks");
// $pdo->exec("DROP TABLE IF EXISTS users");

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS banks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    balance_cents INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
);
SQL);

$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    brand_name TEXT,
    item_number TEXT,
    qty INTEGER NOT NULL CHECK(qty >= 0),
    price_cents INTEGER NOT NULL CHECK(price_cents >= 0),
    tax_pct REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
);
SQL);
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS purchases (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  item_name TEXT NOT NULL,
  brand_name TEXT,
  item_number TEXT,
  qty INTEGER NOT NULL,
  price_cents INTEGER NOT NULL,
  tax_pct REAL NOT NULL DEFAULT 0,
  -- payment type: 'cash', 'credit', 'bank', 'prepaid'
  payment_type TEXT NOT NULL CHECK (payment_type IN ('cash','credit','bank','prepaid')),
  -- for bank payments
  bank_id INTEGER REFERENCES banks(id)  DEFAULT NULL,
  -- for credit purchases
  due_date TEXT,
  -- for prepaid purchases
  prepaid_min_cents INTEGER CHECK (prepaid_min_cents >= 0),
  date TEXT NOT NULL DEFAULT (date('now')),
  total_cents INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
    ,ids TEXT,
    status TEXT DEFAULT 'paid'
);
SQL);
try {
  $pdo->exec("ALTER TABLE purchases ADD COLUMN status TEXT DEFAULT 'paid'");
} catch (Throwable $e) {
}
try {
  $pdo->exec("ALTER TABLE sales ADD COLUMN item_number TEXT");
} catch (Throwable $e) {
}
try {
  $pdo->exec("ALTER TABLE purchases ADD COLUMN due_date TEXT");
} catch (Throwable $e) {
}
try {
  $pdo->exec("ALTER TABLE sales ADD COLUMN payment_method TEXT DEFAULT 'Paid'");
} catch (Throwable $e) {
}
// $pdo->exec('ALTER TABLE items ADD COLUMN image_path TEXT;');
// $pdo->exec(<<<SQL
// ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT '[]'
// SQL);
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS sales (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_id INTEGER REFERENCES items(id) ON DELETE SET NULL,
  item_name TEXT NOT NULL,
  qty INTEGER NOT NULL,
  price_cents INTEGER NOT NULL,
  tax_pct REAL NOT NULL DEFAULT 0,
  payment_method TEXT NOT NULL CHECK(payment_method IN ('Paid','Pre-paid','Credit')),
  paid_via TEXT, -- 'bank' or 'cash' (updated when payment is made)
  bank_id INTEGER REFERENCES banks(id) ON DELETE SET NULL,
  prepayment_cents INTEGER DEFAULT 0, -- only for Pre-paid
  due_date TEXT, -- for Pre-paid and Credit
  date TEXT NOT NULL DEFAULT (date('now')),
  total_cents INTEGER NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL ,
    ids TEXT ,
    username TEXT,
    phone TEXT,
    status TEXT DEFAULT 'pending'
);
SQL);
try {
  $pdo->exec("ALTER TABLE sales ADD COLUMN status TEXT DEFAULT 'unpaid'");
} catch (Throwable $e) {
  // Column may already exist; ignore
}
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS misc (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  reason TEXT,
  amount_cents INTEGER NOT NULL,
  bank_id INTEGER NOT NULL REFERENCES banks(id) ON DELETE RESTRICT,
  date TEXT NOT NULL DEFAULT (date('now')),
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
    user_id INTEGER NOT NULL
);
SQL);
// echo $_SESSION['role'];
// var_dump($_SESSION['role']);
// Add user_id to all existing tables
const UPLOADS_DIR = __DIR__ . '/uploads/items';
// Create uploads directory
if (!is_dir(UPLOADS_DIR)) {
  mkdir(UPLOADS_DIR, 0755, true);
}
$path = '';
function seter($pasi)
{
  $path = $pasi;
}
function handleImageUpload()
{
  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    return null;
  }
  $file = $_FILES['image'];
  $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  if (!in_array($file['type'], $allowed)) {
    return null;
  }
  if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
    return null;
  }
  $filename = uniqid('item_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
  $filepath = UPLOADS_DIR . '/' . $filename;
  // $path = $filepath;
  seter('uploads/items/' . $filename);
  if (move_uploaded_file($file['tmp_name'], $filepath)) {
    return 'uploads/items/' . $filename;
  }
  return 'uploads/items/' . $filename;
}
$tables = ['banks', 'items', 'purchases', 'sales', 'misc'];
foreach ($tables as $t) {
  try {
    $pdo->exec("ALTER TABLE $t ADD COLUMN user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE");
  } catch (\Throwable $_) { /* ignore if exists */
  }
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$t}_user ON $t(user_id)");
}
// ---------- AUTH HELPERS ----------
function j($data, $code = 200)
{
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}
function isLoggedIn()
{
  return isset($_SESSION['user_id']);
}
function getUserId()
{
  return $_SESSION['user_id'];
}
function requireLogin()
{
  if (!isLoggedIn()) {
    header(header: 'Location: ?action=login');
    exit;
  }
}
function hashPw($p)
{
  return password_hash($p, PASSWORD_BCRYPT);
}
function verifyPw($p, $h)
{
  return password_verify($p, $h);
}
function cents($v)
{
  $v = is_string($v) ? trim($v) : $v;
  return ($v === '' || !is_numeric($v)) ? 0 : (int)round(((float)$v) * 100);
}
function money($c)
{
  return number_format($c / 100, 2);
}
function periodRange($period, $from = null, $to = null)
{
  if ($from && $to) return [$from, $to];
  $d = new DateTime();
  switch ($period) {
    case 'daily':
      $s = $d->format('Y-m-d');
      return [$s, $s];
    case 'weekly':
      $w = (int)$d->format('N');
      return [$d->modify('-' . ($w - 1) . 'days')->format('Y-m-d'), $d->modify('+' . (7 - $w) . 'days')->format('Y-m-d')];
    case 'monthly':
      return [$d->format('Y-m-01'), $d->modify('last day')->format('Y-m-d')];
    case 'yearly':
      return [$d->format('Y-01-01'), $d->format('Y-12-31')];
    default:
      return ['1970-01-01', '2999-12-31'];
  }
}
// ---------- ACTION ROUTER ----------
$action = $_POST['action'] ?? $_GET['action'] ?? null;
$user_id = isLoggedIn() ? getUserId() : null;
// AUTH endpoints
if ($action === 'register' && isset($_POST['username'])) {
  $u = trim($_POST['username']);
  $e = trim($_POST['email']);
  $p = $_POST['password'];
  $cp = $_POST['confirm_password'];
  if (!$u || !$e || !$p || $p !== $cp || strlen($p) < 6) j(['error' => 'Invalid input'], 400);
  $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=? OR email=?');
  $stmt->execute([$u, $e]);
  $tx_ref = 'tx-ref-' . time() . '-' . bin2hex(random_bytes(4));
  $pkg = trim($_POST['pkg']);
  if ($stmt->fetchColumn()) j(['error' => 'User exists'], 400);
  // $pdo->prepare('INSERT INTO users(username,email,pstatus,password_hash,txn,pkg,company_id,role) VALUES(?,?,?,?,?,?,?,?)')->execute([$u, $e, 'paid', hashPw($p), $tx_ref, $pkg,22,'admin']);
  // $stmt = $pdo->prepare('SELECT id,username,password_hash FROM users WHERE username=? OR email=?');
  // $stmt->execute([$u, $u]);
  // $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $pdo->beginTransaction();
  try {
    // 1. Create a company for this admin
    $stmt = $pdo->prepare("INSERT INTO companies (name) VALUES (?)");
    $stmt->execute([$u . "'s Company"]); // Or take company name from registration form
    $companyId = $pdo->lastInsertId();
    $role = 'admin';
    // 2. Insert the admin user with the company_id
    $stmt = $pdo->prepare("INSERT INTO users(username,email,pstatus,password_hash,txn,pkg,company_id,role)
                           VALUES(?,?,?,?,?,?,?,?)");
    $stmt->execute([$u, $e, 'unpaid', hashPw($p), $tx_ref, $pkg, $companyId, $role]);
    // 3. Update company owner_id
    $adminId = $pdo->lastInsertId();
    $pdo->prepare("UPDATE companies SET owner_id=? WHERE id=?")->execute([$adminId, $companyId]);
    $pdo->commit();
    // var_dump($adminId);
  } catch (Exception $ex) {
    $pdo->rollBack();
    die("Registration failed: " . $ex->getMessage());
  }
  $chapaData = [
    'amount' => '100',
    'currency' => 'ETB',
    'first_name' => explode(' ', $u)[0] ?? 'John',
    'last_name' => explode(' ', $u)[1] ?? 'Doe',
    'tx_ref' => $tx_ref,
    'callback_url' => 'https://blogcat.iambiruk.com/pay.php?user_id=' . $row['id'] . '&tx_ref=' . $tx_ref,
    'return_url' => 'https://blogcat.iambiruk.com/'
    // 'return_url' => 'https://localhost:8080/' // Replace with your return URL
  ];
  // Biruk
  // Call Chapa again
  $ch = curl_init();
  curl_setopt_array($ch, [
    // CURLOPT_URL => 'https://api.chapa.co/v1/transaction/initialize',
    CURLOPT_URL => 'https://api.chapa.co/v1/transaction/initialize',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($chapaData),
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer CHASECK_TEST-Pb0uEY4MEavkr0d8sycOu6nNkzbuHq31',
      // 'Authorization: Bearer CHASECK-1N5xl2BrapA3yQ48GVCkoN2XoqtSNx5K',
      'Content-Type: application/json'
    ],
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);
  $data = json_decode($res, true);
  // var_dump($data);
  if ($data['status'] === 'paid') {
    // var_dump($err);
    // var_dump($res);
    $data = json_decode($res, true);
    // var_dump($data);
    // header('Location: ' . $data['data']['checkout_url']);
    j(['ok' => true, 'redirect' => $data['data']['checkout_url']]);
  } else {
    // var_dump($err);
    // var_dump(value: $res);
    $data = json_decode($res, true);
    // var_dump($data);
    j(['error' => 'Payment required but failed to init'], 500);
  }
  $_SESSION['user_id'] = $row['id'];
  $_SESSION['username'] = $row['username'];
  $_SESSION['role'] = $row['role'];
  // j(['ok' => true, 'redirect' => '?']);
}
if ($action === 'login' && isset($_POST['username'])) {
  $u = trim($_POST['username']);
  $p = $_POST['password'];
  if (!$u || !$p) j(['error' => 'Required'], 400);
  $stmt = $pdo->prepare('SELECT * FROM users WHERE username=? OR email=?');
  $stmt->execute([$u, $u]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  // if the pending filed is unpaid return the user to the chapa payment page if not contiue or if it say paid continue to login
  if (!$row || !verifyPw($p, $row['password_hash'])) j(['error' => 'Invalid'], 401);
  // $u = trim($_POST['username']);
  // $p = $_POST['password'];
  // $stmt = $pdo->prepare('SELECT id,username,password_hash,payment_status FROM users WHERE username=? OR email=?');
  // $stmt->execute([$u, $u]);
  // $row = $stmt->fetch(PDO::FETCH_ASSOC);
  // if (!$row || !password_verify($p, $row['password_hash'])) {
  // j(['error' => 'Invalid'], 401);
  // }
  // If unpaid → re-initiate payment
  if ($row['pstatus'] === 'unpaid') {
    // Generate new tx_ref
    $tx_ref = 'tx-ref-' . time() . '-' . bin2hex(random_bytes(4));
    $chapaData = [
      'amount' => '100',
      'currency' => 'ETB',
      'email' => $u,
      'first_name' => explode(' ', $u)[0] ?? 'John',
      'last_name' => explode(' ', $u)[1] ?? 'Doe',
      'tx_ref' => $tx_ref,
      'callback_url' => 'https://blogcat.iambiruk.com/pay.php?user_id=' . $row['id'],
      'return_url' => 'https://blogcat.iambiruk.com/'
    ];
    // Call Chapa again
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => 'https://api.chapa.co/v1/transaction/initialize',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode($chapaData),
      CURLOPT_HTTPHEADER => [
        // 'Authorization: Bearer YOUR_CHAPA_SECRET_KEY',
        'Authorization: Bearer CHASECK-1N5xl2BrapA3yQ48GVCkoN2XoqtSNx5K',
        'Content-Type: application/json'
      ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    if ($data['status'] === 'success') {
      j(['ok' => true, 'redirect' => $data['data']['checkout_url']]);
    } else {
      j(['error' => 'Payment required but failed to init'], 500);
    }
    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['company_id'] = $row['company_id'];
    $_SESSION['permissions'] = $row['permissions'] ?? '[]';
    j(['ok' => true, 'redirect' => '?']);
  }
  $_SESSION['user_id'] = $row['id'];
  $_SESSION['username'] = $row['username'];
  $_SESSION['role'] = $row['role'];
  $_SESSION['company_id'] = $row['company_id'];
  $_SESSION['permissions'] = $row['permissions'] ?? '[]'; // Load permissions into session
  // var_dump( $row);
  // exit;
  j(['ok' => true, 'redirect' => '?']);
}
if ($action === 'logout') {
  session_destroy();
  header('Location: ?action=login');
  exit;
}
// Protected endpoints
if ($action && in_array($action, ['add_bank', 'list_customers', 'delete_salse', 'edit_bank', 'delete_bank', 'list_banks', 'add_purchase', 'list_purchases', 'edit_purchase', 'delete_purchase', 'add_sale', 'list_sales', 'edit_sales_item', 'list_items', 'edit_item', 'delete_item', 'add_stock', 'add_misc', 'list_misc', 'dashboard', 'reports', 'list_prepaid_sales', 'list_credit_sales', 'update_purchase'])) {
  requireLogin();
  
  // Use actual user_id for audit/logging, but company_id for data ownership
  $user_id = getUserId();
  $company_id = $_SESSION['company_id']; 

  function checkPermission($action)
  {
    if ($_SESSION['role'] === 'admin') return true; // Admin can do everything
    $perms = json_decode($_SESSION['permissions'] ?? '[]', true);
    if (!in_array($action, $perms)) {
      j(['error' => 'Access denied'], 403);
      exit;
    }
    return true; // Permission granted
  }
  try {
    switch ($action) {
      // BANKS
      case 'add_bank':
        checkPermission('add_bank');
        $n = trim($_POST['name']);
        $b = cents($_POST['balance']);
        if (!$n) j(['error' => 'Bank name is required'], 400);
        $pdo->prepare('INSERT INTO banks(user_id,company_id,name,balance_cents) VALUES(?,?,?,?)')->execute([$user_id, $company_id, $n, $b]);
        j(['ok' => true]);
      case 'edit_bank':
        checkPermission('edit_bank');
        $id = (int)$_POST['id'];
        $n = trim($_POST['name']);
        $b = cents($_POST['balance']);
        if (!$n) j(['error' => 'Name required'], 400);
        $pdo->prepare('UPDATE banks SET name=?, balance_cents=? WHERE id=? AND company_id=?')->execute([$n, $b, $id, $company_id]);
        j(['ok' => true]);
      case 'delete_bank':
        checkPermission('delete_bank');
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM banks WHERE id=? AND company_id=?')->execute([$id, $company_id]);
        j(['ok' => true]);
      case 'list_banks':
        // checkPermission('list_banks');
        // j(['banks' => $user_id]);
        $stmt = $pdo->prepare("SELECT id,name,balance_cents FROM banks WHERE company_id=? ORDER BY name");
        $stmt->execute([$company_id]);
        j(['banks' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        // ITEMS
      case 'list_items':
        // checkPermission('list_items');
        $stmt = $pdo->prepare("SELECT * FROM items WHERE company_id=? ORDER BY name");
        $stmt->execute([$company_id]);
        j(['items' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
      case 'edit_item':
        checkPermission('edit_item');
        $id = (int)($_POST['id'] ?? 0);
        $n = trim($_POST['name']);
        $bn = trim($_POST['brand_name'] ?? '');
        $in = trim($_POST['item_number'] ?? '');
        $q = (int)$_POST['qty'];
        $p = cents($_POST['price']);
        $t = (float)$_POST['tax_pct'];
        
        // Handle Image Upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = handleImageUpload();
        }
        
        if (!$n) j(['error' => 'Name required'], 400);
        if ($id) {
            // Update existing item
            $sql = "UPDATE items SET name=?, brand_name=?, item_number=?, qty=?, price_cents=?, tax_pct=?";
            $params = [$n, $bn, $in, $q, $p, $t];
            
            if ($imagePath) {
                $sql .= ", image_path=?";
                $params[] = $imagePath;
            }
            
            $sql .= " WHERE id=? AND company_id=?";
            $params[] = $id;
            $params[] = $company_id;
            
            $pdo->prepare($sql)->execute($params);
        } else {
            // Insert new item
            $pdo->prepare('INSERT INTO items(user_id,company_id,name,brand_name,item_number,qty,price_cents,tax_pct,image_path) VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$user_id, $company_id, $n, $bn, $in, $q, $p, $t, $imagePath]);
        }
        j(['ok' => true]);
      case 'delete_item':
        checkPermission('delete_item');
        $id = (int)$_POST['id'];
        $pdo->prepare('DELETE FROM items WHERE id=? AND company_id=?')->execute([$id, $company_id]);
        j(['ok' => true]);
      case 'add_stock':
        checkPermission('add_stock');
        $id = (int)$_POST['id'];
        $q = (int)$_POST['qty'];
        if ($q <= 0) j(['error' => 'Invalid quantity'], 400);
        $pdo->prepare('UPDATE items SET qty=qty+? WHERE id=? AND company_id=?')->execute([$q, $id, $company_id]);
        j(['ok' => true]);
      case 'delete_purchase':
        checkPermission('delete_purchase');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) j(['error' => 'Invalid purchase ID'], 400);
        $pdo->beginTransaction();
        // Get purchase data for bank adjustment
        $p = $pdo->prepare('SELECT * FROM purchases WHERE id=? AND company_id=?');
        $p->execute([$id, $company_id]);
        $purchase = $p->fetch(PDO::FETCH_ASSOC);
        if ($purchase) {
          // Refund to bank only if payment was via bank
          if ($purchase['payment_type'] === 'bank' && $purchase['bank_id']) {
            $pdo->prepare('UPDATE banks SET balance_cents = balance_cents + ? WHERE id=?')->execute([$purchase['total_cents'], $purchase['bank_id']]);
          }
          // Delete purchase
          $pdo->prepare('DELETE FROM purchases WHERE id=?')->execute([$id]);
        }
        $pdo->commit();
        j(['ok' => true]);
      case 'delete_salse':
        checkPermission('delete_salse');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) j(['error' => 'Invalid sales ID'], 400);
        $pdo->beginTransaction();
        // Get sale data
        $p = $pdo->prepare('SELECT * FROM sales WHERE id=?');
        $p->execute([$id]);
        $sale = $p->fetch(PDO::FETCH_ASSOC);
        if ($sale) {
          // Restore inventory
          if ($sale['item_id']) {
            $pdo->prepare('UPDATE items SET qty = qty + ? WHERE id=?')->execute([$sale['qty'], $sale['item_id']]);
          }
          
          // Refund from bank only if payment was via bank and status is paid
          if ($sale['status'] === 'paid' && $sale['paid_via'] === 'bank' && $sale['bank_id']) {
            $pdo->prepare('UPDATE banks SET balance_cents = balance_cents - ? WHERE id=?')->execute([$sale['total_cents'], $sale['bank_id']]);
          }
          
          // Delete sale
          $pdo->prepare('DELETE FROM sales WHERE id=?')->execute([$id]);
        }
        $pdo->commit();
        j(['ok' => true]);
        // New action for Pre-paid sales list
      case 'list_prepaid_sales':
        checkPermission('list_prepaid_sales');
        $stmt = $pdo->prepare('SELECT s.*, b.name AS bank_name FROM sales s LEFT JOIN banks b ON s.bank_id=b.id WHERE s.payment_method="Pre-paid" AND s.status="unpaid" AND s.user_id=? ORDER BY s.created_at DESC');
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        j(['sales' => $rows ?? []]);
      case 'list_credit_sales':
        checkPermission('list_credit_sales');
        $stmt = $pdo->prepare('SELECT s.*, b.name AS bank_name FROM sales s LEFT JOIN banks b ON s.bank_id=b.id WHERE s.payment_method="Credit" AND s.status="unpaid" AND s.user_id=? ORDER BY s.created_at DESC');
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        j(['sales' => $rows ?? []]);
      case 'edit_sales_item':
        checkPermission('edit_sales_item');
        $id = (int)($_POST['id']);
        $usn = $_POST['username'];
        $pho = $_POST['phone'];
        $ids = $_POST['ids'] ?? 0;
        $item_name = trim($_POST['name'] ?? '');
        $qty = max(0, (int)($_POST['qty'] ?? 0));
        $price_c = cents($_POST['price'] ?? 0);
        $tax_pct = max(0, (float)($_POST['tax_pct'] ?? 0));
        // $payment_method = trim($_POST['payment_method'] ?? '');
        $paid_via_post = trim($_POST['paid_via'] ?? '');
        // var_dump($paid_via_post);
        $bank_id = null;
        $paid_via = null;
        if (strpos($paid_via_post, 'bank:') === 0) {
          $paid_via = 'bank';
          $bank_id = (int) substr($paid_via_post, 5);
        } else if ($paid_via_post === 'cash') {
          $paid_via = 'cash';
          $bank_id = null;
        } else if ($paid_via_post === 'credit') {
          $paid_via = 'Credit';
          $bank_id = null;
        }
        $prepayment_cents = cents($_POST['prepayment_cents'] ?? 0);
        $old = $pdo->prepare('SELECT * FROM sales WHERE id=? AND user_id=?');
        $old->execute([$id, $user_id]);
        $current = $old->fetch(PDO::FETCH_ASSOC);
        if (!$current) j(['error' => 'Sale not found'], 404);
        $dud = $current['due_date'];
        $total_cents = $qty * $price_c + (int) round(($qty * $price_c) * ($tax_pct / 100));
        $due_date = trim($_POST['due_date']) == "" ? $dud : trim($_POST['due_date']);
        $status = trim($_POST['status'] ?? $current['status']);
        $old_status = $current['status'];
        if ($id <= 0 || $item_name === '') {
          j(['error' => 'Invalid sales item edit'], 400);
        }
        if ($old_status !== 'paid' && $status === 'paid') {
          $remaining = $total_cents - $current['prepayment_cents'];
          if ($remaining > 0) {
            if ($paid_via === 'bank' && $bank_id > 0) {
              $pdo->prepare('UPDATE banks SET balance_cents=balance_cents+? WHERE id=? AND user_id=?')->execute([$remaining, $bank_id, $user_id]);
            } // if cash, no track
          }
        }
        
        // Determine payment_method based on status
        $payment_method = $current['payment_method'];
        if ($status === 'paid' && ($current['payment_method'] === 'Credit' || $current['payment_method'] === 'Pre-paid')) {
          $payment_method = 'Paid';
        }
        
        // Update
        $pdo->prepare('
    UPDATE sales
    SET item_name = ?,
        qty = ?,
        price_cents = ?,
        tax_pct = ?,
        total_cents = ?,
        ids = ?,
        username = ?,
        phone = ?,
        due_date = ?,
        status = ?,
        paid_via = ?,
        bank_id = ?,
        payment_method = ?
    WHERE id = ? AND user_id = ?
')->execute([
          $item_name,
          $qty,
          $price_c,
          $tax_pct,
          $total_cents,
          $ids,
          $usn,
          $pho,
          $due_date,
          $status,
          $paid_via ?? $current['paid_via'],
          $bank_id ?? $current['bank_id'],
          $payment_method,
          $id,
          $user_id
        ]);
        j(['ok' => true]);
        // PURCHASES
      case 'add_purchase':
        checkPermission('add_purchase');

        // Handle image upload (returns path or null)
        $path = null;
        try {
          $path = handleImageUpload(); // assuming this returns string or null
        } catch (\Throwable $th) {
          // Let it bubble up only if critical, or log and continue without image
          // For now, we'll allow purchase without image
          error_log('Image upload failed: ' . $th->getMessage());
          $path = null;
        }

        // Sanitize and validate inputs
        $n  = trim($_POST['item_name'] ?? '');
        $bn = trim($_POST['brand_name'] ?? '');
        $in = trim($_POST['item_number'] ?? '');
        $q  = max(0, (int)($_POST['qty'] ?? 0));
        $p  = cents($_POST['price'] ?? 0); // your helper to convert to cents
        $t  = max(0, (float)($_POST['tax_pct'] ?? 0));
        $pt = $_POST['payment_type'] ?? 'bank';
        $dd = $_POST['due_date'] ?? null;
        $pb = cents($_POST['prepaid_balance'] ?? 0);

        // Parse bank_id properly (supports both "bank:5" and just "5")
        $bid = 0;
        $raw_bank = trim($_POST['bank_id'] ?? '');
        if ($raw_bank !== '') {
          if (str_starts_with($raw_bank, 'bank:')) {
            $bid = (int)substr($raw_bank, 5);
          } else {
            $bid = (int)$raw_bank;
          }
        }

        // Validation
        if (!$n || $q <= 0 || $p <= 0) {
          j(['error' => 'Item name, quantity, and price are required'], 400);
        }

        if ($pt === 'bank') {
          if ($bid <= 0) {
            j(['error' => 'Please select a valid bank account'], 400);
          }
          // Double-check that the bank belongs to the user
          $check = $pdo->prepare('SELECT id FROM banks WHERE id = ? AND user_id = ?');
          $check->execute([$bid, $user_id]);
          if (!$check->fetch()) {
            j(['error' => 'Selected bank account not found'], 400);
          }
        }

        if (($pt === 'credit' || $pt === 'prepaid') && empty($dd)) {
          j(['error' => 'Due date is required for credit/prepaid purchases'], 400);
        }

        if ($pt === 'prepaid' && $pb <= 0) {
          j(['error' => 'Prepaid amount must be greater than zero'], 400);
        }

        // Calculate totals
        $subtotal = $q * $p;
        $tax      = (int)round($subtotal * $t / 100);
        $total    = $subtotal + $tax;

        // Start transaction
        $pdo->beginTransaction();

        try {
          // Check if item already exists by item_number (if provided), otherwise by name
          if (!empty($in)) {
            $stmt = $pdo->prepare('SELECT id, qty FROM items WHERE item_number = ? AND user_id = ?');
            $stmt->execute([$in, $user_id]);
          } else {
            $stmt = $pdo->prepare('SELECT id, qty FROM items WHERE name = ? AND company_id = ?');
            $stmt->execute([$n, $company_id]);
          }
          $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);

          if ($existing_item) {
            // Update existing item
            $update = $pdo->prepare('UPDATE items SET qty = qty + ?, price_cents = ?, tax_pct = ?, image_path = COALESCE(?, image_path) WHERE id = ?');
            $update->execute([$q, $p, $t, $path, $existing_item['id']]);
            $item_id = $existing_item['id'];
          } else {
            // Insert new item
            $insert = $pdo->prepare('INSERT INTO items (name, brand_name, item_number, qty, price_cents, tax_pct, user_id, company_id, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$n, $bn, $in, $q, $p, $t, $user_id, $company_id, $path]);
            $item_id = $pdo->lastInsertId();
          }

          // Insert purchase record
          // bank_id is NULL when not using bank payment
          $purchase_bank_id = ($pt === 'bank') ? $bid : null;

          // Determine status
          $status = ($pt === 'bank' || $pt === 'cash') ? 'paid' : 'pending';

          $purchase_sql = 'INSERT INTO purchases (
            item_id, item_name, brand_name, item_number, qty, price_cents, tax_pct,
            payment_type, bank_id, due_date, prepaid_min_cents, total_cents, user_id, company_id, status, date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

          $pdo->prepare($purchase_sql)->execute([
            $item_id,
            $n,
            $bn,
            $in,
            $q,
            $p,
            $t,
            $pt,
            $purchase_bank_id,
            $dd,
            $pb,
            $total,
            $user_id,
            $company_id,
            $status,
            date('Y-m-d')
          ]);

          // Deduct from bank balance only if paid by bank
          if ($pt === 'bank' && $bid > 0) {
            $deduct = $pdo->prepare('UPDATE banks SET balance_cents = balance_cents - ? WHERE id = ? AND user_id = ?');
            $deduct->execute([$total, $bid, $user_id]);
          }

          $pdo->commit();
          j(['ok' => true, 'item_id' => $item_id]);
        } catch (\Throwable $th) {
          $pdo->rollBack();
          error_log('Purchase failed: ' . $th->getMessage());
          j(['error' => 'Failed to save purchase. Please try again.'], 500);
        }

        break;
      case 'list_purchases':
        // checkPermission('list_purchases');
        $stmt = $pdo->prepare("SELECT
        p.*,
        b.name AS bank_name,
        i.image_path AS item_image_path
    FROM purchases p
    LEFT JOIN banks b ON p.bank_id = b.id
    LEFT JOIN items i ON p.item_id = i.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC");
        $stmt->execute([$user_id]);
        j(['purchases' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
      case 'edit_purchase':
        checkPermission('edit_purchase');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['item_name'] ?? '');
        $brand_name = trim($_POST['brand_name'] ?? '');
        $item_number = trim($_POST['item_number'] ?? '');
        $qty = max(0, (int)($_POST['qty'] ?? 0));
        $price_c = cents($_POST['price'] ?? 0);
        $tax_pct = max(0, (float)($_POST['tax_pct'] ?? 0));
        $bank_id = (int)($_POST['bank_id'] ?? 0);
        $due_date = $_POST['due_date'] ?? null;
        $status = $_POST['status'] ?? 'paid';
        $payment_type = $_POST['payment_type'] ?? null;
        
        // Relaxed validation: bank_id is only required if payment type involves bank (but here we just check basics)
        if ($id <= 0 || $name === '' || $qty <= 0 || $price_c <= 0) j(['error' => 'Invalid purchase edit input'], 400);
        
        $pdo->beginTransaction();
        // Get old purchase data
        $old = $pdo->prepare('SELECT * FROM purchases WHERE id=?');
        $old->execute([$id]);
        $oldPurchase = $old->fetch(PDO::FETCH_ASSOC);
        if (!$oldPurchase) j(['error' => 'Purchase not found'], 404);
        
        // Compute new total
        $subtotal = $qty * $price_c;
        $tax = (int) round($subtotal * ($tax_pct / 100));
        $total = $subtotal + $tax;
        
        // If payment_type is provided, use it; otherwise keep the old one
        if ($payment_type === null) {
          $payment_type = $oldPurchase['payment_type'];
        }
        
        // Update purchase
        $p = $pdo->prepare('UPDATE purchases SET item_name=?, brand_name=?, item_number=?, qty=?, price_cents=?, tax_pct=?, payment_type=?, bank_id=?, due_date=?, total_cents=?, status=? WHERE id=?');
        $p->execute([$name, $brand_name, $item_number, $qty, $price_c, $tax_pct, $payment_type, $bank_id ?: null, $due_date, $total, $status, $id]);
        
        // Adjust bank balances based on payment type and status changes
        $oldWasBank = ($oldPurchase['payment_type'] === 'bank' && $oldPurchase['bank_id'] > 0);
        $newIsBank = ($payment_type === 'bank' && $bank_id > 0);
        $oldWasPaid = ($oldPurchase['status'] === 'paid');
        $newIsPaid = ($status === 'paid');
        
        // Handle bank balance adjustments
        if ($oldWasBank && $newIsBank && $oldWasPaid && $newIsPaid) {
          // Both are paid bank payments
          if ($oldPurchase['bank_id'] == $bank_id) {
            // Same bank, adjust by difference
            $diff = $total - $oldPurchase['total_cents'];
            $pdo->prepare('UPDATE banks SET balance_cents = balance_cents - ? WHERE id=?')->execute([$diff, $bank_id]);
          } else {
            // Different banks, revert old and apply new
            $pdo->prepare('UPDATE banks SET balance_cents = balance_cents + ? WHERE id=?')->execute([$oldPurchase['total_cents'], $oldPurchase['bank_id']]);
            $pdo->prepare('UPDATE banks SET balance_cents = balance_cents - ? WHERE id=?')->execute([$total, $bank_id]);
          }
        } elseif ($oldWasBank && $oldWasPaid && (!$newIsBank || !$newIsPaid)) {
          // Was paid via bank, now either not bank or not paid - refund the old bank
          $pdo->prepare('UPDATE banks SET balance_cents = balance_cents + ? WHERE id=?')->execute([$oldPurchase['total_cents'], $oldPurchase['bank_id']]);
        } elseif ($newIsBank && $newIsPaid && (!$oldWasBank || !$oldWasPaid)) {
          // Now paid via bank, but wasn't before - deduct from new bank
          $pdo->prepare('UPDATE banks SET balance_cents = balance_cents - ? WHERE id=?')->execute([$total, $bank_id]);
        } elseif ($oldWasBank && !$oldWasPaid && $newIsBank && !$newIsPaid) {
          // Both unpaid bank purchases, might have changed banks or amounts
          if ($oldPurchase['bank_id'] != $bank_id) {
            // Different banks but both unpaid - no balance changes needed yet
          }
        }
        // Note: Cash purchases don't affect bank balances, only bank purchases do
        
        $pdo->commit();
        j(['ok' => true]);
        // SALES
      case 'add_sale':
        checkPermission('add_sale');
        $item_id = (int)$_POST['item_id'];
        $q = max(0, (int)$_POST['qty']);
        $usn = $_POST['username'];
        $pho = $_POST['phone'];
        $p = cents($_POST['price']);
        $t = max(0, (float)$_POST['tax_pct']);
        $pm = $_POST['payment_method'];
        $ids = $_POST['ids'] ?? 0;
        $pv = $_POST['paid_via'] ?? null;
        $bid = null;
        $pv2 = null;
        if ($pm === 'Paid') {

          if ($pv === 'bank') {
            if ($bid <= 0) j(['error' => 'Select a bank'], 400);
            $pv2 = 'bank';
          } else {
            $pv2 = 'cash';
            $bid = null;
          }
        } elseif ($pm === 'Pre-paid') {
          // Check prepaid balance
          if ($bid <= 0) j(['error' => 'Select a bank for prepaid'], 400);
          
          // Calculate available prepaid balance from purchases
          $stmt = $pdo->prepare("
            SELECT 
              COALESCE(SUM(prepaid_min_cents), 0) as total_prepaid,
              (SELECT COALESCE(SUM(prepayment_cents), 0) FROM sales WHERE bank_id=? AND company_id=? AND payment_method='Pre-paid') as used_prepaid
            FROM purchases 
            WHERE bank_id=? AND company_id=? AND payment_type='prepaid'
          ");
          $stmt->execute([$bid, $company_id, $bid, $company_id]);
          $res = $stmt->fetch(PDO::FETCH_ASSOC);
          $balance = $res['total_prepaid'] - $res['used_prepaid'];
          
          $st_temp = $q * $p;
          $tx_temp = (int)round($st_temp * $t / 100);
          $tot_temp = $st_temp + $tx_temp;
          
          if ($balance < $tot_temp) {
            j(['error' => 'Insufficient prepaid balance. Available: ' . number_format($balance / 100, 2)], 400);
          }
          
          $pm = 'Paid'; // Mark as paid since it's covered by prepaid
          $pv2 = 'prepaid';
          $bid = null; // No direct bank transaction for this sale, it's from prepaid balance
        } else if ($pm === 'Pay with Prepaid') {
          // Calculate available balance
          // Credits: Pre-paid sales (pending)
          $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cents),0) FROM sales WHERE user_id=? AND phone=? AND payment_method='Pre-paid' AND status='pending'");
          $stmt->execute([$user_id, $pho]);
          $credits = (int)$stmt->fetchColumn();
          
          // Debits: Sales paid via prepaid
          $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cents),0) FROM sales WHERE user_id=? AND phone=? AND paid_via='prepaid'");
          $stmt->execute([$user_id, $pho]);
          $debits = (int)$stmt->fetchColumn();
          
          $balance = $credits - $debits;
          
          // Calculate total for this sale
          $st_temp = $q * $p;
          $tx_temp = (int)round($st_temp * $t / 100);
          $tot_temp = $st_temp + $tx_temp;
          
          if ($balance < $tot_temp) {
            j(['error' => 'Insufficient prepaid balance. Available: ' . number_format($balance / 100, 2)], 400);
          }
          
          $pm = 'Paid';
          $pv2 = 'prepaid';
          $bid = null;
        }
        $prep = cents($_POST['prepayment'] ?? 0);
        $dd = $_POST['due_date'] ?? null;
        if ($item_id <= 0 || $q <= 0 || $p <= 0 || !in_array($pm, ['Paid', 'Pre-paid', 'Credit'])) j(['error' => 'Invalid'], 400);
        
        try {
          $stmt = $pdo->prepare('SELECT id, name, qty, item_number FROM items WHERE id = ? AND company_id = ?');
          $stmt->execute([$item_id, $company_id]);
          $it = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
          $it = null; 
        }
        if (!$it || $it['qty'] < $q) j(['error' => 'No stock'], 400);
        $st = $q * $p;
        $tx = (int)round($st * $t / 100);
        $tot = $st + $tx;
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE items SET qty=qty-? WHERE id=? AND company_id=?')->execute([$q, $item_id, $company_id]);
        $status = ($pm === 'Paid') ? 'paid' : 'unpaid';
        $pdo->prepare('INSERT INTO sales(user_id,company_id,item_id,item_name,qty,price_cents,tax_pct,payment_method,paid_via,bank_id,prepayment_cents,due_date,total_cents,ids,username,phone,status,item_number,date) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$user_id, $company_id, $item_id, $it['name'], $q, $p, $t, $pm, $pv2, $bid, $prep, $dd, $tot, $ids, $usn, $pho, $status, $it['item_number'], date('Y-m-d')]);
        if ($pm === 'Paid' && $pv2 === 'bank' && $bid) $pdo->prepare('UPDATE banks SET balance_cents=balance_cents+? WHERE id=? AND company_id=?')->execute([$tot, $bid, $company_id]);
        if ($pm === 'Pre-paid' && $bid) $pdo->prepare('UPDATE banks SET balance_cents=balance_cents+? WHERE id=? AND company_id=?')->execute([$prep, $bid, $company_id]);
        $pdo->commit();
        j(['ok' => true]);
        
      case 'list_sales':
        // checkPermission('list_sales');
        $stmt = $pdo->prepare("SELECT
            s.*,
            b.name AS bank_name,
            i.image_path AS item_image_path
        FROM sales s
        LEFT JOIN banks b ON s.bank_id = b.id
        LEFT JOIN items i ON s.item_id = i.id
        WHERE s.company_id = ?
        ORDER BY s.created_at DESC");
        $stmt->execute([$company_id]);
        j(['sales' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'low_stock' => $pdo->query("SELECT id,name,item_number,qty,price_cents,tax_pct FROM items WHERE company_id=$company_id AND qty<=5 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC)]);
        // DASHBOARD
      case 'dashboard':
        // checkPermission('dashboard');
        $stmt = $pdo->prepare("SELECT id,name,balance_cents FROM banks WHERE user_id=? ORDER BY name");
        $stmt->execute([$user_id]);
        $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $bank_total = array_sum(array_column($banks, 'balance_cents'));
        // Calculate totals for the period using created_at adjusted for timezone (+3 hours for Ethiopia)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cents),0) FROM sales WHERE company_id = ? AND date(created_at, '+3 hours') BETWEEN ? AND ? AND status='paid'");
        $stmt->execute([$company_id, $start, $end]);
        $sales_total = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cents),0) FROM purchases WHERE company_id = ? AND date(created_at, '+3 hours') BETWEEN ? AND ? AND status='paid'");
        $stmt->execute([$company_id, $start, $end]);
        $purchases_total = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_cents),0) FROM misc WHERE company_id = ? AND date(created_at, '+3 hours') BETWEEN ? AND ?");
        $stmt->execute([$company_id, $start, $end]);
        $misc_total = (int)$stmt->fetchColumn();
        
        // Calculate purchase credit (pending purchases including prepaid)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_cents-prepaid_min_cents),0) FROM purchases WHERE user_id=? AND payment_type IN ('credit', 'prepaid') AND status='pending'");
        $stmt->execute([$user_id]);
        $purchase_credit = (int)$stmt->fetchColumn();
        
        // Combine credits if needed, or return separately. User asked for "credit box" to include prepaid.
        // Assuming $credit variable is used for sales credit box.
        // If there's a separate purchase credit box, we need to find where it is used.
        // For now, let's update the $prepaid variable to be 0 or used differently if needed, 
        // but the request was "prepaid also count as credit".
        
        // $prepaid = 0; // No longer separating prepaid pending, merged into credit
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchases WHERE user_id=?");
        $stmt->execute([$user_id]);
        $purchases_count = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE user_id=?");
        $stmt->execute([$user_id]);
        $sales_count = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM purchases WHERE user_id=?");
        $stmt->execute([$user_id]);
        $purchasees_list = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare("SELECT 'sale' AS type,id,item_name AS name,due_date,date,payment_method FROM sales WHERE user_id=? AND payment_method IN ('Pre-paid','Credit') AND due_date IS NOT NULL AND date(due_date)<=date('now','+7 day') AND status='unpaid' UNION ALL SELECT 'purchase' AS type,id,item_name AS name,due_date,date,'Purchase' FROM purchases WHERE user_id=? AND due_date IS NOT NULL AND date(due_date)<=date('now','+7 day') AND status!='paid' ORDER BY due_date");
        $stmt->execute([$user_id, $user_id]);
        $notif = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT id,name,qty FROM items WHERE user_id=? AND qty<=5 ORDER BY qty,name");
        $stmt->execute([$user_id]);
        $low = $stmt->fetchAll(PDO::FETCH_ASSOC);
        j(['bank_total_cents' => $bank_total, 'banks' => $banks, 'cash_total_cents' => $cash, 'credit_cents' => $credit, 'prepaid_cents' => $prepaid, 'purchases_count' => $purchases_count, 'sales_count' => $sales_count, 'purchasees_list' => $purchasees_list, 'notifications' => $notif, 'low_stock' => $low, 'purchase_credit_cents' => $purchase_credit]);
        // MISC
      case 'add_misc':
        checkPermission('add_misc');
        $n = trim($_POST['name']);
        $r = trim($_POST['reason'] ?? '');
        $a = cents($_POST['amount']);
        $bid_raw = trim($_POST['bank_id'] ?? '');
        
        // Parse bank_id (handle "bank:123" format from select)
        // If empty or "cash", set to NULL for cash payment
        if (empty($bid_raw) || $bid_raw === 'cash') {
          $bid = null;
        } elseif (strpos($bid_raw, 'bank:') === 0) {
          $bid = (int) substr($bid_raw, 5);
        } else {
          $bid = (int) $bid_raw;
        }
        
        $d = $_POST['date'] ?? null;
        if (!$n) j(['error' => 'Expense name is required'], 400);
        if ($a <= 0) j(['error' => 'Amount must be greater than 0'], 400);
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO misc(user_id,company_id,name,reason,amount_cents,bank_id,date) VALUES(?,?,?,?,?,?,COALESCE(?,?))')->execute([$user_id, $company_id, $n, $r, $a, $bid, $d, date('Y-m-d')]);
        // Only deduct from bank if bank_id is provided
        if ($bid) {
          $pdo->prepare('UPDATE banks SET balance_cents=balance_cents-? WHERE id=? AND company_id=?')->execute([$a, $bid, $company_id]);
        }

        $pdo->commit();
        j(['ok' => true]);
      case 'list_misc':
        // checkPermission('list_misc');
        $stmt = $pdo->prepare("SELECT m.*,b.name AS bank_name FROM misc m LEFT JOIN banks b ON m.bank_id=b.id WHERE m.company_id=? ORDER BY m.created_at DESC");
        $stmt->execute([$company_id]);
        j(['misc' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        // REPORTS
      case 'reports':
        // checkPermission('reports');
        $period = $_GET['period'] ?? 'monthly';
        [$start, $end] = periodRange($period, $_GET['from'] ?? null, $_GET['to'] ?? null);
        $stmt = $pdo->prepare("SELECT * FROM purchases WHERE company_id=? AND date(created_at, '+3 hours') BETWEEN ? AND ? ORDER BY date DESC");
        $stmt->execute([$company_id, $start, $end]);
        $p = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Credit Pending (Sales)
        $credit_pending = $pdo->query("SELECT * FROM sales WHERE company_id=$company_id AND payment_method='credit' AND status='pending' ORDER BY due_date ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Prepaid Pending (Sales)
        $prepaid_pending = $pdo->query("SELECT * FROM sales WHERE company_id=$company_id AND payment_method='prepaid' AND status='pending' ORDER BY due_date ASC")->fetchAll(PDO::FETCH_ASSOC);

        // Credit Pending (Purchases)
        $credit_purchases = $pdo->query("SELECT * FROM purchases WHERE company_id=$company_id AND payment_type='credit' AND status='pending' ORDER BY due_date ASC")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE company_id=? AND date(created_at, '+3 hours') BETWEEN ? AND ? ORDER BY date DESC");
        $stmt->execute([$company_id, $start, $end]);
        $s = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare("SELECT * FROM misc WHERE company_id=? AND date(created_at, '+3 hours') BETWEEN ? AND ? ORDER BY date DESC");
        $stmt->execute([$company_id, $start, $end]);
        $m = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sum = function ($rows, $f) {
          $t = 0;
          foreach ($rows as $r) $t += (int)$r[$f];
          return $t;
        };
        $profit = $sum($s, 'total_cents') - $sum($p, 'total_cents');
        j(['range' => [$start, $end], 'purchases' => $p, 'sales' => $s, 'misc' => $m, 'totals' => ['purchases_cents' => $sum($p, 'total_cents'), 'sales_cents' => $sum($s, 'total_cents'), 'misc_cents' => $sum($m, 'amount_cents'), 'tax_collected_cents' => array_sum(array_map(fn($s) => (int)round($s['qty'] * $s['price_cents'] * $s['tax_pct'] / 100), $s)), 'profit_cents' => $profit]]);
      default:
        j(['error' => 'Unknown'], 404);
    }
  } catch (Throwable $e) {
    j(['error' => $e->getMessage()], 500);
  }
}
// ---------- AUTH PAGES ----------
if (isset($_GET['action']) && $_GET['action'] === 'login') {
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="./index.js"></script>
  </head>

  <body class="bg-gradient-to-r from-blue-500 to-purple-600 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md">
      <h2 class="text-2xl font-bold mb-6 text-center">Sign In</h2>
      <form id="loginForm" class="space-y-4">
        <input type="text" name="username" placeholder="Username or Email" required class="w-full p-2 border rounded">
        <input type="password" name="password" placeholder="Password" required class="w-full p-2 border rounded">
        <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded hover:bg-blue-700">Sign In</button>
        <p class="text-center"><a href="?action=register" class="text-blue-600">Create account</a></p>
      </form>
      <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
          e.preventDefault();
          const f = new FormData(e.target);
          f.append('action', 'login');
          const r = await fetch(location.href, {
            method: 'POST',
            body: f
          });
          const d = await r.json();
          d.ok ? location.href = d.redirect : alert(d.error);
        });
      </script>
    </div>
  </body>

  </html><?php exit;
        }
        if (isset($_GET['action']) && $_GET['action'] === 'register') {
          ?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="./index.js"></script>
    <style>
      .onio {
        margin-bottom: 10rem !important;
        /*
                margin-bottom: 10rem 1important;
                .mb-\[30rem\] {
  margin-bottom: 30rem !important; */
      }

      /* Style for label containing checked radio input */
      label:has(input[type="radio"]:checked) {
        background-color: #ede9fe;
        /* Tailwind purple-100 */
        border-color: #7c3aed;
        /* Tailwind purple-600 */
      }
    </style>
  </head>

  <body class="bg-gradient-to-r from-purple-500 rounded-lg to-pink-600 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
      <!-- <h2 class="text-2xl font-bold mb-6 text-center">Create Account</h2> -->
      <div class=" rounded-lg flex items-center justify-center bg-gray-100">
        <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
          <h2 class="text-2xl font-bold text-center mb-6 text-purple-600">Register</h2>
          <form id="registerForm" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Select Package</label>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <label class="flex flex-col items-center p-4 border rounded-lg cursor-pointer transition-all border-gray-300 hover:border-purple-400">
                  <input type="radio" name="pkg" value="3" required class="hidden">
                  <span class="text-lg font-semibold text-gray-800">3 Months</span>
                  <span class="text-sm text-gray-600">$30</span>
                </label>
                <label class="flex flex-col items-center p-4 border rounded-lg cursor-pointer transition-all border-gray-300 hover:border-purple-400">
                  <input type="radio" name="pkg" value="6" required class="hidden">
                  <span class="text-lg font-semibold text-gray-800">6 Months</span>
                  <span class="text-sm text-gray-600">$50</span>
                </label>
                <label class="flex flex-col items-center p-4 border rounded-lg cursor-pointer transition-all border-gray-300 hover:border-purple-400">
                  <input type="radio" name="pkg" value="12" required class="hidden">
                  <span class="text-lg font-semibold text-gray-800">1 Year</span>
                  <span class="text-sm text-gray-600">$90</span>
                </label>
              </div>
            </div>
            <input type="text" name="username" placeholder="Username" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-600">
            <input type="email" name="email" placeholder="Email" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-600">
            <input type="password" name="password" placeholder="Password (≥6 chars)" required minlength="6" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-600">
            <input type="password" name="confirm_password" placeholder="Confirm Password" required class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-600">
            <button type="submit" class="w-full bg-purple-600 text-white p-2 rounded hover:bg-purple-700 transition-colors">Register</button>
            <p class="text-center text-sm">
              <a href="?action=login" class="text-purple-600 hover:underline">Already have an account? Sign in</a>
            </p>
          </form>
        </div>
      </div>
      <script>
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
          e.preventDefault();
          const f = new FormData(e.target);
          f.append('action', 'register');
          const r = await fetch(location.href, {
            method: 'POST',
            body: f
          });
          const d = await r.json();
          console.log(d);
          d.ok ? location.href = d.redirect : alert(d.error);
        });
      </script>
    </div>
  </body>

  </html><?php exit;
        }
        requireLogin();
          ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inventory - <?= htmlspecialchars($_SESSION['username']) ?></title>
  <script src="./index.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#f0f9ff',
              100: '#e0f2fe',
              500: '#0ea5e9',
              600: '#0284c7',
              700: '#0369a1',
              800: '#075985',
              900: '#0c4a6e'
            },
            success: {
              50: '#f0fdf4',
              500: '#22c55e',
              600: '#16a34a'
            },
            warning: {
              50: '#fefce8',
              500: '#eab308',
              600: '#ca8a04'
            },
            danger: {
              50: '#fef2f2',
              500: '#ef4444',
              600: '#dc2626'
            }
          }
        }
      }
    }
  </script>
  <style>
    body {
      background: linear-gradient(to bottom, #87CEEB, #ADD8E6);
      font-family: 'Noto Sans Ethiopic', sans-serif;
    }

    .stat-card {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .stat-card {
      background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%);
      color: white;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .btn {
      @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition;
    }

    /* Modern Modal Styles */
    .onio {
      margin-bottom: 10rem !important;
      /*
                margin-bottom: 10rem 1important;
                .mb-\[30rem\] {
  margin-bottom: 30rem !important; */
    }

    dialog {
      padding: 0;
      border: none;
      border-radius: 16px;
      background: transparent;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      backdrop-filter: blur(8px);
    }

    dialog::backdrop {
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(2px);
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      overflow: hidden;
    }

    /* Professional Card Styles */
    /* .stat-card {
            background: #1100ffff;
            color: white;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        } */
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* .stat-card.cash {
            background: #1100ffff;
        }
        .stat-card.purchases {
            background: #1100ffff;
        }
        .stat-card.sales {
            background: #1100ffff;
        }
        .stat-card.balance {
            background: #1100ffff;
        }
        .stat-card.credit {
            background: #1100ffff;
        }
        .stat-card.prepaid {
            background: #1100ffff;
        } */
    /* Alert Cards */
    .alert-card {
      border-left: 4px solid;
      background: white;
      transition: all 0.2s ease;
    }

    .alert-card:hover {
      transform: translateX(4px);
    }

    .alert-overdue {
      border-left-color: #ef4444;
      background: #fef2f2;
    }

    .alert-upcoming {
      border-left-color: #f59e0b;
      background: #fffbeb;
    }

    .alert-low-stock {
      border-left-color: #eab308;
      background: #fefce8;
    }

    /* Button Styles */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      border-radius: 0.75rem;
      font-weight: 500;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      border: none;
      cursor: pointer;
      gap: 0.5rem;
    }

    .button-container {
      position: fixed;
      margin: auto 0;
      bottom: 80px;
      /* Slightly above the bottom nav bar */
      /* left: 30%; */
      /* transform: translateX(-50%); */
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      margin-left: 29%;
      margin-right: 20%;
      gap: 20px;
    }

    .buy-button,
    .sell-button {
      padding: 12px 24px;
      font-size: 16px;
      font-weight: bold;
      margin: auto 0;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .buy-button {
      background-color: #28a745;
      /* Green color for Buy */
      color: white;
    }

    .buy-button:hover {
      background-color: #218838;
    }

    .sell-button {
      background-color: #dc3545;
      /* Red color for Sell */
      color: white;
    }

    .sell-button:hover {
      background-color: #c82333;
    }

    .btn-primary {
      background: #0284c7;
      color: white;
    }

    .btn-primary:hover {
      background: #0369a1;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(2, 132, 199, 0.3);
    }

    .btn-success {
      background: #16a34a;
      color: white;
    }

    .btn-success:hover {
      background: #15803d;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(22, 163, 74, 0.3);
    }

    .btn-warning {
      background: #ca8a04;
      color: white;
    }

    .btn-warning:hover {
      background: #a16207;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(202, 138, 4, 0.3);
    }

    .btn-danger {
      background: #dc2626;
      color: white;
    }

    .btn-danger:hover {
      background: #b91c1c;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(220, 38, 38, 0.3);
    }

    .btn-secondary {
      background: #f1f5f9;
      color: #475569;
      border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
      background: #e2e8f0;
      transform: translateY(-1px);
    }

    .btn-error {
      background: #dc2626;
      color: white;
    }

    .btn-info {
      background: #3b82f6;
      color: white;
    }

    /* Form Styles */
    .form-input {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.75rem;
      font-size: 0.875rem;
      transition: all 0.2s ease;
      background: white;
    }

    .form-input:focus {
      outline: none;
      border-color: #0284c7;
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.1);
    }

    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 500;
      color: #374151;
      margin-bottom: 0.5rem;
    }

    /* Table Styles */
    .data-table {
      width: 100%;
      font-size: 0.875rem;
    }

    .data-table th {
      padding: 1rem 0.75rem;
      text-align: left;
      font-weight: 600;
      color: #6b7280;
      background: #f9fafb;
      border-bottom: 1px solid #e5e7eb;
    }

    .data-table td {
      padding: 0.875rem 0.75rem;
      border-bottom: 1px solid #f3f4f6;
    }

    .data-table tbody tr:hover {
      background: #f9fafb;
    }

    /* Professional Cards */
    .card {
      background: white;
      border-radius: 1rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      border: 1px solid #f1f5f9;
      transition: all 0.2s ease;
    }

    .card:hover {
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
    }

    .toaste {
      z-index: 1000;
    }

    /* Status Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .badge-success {
      background: #dcfce7;
      color: #166534;
    }

    .badge-warning {
      background: #fef3c7;
      color: #92400e;
    }

    .badge-danger {
      background: #fecaca;
      color: #991b1b;
    }

    .badge-info {
      background: #dbeafe;
      color: #1e40af;
    }

    .badge-error {
      background: #fecaca;
      color: #991b1b;
    }

    /* Animation */
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .slide-in {
      animation: slideIn 0.3s ease-out;
    }
  </style>
</head>

<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
  <!-- Navigation Header -->
  <nav class="bg-white shadow-sm border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between items-center ">
        <div class="flex justify-between items-center py-2">
          <div class="flex items-center space-x-3">
            <!--
                        <button id="btnReports" class="btn btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Reports
                        </button> -->
          </div>
        </div>
        <div class="flex items-center space-x-4">
          <span class="text-sm">Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
          </span>
          <a href="?action=logout" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600">Logout</a>
        </div>
      </div>
    </div>
  </nav>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Dashboard Stats -->
    <section class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div id="cardBalance" class="stat-card balance p-2 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Total Bank Balance</p>
            <p id="bankTotal" class="text-3xl font-bold mt-2 number-displaybank">0.00</p>
            <p class="text-xs opacity-70 mt-1">Click to manage banks</p>
          </div>
        </div>
      </div>
      <div id="cardPurchases" class="stat-card purchases p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Total Purchases</p>
            <div class="flex flex-row">
              <p id="purchaseCount" class="text-3xl font-bold mt-2">0</p>
              <p class="text-3xl font-bold mt-2">|</p>
              <p id="purchasees_list" class="text-3xl font-bold mt-2"></p>
            </div>
            <p class="text-xs opacity-70 mt-1">Click to view history</p>
          </div>
          <!-- <div class="bg-white/20 p-3 rounded-lg">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 2M7 13l1.5 2m7.5-2a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                    </div> -->
        </div>
      </div>
      <div id="cardSales" class="stat-card sales p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Total Sales</p>
            <p id="salesCount" class="text-3xl font-bold mt-2 money-kalc">0</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div>
      <!-- <div id="cardCusts" class="stat-card sales p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Total Customers</p>
            <p id="custsCount" class="text-3xl font-bold mt-2 money-kalc">0</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div> -->
      <div id="cardCash" class="stat-card cash p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Cash on Hand</p>
            <p id="cashTotal" class="text-3xl font-bold mt-2 number-display cashTotal">0.00</p>
            <p class="text-xs opacity-70 mt-1">Cash sales total</p>
          </div>
        </div>
      </div>
      <div id="cardCredit" class="stat-card credit p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Credit Due</p>
            <p id="creditTotal" class="creditTotals text-3xl font-bold mt-2 money-kalc"></p>
            <p class="text-xs opacity-70 mt-1">Click to view Credit sales</p>
          </div>
        </div>
      </div>
      <div id="cardPrepaid" class="stat-card prepaid p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Pre-paid Due</p>
            <p id="prepaidTotal" class="text-3xl font-bold mt-2"></p>
            <p class="text-xs opacity-70 mt-1">Click to view Pre-paid sales</p>
          </div>
        </div>
      </div>
      <div id="cardPurchasePrepaid" class="stat-card prepaid p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Purchase Prepaid</p>
            <p id="purchasePrepaidTotal" class="text-3xl font-bold mt-2">$0.00</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div>
      <div id="cardPurchaseCredit" class="stat-card credit p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Purchase Credit</p>
            <p id="purchaseCreditTotal" class="text-3xl font-bold mt-2">$0.00</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div>
      <div id="cardPurchaseCash" class="stat-card cash p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Cash Purchases</p>
            <p id="purchaseCashTotal" class="text-3xl font-bold mt-2 purchaseCashTotals">$0.00</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div>
      <div id="cardPurchaseBank" class="stat-card balance p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Bank Purchases</p>
            <p id="purchaseBankTotal" class="text-3xl font-bold mt-2 purchaseBankTotals">0.00</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div>
      <div id="btnMisc" class="stat-card balance p-6 rounded-xl cursor-pointer slide-in">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm opacity-80">Petty Cash</p>
            <p class="text-xs opacity-70 mt-1">Click to view details</p>
          </div>
        </div>
      </div>
    </section>
    <!-- Alerts & Notifications -->
    <section class="grid lg:grid-cols-2 mb-[30rem] onio gap-6">
      <div class="card p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-orange-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            Upcoming & Overdue
          </h2>
        </div>
        <div id="notifList" class="space-y-3"></div>
      </div>
      <div class="card p-6 ">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-semibold text-gray-900 flex items-center">
            <svg class="w-5 h-5 mr-2 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
              <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-1.91l-.01-.01L23 10z" />
            </svg>
            Low Stock Alert
          </h2>
          <span class="badge badge-warning">Qty ≤ 5</span>
        </div>
        <div id="lowStockList" class="space-y-3"></div>
      </div>
    </section>
    <!-- Buy/Sell Buttons Container -->
    <!-- <div class="fixed bottom-14 left-0 z-20">
           
        </div> -->
    <!-- <div class=" fixed mx-auto mb-24 sm:px-6 lg:px-8 py-4 flex justify-center ">
                <button class=" bg-green-500 text-white mb-2 py-2 rounded-md hover:bg-green-600 transition">
                    Buy
                </button>
                <button class=" bg-red-500 text-white mb-2 py-2 rounded-md hover:bg-blue-600 transition">
                    Sell
                </button>
            </div> -->
    <!-- <div class="button-container">
            <button id="buyButton" class="buy-button">Buy</button>
            <button id="sellButton" class="sell-button">Sell</button>
        </div> -->
    <!-- Buy and Sell Buttons -->
    <div class="fixed bottom-24 left-1/4 transform-translate-x-1/2 flex gap-4 z-20 w-full max-w-md ">
      <button id="buyButton" class="flex-1 max-w-[100px] bg-green-800 text-white font-bold py-3 px-6 rounded-md hover:bg-green-600 transition-colors text-base sm:text-sm">Buy</button>
      <button id="sellButton" class="flex-1 max-w-[100px] bg-red-500 text-white font-bold py-3 px-6 rounded-md hover:bg-red-600 transition-colors text-base sm:text-sm">Sell</button>
    </div>
    <!-- Add Purchase Section -->
    <dialog id="modalpurchaseSection">
      <section class="card ">
        <div class="bg-gradient-to-r from-green-500 to-teal-600 text-white p-6">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-bold flex items-center">
              <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              Add New Purchase
            </h3>
            <!-- <button class=" px-4 btn bg-red-900 hover:bg-white/30 text-white border-0" onclick="closeModal('')">Close</button> -->
            <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalpurchaseSection')">Close</button>
          </div>
        </div>
        <form id="formPurchase" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-4"
          enctype="multipart/form-data">
          <div>
            <label class="form-label">Item Name</label>
            <input name="item_name" class="form-input" placeholder="Enter item name" required />
          </div>
          <div>
            <label class="form-label">Brand Name</label>
            <input name="brand_name" class="form-input" placeholder="Enter brand name" />
          </div>
          <div>
            <label class="form-label">Item Number</label>
            <input name="item_number" class="form-input" placeholder="Enter item number" />
          </div>
          <div>
            <label class="form-label">Quantity</label>
            <input name="qty" type="number" min="1" class="form-input" placeholder="0" required value="1" />
          </div>
          <div>
            <label class="form-label">Price per Unit</label>
            <input name="price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" required />
          </div>
          <div>
            <label class="form-label">VAT %</label>
            <input name="tax_pct" type="number" min="0" step="0.01" class="form-input" placeholder="0" value="0" />
          </div>
          <div>
            <label class="form-label">Payment Method</label>
            <select name="payment_type" id="purchasePaymentType" class="form-input" required>
              <option value="">Select Method</option>
              <option value="cash">Cash</option>
              <option value="bank">Bank</option>
              <option value="credit">Credit</option>
              <option value="prepaid">Pre-paid</option>
            </select>
          </div>
            <div id="prepayBankWrapper" class="hidden">
              <label class="form-label">Prepayment Received Via</label>
              <select name="prepay_via" id="prepayBank" class="form-input">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
              </select>
            </div>
          <div id="purchaseBankWrapper" class="hidden">
            <label class="form-label">Bank</label>
            <select name="bank_id" id="purchaseBank" class="form-input">
              <option value="">Select Bank</option>
            </select>
          </div>
          <div id="purchaseDueDateWrapper" class="hidden">
            <label class="form-label">Due Date</label>
            <input name="due_date" type="date" class="form-input" />
          </div>
          <div id="purchasePrepaidWrapper" class="hidden">
            <label class="form-label">Prepaid Amount</label>
            <input name="prepaid_balance" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
          </div>
          <div class="md:col-span-6">
            <label class="form-label">Item Image</label>
            <input type="file" name="image" accept="image/*" class="form-input">
            <small class="text-gray-500">Max 5MB - PNG, JPG, GIF, WebP</small>
          </div>
          <div class="sm:col-span-2 md:col-span-6">
            <button class="btn btn-success" type="submit" id="btnAddPurchase">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
              </svg>
              Buy
            </button>
          </div>
        </form>
      </section>
    </dialog>
    <!-- Inventory Table -->
    <!-- Modals -->
    <dialog id="modalSellinventorySection">
      <section class="card ">
        <!-- <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 sm:mb-0 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 00-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10 2 10 2 10 2 4 4 4 10v14h16V6zm-2 16H6v-1a1 1 0 010-1s4 0 6-2c2 2 6 2 6 2a1 1 0 010 1v1z" />
                        </svg>
                      
                    </h2>
                    <div class="w-full sm:w-auto">
                        <input id="searchItems" placeholder="Search inventory..." class="form-input max-w-xs" />
                    </div>
                    <button class="btn bg-red-900 z-20 text-white border-1 p-4" onclick="closeModal('modalSellinventorySection')">Close</button>
                </div> -->
        <div class="bg-gradient-to-r from-green-500 to-teal-600 text-white p-6">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-bold flex items-center">
              <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              Current Inventory
            </h3>
            <!-- <button class=" px-4 btn bg-red-900 hover:bg-white/30 text-white border-0" onclick="closeModal('')">Close</button> -->
            <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalSellinventorySection')">Close</button>
          </div>
        </div>
        <div class="w-full sm:w-auto">
          <input id="searchItems" placeholder="Search inventory..." class="form-input max-w-xs" />
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Stock Qty</th>
                <th>Brand Name</th>
                <th>Item Number</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="itemsTable"></tbody>
          </table>
        </div>
      </section>
    </dialog>
    <!-- <section class="card p-6 mb-8 py-32">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4 sm:mb-0 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 00-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10 2 10 2 10 2 4 4 4 10v14h16V6zm-2 16H6v-1a1 1 0 010-1s4 0 6-2c2 2 6 2 6 2a1 1 0 010 1v1z" />
                    </svg>
                    Current Inventory
                </h2>
                <div class="w-full sm:w-auto">
                    <input id="searchItems" placeholder="Search inventory..." class="form-input max-w-xs" />
                </div>
                <button class="btn bg-red/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPurchaseCash')">Close</button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Stock Qty</th>
                            <th>Brand Name</th>
                            <th>Item Number</th>
                            <th>Unit Price</th>
                            <th>Tax %</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTable"></tbody>
                </table>
            </div>
        </section> -->
    <script>
      document.getElementById('purchasePaymentType').addEventListener('change', function() {
        let method = this.value;
        document.getElementById('purchaseBankWrapper').classList.add('hidden');
        document.getElementById('purchaseDueDateWrapper').classList.add('hidden');
        document.getElementById('purchasePrepaidWrapper').classList.add('hidden');
        // Remove required attribute from all fields initially
        document.querySelector('[name="bank_id"]').removeAttribute('required');
        document.querySelector('[name="due_date"]').removeAttribute('required');
        document.querySelector('[name="prepaid_balance"]').removeAttribute('required');
        if (method === 'bank') {
          document.getElementById('purchaseBankWrapper').classList.remove('hidden');
          document.querySelector('[name="bank_id"]').setAttribute('required', 'required');
        }
        if (method === 'credit') {
          document.getElementById('purchaseDueDateWrapper').classList.remove('hidden');
          document.querySelector('[name="due_date"]').setAttribute('required', 'required');
        }
        if (method === 'prepaid') {
          document.getElementById('purchasePrepaidWrapper').classList.remove('hidden');
          document.getElementById('purchaseDueDateWrapper').classList.remove('hidden');
          document.querySelector('[name="due_date"]').setAttribute('required', 'required');
          document.querySelector('[name="prepaid_balance"]').setAttribute('required', 'required');
        }
      });
    </script>
    <!-- Bottom Navbar -->
    <div class="fixed w-full bottom-0 left-0 bg-white shadow-md z-20">
      <div class=" mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-around items-center">
        <button class="flex flex-col items-center text-gray-600 hover:text-blue-500">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
          </svg>
          <span class="text-xs mt-1">Home</span>
        </button>
        <!-- <button class="flex flex-col items-center text-gray-600 hover:text-blue-500">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.22-1.79L9 14v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1h-6v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z" />
                    </svg>
                    <span class="text-xs mt-1">News</span>
                </button> -->
        <a href="reports.php">
          <button class="flex flex-col items-center text-gray-600 hover:text-blue-500">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
            </svg>
            <span class="text-xs mt-1">Report</span>
          </button>
        </a>
        <a href="pro.php">
          <button class="flex flex-col items-center text-gray-600 hover:text-blue-500 ">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
            </svg>
            <span class="text-xs mt-1">Profile</span>
          </button>
        </a>
      </div>
    </div>
  </div>
  <dialog id="modalPurchaseCash">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            Cash Purchases
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPurchaseCash')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Brand</th>
                <th>Item Number</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="purchaseCashTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Bank Purchases Modal -->
  <dialog id="modalPurchaseBank">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            Bank Purchases
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPurchaseBank')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Brand</th>
                <th>Item Number</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Bank</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="purchaseBankTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Banks Modal -->
  <dialog id="modalBanks">
    <div class="modal-content w-full max-w-4xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            Bank Accounts Management
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalBanks')">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" />
            </svg>
            Close
          </button>
        </div>
      </div>
      <div class="p-6">
        <div id="banksList" class="grid gap-4 mb-6"></div>
        <div class="border-t pt-6">
          <h4 class="text-lg font-semibold mb-4">Add New Bank Account</h4>
          <form id="formBank" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="form-label">Bank Name</label>
              <input name="name" placeholder="e.g. Chase Checking" class="form-input" required />
            </div>
            <div>
              <label class="form-label">Initial Balance</label>
              <input name="balance" placeholder="0.00" type="number" step="0.01" min="0" class="form-input" required />
            </div>
            <div class="flex items-end">
              <button class="btn btn-primary w-full" type="submit">Add Bank Account</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </dialog>
  <!-- edit --><!-- Edit Item Modal -->
  <dialog id="modalEditItem">
    <div class="modal-content w-full max-w-md">
      <form id="formEditItem">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-6">
          <h3 class="text-xl font-bold">Edit Item</h3>
        </div>
        <div class="p-6 space-y-4">
          <input type="hidden" name="id" />
          <div>
            <label class="form-label">Item Name</label>
            <input name="name" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Brand Name</label>
            <input name="brand_name" class="form-input" />
          </div>
          <div>
            <label class="form-label">Item Number</label>
            <input name="item_number" class="form-input" />
          </div>
          <div>
            <label class="form-label">Quantity</label>
            <input name="qty" type="number" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Price per Unit</label>
            <input name="price" type="number" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Payment Code</label>
            <input name="ids" type="number" class="form-input" required />
          </div>
          <div>
            <label class="form-label">VAT %</label>
            <input name="tax_pct" type="number" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Buyer's Name</label>
            <input name="username" type="text" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Buyer's Phone</label>
            <input name="phone" type="number" class="form-input" required />
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditItem')">Cancel</button>
            <button class="btn btn-primary" type="submit">Update Item</button>
          </div>
        </div>
      </form>
    </div>
  </dialog>
  <!-- edit --><!-- Edit Item Modal -->
  <dialog id="salesmodeleditform">
    <div class="modal-content w-full max-w-md">
      <form id="modalEditSales">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-6">
          <h3 class="text-xl font-bold">Edit Item</h3>
        </div>
        <div class="p-6 space-y-4">
          <input type="hidden" name="id" />
          <div>
            <label class="form-label">Item Name</label>
            <input name="name" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Item Number</label>
            <input name="item_number" class="form-input" />
          </div>
          <div>
            <label class="form-label">Quantity</label>
            <input name="qty" type="number" min="0" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Price per Unit</label>
            <input name="price" type="number" min="0" step="0.01" class="form-input" required />
          </div>
          <div>
            <label class="form-label">VAT %</label>
            <input name="tax_pct" type="number" min="0" step="0.01" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Ids</label>
            <input name="ids" type="number" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Buyer's Name</label>
            <input name="username" type="text" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Buyer's Phone</label>
            <input name="phone" type="number" class="form-input" required />
          </div>
          <div>
            <div>
              <div>
                <label class="form-label">Payment Due Date</label>
                <input name="due_date" type="date" class="form-input" />
              </div>
              <div id="statusWrapper">
                <label class="form-label">Status</label>
                <select name="status" class="form-input">
                  <option value="pending">Pending</option>
                  <option value="paid">Paid</option>
                </select>
              </div>
            </div>
            <div id="paidViaWrapper" class="hidden">
              <label class="form-label">Paid Via for Remaining</label>
              <select name="paid_via" id="editPaidVia" class="form-input">
                <option value="cash">Cash</option>
              </select>
            </div>
            <div class="flex justify-end space-x-3">
              <button type="button" class="btn btn-secondary" onclick="closeModal('salesmodeleditform')">Cancel</button>
              <button class="btn btn-primary" type="submit">Update Sale</button>
            </div>
          </div>
      </form>
    </div>
  </dialog>
  <!-- Purchases Modal -->
  <dialog id="modalPurchases">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-purple-500 to-pink-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 2M7 13l1.5 2m7.5-2a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
            </svg>
            Purchase History
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPurchases')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <!-- STATISTICS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="bg-blue-600 text-white rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Records</h6>
            <p class="text-2xl font-bold mt-1" id="statPurchRecords">0</p>
          </div>
          <div class="bg-green-600 text-white rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Cash</h6>
            <p class="text-2xl font-bold mt-1" id="statPurchCash">0.00</p>
          </div>
          <div class="bg-yellow-500 text-gray-900 rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Credit</h6>
            <p class="text-2xl font-bold mt-1" id="statPurchCredit">0.00</p>
          </div>
          <div class="bg-cyan-600 text-white rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Bank</h6>
            <p class="text-2xl font-bold mt-1" id="statPurchBank">0.00</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Image</th>
                <th>Brand</th>
                <th>Item Number</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Bank</th>
                <th>Due Date</th>
                <th>Total</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="purchasesTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Sales Modal -->
  <dialog id="modalSales">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-green-500 to-teal-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            Sales History
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalSales')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <!-- SEARCH -->
        <div class="mb-5">
          <input type="text" id="searchInput"
            class="w-full md:w-96 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Search by Name or Phone…" autocomplete="off">
        </div>
        <!-- TIME FILTER -->
        <div class="mb-4 flex justify-end">
          <select id="timeFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="all">All Time</option>
            <option value="6months">Last 6 Months</option>
            <option value="1year">Last 1 Year</option>
          </select>
        </div>
        <!-- STATISTICS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
          <div class="bg-blue-600 text-white rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Records</h6>
            <p class="text-2xl font-bold mt-1" id="statRecords">0</p>
          </div>
          <div class="bg-green-600 text-white rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Cash</h6>
            <p class="text-2xl font-bold mt-1" id="statCash">0.00</p>
          </div>
          <div class="bg-yellow-500 text-gray-900 rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Credit</h6>
            <p class="text-2xl font-bold mt-1" id="statCredit">0.00</p>
          </div>
          <div class="bg-cyan-600 text-white rounded-lg p-4 text-center">
            <h6 class="text-sm font-medium">Bank</h6>
            <p class="text-2xl font-bold mt-1" id="statOther">0.00</p>
          </div>

        </div>
        <!-- TABLE -->
        <div class="overflow-x-auto rounded-lg border border-gray-200">
          <table class="data-table w-full table-auto">
            <thead class="bg-gray-100 text-gray-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Name</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Phone</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Image</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Qty</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">ids</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Unit Price</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Tax %</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Method</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Paid Via</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Due Date</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Prepaid</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Total</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Edit</th>
                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Delete</th>
              </tr>
            </thead>
            <tbody id="salesTable" class="bg-white divide-y divide-gray-200"></tbody>
          </table>
        </div>
      </div>
    </div>
    </div>
  </dialog>
  <!-- customers list crm -->
  <!-- Sales Modal -->
  <dialog id="modalCusts">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-green-500 to-teal-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            Customers
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalCusts')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>addres</th>
                <th>Qty</th>
                <th>Edit</th>
              </tr>
            </thead>
            <tbody id="custsTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Cash Sales Modal -->
  <dialog id="modalCash">
    <div class="modal-content w-full max-w-5xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
            </svg>
            Cash Sales Record
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalCash')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="cashTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Sell Item Modal -->
  <dialog id="modalSell">
    <div class="modal-content w-full max-w-2xl">
      <form id="formSell">
        <div class="bg-gradient-to-r from-orange-500 to-red-600 text-white p-6">
          <div class="flex items-center justify-between">
            <h3 class="text-2xl font-bold flex items-center">
              <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              Process Sale
            </h3>
            <button type="button" class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalSell')">Close</button>
          </div>
        </div>
        <div class="p-6 space-y-6">
          <input type="hidden" name="item_id" />
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="form-label">Quantity to Sell</label>
              <input name="qty" type="number" min="1" class="form-input" required value="1" />
            </div>
            <div>
              <label class="form-label">Price per Unit</label>
              <input name="price" type="number" min="0" step="0.01" class="form-input" required />
            </div>
            <div>
              <label class="form-label">Tax Percentage</label>
              <input name="tax_pct" type="number" min="0" step="0.01" class="form-input" value="0" />
            </div>
            <div>
              <label class="form-label">Payment Code</label>
              <input name="ids" type="number" class="form-input" value="0" />
            </div>
          </div>
          <div>
            <label class="form-label">Payment Method</label>
            <select name="payment_method" id="pmethod" class="form-input" required>
              <option value="Paid">Paid (Full Payment)</option>
              <option value="Pre-paid">Pre-paid (Partial Payment)</option>
              <option value="Credit">Credit (Payment Later)</option>
              <option value="Pay with Prepaid">Pay with Prepaid Balance</option>
            </select>
          </div>
          <div id="paidBlock" class="space-y-4">
            <div>
              <label class="form-label">Payment Received Via</label>
              <select name="paid_via" id="paidVia" class="form-input">
                <option value="cash">Cash Payment</option>
              </select>
            </div>
          </div>
          <div id="prepaidBlock" class="grid grid-cols-1 md:grid-cols-2 gap-4 hidden">
            <div>
              <label class="form-label">Prepayment Amount</label>
              <input name="prepayment" type="number" min="0" step="0.01" class="form-input" value="0" />
            </div>
            <div>
              <label class="form-label">Prepayment Received Via</label>
              <select name="prepay_via" id="prepayBank" class="form-input">
                <option value="cash">Cash</option>
              </select>
            </div>
          </div>
          <div id="dueBlock" class="hidden">
            <div>
              <label class="form-label">Payment Due Date</label>
              <input name="due_date" type="date" class="form-input" />
            </div>
          </div>
          <div>
            <label class="form-label">Buyer's Name</label>
            <input name="username" type="text" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Buyer's Phone</label>
            <input name="phone" type="number" class="form-input" required />
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalSell')">Cancel</button>
            <button class="btn btn-success" type="submit" id="confirmSellBtnwaiing">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Confirm Sale
            </button>
          </div>
        </div>
      </form>
    </div>
  </dialog>
  <!-- Add these modals to your modal section -->
  <!-- Purchase Prepaid Modal -->
  <dialog id="modalPurchasePrepaid">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-yellow-500 to-orange-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4V8h16v10zm-7-2h2v2h-2v-2zm0-4h2v2h-2v-2z" />
            </svg>
            Prepaid Purchases
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPurchasePrepaid')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Brand</th>
                <th>Item Number</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Due Date</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="purchasePrepaidTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Purchase Credit Modal -->
  <dialog id="modalPurchaseCredit">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-red-500 to-pink-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4V8h16v10zm-7-2h2v2h-2v-2zm0-4h2v2h-2v-2z" />
            </svg>
            Credit Purchases
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPurchaseCredit')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Brand</th>
                <th>Item Number</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Due Date</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody id="purchaseCreditTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Miscellaneous Modal -->
  <dialog id="modalMisc">
    <div class="modal-content w-full max-w-5xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 100 4m0-4v2m0-6V4" />
            </svg>
            Pity Cash
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalMisc')">Close</button>
        </div>
      </div>
      <div class="p-6 space-y-6">
        <div class="border-b pb-6">
          <h4 class="text-lg font-semibold mb-4">Record New Expense</h4>
          <form id="formMisc" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div>
              <label class="form-label">Expense Name</label>
              <input name="name" placeholder="e.g. Office Supplies" class="form-input" required />
            </div>
            <div>
              <label class="form-label">Reason/Description</label>
              <input name="reason" placeholder="Optional details" class="form-input" />
            </div>
            <div>
              <label class="form-label">Date</label>
              <input name="date" type="date" class="form-input" />
            </div>
            <div>
              <label class="form-label">Amount</label>
              <input name="amount" placeholder="0.00" type="number" step="0.01" min="0" class="form-input" required />
            </div>
            <div>
              <label class="form-label">Paid From</label>
              <select name="bank_id" id="miscBank" class="form-input">
                <option value="cash">Cash</option>
              </select>
            </div>
            <div class="md:col-span-5">
              <button class="btn btn-primary" type="submit">Record Expense</button>
            </div>
          </form>
        </div>
        <div>
          <h4 class="text-lg font-semibold mb-4">Expense History</h4>
          <div class="overflow-x-auto">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Name</th>
                  <th>Reason</th>
                  <th>Bank</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody id="miscTable"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Reports Modal -->
  <dialog id="modalReports">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-teal-500 to-cyan-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Financial Reports
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalReports')">Close</button>
        </div>
      </div>
      <div class="p-6 space-y-6">
        <div class="bg-gray-50 rounded-lg p-4">
          <h4 class="text-lg font-semibold mb-4">Report Parameters</h4>
          <form id="formReports" class="flex flex-wrap gap-4">
            <div>
              <label class="form-label">Period</label>
              <select name="period" class="form-input min-w-[160px]">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly" selected>Monthly</option>
                <option value="yearly">Yearly</option>
                <option value="all">All Time</option>
              </select>
            </div>
            <div>
              <label class="form-label">From Date</label>
              <input name="from" type="date" class="form-input" />
            </div>
            <div>
              <label class="form-label">To Date</label>
              <input name="to" type="date" class="form-input" />
            </div>
            <div class="flex items-end">
              <button class="btn btn-primary" type="submit">Generate Report</button>
            </div>
          </form>
        </div>
        <div id="reportSummary" class="grid grid-cols-1 md:grid-cols-4 gap-4"></div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="card p-4">
            <h4 class="font-semibold mb-3 text-purple-600 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13" />
              </svg>
              Purchases
            </h4>
            <div class="overflow-x-auto">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Brand</th>
                    <th>Item Number</th>
                    <th>Qty</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody id="reportPurchases"></tbody>
              </table>
            </div>
          </div>
          <div class="card p-4">
            <h4 class="font-semibold mb-3 text-green-600 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
              Sales
            </h4>
            <div class="overflow-x-auto">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Method</th>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody id="reportSales"></tbody>
              </table>
            </div>
          </div>
          <div class="card p-4">
            <h4 class="font-semibold mb-3 text-orange-600 flex items-center">
              <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 6V4m0 2a2 2 0 100 4" />
              </svg>
              Expenses
            </h4>
            <div class="overflow-x-auto">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Bank</th>
                    <th>Amount</th>
                  </tr>
                </thead>
                <tbody id="reportMisc"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </dialog>
  <!-- Edit Bank Modal -->
  <dialog id="modalEditBank">
    <div class="modal-content w-full max-w-md">
      <form id="formEditBank">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-6">
          <h3 class="text-xl font-bold">Edit Bank Account</h3>
        </div>
        <div class="p-6 space-y-4">
          <input type="hidden" name="id" />
          <div>
            <label class="form-label">Bank Name</label>
            <input name="name" class="form-input" required />
          </div>
          <div>
            <label class="form-label">Current Balance</label>
            <input name="balance" type="number" step="0.01" class="form-input" required />
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditBank')">Cancel</button>
            <button class="btn btn-primary" type="submit">Update Bank</button>
          </div>
        </div>
      </form>
    </div>
  </dialog>
  <!-- credit -->
  <!-- New Credit Sales Modal -->
  <dialog id="modalCredit">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-red-500 to-pink-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4V8h16v10zm-7-2h2v2h-2v-2zm0-4h2v2h-2v-2z" />
            </svg>
            Credit Sales
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalCredit')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Method</th>
                <th>Due Date</th>
                <th>Amount Due</th>
              </tr>
            </thead>
            <tbody id="creditTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- New Pre-paid Sales Modal -->
  <dialog id="modalPrepaid">
    <div class="modal-content w-full max-w-7xl max-h-[90vh] overflow-y-auto">
      <div class="bg-gradient-to-r from-yellow-500 to-orange-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4V8h16v10zm-7-2h2v2h-2v-2zm0-4h2v2h-2v-2z" />
            </svg>
            Pre-paid Sales
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalPrepaid')">Close</button>
        </div>
      </div>
      <div class="p-6">
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Tax %</th>
                <th>Bank</th>
                <th>Due Date</th>
                <th>Prepaid</th>
                <th>Amount Due</th>
              </tr>
            </thead>
            <tbody id="prepaidTable"></tbody>
          </table>
        </div>
      </div>
    </div>
  </dialog>
  <!-- New Update Purchase Modal -->
  <dialog id="modalUpdatePurchase">
    <div class="modal-content w-full max-w-md">
      <div class="bg-gradient-to-r from-orange-500 to-red-600 text-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-2xl font-bold flex items-center">
            <svg class="w-6 h-6 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 2M7 13l1.5 2m7.5-2a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
            </svg>
            Purchase Existing Item
          </h3>
          <button class="btn bg-white/20 hover:bg-white/30 text-white border-0" onclick="closeModal('modalUpdatePurchase')">Close</button>
        </div>
      </div>
      <form id="formUpdatePurchase" class="p-6 space-y-4">
        <input type="hidden" name="item_id" id="updatePurchaseItemId">
        <div>
          <label class="block text-sm font-medium mb-1">Item Name</label>
          <input type="text" id="updatePurchaseItemName" class="form-input" disabled>
        </div>
        <div>
          <label class="form-label">Brand Name</label>
          <input name="brand_name" class="form-input" placeholder="Enter brand name" />
        </div>
        <div>
          <label class="form-label">Item Number</label>
          <input name="item_number" class="form-input" placeholder="Enter item number" />
        </div>
        <div>
          <label class="form-label">Quantity</label>
          <input name="qty" type="number" min="1" class="form-input" placeholder="0" required />
        </div>
        <div>
          <label class="form-label">Price per Unit</label>
          <input name="price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" required />
        </div>
        <div>
          <label class="form-label">VAT %</label>
          <input name="tax_pct" type="number" min="0" step="0.01" class="form-input" placeholder="0" value="0" />
        </div>
        <div>
          <label class="form-label">Due Date</label>
          <input name="due_date" type="date" class="form-input" />
        </div>
        <div>
          <label class="form-label">Payment Bank</label>
          <select name="bank_id" class="form-input" required>
            <option value="">Select Bank</option>
          </select>
        </div>
        <button type="submit" class="btn bg-blue-600 hover:bg-blue-700 text-white w-full">Record Purchase</button>
      </form>
    </div>
  </dialog>
  <!-- Edit Purchase Modal -->
  <dialog id="modalEditPurchase">
    <div class="modal-content w-full max-w-2xl">
      <form id="formEditPurchase">
        <div class="bg-gradient-to-r from-purple-500 to-pink-600 text-white p-6">
          <h3 class="text-xl font-bold">Edit Purchase</h3>
        </div>
        <div class="p-6 space-y-4">
          <input type="hidden" name="id" />
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="form-label">Item Name</label>
              <input name="item_name" class="form-input" required />
            </div>
            <div>
              <label class="form-label">Brand Name</label>
              <input name="brand_name" class="form-input" />
            </div>
            <div>
              <label class="form-label">Item Number</label>
              <input name="item_number" class="form-input" />
            </div>
            <div>
              <label class="form-label">Quantity</label>
              <input name="qty" type="number" min="1" class="form-input" required />
            </div>
            <div>
              <label class="form-label">Unit Price</label>
              <input name="price" type="number" min="0" step="0.01" class="form-input" required />
            </div>
            <div>
              <label class="form-label">VAT %</label>
              <input name="tax_pct" type="number" min="0" step="0.01" class="form-input" />
            </div>
            <div>
              <label class="form-label">Payment Type</label>
              <select name="payment_type" id="editPurchasePaymentType" class="form-input" required>
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
                <option value="credit">Credit</option>
                <option value="prepaid">Prepaid</option>
              </select>
            </div>
            <div id="editPurchaseBankWrapper">
              <label class="form-label">Bank</label>
              <select name="bank_id" id="editPurchaseBank" class="form-input"></select>
            </div>
            <div id="editPurchaseDueDateWrapper">
              <label class="form-label">Due Date</label>
              <input name="due_date" type="date" class="form-input" />
            </div>
            <script>
            // Edit Purchase Payment Type Change Handler
            document.getElementById('editPurchasePaymentType').addEventListener('change', function() {
              const method = this.value;
              const bankWrapper = document.getElementById('editPurchaseBankWrapper');
              const dateWrapper = document.getElementById('editPurchaseDueDateWrapper');
              const bankSelect = document.getElementById('editPurchaseBank');
              
              bankWrapper.classList.add('hidden');
              dateWrapper.classList.add('hidden');
              bankSelect.removeAttribute('required');
              
              if (method === 'bank') {
                bankWrapper.classList.remove('hidden');
                bankSelect.setAttribute('required', 'required');
              } else if (method === 'credit' || method === 'prepaid') {
                dateWrapper.classList.remove('hidden');
              }
            });
            </script>

            <div>
              <label class="form-label">Status</label>
              <select name="status" class="form-input">
                <option value="paid">Paid</option>
                <option value="unpaid">Unpaid</option>
                <option value="pending">Pending</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditPurchase')">Cancel</button>
            <button class="btn btn-primary" type="submit">Update Purchase</button>
          </div>
        </div>
      </form>
    </div>
  </dialog>
  <script>
    const userRole = '<?php echo $_SESSION['role'] ?? 'guest'; ?>';
    const userPermissions = <?php echo json_encode(json_decode($_SESSION['permissions'] ?? '[]', true)); ?>;
  </script>
  <script>
    const fmt = c => `${(c / 100).toFixed(2)} Birr`;

    function loadnums() {
      function formatNumbers(num) {
        if (typeof num !== 'number') return num;
        const absNum = Math.abs(num);
        if (absNum >= 1e6) {
          return (num / 1e6).toFixed(2) + 'M';
        } else if (absNum >= 1e3) {
          return (num / 1e3).toFixed(2) + 'K';
        }
        return num.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
      // Apply overflow handling and number formatting to all number displays
      // setTimeout(() => {
      // const numberDisplays = document.querySelectorAll('.number-display');
      // numberDisplays.forEach(element => {
      // const text = element.textContent.trim();
      // if (text) {
      // const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
      // if (!isNaN(num)) {
      // element.textContent = formatNumbers(num); // Format number
      // if (text.length > 10 || num < 0) {
      // element.classList.add('large-number'); // Smaller font for large numbers
      // }
      // if (num < 0) {
      // element.classList.add('negative'); // Red color for negative numbers
      // }
      // }
      // }
      // });
      // }, 1);
      setTimeout(() => {
        const text = document.getElementById('purchaseBankTotal').textContent.trim();
        const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
        if (!isNaN(num)) {
          document.getElementById('purchaseBankTotal').textContent = formatNumbers(num); // Format number
          if (text.length > 10 || num < 0) {
            document.querySelector('.purchaseBankTotals').classList.add('large-number'); // Smaller font for large numbers
          }
          if (num < 0) {
            element.classList.add('negative'); // Red color for negative numbers
          }
        }
      }, 100);
      setTimeout(() => {
        const text = document.getElementById('purchaseCashTotal').textContent.trim();
        const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
        if (!isNaN(num)) {
          document.getElementById('purchaseCashTotal').textContent = formatNumbers(num); // Format number
          if (text.length > 10 || num < 0) {
            document.querySelector('.purchaseCashTotals').classList.add('large-number'); // Smaller font for large numbers
          }
          if (num < 0) {
            element.classList.add('negative'); // Red color for negative numbers
          }
        }
      }, 100);
      setTimeout(() => {
        const text = document.getElementById('creditTotal').textContent.trim();
        const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
        if (!isNaN(num)) {
          document.getElementById('creditTotal').textContent = formatNumbers(num); // Format number
          if (text.length > 10 || num < 0) {
            document.querySelector('.creditTotals').classList.add('large-number'); // Smaller font for large numbers
          }
          if (num < 0) {
            element.classList.add('negative'); // Red color for negative numbers
          }
        }
      }, 100);

      function formatNumber(num) {
        if (typeof num !== 'number') return num;
        const absNum = Math.abs(num);
        if (absNum >= 1e6) {
          return (num / 1e6).toFixed(2) + 'M';
        } else if (absNum >= 1e3) {
          return (num / 1e3).toFixed(2) + 'K';
        }
        return num.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
      setTimeout(() => {
        const text = document.getElementById('bankTotal').textContent.trim();
        const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
        if (!isNaN(num)) {
          document.getElementById('bankTotal').textContent = formatNumber(num); // Format number
          if (text.length > 10 || num < 0) {
            document.querySelector('.number-displaybank').classList.add('large-number'); // Smaller font for large numbers
          }
          if (num < 0) {
            element.classList.add('negative'); // Red color for negative numbers
          }
        }
      }, 100);
      setTimeout(() => {
        const text = document.getElementById('cashTotal').textContent.trim();
        const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
        if (!isNaN(num)) {
          document.getElementById('cashTotal').textContent = formatNumber(num); // Format number
          if (text.length > 10 || num < 0) {
            document.querySelector('.cashtotal').classList.add('large-number'); // Smaller font for large numbers
          }
          if (num < 0) {
            element.classList.add('negative'); // Red color for negative numbers
          }
        }
      }, 100);
    }
    // JavaScript to toggle sections
    const buyButton = document.getElementById('buyButton');
    const sellButton = document.getElementById('sellButton');
    // const purchaseSection = document.getElementById('purchaseSection');
    // const sellInventorySection = document.getElementById('sellinventorySection');
    // buyButton.addEventListener('click', () => {
    // purchaseSection.classList.remove('hidden');
    // sellInventorySection.classList.add('hidden');
    // });
    // sellButton.addEventListener('click', () => {
    // sellInventorySection.classList.remove('hidden');
    // purchaseSection.classList.add('hidden');
    // });
    // JavaScript for purchase form dynamic fields
    // document.getElementById('purchasePaymentType').addEventListener('change', function() {
    // let method = this.value;
    // document.getElementById('purchaseBankWrapper').classList.add('hidden');
    // document.getElementById('purchaseDueDateWrapper').classList.add('hidden');
    // document.getElementById('purchasePrepaidWrapper').classList.add('hidden');
    // document.querySelector('[name="bank_id"]').removeAttribute('required');
    // document.querySelector('[name="due_date"]').removeAttribute('required');
    // document.querySelector('[name="prepaid_balance"]').removeAttribute('required');
    // if (method === 'bank') {
    // document.getElementById('purchaseBankWrapper').classList.remove('hidden');
    // document.querySelector('[name="bank_id"]').setAttribute('required', 'required');
    // }
    // if (method === 'credit') {
    // document.getElementById('purchaseDueDateWrapper').classList.remove('hidden');
    // document.querySelector('[name="due_date"]').setAttribute('required', 'required');
    // }
    // if (method === 'prepaid') {
    // document.getElementById('purchasePrepaidWrapper').classList.remove('hidden');
    // document.getElementById('purchaseDueDateWrapper').classList.remove('hidden');
    // document.querySelector('[name="due_date"]').setAttribute('required', 'required');
    // document.querySelector('[name="prepaid_balance"]').setAttribute('required', 'required');
    // }
    // });
  </script>
  <script>
    // ---------- UTILITY FUNCTIONS ----------
    const openModal = id => document.getElementById(id).showModal();
    const closeModal = id => document.getElementById(id).close();
    async function api(action, data = {}) {
      const form = new FormData();
      form.append('action', action);
      for (const k in data) {
        if (data[k] !== undefined && data[k] !== null) form.append(k, data[k]);
      }
      try {
        const res = await fetch(location.href, {
          method: 'POST',
          body: form
        });
        const js = await res.json();
        if (!res.ok || js.error) throw new Error(js.error || 'Request failed');
        return js;
      } catch (err) {
        console.error('API Error:', err);
        throw err;
      }
    }
    // function toast(msg, type = 'success') {
    // const colors = {
    // success: 'bg-green-500',
    // error: 'bg-red-500',
    // warning: 'bg-yellow-500',
    // info: 'bg-blue-500'
    // };
    // const toast = document.createElement('div');
    // toast.className = `fixed bottom-4 z-[100] toaste right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 slide-in z-50`;
    // toast.textContent = msg;
    // document.body.appendChild(toast);
    // setTimeout(() => toast.remove(), 4000);
    // }
    // function toast(msg, type = 'success') {
    // const colors = {
    // success: 'bg-green-600',
    // error: 'bg-red-600',
    // info: 'bg-blue-600',
    // warning: 'bg-yellow-600'
    // };
    // const toast = document.createElement('div');
    // toast.className = `fixed bottom-4 right-4 z-[9999999] ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg animate-slide-in`;
    // toast.textContent = msg;
    // toast.style.setProperty("z-index", "999999999", "important");
    // document.body.appendChild(toast);
    // setTimeout(() => toast.remove(), 4000);
    // }
    function toast(msg, type = 'success') {
      const toast = document.createElement('div');
      toast.className = `
        fixed bottom-4 right-4
        px-6 py-3 rounded-lg shadow-lg
        text-white
        bg-green-600
        animate-slide-in
    `;
      toast.textContent = msg;
      // SUPER Z-INDEX
      toast.style.setProperty("z-index", "2147483647", "important");
      // Append directly to BODY to escape modals
      document.body.appendChild(toast);
      setTimeout(() => {
        toast.remove();
      }, 3000);
    }
    // ---------- DASHBOARD FUNCTIONS ----------
    async function loadDashboard() {
      try {
        const d = await api('dashboard');
        // Update stats
        // function oool(cents) {
        // if (cents > 999999) {
        // return '...';
        // }
        // const dollars = cents / 100;
        // return new Intl.NumberFormat('en-US', {
        // style: 'currency',
        // currency: 'USD',
        // minimumFractionDigits: 2,
        // maximumFractionDigits: 2
        // }).format(dollars);
        // }
        document.getElementById('bankTotal').textContent = fmt(d.bank_total_cents);
        document.getElementById('cashTotal').textContent = fmt(d.cash_total_cents);
        document.getElementById('purchaseCount').textContent = d.purchases_count;
        document.getElementById('salesCount').textContent = d.sales_count;
        document.getElementById('creditTotal').textContent = fmt(d.credit_cents);
        document.getElementById('purchasees_list').textContent = d.purchasees_list;
        document.getElementById('prepaidTotal').textContent = fmt(d.prepaid_cents);

        // Update Purchase Credit Display
        const purchaseCreditEl = document.getElementById('purchaseCreditTotal');
        if (purchaseCreditEl) {
          purchaseCreditEl.textContent = fmt(d.purchase_credit_cents);
        } else {
          // If element doesn't exist, we might need to create it or append it to an existing box
          // For now, let's assume we want to show it in the purchases card or a new location
          // Or maybe the user wants it combined? "prepaid also count as credit"
          // Let's look for where to put it.
          // The user said "in the purchase history preapd is not showing up at the credit box prepaid also count as credit"
          // This implies there is a credit box in purchase history or dashboard.
          // Let's assume there is a 'creditTotal' for sales and we need one for purchases.
          // Or maybe update an existing element.

          // Let's try to find an element with id 'purchaseCreditTotal' in the HTML first.
          // If not found, we might need to add it to the HTML.
        }
        // setTimeout(() => {
        // const text = document.getElementById('bankTotal').textContent.trim();
        // const num = parseFloat(text.replace(/[^0-9.-]+/g, ''));
        // if (!isNaN(num)) {
        // document.getElementById('bankTotal').textContent = formatNumber(num); // Format number
        // if (text.length > 10 || num < 0) {
        // document.querySelector('.number-displaybank').classList.add('large-number'); // Smaller font for large numbers
        // }
        // if (num < 0) {
        // element.classList.add('negative'); // Red color for negative numbers
        // }
        // }
        // }, 2);
        // setInterval(() => {
        // console.log(text);
        // }, 1);
        // NEW
        function formatNumber(num) {
          if (typeof num !== 'number') return num;
          const absNum = Math.abs(num);
          if (absNum >= 1e6) {
            return (num / 1e6).toFixed(2) + 'M';
          } else if (absNum >= 1e3) {
            return (num / 1e3).toFixed(2) + 'K';
          }
          return num.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        }
        const purchases = await api('list_purchases');
        const prepaidTotal = purchases.purchases.filter(p => p.payment_type === 'prepaid')
          .reduce((sum, p) => sum + p.total_cents, 0);
        const creditTotal = purchases.purchases.filter(p => p.payment_type === 'credit')
          .reduce((sum, p) => sum + p.total_cents, 0);
        document.getElementById('purchasePrepaidTotal').textContent = formatNumber(prepaidTotal);
        document.getElementById('purchaseCreditTotal').textContent = formatNumber(creditTotal);
        const bankPurchases = purchases.purchases.filter(p => p.payment_type === 'bank')
          .reduce((sum, p) => sum + p.total_cents, 0);
        const cashTotal = purchases.purchases.filter(p => p.payment_type === 'cash')
          .reduce((sum, p) => sum + p.total_cents, 0);
        document.getElementById('purchaseBankTotal').textContent = fmt(bankPurchases);
        document.getElementById('purchaseCashTotal').textContent = fmt(cashTotal);
        // Update notifications
        const notifList = document.getElementById('notifList');
        notifList.innerHTML = '';
        if (d.notifications.length === 0) {
          notifList.innerHTML = '<div class="text-gray-500 text-center py-4">No upcoming payments or overdue items</div>';
        } else {
          d.notifications.forEach(notif => {
            const isOverdue = new Date(notif.due_date) < new Date();
            const div = document.createElement('div');
            div.className = `alert-card ${isOverdue ? 'alert-overdue' : 'alert-upcoming'} p-4 rounded-lg`;
            div.innerHTML = `
              <div class="flex justify-between mb-8 items-start">
                <div>
                  <div class="font-semibold text-gray-900">${notif.type.toUpperCase()}: ${notif.name}</div>
                  <div class="text-sm text-gray-600">Due: ${notif.due_date || 'N/A'}</div>
                </div>
                <span class="badge ${isOverdue ? 'badge-danger' : 'badge-warning'}">
                  ${isOverdue ? 'Overdue' : 'Upcoming'}
                </span>
              </div>
            `;
            notifList.appendChild(div);
          });
        }
        // Update low stock
        const lowStockList = document.getElementById('lowStockList');
        lowStockList.innerHTML = '';
        if (d.low_stock.length === 0) {
          lowStockList.innerHTML = '<div class="text-gray-500 text-center py-4">All items are well-stocked</div>';
        } else {
          d.low_stock.forEach(item => {
            const div = document.createElement('div');
            div.className = 'alert-card alert-low-stock p-4 rounded-lg';
            div.innerHTML = `
              <div class="flex justify-between items-center">
                <div class="font-semibold text-gray-900">${item.name}</div>
                <span class="badge badge-warning">Qty: ${item.qty}</span>
              </div>
            `;
            lowStockList.appendChild(div);
          });
        }
        await refreshBanks();
      } catch (err) {
        toast('Failed to load dashboard: ' + err.message, 'error');
      }
      loadnums();
    }
    // ---------- BANK MANAGEMENT ----------
    async function refreshBanks() {
      try {
        const d = await api('list_banks');
        // Update banks list in modal
        const banksList = document.getElementById('banksList');
        banksList.innerHTML = '';
        d.banks.forEach(bank => {
          const div = document.createElement('div');
          div.className = 'card p-4 flex justify-between items-center hover:shadow-md transition-shadow';
          div.innerHTML = `
            <div>
              <div class="font-semibold text-lg text-gray-900">${bank.name}</div>
              <div class="text-gray-600">Balance: ${fmt(bank.balance_cents)}</div>
            </div>
            <div class="flex space-x-2">
              <button class="btn btn-warning btn-sm" onclick="editBank(${bank.id}, '${bank.name}', ${bank.balance_cents})">Edit</button>
              <button class="btn btn-danger btn-sm" onclick="deleteBank(${bank.id}, '${bank.name}')">Delete</button>
            </div>
          `;
          banksList.appendChild(div);
        });
        // Update select elements
        const selectors = ['purchaseBank', 'miscBank', 'editPurchaseBank', 'editPaidVia'];
        selectors.forEach(id => {
          const select = document.getElementById(id);
          if (select) {
            const currentValue = select.value;
            if (id === 'purchaseBank' || id === 'editPurchaseBank') {
              select.innerHTML = '<option value="">Select Bank</option>';
            } else {
              select.innerHTML = '<option value="cash">Cash</option>';
            }
            d.banks.forEach(bank => {
              const option = document.createElement('option');
              option.value = `bank:${bank.id}`;
              option.textContent = `Bank: ${bank.name}`;
              if (option.value === currentValue) option.selected = true;
              select.appendChild(option);
            });
          }
        });
        
        // Update prepayBank with cash and bank options
        const prepayBank = document.getElementById('prepayBank');
        if (prepayBank) {
          const currentValue = prepayBank.value;
          prepayBank.innerHTML = '<option value="cash">Cash</option>';
          d.banks.forEach(bank => {
            const option = document.createElement('option');
            option.value = `bank:${bank.id}`;
            option.textContent = `Bank: ${bank.name}`;
            if (option.value === currentValue) option.selected = true;
            prepayBank.appendChild(option);
          });
        }
        // Update paid via select
        const paidVia = document.getElementById('paidVia');
        if (paidVia) {
          const currentValue = paidVia.value;
          paidVia.innerHTML = '<option value="cash">Cash Payment</option>';
          d.banks.forEach(bank => {
            const option = document.createElement('option');
            option.value = `bank:${bank.id}`;
            option.textContent = `Bank: ${bank.name}`;
            if (option.value === currentValue) option.selected = true;
            paidVia.appendChild(option);
          });
        }
      } catch (err) {
        toast('Failed to load banks: ' + err.message, 'error');
      }
    }

    function editBank(id, name, balanceCents) {
      const form = document.getElementById('formEditBank');
      form.id.value = id;
      form.name.value = name;
      form.balance.value = (balanceCents / 100).toFixed(2);
      openModal('modalEditBank');
    }
    async function deleteBank(id, name) {
      if (!confirm(`Are you sure you want to delete bank "${name}"?`)) return;
      try {
        await api('delete_bank', {
          id
        });
        toast('Bank deleted successfully');
        await loadDashboard();
        await refreshBanks();
      } catch (err) {
        toast('Failed to delete bank: ' + err.message, 'error');
      }
    }
    // load prepaid
    // New function to load Credit sales
    async function loadCreditSales() {
      try {
        const d = await api('list_credit_sales');
        const tbody = document.getElementById('creditTable');
        tbody.innerHTML = '';
        if (d.sales.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-4">No Credit sales recorded</td></tr>';
          return;
        }
        d.sales.forEach(sale => {
          const amountDue = sale.total_cents - (sale.prepayment_cents || 0);
          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="py-3">${sale.date}</td>
                <td class="font-semibold">${sale.item_name}</td>
                <td>${sale.qty}</td>
                <td>${fmt(sale.price_cents)}</td>
                <td>${sale.tax_pct}%</td>
                <td><span class="badge badge-error">${sale.payment_method}</span></td>
                <td>${sale.due_date || '-'}</td>
                <td class="font-semibold text-red-600">${fmt(amountDue)}</td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load Credit sales: ' + err.message, 'error');
      }
    }
    // New function to load Pre-paid sales
    async function loadPrepaidSales() {
      try {
        const d = await api('list_prepaid_sales');
        const tbody = document.getElementById('prepaidTable');
        tbody.innerHTML = '';
        if (d.sales.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-center text-gray-500 py-4">No Pre-paid sales recorded</td></tr>';
          return;

        }
        d.sales.forEach(sale => {
          const amountDue = sale.total_cents - (sale.prepayment_cents || 0);
          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="py-3">${sale.date}</td>
                <td class="font-semibold">${sale.item_name}</td>
                <td>${sale.qty}</td>
                <td>${fmt(sale.price_cents)}</td>
                <td>${sale.tax_pct}%</td>
                <td><span class="badge badge-info">${sale.bank_name || '-'}</span></td>
                <td>${sale.due_date || '-'}</td>
                <td>${fmt(sale.prepayment_cents || 0)}</td>
                <td class="font-semibold text-red-600">${fmt(amountDue)}</td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load Pre-paid sales: ' + err.message, 'error');
      }
    }
    // ---------- PURCHASE MANAGEMENT ----------
    async function loadPurchases() {
      try {
        const d = await api('list_purchases');
        const tbody = document.getElementById('purchasesTable');
        tbody.innerHTML = '';
        d.purchases.forEach(purchase => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="py-3">${purchase.date}</td>
            <td class="font-semibold">${purchase.item_name}</td>
                <td class="text-center">
    ${purchase.item_image_path ?
        `<img src="${purchase.item_image_path}" alt="Item" class="w-12 h-12 rounded object-cover">` :
        '📷'
    }
</td>
            <td>${purchase.brand_name || '-'}</td>
            <td>${purchase.item_number || '-'}</td>
            <td>${purchase.qty}</td>
            <td>${fmt(purchase.price_cents)}</td>
            <td>${purchase.tax_pct}%</td>
            <td><span class="badge badge-info">${purchase.bank_name || (purchase.payment_type === 'cash' ? 'Cash' : purchase.payment_type === 'credit' ? 'Credit' : '-')}</span></td>
            <td>${purchase.due_date || '-'}</td>
            <td class="font-semibold">${fmt(purchase.total_cents)}</td>
            <td>
              <span class="badge ${purchase.status === 'paid' ? 'badge-success' : 'badge-warning'}">
                ${purchase.status ? purchase.status.toUpperCase() : 'PAID'}
              </span>
            </td>
            <td>
              <div class="flex space-x-2">
                <button class="btn btn-warning btn-sm" onclick="editPurchase(${JSON.stringify(purchase).replace(/"/g, '&quot;')})">Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deletePurchase(${purchase.id})">Delete</button>
              </div>
            </td>
          `;
          tbody.appendChild(tr);
        });
        
        // Calculate statistics
        let totalRecords = d.purchases.length;
        const cashPurchases = d.purchases.filter(p => p.payment_type === 'cash');
        const creditPurchases = d.purchases.filter(p => p.payment_type === 'credit' || p.payment_type === 'prepaid');
        const bankPurchases = d.purchases.filter(p => p.payment_type === 'bank');

        // Update stats
        document.getElementById('statPurchRecords').textContent = d.purchases.length;
        document.getElementById('statPurchCash').textContent = fmt(cashPurchases.reduce((a, b) => a + b.total_cents, 0));
        document.getElementById('statPurchCredit').textContent = fmt(creditPurchases.reduce((a, b) => a + b.total_cents, 0));
        document.getElementById('statPurchBank').textContent = fmt(bankPurchases.reduce((a, b) => a + b.total_cents, 0));
      } catch (err) {
        toast('Failed to load purchases: ' + err.message, 'error');
      }
    }
    // NEW
    // Function to load purchase credit data
    async function loadPurchaseCredit() {
      try {
        const purchases = await api('list_purchases');
        const creditPurchases = purchases.purchases.filter(p => (p.payment_type === 'credit' || p.payment_type === 'prepaid') && p.status !== 'paid');
        // console.log(creditPurchases);
        // console.log(purchases);
        // Update the card total
        const creditTotal = creditPurchases.reduce((sum, p) => sum + p.total_cents, 0);
        document.getElementById('purchaseCreditTotal').textContent = fmt(creditTotal);
        // Update the table
        const tbody = document.getElementById('purchaseCreditTable');
        tbody.innerHTML = '';
        if (creditPurchases.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-center text-gray-500 py-4">No credit purchases recorded</td></tr>';
          return;
        }
        creditPurchases.forEach(purchase => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="py-3">${purchase.date}</td>
                <td class="font-semibold">${purchase.item_name}</td>
                <td>${purchase.brand_name || '-'}</td>
                <td>${purchase.item_number || '-'}</td>
                <td>${purchase.qty}</td>
                <td>${fmt(purchase.price_cents)}</td>
                <td>${purchase.tax_pct}%</td>
                <td>${purchase.due_date || '-'}</td>
                <td class="font-semibold">${fmt(purchase.total_cents)}</td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load credit purchases: ' + err.message, 'error');
      }
    }
    // bank
    async function loadPurchaseCash() {
      try {
        const purchases = await api('list_purchases');
        const cashPurchases = purchases.purchases.filter(p => p.payment_type === 'cash');
        console.log(cashPurchases);
        // Update the card total
        const cashTotal = cashPurchases.reduce((sum, p) => sum + p.total_cents, 0);
        document.getElementById('purchaseCashTotal').textContent = fmt(cashTotal);
        // Update the table
        const tbody = document.getElementById('purchaseCashTable');
        tbody.innerHTML = '';
        if (cashPurchases.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-4">No cash purchases recorded</td></tr>';
          return;
        }
        cashPurchases.forEach(purchase => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="py-3">${purchase.date}</td>
                <td class="font-semibold">${purchase.item_name}</td>
                <td>${purchase.brand_name || '-'}</td>
                <td>${purchase.item_number || '-'}</td>
                <td>${purchase.qty}</td>
                <td>${fmt(purchase.price_cents)}</td>
                <td>${purchase.tax_pct}%</td>
                <td class="font-semibold">${fmt(purchase.total_cents)}</td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load cash purchases: ' + err.message, 'error');
      }
    }
    // Function to load bank purchases
    async function loadPurchaseBank() {
      try {
        const purchases = await api('list_purchases');
        const bankPurchases = purchases.purchases.filter(p => p.payment_type === 'bank');
        // Update the card total
        const bankTotal = bankPurchases.reduce((sum, p) => sum + p.total_cents, 0);
        document.getElementById('purchaseBankTotal').textContent = fmt(bankTotal);
        // Update the table
        const tbody = document.getElementById('purchaseBankTable');
        tbody.innerHTML = '';
        if (bankPurchases.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-center text-gray-500 py-4">No bank purchases recorded</td></tr>';
          return;
        }
        bankPurchases.forEach(purchase => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="py-3">${purchase.date}</td>
                <td class="font-semibold">${purchase.item_name}</td>
                <td>${purchase.brand_name || '-'}</td>
                <td>${purchase.item_number || '-'}</td>
                <td>${purchase.qty}</td>
                <td>${fmt(purchase.price_cents)}</td>
                <td>${purchase.tax_pct}%</td>
                <td><span class="badge badge-info">${purchase.bank_name || (purchase.payment_type === 'cash' ? 'Cash' : purchase.payment_type === 'credit' ? 'Credit' : '-')}</span></td>
                <td class="font-semibold">${fmt(purchase.total_cents)}</td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load bank purchases: ' + err.message, 'error');
      }
    }
    // Function to load purchase prepaid data
    async function loadPurchasePrepaid() {
      try {
        const purchases = await api('list_purchases');
        const prepaidPurchases = purchases.purchases.filter(p => p.payment_type === 'prepaid');
        // Update the card total
        const prepaidTotal = prepaidPurchases.reduce((sum, p) => sum + p.total_cents, 0);
        document.getElementById('purchasePrepaidTotal').textContent = fmt(prepaidTotal);
        // Update the table
        const tbody = document.getElementById('purchasePrepaidTable');
        tbody.innerHTML = '';
        if (prepaidPurchases.length === 0) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-center text-gray-500 py-4">No prepaid purchases recorded</td></tr>';
          return;
        }
        prepaidPurchases.forEach(purchase => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="py-3">${purchase.date}</td>
                <td class="font-semibold">${purchase.item_name}</td>
                <td>${purchase.brand_name || '-'}</td>
                <td>${purchase.item_number || '-'}</td>
                <td>${purchase.qty}</td>
                <td>${fmt(purchase.price_cents)}</td>
                <td>${purchase.tax_pct}%</td>
                <td>${purchase.due_date || '-'}</td>
                <td class="font-semibold">${fmt(purchase.total_cents)}</td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load prepaid purchases: ' + err.message, 'error');
      }
    }
    // Add these event listeners to your existing event listener section
    document.getElementById('cardPurchaseCash').addEventListener('click', () => {
      loadPurchaseCash();
      openModal('modalPurchaseCash');
    });
    // buy
    document.getElementById('buyButton').addEventListener('click', () => {
      loadPurchaseCash();
      openModal('modalpurchaseSection');
    });
    document.getElementById('btnAddPurchase').addEventListener('click', () => {
      loadPurchaseCash();
      closeModal('modalpurchaseSection');
    });
    // sell
    document.getElementById('sellButton').addEventListener('click', () => {
      // loadPurchaseCash();
      console.log('sell');
      openModal('modalSellinventorySection');
    });
    // document.getElementById('sellButton').addEventListener('click', () => {
    // // loadPurchaseCash();
    // closeModal('modalSellinventorySection');
    // });
    // closeModal('salesmodeleditform');
    document.getElementById('cardPurchaseBank').addEventListener('click', () => {
      loadPurchaseBank();
      openModal('modalPurchaseBank');
    });
    document.getElementById('cardPurchasePrepaid').addEventListener('click', () => {
      loadPurchasePrepaid();
      openModal('modalPurchasePrepaid');
    });
    document.getElementById('cardPurchaseCredit').addEventListener('click', () => {
      loadPurchaseCredit();
      openModal('modalPurchaseCredit');
    });

    async function editPurchase(purchase) {
      const form = document.getElementById('formEditPurchase');
      form.id.value = purchase.id;
      form.item_name.value = purchase.item_name;
      form.brand_name.value = purchase.brand_name || '';
      form.item_number.value = purchase.item_number || '';
      form.qty.value = purchase.qty;
      form.price.value = (purchase.price_cents / 100).toFixed(2);
      form.tax_pct.value = purchase.tax_pct;
      // form.due_date.value = purchase.due_date || ''; // Handled by payment type logic
      form.status.value = purchase.status || 'paid';
      
      // Set payment type
      const paymentType = purchase.payment_type || 'cash';
      const paymentSelect = document.getElementById('editPurchasePaymentType');
      paymentSelect.value = paymentType;

      // Populate banks
      const bankSelect = document.getElementById('editPurchaseBank');
      bankSelect.innerHTML = '<option value="0">-- No Bank --</option>';
      try {
        const d = await api('list_banks');
        d.banks.forEach(b => {
          const opt = document.createElement('option');
          opt.value = b.id;
          opt.textContent = `${b.name} ($${fmt(b.balance_cents)})`;
          bankSelect.appendChild(opt);
        });
        bankSelect.value = purchase.bank_id || '0'; // Set selected bank
      } catch (err) {
        toast('Failed to load banks for edit purchase: ' + err.message, 'error');
      }

      // Set due date if available
      const dueDateInput = form.querySelector('[name="due_date"]');
      if (dueDateInput) {
        dueDateInput.value = purchase.due_date || '';
      }
      
      // Trigger change event to set visibility
      paymentSelect.dispatchEvent(new Event('change'));

      openModal('modalEditPurchase');
    }
    async function deletePurchase(id) {
      if (!confirm('Are you sure you want to delete this purchase?')) return;
      try {
        await api('delete_purchase', {
          id
        });
        toast('Purchase deleted successfully');
        await loadDashboard();
        await loadPurchases();
        await loadPurchaseCredit();
      } catch (err) {
        toast('Failed to delete purchase: ' + err.message, 'error');
      }
    }
    // New function to open Update Purchase modal
    async function openUpdatePurchase(id, name, brand_name, item_number, price_cents, tax_pct) {
      const form = document.getElementById('formUpdatePurchase');
      form.querySelector('[name="item_id"]').value = id;
      form.querySelector('#updatePurchaseItemName').value = name;
      form.querySelector('[name="price"]').value = (price_cents / 100).toFixed(2);
      form.querySelector('[name="tax_pct"]').value = tax_pct;
      form.querySelector('[name="brand_name"]').value = brand_name === '-' ? '' : brand_name;
      form.querySelector('[name="item_number"]').value = item_number === '-' ? '' : item_number;
      // Populate banks
      try {
        const d = await api('list_banks');
        const select = form.querySelector('[name="bank_id"]');
        select.innerHTML = '<option value="">Select a bank</option>';
        d.banks.forEach(b => {
          const opt = document.createElement('option');
          opt.value = b.id;
          opt.textContent = `${b.name} ($${fmt(b.balance_cents)})`;
          select.appendChild(opt);
        });
        openModal('modalUpdatePurchase');
      } catch (err) {
        toast('Failed to load banks: ' + err.message, 'error');
      }
    }
    // New function to delete item
    async function deleteItem(id) {
      if (!confirm('Are you sure you want to delete this item?')) return;
      try {
        await api('delete_item', {
          id
        });
        toast('Item deleted successfully');
        await loadItems();
        await loadDashboard();
      } catch (err) {
        toast('Failed to delete item: ' + err.message, 'error');
      }
    }
    // ---------- SALES MANAGEMENT ----------
    // async function loadSales() {
    // try {
    // const d = await api('list_sales');
    // const tbody = document.getElementById('salesTable');
    // tbody.innerHTML = '';
    // console.log(d.sales);
    // d.sales.forEach(sale => {
    // const paidVia = sale.payment_method === 'Paid' ?
    // (sale.paid_via === 'cash' ? 'Cash' : sale.bank_name || '-') :
    // (sale.bank_name || '-');
    // const tr = document.createElement('tr');
    // tr.innerHTML = `
    // <td class="py-3">${sale.date}</td>
    // <td class="font-semibold">${sale.item_name}</td>
    // <td>${sale.username}</td>
    // <td>${sale.phone}</td>
    // <td>${sale.qty}</td>
    // <td>${sale.ids}</td>
    // <td>${fmt(sale.price_cents)}</td>
    // <td>${sale.tax_pct}%</td>
    // <td><span class="badge badge-success">${sale.payment_method}</span></td>
    // <td>${paidVia}</td>
    // <td>${sale.due_date || '-'}</td>
    // <td>${fmt(sale.prepayment_cents || 0)}</td>                          
    // <td class="font-semibold">${fmt(sale.total_cents)}</td>
    // <button class="btn btn-warning btn-sm" onclick="editSalesItem(${sale.id}, '${sale.item_name.replace(/'/g, "\\'")}', '${sale.item_number}', ${sale.qty},${sale.ids}, ${sale.price_cents}, ${sale.tax_pct},'${sale.username.replace(/'/g, "\\'")}','${sale.phone.replace(/'/g, "\\'")}')">
    // <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
    // <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 000-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
    // </svg>
    // Edit
    // </button>
    // <button class="btn btn-danger btn-sm" onclick="delete_salse(${sale.id}, '${sale.item_name.replace(/'/g, "\\'")}', '${sale.item_number}', ${sale.qty}, ${sale.price_cents}, ${sale.tax_pct})">
    // Delete
    // </button>
    // `;
    // tbody.appendChild(tr);
    // });
    // } catch (err) {
    // toast('Failed to load sales: ' + err.message, 'error');
    // }
    // }
    /* -------------------------------------------------
   GLOBAL ARRAY – filled by API
   ------------------------------------------------- */                                                                                                                               
    let allSales = [];
    /* -------------------------------------------------
       HELPERS (keep yours)
       ------------------------------------------------- */
    function fmts(cents) {
      const dollars = cents / 100;
      if (dollars >= 1000) {
        return (dollars / 1000).toFixed(1) + 'k';
      }
      return dollars.toFixed(2);
    }
    // function toast(msg, type = 'info') {
    // // your toast implementation
    // }
    /* -------------------------------------------------
       RENDER ONE ROW (exact HTML you already use)
       ------------------------------------------------- */
    function renderRow(sale) {
      // Show how it was paid if status is paid, otherwise show payment method type
      const paidVia = sale.status === 'paid' ?
        (sale.paid_via === 'cash' ? 'Cash' : (sale.bank_name || 'Bank')) :
        (sale.payment_method || '-');
      const tr = document.createElement('tr');
      tr.className = 'hover:bg-gray-50 transition-colors';
      tr.innerHTML = `
    <td class="px-4 py-3 text-sm">${sale.date}</td>
    <td class="px-4 py-3 text-sm font-semibold">${sale.item_name}</td>
    <td class="px-4 py-3 text-sm">${sale.username}</td>
    <td class="px-4 py-3 text-sm">${sale.phone}</td>
    <td class="text-center">
    ${sale.item_image_path ?
        `<img src="${sale.item_image_path}" alt="Item" class="w-12 h-12 rounded object-cover">` :
        '📷'
    }
</td>
    <td class="px-4 py-3 text-sm">${sale.qty}</td>
    <td class="px-4 py-3 text-sm">${sale.ids}</td>

    <td class="px-4 py-3 text-sm">${fmts(sale.price_cents)}</td>
    <td class="px-4 py-3 text-sm">${sale.tax_pct}%</td>
    <td class="px-4 py-3">
      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
        ${sale.payment_method === 'Paid' ? 'Bank' : sale.payment_method }
      </span>
    </td>
    <td class="px-4 py-3 text-sm">${paidVia}</td>
    <td class="px-4 py-3 text-sm">${sale.due_date || '-'}</td>
    <td class="px-4 py-3 text-sm">${fmts(sale.prepayment_cents || 0)}</td>
    <td class="px-4 py-3 text-sm font-semibold">${fmts(sale.total_cents)}</td>
    <td class="px-4 py-3">
  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${sale.status.toLowerCase() === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">
    ${sale.status.charAt(0).toUpperCase() + sale.status.slice(1)}
  </span>
</td>
    <td class="px-4 py-3 text-sm space-x-1">
<button class="btn btn-warning btn-sm" onclick="editSalesItem(${sale.id},
  '${(sale.item_name ?? '').replace(/'/g, "\\'")}',
  '${(sale.item_number ?? '00')}',
  ${sale.qty ?? 0},
${sale.ids === "" ? 0 : sale.ids},
  ${sale.price_cents ?? 0},
  ${sale.tax_pct ?? 0},
  '${(sale.username ?? '').replace(/'/g, "\\'")}',
  '${(sale.phone ?? '')}','${(sale.payment_method ?? '').replace(/'/g, "\\'")}','${(sale.status ?? 'unpaid')}', ${sale.prepayment_cents ?? 0}, ${sale.total_cents ?? 0}, ${sale.bank_id || 'null'}, '${sale.paid_via || ''}')">
        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
          <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 000-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
        </svg>
        Edit
      </button>
      </td>
         <td class="px-4 py-3 text-sm space-x-1">
      <button class="inline-flex items-center px-2 py-1 bg-red-500 hover:bg-yellow-600 text-white text-xs rounded-md"
              onclick="delete_salse(${sale.id},
                '${sale.item_name.replace(/'/g, "\\'")}',
                '${sale.item_number || ''}',
                ${sale.qty},
                ${sale.price_cents},
                ${sale.tax_pct})">
        Delete
      </button>
  
    </td>
  `;
      return tr;
    }
    /* -------------------------------------------------
       FILTER + STATISTICS
       ------------------------------------------------- */
    const $tbody = document.getElementById('salesTable');
    const $search = document.getElementById('searchInput');
    const $stats = {
      rec: document.getElementById('statRecords'),
      cash: document.getElementById('statCash'),
      cred: document.getElementById('statCredit'),
      // bank: document.getElementById('statBank'),
      oth: document.getElementById('statOther')
    };

    function applyFilter() {
      const term = $search.value.trim().toLowerCase();
      const timeRange = document.getElementById('timeFilter').value;
      
      // Calculate cutoff date
      let cutoffDate = null;
      if (timeRange === '6months') {
        cutoffDate = new Date();
        cutoffDate.setMonth(cutoffDate.getMonth() - 6);
      } else if (timeRange === '1year') {
        cutoffDate = new Date();
        cutoffDate.setFullYear(cutoffDate.getFullYear() - 1);
      }
      
      const visible = allSales.filter(s => {
        const matchesSearch = s.username.toLowerCase().includes(term) ||
          s.phone.includes(term);
        
        if (!matchesSearch) return false;
        
        if (cutoffDate) {
          const saleDate = new Date(s.date);
          if (saleDate < cutoffDate) return false;
        }
        
        return true;
      });
      // ---- render rows -------------------------------------------------
      $tbody.innerHTML = '';
      visible.forEach(s => $tbody.appendChild(renderRow(s)));
      if (visible.length === 0) {
        $tbody.innerHTML = '<tr><td colspan="17" class="text-center text-gray-500 py-4">No sales recorded</td></tr>';
      }
      // ---- calculate stats ---------------------------------------------
      let cash = 0,
        credit = 0,
        // bank = 0;
        other = 0;
      visible.forEach(s => {
        const tot = s.total_cents / 100;
        const method = (s.payment_method || '').toLowerCase();
        const paidVia = (s.paid_via || '').toLowerCase();

        if (method === 'credit' || method === 'pre-paid') {
          credit += tot;
        } else if (method === 'paid') {
          if (paidVia === 'cash') {
            cash += tot;
          } else {
            // Paid via Bank
            other += tot;
          }
        }
      });
      $stats.rec.textContent = visible.length;
      $stats.cash.textContent = cash.toFixed(2);
      $stats.cred.textContent = credit.toFixed(2);
      // $stats.bank.textContent = bank.toFixed(2);
      $stats.oth.textContent = other.toFixed(2);
    }
    /* -------------------------------------------------
       LOAD SALES (store + initial render)
       ------------------------------------------------- */
    async function loadSales() {
      try {
        const d = await api('list_sales');
        allSales = d.sales;
        applyFilter(); // show everything first
      } catch (err) {
        toast('Failed to load sales: ' + err.message, 'error');
      }
    }
    /* -------------------------------------------------
       EVENT LISTENERS
       ------------------------------------------------- */
    $search.addEventListener('input', applyFilter);
    document.getElementById('timeFilter').addEventListener('change', applyFilter);
    /* -------------------------------------------------
       REFRESH AFTER EDIT/DELETE
       ------------------------------------------------- */
    // function refreshAfterMutation() {
    // loadSales(); // re-fetch + keep current search term
    // }
    /* -------------------------------------------------
       STUBS – replace with your real logic
       ------------------------------------------------- */
    // function editSalesItem(...args) {
    // alert('Edit – implement your modal.\nArgs: ' + args.join(', '));
    // }
    // function delete_salse(...args) {
    // if (confirm('Delete this sale?')) {
    // // call your delete API, then:
    // refreshAfterMutation();
    // }
    // }
    document.getElementById('modalEditSales').addEventListener('submit', async e => {
      e.preventDefault();
      try {
        await api('edit_sales_item', Object.fromEntries(new FormData(e.target)));
        toast('Item updated successfully');
        closeModal('salesmodeleditform');
        await loadSales();
        await loadDashboard();
      } catch (err) {
        toast('Failed to update item: ' + err.message, 'error');
      }
    });
    async function loadCash() {
      try {
        const d = await api('list_sales');
        const tbody = document.getElementById('cashTable');
        tbody.innerHTML = '';
        const cashSales = d.sales.filter(s => s.payment_method === 'Paid' && s.paid_via === 'cash');
        cashSales.forEach(sale => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="py-3">${sale.date}</td>
            <td class="font-semibold">${sale.item_name}</td>
            <td>${sale.qty}</td>
            <td>${fmt(sale.price_cents)}</td>
            <td>${sale.tax_pct}%</td>
            <td class="font-semibold">${fmt(sale.total_cents)}</td>
          `;
          tbody.appendChild(tr);
        });
        if (cashSales.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="text-center text-gray-500 py-4">No cash sales recorded</td></tr>';
        }
      } catch (err) {
        toast('Failed to load cash sales: ' + err.message, 'error');
      }
    }
    // ---------- INVENTORY MANAGEMENT ----------
    let currentItems = [];
    async function loadItems() {
      try {
        const d = await api('list_items');
        currentItems = d.items;
        renderItems();
      } catch (err) {
        toast('Failed to load inventory: ' + err.message, 'error');
      }
    }
    async function renderItems() {
      const query = document.getElementById('searchItems').value.toLowerCase();
      const tbody = document.getElementById('itemsTable');
      tbody.innerHTML = '';
      try {
        const d = await api('list_items');
        currentItems = d.items;
        // Ensure brand_name and item_number are strings
        currentItems.forEach(item => {
          item.brand_name = item.brand_name != null ? String(item.brand_name) : '-';
          item.item_number = item.item_number != null ? String(item.item_number) : '-';
        });
        const filteredItems = currentItems.filter(item => {
          const q = query.toLowerCase();
          return item.name.toLowerCase().includes(q) ||
                 item.brand_name.toLowerCase().includes(q) ||
                 item.item_number.toLowerCase().includes(q);
        }).sort((a, b) => {
          const q = query.toLowerCase();
          // Prioritize exact item number match
          if (a.item_number.toLowerCase() === q && b.item_number.toLowerCase() !== q) return -1;
          if (b.item_number.toLowerCase() === q && a.item_number.toLowerCase() !== q) return 1;
          // Then starts with item number
          if (a.item_number.toLowerCase().startsWith(q) && !b.item_number.toLowerCase().startsWith(q)) return -1;
          if (b.item_number.toLowerCase().startsWith(q) && !a.item_number.toLowerCase().startsWith(q)) return 1;
          return 0;
        });
        if (filteredItems.length === 0) {
          tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-500 py-8">No items found</td></tr>';
          return;
        }
        filteredItems.forEach(item => {
          const isLowStock = item.qty <= 5;
          const tr = document.createElement('tr');
          tr.className = isLowStock ? 'bg-yellow-50' : '';
          tr.innerHTML = `
                <td class="py-3">
                    <div class="font-semibold text-gray-900 mb-8">${item.name}</div>
                    ${isLowStock ? '<div class="text-xs text-yellow-600 font-medium">Low Stock!</div>' : ''}
                </td>
                <td>
                    <span class="font-semibold ${isLowStock ? 'text-yellow-600' : 'text-gray-900'}">${item.qty}</span>
                </td>
                <td>${item.brand_name}</td>
                <td>${item.item_number}</td>
                <td>$${fmt(item.price_cents)}</td>
                <td>${item.tax_pct}%</td>
                <td>
                    ${isLowStock
                        ? '<span class="badge badge-warning">Low Stock</span>'
                        : '<span class="badge badge-success">In Stock</span>'
                    }
                </td>
                <td>
                    <div class="flex space-x-2">
                        <button class="btn btn-danger btn-sm" onclick="openSellModal(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                            Sell
                        </button>
                       ${userRole === 'admin' || (userPermissions && userPermissions.purchase) ? `
                       <button class="btn btn-warning btn-sm" onclick="openUpdatePurchase(${item.id}, '${item.name.replace(/'/g, "\\\\'") }', '${item.brand_name.replace(/'/g, "\\\\'") }', '${item.item_number.replace(/'/g, "\\\\'") }', ${item.price_cents}, ${item.tax_pct})">
    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
        <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 2M7 13l1.5 2m7.5-2a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
    </svg>
    Purchase
</button>
` : ''}
                        <button class="btn btn-warning btn-sm" onclick="openEditItem(${item.id}, '${item.name.replace(/'/g, "\\\\'") }', '${item.brand_name.replace(/'/g, "\\\\'") }', '${item.item_number}', ${item.qty}, ${item.ids}, ${item.price_cents}, ${item.tax_pct})">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a.996.996 0 000-1.41l-2.34-2.34a.996.996 0 00-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                            </svg>
                            Edit
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id})">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                            </svg>
                            Delete
                        </button>
                    </div>
                </td>
            `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load inventory: ' + err.message, 'error');
      }
    }

    function openEditItem(id, name, brand_name, item_number, qty, price_cents, tax_pct) {
      const form = document.getElementById('formEditItem');
      form.id.value = id;
      form.name.value = name;
      form.brand_name.value = brand_name === '-' ? '' : brand_name;
      form.item_number.value = item_number === '-' ? '' : item_number;
      form.qty.value = qty;
      form.price.value = (price_cents / 100).toFixed(2);
      form.tax_pct.value = tax_pct;
      openModal('modalEditItem');
    }

    function editSalesItem(id, item_name, item_number, qty, ids, price_cents, tax_pct, username, phone, payment_method, status, prepayment_cents, total_cents, bank_id, paid_via) {
      console.log(username);
      console.log(id);
      const form = document.getElementById('modalEditSales');
      form.id.value = id;
      form.name.value = item_name;
      form.item_number.value = item_number === '' ? '' : item_number;
      form.qty.value = qty;
      form.price.value = (price_cents / 100).toFixed(2);
      form.tax_pct.value = tax_pct;
      form.ids.value = ids;
      form.elements.username.value = username;
      form.elements.phone.value = phone;
      const statusWrapper = document.getElementById('statusWrapper');
      const paidViaWrapper = document.getElementById('paidViaWrapper');
      const paidViaSelect = document.getElementById('editPaidVia');
      let currentPaidViaValue = '';
      if (paid_via === 'cash') {
        currentPaidViaValue = 'cash';
      } else if (paid_via === 'bank' && bank_id) {
        currentPaidViaValue = 'bank:' + bank_id;
      }
      // Always show status
      statusWrapper.classList.remove('hidden');
      form.status.value = (status === 'unpaid') ? 'pending' : status;

      // Initial Paid Via visibility
      paidViaWrapper.classList.toggle('hidden', form.status.value !== 'paid');
      if (form.status.value === 'paid') {
        paidViaSelect.value = currentPaidViaValue;
      }

      // Change listener
      form.status.addEventListener('change', e => {
        paidViaWrapper.classList.toggle('hidden', e.target.value !== 'paid');
      });
      // form.phone.value = phone;
      openModal('salesmodeleditform');
    }
    async function delete_salse(id) {
      if (!confirm('Are you sure you want to delete this sales?')) return;
      try {
        await api('delete_salse', {
          id
        });
        toast('Sales deleted successfully');
        await loadDashboard();
        await loadSales();
        // await loadPurchaseCredit();
      } catch (err) {
        toast('Failed to delete sales: ' + err.message, 'error');
      }
    }
    // document.getElementById('formEditItem').addEventListener('submit', async e => {
    // e.preventDefault();
    // try {
    // await api('edit_item', Object.fromEntries(new FormData(e.target)));
    // toast('Item updated successfully');
    // closeModal('modalEditItem');
    // await loadItems();
    // await loadDashboard();
    // } catch (err) {
    // toast('Failed to update item: ' + err.message, 'error');
    // }
    // });
    function openSellModal(item) {
      const form = document.getElementById('formSell');
      form.reset();
      form.item_id.value = item.id;
      form.ids.value = item.ids;
      form.price.value = (item.price_cents / 100).toFixed(2);
      form.tax_pct.value = item.tax_pct;
      document.getElementById('pmethod').value = 'Paid';
      showMethodBlocks('Paid');
      openModal('modalSell');
    }

    function showMethodBlocks(method) {
      document.getElementById('paidBlock').classList.toggle('hidden', method !== 'Paid');
      document.getElementById('prepaidBlock').classList.toggle('hidden', method !== 'Pre-paid');
      document.getElementById('dueBlock').classList.toggle('hidden', method === 'Paid' || method === 'Pay with Prepaid');
    }
    // function openEditItem(id, name, qty, price_cents, tax_pct) {
    // const form = document.getElementById('formEditItem');
    // form.id.value = id;
    // form.name.value = name;
    // form.brand_name.value = brand_name === '-' ? '' : brand_name;
    // form.item_number.value = item_number === '-' ? '' : item_number;
    // form.qty.value = qty;
    // form.price.value = (price_cents / 100).toFixed(2);
    // form.tax_pct.value = tax_pct;
    // openModal('modalEditItem');
    // }
    // document.getElementById('formEditItem').addEventListener('submit', async e => {
    // e.preventDefault();
    // try {
    // await api('edit_item', Object.fromEntries(new FormData(e.target)));
    // toast('Item updated successfully');
    // closeModal('modalEditItem');
    // await loadItems();
    // await loadDashboard();
    // } catch (err) {
    // toast('Failed to update item: ' + err.message, 'error');
    // }
    // });
    document.getElementById('formEditItem').addEventListener('submit', async e => {
      e.preventDefault();
      try {
        await api('edit_item', Object.fromEntries(new FormData(e.target)));
        toast('Item updated successfully');
        closeModal('modalEditItem');
        await loadItems();
        await loadDashboard();
      } catch (err) {
        toast('Failed to update item: ' + err.message, 'error');
      }
    });
    // ---------- MISCELLANEOUS MANAGEMENT ----------
    async function loadMisc() {
      try {
        await refreshBanks();
        const d = await api('list_misc');
        const tbody = document.getElementById('miscTable');
        tbody.innerHTML = '';
        d.misc.forEach(misc => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="py-3">${misc.date}</td>
            <td class="font-semibold">${misc.name}</td>
            <td class="text-gray-600">${misc.reason || '-'}</td>
            <td><span class="badge badge-info">${misc.bank_name}</span></td>
            <td class="font-semibold text-red-600">${fmt(misc.amount_cents)}</td>
          `;
          tbody.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to load miscellaneous expenses: ' + err.message, 'error');
      }
    }
    // ---------- REPORTS (CONTINUED) ----------
    async function generateReport(formData) {
      try {
        const params = Object.fromEntries(formData);
        const d = await api('reports', params);
        // Update summary cards
        const reportSummary = document.getElementById('reportSummary');
        reportSummary.innerHTML = `
          <div class="stat-card p-6 rounded-xl">
            <p class="text-sm opacity-80">Total Purchases</p>
            <p class="text-2xl font-bold mt-2">$${fmt(d.totals.purchases_cents)}</p>
            <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
          </div>
          <div class="stat-card p-6 rounded-xl">
            <p class="text-sm opacity-80">Total Sales</p>
            <p class="text-2xl font-bold mt-2">$${fmt(d.totals.sales_cents)}</p>
            <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
          </div>
          <div class="stat-card p-6 rounded-xl">
            <p class="text-sm opacity-80">Tax Collected</p>
            <p class="text-2xl font-bold mt-2">$${fmt(d.totals.tax_collected_cents)}</p>
            <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
          </div>
          <div class="stat-card p-6 rounded-xl">
            <p class="text-sm opacity-80">Net Profit</p>
            <p class="text-2xl font-bold mt-2">$${fmt(d.totals.profit_cents)}</p>
            <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
          </div>
        `;
        // Update purchases table
        const reportPurchases = document.getElementById('reportPurchases');
        reportPurchases.innerHTML = '';
        d.purchases.forEach(p => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="py-3">${p.date}</td>
            <td>${p.item_name}</td>
            <td>${p.brand_name || '-'}</td>
            <td>${p.item_number || '-'}</td>
            <td>${p.qty}</td>
            <td>$${fmt(p.total_cents)}</td>
          `;
          reportPurchases.appendChild(tr);
        });
        // Update sales table
        const reportSales = document.getElementById('reportSales');
        reportSales.innerHTML = '';
        d.sales.forEach(s => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="py-3">${s.date}</td>
            <td>${s.item_name}</td>
            <td>${s.qty}</td>
            <td><span class="badge badge-success">${s.payment_method}</span></td>
            <td>$${fmt(s.total_cents)}</td>
          `;
          reportSales.appendChild(tr);
        });
        // Update misc expenses table
        const reportMisc = document.getElementById('reportMisc');
        reportMisc.innerHTML = '';
        d.misc.forEach(m => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="py-3">${m.date}</td>
            <td>${m.name}</td>
            <td>${m.bank_name}</td>
            <td>$${fmt(m.amount_cents)}</td>
          `;
          reportMisc.appendChild(tr);
        });
      } catch (err) {
        toast('Failed to generate report: ' + err.message, 'error');
      }
    }
    // ---------- EVENT LISTENERS ----------
    document.addEventListener('DOMContentLoaded', () => {
      // Initial load
      loadDashboard();
      loadItems();
      // Add event listener for Update Purchase form submission
      document.getElementById('formUpdatePurchase').addEventListener('submit', async e => {
        e.preventDefault();
        try {
          await api('update_purchase', Object.fromEntries(new FormData(e.target)));
          toast('Purchase recorded successfully');
          e.target.reset();
          closeModal('modalUpdatePurchase');
          await loadItems();
          await loadDashboard();
        } catch (err) {
          toast('Failed to record purchase: ' + err.message, 'error');
        }
      });
      // Navigation buttons
      // Add click handler for Pre-paid card
      document.getElementById('cardPrepaid').addEventListener('click', () => {
        loadPrepaidSales();
        openModal('modalPrepaid');
      });
      // Add click handler for Credit card
      document.getElementById('cardCredit').addEventListener('click', () => {
        loadCreditSales();
        openModal('modalCredit');
      });
      document.getElementById('btnMisc').addEventListener('click', () => {
        loadMisc();
        openModal('modalMisc');
      });
      // document.getElementById('btnReports').addEventListener('click', () => {
      // generateReport(new FormData(document.getElementById('formReports')));
      // openModal('modalReports');
      // });
      // Dashboard card clicks
      document.getElementById('cardBalance').addEventListener('click', () => {
        refreshBanks();
        openModal('modalBanks');
      });
      document.getElementById('cardPurchases').addEventListener('click', () => {
        loadPurchases();
        openModal('modalPurchases');
      });
      document.getElementById('cardSales').addEventListener('click', () => {
        loadSales();
        openModal('modalSales');
      });
      document.getElementById('cardCash').addEventListener('click', () => {
        loadCash();
        openModal('modalCash');
      });
      // Form submissions
      document.getElementById('formBank').addEventListener('submit', async e => {
        e.preventDefault();
        try {
          await api('add_bank', Object.fromEntries(new FormData(e.target)));
          toast('Bank added successfully');
          e.target.reset();
          await refreshBanks();
          await loadDashboard();
        } catch (err) {
          toast('Failed to add bank: ' + err.message, 'error');
        }
      });
      document.getElementById('formEditBank').addEventListener('submit', async e => {
        e.preventDefault();
        try {
          await api('edit_bank', Object.fromEntries(new FormData(e.target)));
          toast('Bank updated successfully');
          closeModal('modalEditBank');
          await refreshBanks();
          await loadDashboard();
        } catch (err) {
          toast('Failed to update bank: ' + err.message, 'error');
        }
      });
      // document.getElementById('formPurchase').addEventListener('submit', async e => {
      // e.preventDefault();
      // try {
      // await api('add_purchase', Object.fromEntries(new FormData(e.target)));
      // toast('Purchase recorded successfully');
      // e.target.reset();
      // await loadItems();
      // await loadDashboard();
      // } catch (err) {
      // toast('Failed to record purchase: ' + err.message, 'error');
      // }
      // });
      // Update your form submission handler to include payment_type
      document.getElementById('formPurchase').addEventListener('submit', async e => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const paymentType = formData.get('payment_type');
        // For cash payments, set bank_id to 0

        if (paymentType === 'cash') {
          formData.delete('bank_id');
        }
        // For non-bank payments, remove bank validation
        if (paymentType !== 'bank') {
          formData.delete('bank_id');
        }
        console.log(formData.get('bank_id'));

        try {
          await api('add_purchase', Object.fromEntries(formData));
          toast('Purchase recorded successfully');
          e.target.reset();
          // Reset the form UI
          document.getElementById('purchaseBankWrapper').classList.add('hidden');
          document.getElementById('purchaseDueDateWrapper').classList.add('hidden');
          document.getElementById('purchasePrepaidWrapper').classList.add('hidden');
          await loadItems();
          await loadDashboard();
        } catch (err) {
          toast('Failed to record purchase: ' + err.message, 'error');
        }
      });
      document.getElementById('formEditPurchase').addEventListener('submit', async e => {
        e.preventDefault();
        try {
          await api('edit_purchase', Object.fromEntries(new FormData(e.target)));
          toast('Purchase updated successfully');
          closeModal('modalEditPurchase');
          await loadPurchases();
          await loadItems();
          await loadDashboard();
          await loadPurchaseBank();
          await loadPurchaseCredit();
        } catch (err) {
          toast('Failed to update purchase: ' + err.message, 'error');
        }
      });
      document.getElementById('formSell').addEventListener('submit', async e => {
        e.preventDefault();
        try {
          const confirmSellBtn = document.getElementById('confirmSellBtnwaiing');
          confirmSellBtn.disabled = true; // disable button
          confirmSellBtn.textContent = "Waiting...";
          try {
            let testin = await api(
              'add_sale',
              Object.fromEntries(new FormData(e.target))
            );
            console.log(testin);
            if (testin && testin.ok === true) {
              toast('Sale recorded successfully');
              closeModal('modalSell');
              await loadItems();
              await loadDashboard();
            } else {
              toast('Error: Could not record sale');
            }
          } catch (err) {
            console.error(err);
            toast('Something went wrong');
          } finally {
            // always re-enable after request
            confirmSellBtn.disabled = false;
            confirmSellBtn.textContent = "Confirm Sale";
          }
          // let testin= await api('add_sale', Object.fromEntries(new FormData(e.target)));
          // confirmSellBtnwaiing
          // if (condition) {

          // }
          // toast('Sale recorded successfully');
          // closeModal('modalSell');
          // await loadItems();
          // await loadDashboard();
          // closeModal('modalsellinventorySection');
        } catch (err) {
          toast('Failed to record sale: ' + err.message, 'error');
        }
      });
      document.getElementById('formMisc').addEventListener('submit', async e => {
        e.preventDefault();
        try {
          await api('add_misc', Object.fromEntries(new FormData(e.target)));
          toast('Expense recorded successfully');
          e.target.reset();
          await loadMisc();
          await loadDashboard();
          // loadnums();
          closeModal('modalMisc');
        } catch (err) {
          toast('Failed to record expense: ' + err.message, 'error');
        }
      });
      document.getElementById('formReports').addEventListener('submit', async e => {
        e.preventDefault();
        await generateReport(new FormData(e.target));
      });
      // Payment method toggle
      document.getElementById('pmethod').addEventListener('change', e => {
        showMethodBlocks(e.target.value);
      });
      // Inventory search
      document.getElementById('searchItems').addEventListener('input', renderItems);
      // loadnums();
      // setTimeout(() => {
      // loadnums();
      // }, 2);
    });
  </script>
</body>

</html>