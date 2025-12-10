<?php
require 'bootstrap.php'; // <- your DB + session init
// session_start();

// Handle logout action
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action === 'logout') {
  session_destroy();
  header('Location: /');
  exit;
}

// Only admins can access
if ($_SESSION['role'] !== 'admin') {
    header('location: /');
    die("Access denied.");
}

// Fetch employees of this admin's company
$stmt = $pdo->prepare("SELECT * FROM users WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['company_id']]);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Calculate statistics
$totalEmployees = count($employees);
$activeEmployees = count(array_filter($employees, function($emp) { 
    return $emp['pstatus'] === 'active'; 
}));

$pendingEmployees = count(array_filter($employees, function($emp) { 
    return $emp['pstatus'] === 'pending'; 
}));

// Function to get role class
function getRoleClass($role) {
    $roleClasses = [
        'admin' => 'bg-gradient-to-r from-purple-500 to-indigo-500 text-white',
        'manager' => 'bg-purple-100 text-purple-800',
        'sales' => 'bg-yellow-100 text-yellow-800',
        'inventory' => 'bg-green-100 text-green-800',
        'finance' => 'bg-red-100 text-red-800',
        'employee' => 'bg-gray-100 text-gray-800'
    ];
    return $roleClasses[$role] ?? 'bg-gray-100 text-gray-800';
}

// Function to get status class
function getStatusClass($status) {
    $statusClasses = [
        'active' => 'bg-green-100 text-green-800',
        'inactive' => 'bg-red-100 text-red-800',
        'pending' => 'bg-yellow-100 text-yellow-800'
    ];
    return $statusClasses[$status] ?? 'bg-gray-100 text-gray-800';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees - <?= htmlspecialchars($_SESSION['company_name'] ?? 'Company') ?></title>
    <script src="./index.js"></script>
    <!-- <script src="https:/"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.js"></script>
    <style>
        body { background: linear-gradient(to bottom, #87CEEB, #ADD8E6); font-family: 'Noto Sans Ethiopic', sans-serif; }
        .card { background: white; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        .btn-blue { @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition; }
        /* Custom animations and gradients for enhanced UI */
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

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .animate-slide-in {
            animation: slideInUp 0.5s ease-out;
        }

        .loading {
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .gradient-bg {
            /* background: #1100ffff; */
        }

        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .hover-scale {
            transition: transform 0.2s ease;
        }

        .hover-scale:hover {
            transform: translateY(-2px);
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Hide scrollbar on mobile */
        @media (max-width: 768px) {
            .hide-scrollbar {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            .hide-scrollbar::-webkit-scrollbar {
                display: none;
            }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen">
      <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="/" class="flex items-center text-blue-600 hover:text-blue-800 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                        </svg>
                        <!-- Back to Dashboard -->
                    </a>
                    <!-- <h1 class="text-2xl font-bold text-gray-900">Financial Reports</h1> -->
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
                    <a href="?action=logout" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600">Logout</a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-24 min-h-screen">
        <!-- Header -->
        <div class="text-center mb-8 animate-slide-in">
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-2 drop-shadow-lg">
                Employee Management
            </h1>
            <p class="text-lg sm:text-xl text-white opacity-90">
                Manage your team members and their roles
            </p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="glass-effect border border-green-200 rounded-2xl p-4 mb-6 animate-slide-in">
            <div class="flex items-center">
                <i data-feather="check-circle" class="w-5 h-5 text-green-600 mr-3"></i>
                <span class="text-green-800 font-medium">
                    Employee <?= $_GET['success'] === 'added' ? 'added' : 'updated' ?> successfully!
                </span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="glass-effect border border-red-200 rounded-2xl p-4 mb-6 animate-slide-in">
            <div class="flex items-center">
                <i data-feather="alert-circle" class="w-5 h-5 text-red-600 mr-3"></i>
                <span class="text-red-800 font-medium">
                    Error: <?= htmlspecialchars($_GET['error']) ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <!-- <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-8">
            <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl sm:rounded-3xl p-6 text-white shadow-xl hover-scale animate-slide-in">
                <div class="text-center">
                    <h3 class="text-2xl sm:text-3xl lg:text-4xl font-bold mb-2"><?= $totalEmployees ?></h3>
                    <p class="text-sm sm:text-base opacity-90">Total Employees</p>
                </div>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl sm:rounded-3xl p-6 text-white shadow-xl hover-scale animate-slide-in">
                <div class="text-center">
                    <h3 class="text-2xl sm:text-3xl lg:text-4xl font-bold mb-2"><?= $activeEmployees ?></h3>
                    <p class="text-sm sm:text-base opacity-90">Active Members</p>
                </div>
            </div>
            <div class="bg-gradient-to-br from-orange-500 to-yellow-500 rounded-2xl sm:rounded-3xl p-6 text-white shadow-xl hover-scale animate-slide-in sm:col-span-2 lg:col-span-1">
                <div class="text-center">
                    <h3 class="text-2xl sm:text-3xl lg:text-4xl font-bold mb-2"><?= $pendingEmployees ?></h3>
                    <p class="text-sm sm:text-base opacity-90">Pending Approval</p>
                </div>
            </div>
        </div> -->

        <!-- Employee List Card -->
        <div class="glass-effect rounded-2xl sm:rounded-3xl p-4 sm:p-6 lg:p-8 mb-6 sm:mb-8 shadow-xl animate-slide-in">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <i data-feather="users" class="w-6 h-6 sm:w-8 sm:h-8 text-indigo-600"></i>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Team Members</h2>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="relative mb-6 max-w-md">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i data-feather="search" class="w-5 h-5 text-gray-400"></i>
                </div>
                <input type="text" 
                       class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-full text-gray-900 placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                       placeholder="Search employees..." 
                       id="searchInput">
            </div>

            <!-- Table for Desktop -->
            <?php if (count($employees) > 0): ?>
            <div class="hidden md:block">
                <div class="overflow-hidden rounded-2xl border border-gray-200 shadow-lg">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gradient-to-r from-indigo-500 to-purple-600">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Employee</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Role</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="employeeTableBody">
                                <?php foreach ($employees as $emp): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200" data-employee-id="<?= $emp['id'] ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($emp['username']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($emp['email']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= getRoleClass($emp['role']) ?>">
                                            <?= ucfirst(htmlspecialchars($emp['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= getStatusClass($emp['pstatus']) ?>">
                                            <?= ucfirst(htmlspecialchars($emp['pstatus'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php if ($emp['role'] !== 'admin'): ?>
                                                <a href="edit_user.php?id=<?= $emp['id'] ?>" 
                                                   class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors duration-200">
                                                    <i data-feather="edit-2" class="w-3 h-3 mr-1"></i>
                                                    Edit
                                                </a>
                                                <button onclick="confirmDelete('<?= htmlspecialchars($emp['username']) ?>', <?= $emp['id'] ?>)" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-lg text-red-700 bg-red-100 hover:bg-red-200 transition-colors duration-200">
                                                    <i data-feather="trash-2" class="w-3 h-3 mr-1"></i>
                                                    Delete
                                                </button>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-purple-500 to-indigo-500 text-white">
                                                    Owner
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="md:hidden space-y-4" id="employeeMobileList">
                <?php foreach ($employees as $emp): ?>
                <div class="bg-white rounded-2xl p-4 shadow-lg border border-gray-100 hover-scale" data-employee-id="<?= $emp['id'] ?>">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900 text-lg"><?= htmlspecialchars($emp['username']) ?></h3>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($emp['email']) ?></p>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-2 mb-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= getRoleClass($emp['role']) ?>">
                            <?= ucfirst(htmlspecialchars($emp['role'])) ?>
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?= getStatusClass($emp['pstatus']) ?>">
                            <?= ucfirst(htmlspecialchars($emp['pstatus'])) ?>
                        </span>
                    </div>
                    
                    <?php if ($emp['role'] !== 'admin'): ?>
                    <div class="flex space-x-3">
                        <a href="edit_user.php?id=<?= $emp['id'] ?>" 
                           class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors duration-200">
                            <i data-feather="edit-2" class="w-4 h-4 mr-2"></i>
                            Edit
                        </a>
                        <button onclick="confirmDelete('<?= htmlspecialchars($emp['username']) ?>', <?= $emp['id'] ?>)" 
                                class="flex-1 inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-xl text-red-700 bg-red-100 hover:bg-red-200 transition-colors duration-200">
                            <i data-feather="trash-2" class="w-4 h-4 mr-2"></i>
                            Delete
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="text-center">
                        <span class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-purple-500 to-indigo-500 text-white">
                            Owner Account
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <i data-feather="users" class="w-16 h-16 text-gray-400 mx-auto mb-4 opacity-50"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No employees found</h3>
                <p class="text-gray-500">Start by adding your first team member below.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Add Employee Card -->
        <div class="glass-effect rounded-2xl sm:rounded-3xl p-4 sm:p-6 lg:p-8 shadow-xl animate-slide-in">
            <div class="flex items-center gap-3 mb-6">
                <i data-feather="user-plus" class="w-6 h-6 sm:w-8 sm:h-8 text-indigo-600"></i>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Add New Employee</h2>
            </div>

            <form method="POST" action="add_user.php" id="addEmployeeForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6">
                    <div class="space-y-2">
                        <label for="username" class="block text-sm font-semibold text-gray-700">Username</label>
                        <input type="text" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                               name="username" 
                               id="username" 
                               placeholder="Enter username" 
                               required>
                    </div>
                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-semibold text-gray-700">Email Address</label>
                        <input type="email" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                               name="email" 
                               id="email" 
                               placeholder="Enter email address" 
                               required>
                    </div>
                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
                        <input type="password" 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 placeholder-gray-500 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                               name="password" 
                               id="password" 
                               placeholder="Enter password" 
                               required>
                    </div>
           <div class="space-y-2">
                     <label class="block text-sm font-semibold text-gray-700 mt-4">Permissions</label>
<div class="grid grid-cols-2 gap-2">
    <label><input type="checkbox" name="permissions[]" value="add_bank"> Add Bank</label>
    <label><input type="checkbox" name="permissions[]" value="edit_bank"> Edit Bank</label>
    <label><input type="checkbox" name="permissions[]" value="delete_bank"> Delete Bank</label>
    <label><input type="checkbox" name="permissions[]" value="list_banks"> List Banks</label>
    <label><input type="checkbox" name="permissions[]" value="add_misc"> Add Expense</label>
    <label><input type="checkbox" name="permissions[]" value="list_misc"> List Expenses</label>
    <label><input type="checkbox" name="permissions[]" value="add_sale"> Add Sale</label>
    <label><input type="checkbox" name="permissions[]" value="edit_sale"> Edit Sale</label>
    <label><input type="checkbox" name="permissions[]" value="delete_sale"> Delete Sale</label>
    <label><input type="checkbox" name="permissions[]" value="add_purchase"> Add Purchase</label>
    <label><input type="checkbox" name="permissions[]" value="edit_purchase"> Edit Purchase</label>
    <label><input type="checkbox" name="permissions[]" value="delete_purchase"> Delete Purchase</label>
    <label><input type="checkbox" name="permissions[]" value="edit_item"> Edit Item</label>
    <label><input type="checkbox" name="permissions[]" value="delete_item"> Delete Item</label>
</div>

           </div>
                    <div class="space-y-2">
                        <label for="role" class="block text-sm font-semibold text-gray-700">Role</label>
                      <input type="text" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-gray-900 focus:outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100 transition-all duration-300" 
                                name="role" 
                               id="role" >  <!--  <select 
                                required>
                            <option value="">Select a role</option>
                            <option value="sales">Sales Representative</option>
                            <option value="inventory">Inventory Manager</option>
                            <option value="finance">Finance Specialist</option>
                            <option value="manager">Department Manager</option>
                            <option value="employee">General Employee</option>
                        </select> -->
                    </div>
                </div>
                <button type="submit" 
                        class="w-full sm:w-auto inline-flex justify-center items-center px-8 py-4 border border-transparent text-base font-semibold rounded-2xl text-white bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-100 transform hover:-translate-y-1 transition-all duration-300 shadow-lg">
                    <i data-feather="plus" class="w-5 h-5 mr-2"></i>
                    Add Employee
                </button>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="fixed bottom-0 left-0 w-full bg-white shadow-2xl z-50 border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex justify-around items-center">
                <a href="/" class="flex flex-col items-center p-2 rounded-xl transition-all duration-200 hover:bg-gray-100">
                    <svg class="w-6 h-6 text-gray-600 hover:text-indigo-600 transition-colors duration-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z" />
                    </svg>
                    <span class="text-xs mt-1 text-gray-600 hover:text-indigo-600 transition-colors duration-200">Home</span>
                </a>
              <a href="reports.php">
                <button  class="flex flex-col items-center p-2 rounded-xl transition-all duration-200 hover:bg-gray-100">
                    <svg class="w-6 h-6 text-gray-600 hover:text-indigo-600 transition-colors duration-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
                    </svg>
                    <span class="text-xs mt-1 text-gray-600 hover:text-indigo-600 transition-colors duration-200">Reports</span>
                </button>

</a>                <a href="pro.php" class="flex flex-col items-center p-2 rounded-xl transition-all duration-200 hover:bg-gray-100">
                    <svg class="w-6 h-6 text-gray-600 hover:text-indigo-600 transition-colors duration-200" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                    </svg>
                    <span class="text-xs mt-1 text-gray-600 hover:text-indigo-600 transition-colors duration-200">Profile</span>
                </a>
            </div>
        </div>
    </nav>

    <script>
        // Initialize Feather icons
        feather.replace();

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Search in desktop table
            const desktopRows = document.querySelectorAll('#employeeTableBody tr');
            desktopRows.forEach(row => {
                const employeeData = row.querySelector('td:first-child').textContent.toLowerCase();
                const roleData = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const statusData = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                
                if (employeeData.includes(searchTerm) || roleData.includes(searchTerm) || statusData.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Search in mobile cards
            const mobileCards = document.querySelectorAll('#employeeMobileList > div');
            mobileCards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                if (cardText.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Delete confirmation
        function confirmDelete(employeeName, employeeId) {
            if (confirm(`Are you sure you want to delete ${employeeName}? This action cannot be undone.`)) {
                // Show loading state
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                loadingDiv.innerHTML = `
                    <div class="bg-white rounded-2xl p-6 flex items-center space-x-3">
                        <div class="loading"></div>
                        <span class="text-gray-700">Deleting employee...</span>
                    </div>
                `;
                document.body.appendChild(loadingDiv);
                
                // Redirect to delete script with employee ID
                window.location.href = `delete_user.php?id=${employeeId}`;
            }
        }

        // Form submission handler
        document.getElementById('addEmployeeForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalContent = submitBtn.innerHTML;
            submitBtn.innerHTML = '<div class="loading mr-2"></div> Adding Employee...';
            submitBtn.disabled = true;
            
            // Re-enable after 5 seconds if still on page (in case of error)
            setTimeout(() => {
                submitBtn.innerHTML = originalContent;
                submitBtn.disabled = false;
                feather.replace();
            }, 5000);
        });

        // Auto-hide success/error messages
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.glass-effect');
            messages.forEach(message => {
                if (message.querySelector('[data-feather="check-circle"], [data-feather="alert-circle"]')) {
                    setTimeout(() => {
                        message.style.opacity = '0';
                        message.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            message.remove();
                        }, 300);
                    }, 5000);
                }
            });
        });

        // Mobile navigation scroll behavior
        let lastScrollTop = 0;
        const isMobile = window.innerWidth < 768;
        
        if (isMobile) {
            window.addEventListener('scroll', function() {
                const currentScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                const bottomNav = document.querySelector('nav.fixed');
                
                if (currentScrollTop > lastScrollTop && currentScrollTop > 100) {
                    // Scrolling down
                    bottomNav.style.transform = 'translateY(100%)';
                } else {
                    // Scrolling up
                    bottomNav.style.transform = 'translateY(0)';
                }
                
                lastScrollTop = currentScrollTop <= 0 ? 0 : currentScrollTop;
            });
        }

        // Add touch feedback for mobile
        if ('ontouchstart' in window) {
            document.addEventListener('touchstart', function() {}, true);
        }
    </script>
</body>
</html>