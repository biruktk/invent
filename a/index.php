<?php
// admin.php - Developer/Super Admin Dashboard
// Fixed permissions to be compatible with index.php

// Ensure this matches the location of your DB file relative to this script
const DB_PATH = __DIR__ . '/../inventory2.sqlite'; 
$db = new PDO('sqlite:' . DB_PATH);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$tables = ['companies','users','items','purchases','sales','misc', 'banks'];
$current = $_GET['table'] ?? 'users';
$id = $_GET['id'] ?? null;

/* ------------------------------------------
   DELETE ROW
------------------------------------------ */
if (isset($_GET['delete']) && $id && in_array($current, $tables)) {
    // Prevent deleting the main admin/owner if creating a safety check
    if ($current === 'users') {
        $check = $db->prepare("SELECT role FROM users WHERE id = ?");
        $check->execute([$id]);
        $u = $check->fetch();
        // Optional: Prevent deleting the very first admin
        if ($id == 1) {
            echo "<script>alert('Cannot delete the root admin!'); window.location='?table=users';</script>";
            exit;
        }
    }

    $stm = $db->prepare("DELETE FROM $current WHERE id=?");
    $stm->execute([$id]);
    header("Location: ?table=$current");
    exit;
}

/* ------------------------------------------
   ADD COMPANY
------------------------------------------ */
if ($current === 'companies' && isset($_POST['add_company'])) {
    $name = trim($_POST['name']);
    if ($name) {
        $stm = $db->prepare("INSERT INTO companies (name) VALUES (?)");
        $stm->execute([$name]);
        $success = "Company '$name' created successfully!";
    } else { 
        $error = "Company name required"; 
    }
}

/* ------------------------------------------
   ADD USER (Fixed Permissions)
------------------------------------------ */
if ($current === 'users' && isset($_POST['add_user'])) {
    
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $company  = intval($_POST['company_id']);
    $role     = $_POST['role'];
    $duration = $_POST['pkg_duration']; // e.g., "+1 month"
    
    // Calculate expiry date for the package
    $expiry_date = date('Y-m-d', strtotime($duration));
    
    /* CRITICAL FIX: 
       Map checkboxes to the actual permission strings used in index.php 
    */
    $perms = [];

    // Sales Permissions
    if (isset($_POST['perm_sales'])) {
        array_push($perms, 'add_sale', 'list_sales', 'edit_sales_item', 'delete_salse', 'list_customers', 'list_prepaid_sales', 'list_credit_sales');
    }
    
    // Purchase Permissions
    if (isset($_POST['perm_purchases'])) {
        array_push($perms, 'add_purchase', 'list_purchases', 'edit_purchase', 'delete_purchase');
    }
    
    // Inventory Permissions
    if (isset($_POST['perm_inventory'])) {
        array_push($perms, 'list_items', 'edit_item', 'delete_item', 'add_stock');
    }

    // Finance/Bank Permissions
    if (isset($_POST['perm_finance'])) {
        array_push($perms, 'add_bank', 'edit_bank', 'delete_bank', 'list_banks', 'add_misc', 'list_misc');
    }

    // Admin Permissions (All Access)
    if ($role === 'admin') {
        // Admins typically bypass checks, but we can give them all tags too
        $perms = ['add_sale', 'list_sales', 'edit_sales_item', 'delete_salse', 'add_purchase', 'list_purchases', 'edit_purchase', 'delete_purchase', 'list_items', 'edit_item', 'delete_item', 'add_bank', 'edit_bank', 'delete_bank', 'list_banks', 'add_misc', 'list_misc'];
    }

    $permissions_json = json_encode(array_values(array_unique($perms)));

    if (!$username || (!$password && $id == null) || !$company) {
        $error = "Missing required fields";
    } else {
        $stm = $db->prepare("SELECT COUNT(*) FROM users WHERE username=?");
        $stm->execute([$username]);
        if ($stm->fetchColumn()) { 
            $error = "User '$username' already exists"; 
        } else {
            $stm = $db->prepare("
              INSERT INTO users (
                username, email, pstatus, password_hash, 
                txn, pkg, company_id, role, permissions
              ) VALUES (?, ?, 'paid', ?, ?, ?, ?, ?, ?)
            ");
            
            $stm->execute([
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'admin_created', 
                $expiry_date,
                $company,
                $role,
                $permissions_json
            ]);
            
            // If this is an Admin, ensure they are set as the company owner if none exists
            if ($role === 'admin') {
                $newUserId = $db->lastInsertId();
                $checkOwner = $db->prepare("SELECT owner_id FROM companies WHERE id=?");
                $checkOwner->execute([$company]);
                if (!$checkOwner->fetchColumn()) {
                    $db->prepare("UPDATE companies SET owner_id=? WHERE id=?")->execute([$newUserId, $company]);
                }
            }

            $success = "User '$username' added successfully with " . count($perms) . " permissions.";
        }
    }
}

/* ------------------------------------------
   LOAD DATA
------------------------------------------ */
$rows = $db->query("SELECT * FROM $current ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$companies = $db->query("SELECT * FROM companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Super Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #f3f4f6; }
        .active-tab { background-color: #2563eb; color: white; }
        .inactive-tab { background-color: #374151; color: #d1d5db; }
        .inactive-tab:hover { background-color: #4b5563; color: white; }
    </style>
</head>

<body class="p-6">

    <nav class="bg-gray-800 p-4 rounded-xl shadow-lg mb-8 flex flex-wrap gap-3">
        <div class="text-white font-bold text-xl mr-4 px-2 py-1">DEV ADMIN</div>
        <?php foreach($tables as $t): ?>
            <a class="px-4 py-2 rounded-lg font-medium transition-colors <?=($current===$t?'active-tab':'inactive-tab')?>"
               href="?table=<?=$t?>"><?=ucfirst($t)?></a>
        <?php endforeach; ?>
    </nav>

    <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-md border border-gray-200">

        <?php if(!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
                <p class="font-bold">Error</p>
                <p><?=h($error)?></p>
            </div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">
                <p class="font-bold">Success</p>
                <p><?=h($success)?></p>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800"><?=ucfirst($current)?> Management</h2>
            <span class="text-sm text-gray-500">Showing last 50 records</span>
        </div>

        <?php if ($current === 'companies'): ?>
        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 mb-8">
            <h3 class="font-bold text-lg mb-4 text-gray-700">Add New Company</h3>
            <form method="post" class="flex gap-4">
                <input name="name" placeholder="Company Name (e.g., Tech Corp)"
                       class="border border-gray-300 p-3 w-full rounded-lg focus:ring-2 focus:ring-blue-500 outline-none" required>
                <button name="add_company"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg transition-colors whitespace-nowrap">
                    Create Company
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($current === 'users'): ?>
        <div class="bg-gray-50 p-6 rounded-xl border border-gray-200 mb-8">
            <h3 class="font-bold text-lg mb-4 text-gray-700">Add New User / Employee</h3>
            <form method="post" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input name="username" placeholder="Username" class="border p-3 rounded-lg" required>
                    <input name="email" placeholder="Email Address" class="border p-3 rounded-lg">
                    <input name="password" type="password" placeholder="Password" class="border p-3 rounded-lg" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <select name="company_id" class="border p-3 rounded-lg bg-white" required>
                        <option value="">Select Company...</option>
                        <?php foreach($companies as $c): ?>
                            <option value="<?=$c['id']?>">ID: <?=$c['id']?> — <?=$c['name']?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="role" id="roleSelect" class="border p-3 rounded-lg bg-white" required>
                        <option value="employee">Employee</option>
                        <option value="admin">Company Admin (Owner)</option>
                    </select>

                    <select name="pkg_duration" class="border p-3 rounded-lg bg-white" required>
                        <option value="+1 month">1 Month Access</option>
                        <option value="+6 months">6 Months Access</option>
                        <option value="+1 year">1 Year Access</option>
                    </select>
                </div>

                <div class="bg-white p-4 rounded-lg border border-gray-200" id="permContainer">
                    <p class="font-semibold text-gray-700 mb-2">Permissions (for Employees)</p>
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="perm_sales" checked class="w-5 h-5 text-blue-600 rounded">
                            <span>Sales & POS</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="perm_purchases" class="w-5 h-5 text-blue-600 rounded">
                            <span>Purchases</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="perm_inventory" class="w-5 h-5 text-blue-600 rounded">
                            <span>Inventory Edit/Delete</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" name="perm_finance" class="w-5 h-5 text-blue-600 rounded">
                            <span>Finance & Banks</span>
                        </label>
                    </div>
                </div>

                <button name="add_user"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-lg transition-colors">
                    Create User
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="overflow-x-auto rounded-lg border border-gray-200">
            <table class="w-full bg-white text-sm text-left">
                <thead class="bg-gray-100 text-gray-600 uppercase font-bold">
                    <tr>
                        <?php if (!empty($rows)): foreach(array_keys($rows[0]) as $col): 
                            if ($col === 'password_hash') continue; // Hide hash
                        ?>
                            <th class="px-4 py-3 border-b"><?=h($col)?></th>
                        <?php endforeach; endif; ?>
                        <th class="px-4 py-3 border-b text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <?php foreach($rows as $r): 
                    $rowClass = "hover:bg-gray-50 transition";
                    
                    // Highlight expiring users
                    if ($current === 'users' && !empty($r['pkg'])) {
                        $expiry = strtotime($r['pkg']);
                        if ($expiry && $expiry < time() + (3*86400)) { // 3 days warning
                            $rowClass = "bg-red-50 hover:bg-red-100";
                        }
                    }
                ?>
                <tr class="<?=$rowClass?>">
                    <?php foreach($r as $k => $v): 
                        if ($k === 'password_hash') continue;
                    ?>
                        <td class="px-4 py-3 align-top">
                            <?php if ($k === 'pkg'): ?>
                                <span class="font-mono text-xs bg-gray-200 px-2 py-1 rounded"><?=h($v)?></span>
                            <?php elseif ($k === 'permissions'): ?>
                                <div class="max-w-xs overflow-hidden text-ellipsis whitespace-nowrap text-xs text-gray-500" title="<?=h($v)?>">
                                    <?=h($v)?>
                                </div>
                            <?php else: ?>
                                <?=h($v)?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="px-4 py-3 text-center align-top">
                        <a class="bg-red-100 text-red-600 hover:bg-red-200 px-3 py-1 rounded text-xs font-bold transition"
                           onclick="return confirm('Are you sure you want to delete ID <?=$r['id']?>? This cannot be undone.')"
                           href="?table=<?=$current?>&delete=1&id=<?=$r['id']?>">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($rows)): ?>
                    <tr><td colspan="100" class="px-4 py-8 text-center text-gray-500">No records found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        // Simple script to toggle permissions visibility based on role
        const roleSelect = document.getElementById('roleSelect');
        const permContainer = document.getElementById('permContainer');
        
        if(roleSelect) {
            roleSelect.addEventListener('change', function() {
                if(this.value === 'admin') {
                    permContainer.style.opacity = '0.5';
                    permContainer.style.pointerEvents = 'none'; // Admins get all perms auto
                } else {
                    permContainer.style.opacity = '1';
                    permContainer.style.pointerEvents = 'auto';
                }
            });
        }
    </script>
</body>
</html>