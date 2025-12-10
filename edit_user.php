<?php
// edit_user.php
require_once __DIR__ . "/bootstrap.php";

// get user id
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Invalid user ID");
}

// fetch user
$stmt = $pdo->prepare("SELECT id, username, email, role, permissions FROM users WHERE id=?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $role     = trim($_POST['role']);
    $permissions = $_POST['permissions'] ?? [];
    $permissions_json = json_encode($permissions);

    $update = $pdo->prepare("UPDATE users SET username=?, email=?, role=?, permissions=? WHERE id=?");
    $update->execute([$username, $email, $role, $permissions_json, $id]);

    header("Location: pro.php?success=updated");
    exit;
}

$current_permissions = json_decode($user['permissions'] ?? '[]', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - <?= htmlspecialchars($user['username']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
    <style>
        body { 
            background: linear-gradient(to bottom, #87CEEB, #ADD8E6); 
            font-family: 'Noto Sans Ethiopic', sans-serif; 
        }
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-slide-in {
            animation: slideInUp 0.5s ease-out;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="pro.php" class="flex items-center text-blue-600 hover:text-blue-800 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                        </svg>
                        Back to Employees
                    </a>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Logged in as <?= htmlspecialchars($_SESSION['username']) ?></span>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-24">
        <!-- Header -->
        <div class="text-center mb-8 animate-slide-in">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2 drop-shadow-lg">
                Edit Employee
            </h1>
            <p class="text-lg text-white opacity-90">
                Update employee information and permissions
            </p>
        </div>

        <!-- Edit Form Card -->
        <div class="glass-effect rounded-2xl sm:rounded-3xl p-6 sm:p-8 max-w-4xl mx-auto shadow-xl animate-slide-in">
            <div class="flex items-center gap-3 mb-6">
                <i data-feather="user-edit" class="w-8 h-8 text-indigo-600"></i>
                <h2 class="text-2xl font-bold text-gray-800">Employee Details</h2>
            </div>

            <form method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="space-y-2">
                        <label for="username" class="block text-sm font-semibold text-gray-700">Username</label>
                        <input type="text" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                               name="username" 
                               id="username" 
                               value="<?= htmlspecialchars($user['username']) ?>"
                               required>
                    </div>
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-semibold text-gray-700">Email Address</label>
                        <input type="email" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                               name="email" 
                               id="email" 
                               value="<?= htmlspecialchars($user['email']) ?>"
                               required>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <label for="role" class="block text-sm font-semibold text-gray-700">Role</label>
                        <input type="text" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                               name="role" 
                               id="role" 
                               value="<?= htmlspecialchars($user['role']) ?>"
                               placeholder="e.g., employee, manager, sales">
                    </div>
                </div>

                <!-- Permissions Section -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-4">
                        <i data-feather="key" class="w-4 h-4 inline mr-2"></i>
                        Permissions
                    </label>
                    <div class="bg-gray-50 rounded-xl p-4 border-2 border-gray-200">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php
                            $all_permissions = [
                                'add_bank' => 'Add Bank',
                                'edit_bank' => 'Edit Bank',
                                'delete_bank' => 'Delete Bank',
                                'list_banks' => 'List Banks',
                                'add_misc' => 'Add Expense',
                                'list_misc' => 'List Expenses',
                                'add_sale' => 'Add Sale',
                                'edit_sale' => 'Edit Sale',
                                'delete_sale' => 'Delete Sale',
                                'add_purchase' => 'Add Purchase',
                                'edit_purchase' => 'Edit Purchase',
                                'delete_purchase' => 'Delete Purchase',
                                'edit_item' => 'Edit Item',
                                'delete_item' => 'Delete Item'
                            ];
                            
                            foreach ($all_permissions as $perm_value => $perm_label):
                                $checked = in_array($perm_value, $current_permissions) ? 'checked' : '';
                            ?>
                                <label class="flex items-center space-x-2 p-2 rounded-lg hover:bg-white transition-colors duration-200 cursor-pointer">
                                    <input type="checkbox" 
                                           name="permissions[]" 
                                           value="<?= $perm_value ?>" 
                                           <?= $checked ?>
                                           class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700"><?= $perm_label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 justify-end">
                    <a href="pro.php" 
                       class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border-2 border-gray-300 text-base font-semibold rounded-xl text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-100 transition-all duration-300">
                        <i data-feather="x" class="w-5 h-5 mr-2"></i>
                        Cancel
                    </a>
                    <button type="submit" 
                            class="w-full sm:w-auto inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-100 transform hover:-translate-y-1 transition-all duration-300 shadow-lg">
                        <i data-feather="save" class="w-5 h-5 mr-2"></i>
                        Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize Feather icons
        feather.replace();
    </script>
</body>
</html>
