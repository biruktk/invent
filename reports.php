<?php
        // reports.php - Standalone Reports Page
        // Copy the session and auth logic from your main file

        session_start();

        // Copy your database connection and helper functions
        const DB_PATH = __DIR__ . '/inventory2.sqlite';
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

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
                header('Location: index.php?action=login');
                exit;
            }
        }

        function j($data, $code = 200)
        {
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
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
                    return [$d->format('Y-01-01'), $d->modify('last day')->format('Y-m-d')];
                case 'yearly':
                    return [$d->format('Y-01-01'), $d->format('Y-12-31')];
                default:
                    return ['1970-01-01', '2999-12-31'];
            }
        }

        requireLogin();

        // Handle AJAX requests
        $action = $_POST['action'] ?? $_GET['action'] ?? null;
        
        // Get company_id from session (or DB if needed, but session should be reliable now)
        $user_id = getUserId();
        // Fetch company_id fresh from DB to be safe
        $stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $company_id = $stmt->fetchColumn();

        if ($action === 'reports') {
            try {
                $period = $_REQUEST['period'] ?? 'all';
                [$start, $end] = periodRange($period, $_REQUEST['from'] ?? null, $_REQUEST['to'] ?? null);
                
                // Query using company_id directly AND created_at adjusted for timezone
                $sql_purchases = "SELECT * FROM purchases WHERE company_id = $company_id AND date(created_at, '+3 hours') BETWEEN '$start' AND '$end' ORDER BY date DESC";
                // error_log("Reports Debug: CompanyID: $company_id, Range: $start to $end");
                
                $purchases = $pdo->query($sql_purchases)->fetchAll(PDO::FETCH_ASSOC);
                $sales = $pdo->query("SELECT * FROM sales WHERE company_id = $company_id AND date(created_at, '+3 hours') BETWEEN '$start' AND '$end' ORDER BY date DESC")->fetchAll(PDO::FETCH_ASSOC);
                $misc= $pdo->query("SELECT m.*,b.name AS bank_name FROM misc m LEFT JOIN banks b ON m.bank_id=b.id WHERE m.company_id = $company_id AND date(m.created_at, '+3 hours') BETWEEN '$start' AND '$end' ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                
                $sum = function ($rows, $f) {
                    $t = 0;
                    foreach ($rows as $r) $t += (int)$r[$f];
                    return $t;
                };
                
        
        $profit = $sum($sales, 'total_cents') - $sum($purchases, 'total_cents') - $sum($misc, 'amount_cents');
        
        j([
            'range' => [$start, $end],
            'purchases' => $purchases,
            'sales' => $sales,
            'misc' => $misc,
            'totals' => [
                'purchases_cents' => $sum($purchases, 'total_cents'),
                'sales_cents' => $sum($sales, 'total_cents'),
                'misc_cents' => $sum($misc, 'amount_cents'),
                'tax_collected_cents' => array_sum(array_map(fn($s) => (int)round($s['qty'] * $s['price_cents'] * $s['tax_pct'] / 100), $sales)),
                'profit_cents' => $profit
            ]
        ]);
            } catch (Throwable $e) {
                j(['error' => $e->getMessage()], 500);
            }
        }

        if ($action === 'export_all_data') {
            try {
                // Fetch all inventory data for comprehensive export using company_id
                $items = $pdo->query("SELECT * FROM items WHERE company_id=$company_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                $purchases = $pdo->query("SELECT p.*, b.name AS bank_name FROM purchases p LEFT JOIN banks b ON p.bank_id=b.id WHERE p.company_id=$company_id ORDER BY p.date DESC")->fetchAll(PDO::FETCH_ASSOC);
                $sales = $pdo->query("SELECT s.*, b.name AS bank_name FROM sales s LEFT JOIN banks b ON s.bank_id=b.id WHERE s.company_id=$company_id ORDER BY s.date DESC")->fetchAll(PDO::FETCH_ASSOC);
                $misc = $pdo->query("SELECT m.*, b.name AS bank_name FROM misc m LEFT JOIN banks b ON m.bank_id=b.id WHERE m.company_id=$company_id ORDER BY m.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                $banks = $pdo->query("SELECT * FROM banks WHERE company_id=$company_id ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                
                // Get company info
                $company = $pdo->query("SELECT * FROM companies WHERE id=$company_id")->fetch(PDO::FETCH_ASSOC);
                
                // Calculate totals
                $totals = [
                    'total_items' => count($items),
                    'total_purchases' => count($purchases),
                    'total_sales' => count($sales),
                    'total_expenses' => count($misc),
                    'total_banks' => count($banks),
                    'inventory_value_cents' => array_sum(array_map(fn($i) => $i['qty'] * $i['price_cents'], $items)),
                    'total_purchases_cents' => array_sum(array_map(fn($p) => (int)$p['total_cents'], $purchases)),
                    'total_sales_cents' => array_sum(array_map(fn($s) => (int)$s['total_cents'], $sales)),
                    'total_expenses_cents' => array_sum(array_map(fn($m) => (int)$m['amount_cents'], $misc)),
                    'total_bank_balance_cents' => array_sum(array_map(fn($b) => (int)$b['balance_cents'], $banks)),
                ];
                
                j([
                    'company' => $company,
                    'items' => $items,
                    'purchases' => $purchases,
                    'sales' => $sales,
                    'expenses' => $misc,
                    'banks' => $banks,
                    'totals' => $totals,
                    'export_date' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $e) {
                j(['error' => $e->getMessage()], 500);
            }
        }
        ?>

        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Financial Reports - <?= htmlspecialchars($_SESSION['username']) ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
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
                                }
                            }
                        }
                    }
                }
            </script>
            <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;700&display=swap" rel="stylesheet">
            <!-- SheetJS for Excel Export -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
            <!-- jsPDF for PDF Export -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
            <style>
                :root { --primary: #60A5FA; }
                body { background: linear-gradient(to bottom, #87CEEB, #ADD8E6); font-family: 'Noto Sans Ethiopic', sans-serif; }
                /* .stat-card { background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%); } */
                .stat-card {
                background: linear-gradient(135deg, #60A5FA 0%, #3B82F6 100%);    color: white;
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .stat-card:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                }
                /* .stat-card.purchases { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
                .stat-card.sales { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
                .stat-card.expenses { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
                .stat-card.profit { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); } */
                
                .card {
                    background: white;
                    border-radius: 1rem;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                    border: 1px solid #f1f5f9;
                    transition: all 0.2s ease;
                }
                .card:hover { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07); }
                
                .data-table { width: 100%; font-size: 0.875rem; }
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
                .data-table tbody tr:hover { background: #f9fafb; }
                
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
                .btn-primary {
                    background: #0284c7;
                    color: white;
                }
                .btn-primary:hover {
                    background: #0369a1;
                    transform: translateY(-1px);
                }
                
                @keyframes slideIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .slide-in { animation: slideIn 0.3s ease-out; }
                
                /* Mobile Safari fixes */
                @media screen and (max-width: 768px) {
                    .data-table {
                        display: block;
                        overflow-x: auto;
                        -webkit-overflow-scrolling: touch;
                        white-space: nowrap;
                    }
                    .card {
                        overflow-x: auto;
                    }
                    .overflow-x-auto {
                        -webkit-overflow-scrolling: touch;
                    }
                }
                
                /* Safari-specific fixes */
                @supports (-webkit-appearance:none) {
                    .data-table {
                        will-change: scroll-position;
                    }
                }
            </style>
        </head>

        <body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
            <!-- Header -->
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
                            <a href="index.php?action=logout" class="px-3 py-2 bg-red-500 text-white rounded hover:bg-red-600">Logout</a>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
                <!-- Report Parameters -->
                <div class="card p-6">
                    <h2 class="text-xl font-semibold mb-4 text-gray-900">Report Parameters</h2>
                    <form id="formReports" class="flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="form-label">Period</label>
                            <select name="period" class="form-input min-w-[160px]">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                                <option value="all" selected>All Time</option>
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
                        <button class="btn btn-primary" type="submit">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Generate Report
                        </button>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div id="reportSummary" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Summary cards will be populated by JavaScript -->
                </div>

                <!-- Detailed Reports -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Purchases Table -->
                    <div class="card p-4">
                        <h3 class="font-semibold mb-3 text-purple-600 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13"/>
                            </svg>
                            Purchases
                        </h3>
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

                    <!-- Sales Table -->
                    <div class="card p-4">
                        <h3 class="font-semibold mb-3 text-green-600 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                            Sales
                        </h3>
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

                    <!-- Expenses Table -->
                    <div class="card p-4">
                        <h3 class="font-semibold mb-3 text-orange-600 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 6V4m0 2a2 2 0 100 4"/>
                            </svg>
                            Expenses
                        </h3>
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

                <!-- Export/Print Options -->
                <div class="card p-6">
                    <h3 class="text-lg font-semibold mb-4">Export Options</h3>
                    <div class="flex space-x-4">
                        <button onclick="window.print()" class="btn btn-primary">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/>
                            </svg>
                            Print Report
                        </button>
                        <button onclick="exportAllDataExcel()" class="btn" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Export to Excel
                </button>
                <button onclick="exportAllDataPDF()" class="btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white;">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                    </svg>
                    Export to PDF
                </button>
                    </div>
                </div>
        </div>
        <!-- Spacer to prevent content from being hidden by fixed nav -->
        <div style="height: 120px;"></div>
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
                    </a>
                  
                    <a href="pro.php" class="flex flex-col items-center p-2 rounded-xl transition-all duration-200 hover:bg-gray-100">
                            <svg class="w-6 h-6 text-gray-600 hover:text-indigo-600 transition-colors duration-200" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                            </svg>
                            <span class="text-xs mt-1 text-gray-600 hover:text-indigo-600 transition-colors duration-200">Profile</span>
                        </a>
                    </div>
                </div>
            </nav>
            </div>


            <script>
                const fmt = (cents) => `$${(cents/100).toFixed(2)}`;
                
                function toast(msg, type = 'success') {
                    const colors = {
                        success: 'bg-green-600',
                        error: 'bg-red-600',
                        info: 'bg-blue-600',
                        warning: 'bg-yellow-600'
                    };
                    const toast = document.createElement('div');
                    toast.className = `fixed bottom-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg slide-in`;
                    toast.textContent = msg;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 4000);
                }

                async function api(action, data = {}) {
                    const form = new FormData();
                    form.append('action', action);
                    for (const k in data) {
                        if (data[k] !== undefined && data[k] !== null) form.append(k, data[k]);
                    }
                    try {
                        const res = await fetch(location.href, { method: 'POST', body: form });
                        const js = await res.json();
                        if (!res.ok || js.error) throw new Error(js.error || 'Request failed');
                        return js;
                    } catch (err) {
                        console.error('API Error:', err);
                        throw err;
                    }
                }

                async function generateReport(formData) {
                    try {
                        const params = Object.fromEntries(formData);
                        const d = await api('reports', params);

                        // Update summary cards
                        const reportSummary = document.getElementById('reportSummary');
                        reportSummary.innerHTML = `
                            <div class="stat-card purchases p-6 rounded-xl slide-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-80">Total Purchases</p>
                                        <p class="text-3xl font-bold mt-2">${fmt(d.totals.purchases_cents)}</p>
                                        <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
                                    </div>
                                    <div class="bg-white/20 p-3 rounded-lg">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card sales p-6 rounded-xl slide-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-80">Total Sales</p>
                                        <p class="text-3xl font-bold mt-2">${fmt(d.totals.sales_cents)}</p>
                                        <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
                                    </div>
                                    <div class="bg-white/20 p-3 rounded-lg">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card expenses p-6 rounded-xl slide-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-80">Total Expenses</p>
                                        <p class="text-3xl font-bold mt-2">${fmt(d.totals.misc_cents)}</p>
                                        <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
                                    </div>
                                    <div class="bg-white/20 p-3 rounded-lg">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 6V4m0 2a2 2 0 100 4"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card profit p-6 rounded-xl slide-in">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm opacity-80">Net Profit</p>
                                        <p class="text-3xl font-bold mt-2">${fmt(d.totals.profit_cents)}</p>
                                        <p class="text-xs opacity-70 mt-1">${d.range[0]} to ${d.range[1]}</p>
                                    </div>
                                    <div class="bg-white/20 p-3 rounded-lg">
                                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        `;

                        // Store data globally for export
                        window.reportData = d;

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
                                <td>${fmt(p.total_cents)}</td>
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
                                <td><span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">${s.payment_method}</span></td>
                                <td>${fmt(s.total_cents)}</td>
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
                                <td>${fmt(m.amount_cents)}</td>
                            `;
                            reportMisc.appendChild(tr);
                        });

                        toast('Report generated successfully');
                    } catch (err) {
                        toast('Failed to generate report: ' + err.message, 'error');
                    }
                }

                function exportToCSV() {
                    if (!window.reportData) {
                        toast('Please generate a report first', 'warning');
                        return;
                    }

                    const data = window.reportData;
                    const escapeCSV = (str) => {
                        if (str === null || str === undefined) return '';
                        str = String(str);
                        if (str.includes(',') || str.includes('"') || str.includes('\n')) {
                            return '"' + str.replace(/"/g, '""') + '"';
                        }
                        return str;
                    };

                    let csv = 'Financial Report\n\n';
                    csv += `Period: ${data.range[0]} to ${data.range[1]}\n\n`;
                    
                    csv += 'Summary\n';
                    csv += 'Category,Amount\n';
                    csv += `Total Purchases,${fmt(data.totals.purchases_cents)}\n`;
                    csv += `Total Sales,${fmt(data.totals.sales_cents)}\n`;
                    csv += `Total Expenses,${fmt(data.totals.misc_cents)}\n`;
                    csv += `Net Profit,${fmt(data.totals.profit_cents)}\n\n`;

                    csv += 'Purchases\n';
                    csv += 'Date,Item,Brand,Item Number,Qty,Total\n';
                    data.purchases.forEach(p => {
                        csv += `${p.date},${escapeCSV(p.item_name)},${escapeCSV(p.brand_name || '')},${escapeCSV(p.item_number || '')},${p.qty},${fmt(p.total_cents)}\n`;
                    });

                    csv += '\nSales\n';
                    csv += 'Date,Item,Qty,Payment Method,Total\n';
                    data.sales.forEach(s => {
                        csv += `${s.date},${escapeCSV(s.item_name)},${s.qty},${escapeCSV(s.payment_method)},${fmt(s.total_cents)}\n`;
                    });

                    csv += '\nExpenses\n';
                    csv += 'Date,Name,Bank,Amount\n';
                    data.misc.forEach(m => {
                        csv += `${m.date},${escapeCSV(m.name)},${escapeCSV(m.bank_name)},${fmt(m.amount_cents)}\n`;
                    });

                    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `financial-report-${new Date().toISOString().split('T')[0]}.csv`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    toast('CSV report exported successfully');
                }

                function exportToExcel() {
                    if (!window.reportData) {
                        toast('Please generate a report first', 'warning');
                        return;
                    }

                    const data = window.reportData;
                    const wb = XLSX.utils.book_new();

                    // Summary Sheet
                    const summaryData = [
                        ['Financial Report'],
                        ['Period', `${data.range[0]} to ${data.range[1]}`],
                        [],
                        ['Summary'],
                        ['Category', 'Amount'],
                        ['Total Purchases', fmt(data.totals.purchases_cents)],
                        ['Total Sales', fmt(data.totals.sales_cents)],
                        ['Total Expenses', fmt(data.totals.misc_cents)],
                        ['Net Profit', fmt(data.totals.profit_cents)]
                    ];
                    const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
                    XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

                    // Purchases Sheet
                    const purchasesData = [
                        ['Date', 'Item', 'Brand', 'Item Number', 'Qty', 'Total']
                    ];
                    data.purchases.forEach(p => {
                        purchasesData.push([
                            p.date,
                            p.item_name,
                            p.brand_name || '',
                            p.item_number || '',
                            p.qty,
                            fmt(p.total_cents)
                        ]);
                    });
                    const purchasesSheet = XLSX.utils.aoa_to_sheet(purchasesData);
                    XLSX.utils.book_append_sheet(wb, purchasesSheet, 'Purchases');

                    // Sales Sheet
                    const salesData = [
                        ['Date', 'Item', 'Qty', 'Payment Method', 'Total']
                    ];
                    data.sales.forEach(s => {
                        salesData.push([
                            s.date,
                            s.item_name,
                            s.qty,
                            s.payment_method,
                            fmt(s.total_cents)
                        ]);
                    });
                    const salesSheet = XLSX.utils.aoa_to_sheet(salesData);
                    XLSX.utils.book_append_sheet(wb, salesSheet, 'Sales');

                    // Expenses Sheet
                    const expensesData = [
                        ['Date', 'Name', 'Bank', 'Amount']
                    ];
                    data.misc.forEach(m => {
                        expensesData.push([
                            m.date,
                            m.name,
                            m.bank_name || '',
                            fmt(m.amount_cents)
                        ]);
                    });
                    const expensesSheet = XLSX.utils.aoa_to_sheet(expensesData);
                    XLSX.utils.book_append_sheet(wb, expensesSheet, 'Expenses');

                    // Export
                    const fileName = `financial-report-${new Date().toISOString().split('T')[0]}.xlsx`;
                    XLSX.writeFile(wb, fileName);
                    
                    toast('Excel report exported successfully');
                }

                async function exportAllDataExcel() {
            try {
                toast('Preparing comprehensive export...', 'info');
                const data = await api('export_all_data', {});
                
                const wb = XLSX.utils.book_new();
                
                // Helper to convert cents to birr (numeric value, not string)
                const toBirr = c => (c / 100);

                // 1. Summary Sheet
                const summaryData = [
                    ['INVENTORY MANAGEMENT SYSTEM - COMPLETE DATA EXPORT'],
                    [],
                    ['Company:', data.company?.name || 'N/A'],
                    ['Export Date:', data.export_date],
                    ['Exported By:', '<?php echo $_SESSION["username"]; ?>'],
                    [],
                    ['SUMMARY STATISTICS'],
                    ['Category', 'Count', 'Value (Birr)'],
                    ['Total Items in Inventory', data.totals.total_items, toBirr(data.totals.inventory_value_cents)],
                    ['Total Purchases', data.totals.total_purchases, toBirr(data.totals.total_purchases_cents)],
                    ['Total Sales', data.totals.total_sales, toBirr(data.totals.total_sales_cents)],
                    ['Total Expenses', data.totals.total_expenses, toBirr(data.totals.total_expenses_cents)],
                    ['Total Bank Accounts', data.totals.total_banks, toBirr(data.totals.total_bank_balance_cents)]
                ];
                const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
                
                // Format summary numbers
                if (!summarySheet['!cols']) summarySheet['!cols'] = [];
                summarySheet['!cols'][0] = { wch: 30 };
                summarySheet['!cols'][1] = { wch: 15 };
                summarySheet['!cols'][2] = { wch: 20 };
                
                XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

                // 2. Items/Products Sheet
                const itemsData = [['Item ID', 'Name', 'Item Number', 'Qty in Stock', 'Unit Price (Birr)', 'Tax %', 'Total Value (Birr)', 'Image Path', 'Created Date']];
                data.items.forEach(item => {
                    itemsData.push([
                        item.id,
                        item.name,
                        item.item_number || '',
                        item.qty,
                        toBirr(item.price_cents),
                        item.tax_pct,
                        toBirr(item.qty * item.price_cents),
                        item.image_path || '',
                        item.created_at
                    ]);
                });
                const itemsSheet = XLSX.utils.aoa_to_sheet(itemsData);
                
                // Format columns
                if (!itemsSheet['!cols']) itemsSheet['!cols'] = [];
                itemsSheet['!cols'][0] = { wch: 10 };
                itemsSheet['!cols'][1] = { wch: 25 };
                itemsSheet['!cols'][2] = { wch: 15 };
                itemsSheet['!cols'][3] = { wch: 12 };
                itemsSheet['!cols'][4] = { wch: 15 };
                itemsSheet['!cols'][5] = { wch: 10 };
                itemsSheet['!cols'][6] = { wch: 18 };
                itemsSheet['!cols'][7] = { wch: 30 };
                itemsSheet['!cols'][8] = { wch: 18 };
                
                XLSX.utils.book_append_sheet(wb, itemsSheet, 'Items-Products');

                // 3. Purchases Sheet
                const purchasesData = [['Purchase ID', 'Date', 'Item', 'Brand', 'Item Number', 'Qty', 'Unit Price (Birr)', 'Tax %', 'Total (Birr)', 'Payment Type', 'Bank', 'Status', 'Due Date', 'Created']];
                data.purchases.forEach(p => {
                    purchasesData.push([
                        p.id,
                        p.date,
                        p.item_name,
                        p.brand_name || '',
                        p.item_number || '',
                        p.qty,
                        toBirr(p.price_cents),
                        p.tax_pct,
                        toBirr(p.total_cents),
                        p.payment_type || '',
                        p.bank_name || '',
                        p.status || 'paid',
                        p.due_date || '',
                        p.created_at
                    ]);
                });
                const purchasesSheet = XLSX.utils.aoa_to_sheet(purchasesData);
                
                // Format columns
                if (!purchasesSheet['!cols']) purchasesSheet['!cols'] = [];
                purchasesSheet['!cols'][0] = { wch: 12 };
                purchasesSheet['!cols'][1] = { wch: 12 };
                purchasesSheet['!cols'][2] = { wch: 25 };
                purchasesSheet['!cols'][3] = { wch: 15 };
                purchasesSheet['!cols'][4] = { wch: 15 };
                purchasesSheet['!cols'][5] = { wch: 8 };
                purchasesSheet['!cols'][6] = { wch: 15 };
                purchasesSheet['!cols'][7] = { wch: 10 };
                purchasesSheet['!cols'][8] = { wch: 15 };
                purchasesSheet['!cols'][9] = { wch: 15 };
                purchasesSheet['!cols'][10] = { wch: 20 };
                purchasesSheet['!cols'][11] = { wch: 12 };
                purchasesSheet['!cols'][12] = { wch: 12 };
                purchasesSheet['!cols'][13] = { wch: 18 };
                
                XLSX.utils.book_append_sheet(wb, purchasesSheet, 'Purchases');

                // 4. Sales Sheet
                const salesData = [['Sale ID', 'Date', 'Item', 'Qty', 'Unit Price (Birr)', 'Tax %', 'Total (Birr)', 'Payment Method', 'Status', 'Paid Via', 'Bank', 'Prepayment (Birr)', 'Due Date', 'Created']];
                data.sales.forEach(s => {
                    salesData.push([
                        s.id,
                        s.date,
                        s.item_name,
                        s.qty,
                        toBirr(s.price_cents),
                        s.tax_pct,
                        toBirr(s.total_cents),
                        s.payment_method || 'Paid',
                        s.status || 'paid',
                        s.paid_via || '',
                        s.bank_name || '',
                        toBirr(s.prepayment_cents || 0),
                        s.due_date || '',
                        s.created_at
                    ]);
                });
                const salesSheet = XLSX.utils.aoa_to_sheet(salesData);
                
                // Format columns
                if (!salesSheet['!cols']) salesSheet['!cols'] = [];
                salesSheet['!cols'][0] = { wch: 10 };
                salesSheet['!cols'][1] = { wch: 12 };
                salesSheet['!cols'][2] = { wch: 25 };
                salesSheet['!cols'][3] = { wch: 8 };
                salesSheet['!cols'][4] = { wch: 15 };
                salesSheet['!cols'][5] = { wch: 10 };
                salesSheet['!cols'][6] = { wch: 15 };
                salesSheet['!cols'][7] = { wch: 15 };
                salesSheet['!cols'][8] = { wch: 12 };
                salesSheet['!cols'][9] = { wch: 12 };
                salesSheet['!cols'][10] = { wch: 20 };
                salesSheet['!cols'][11] = { wch: 15 };
                salesSheet['!cols'][12] = { wch: 12 };
                salesSheet['!cols'][13] = { wch: 18 };
                
                XLSX.utils.book_append_sheet(wb, salesSheet, 'Sales');

                // 5. Expenses Sheet
                const expensesData = [['Expense ID', 'Date', 'Name', 'Reason', 'Amount (Birr)', 'Bank', 'Created']];
                data.expenses.forEach(m => {
                    expensesData.push([
                        m.id,
                        m.date,
                        m.name,
                        m.reason || '',
                        toBirr(m.amount_cents),
                        m.bank_name || 'Cash',
                        m.created_at
                    ]);
                });
                const expensesSheet = XLSX.utils.aoa_to_sheet(expensesData);
                
                // Format columns
                if (!expensesSheet['!cols']) expensesSheet['!cols'] = [];
                expensesSheet['!cols'][0] = { wch: 12 };
                expensesSheet['!cols'][1] = { wch: 12 };
                expensesSheet['!cols'][2] = { wch: 25 };
                expensesSheet['!cols'][3] = { wch: 30 };
                expensesSheet['!cols'][4] = { wch: 15 };
                expensesSheet['!cols'][5] = { wch: 20 };
                expensesSheet['!cols'][6] = { wch: 18 };
                
                XLSX.utils.book_append_sheet(wb, expensesSheet, 'Expenses');

                // 6. Banks Sheet
                const banksData = [['Bank ID', 'Bank Name', 'Current Balance (Birr)', 'Created Date']];
                data.banks.forEach(b => {
                    banksData.push([
                        b.id,
                        b.name,
                        toBirr(b.balance_cents),
                        b.created_at
                    ]);
                });
                const banksSheet = XLSX.utils.aoa_to_sheet(banksData);
                
                // Format columns
                if (!banksSheet['!cols']) banksSheet['!cols'] = [];
                banksSheet['!cols'][0] = { wch: 10 };
                banksSheet['!cols'][1] = { wch: 25 };
                banksSheet['!cols'][2] = { wch: 20 };
                banksSheet['!cols'][3] = { wch: 18 };
                
                XLSX.utils.book_append_sheet(wb, banksSheet, 'Banks');

                // Export the workbook
                const fileName = `inventory-complete-export-${new Date().toISOString().split('T')[0]}.xlsx`;
                XLSX.writeFile(wb, fileName);
                
                toast('Complete inventory data exported to Excel successfully!', 'success');
            } catch (err) {
                toast('Failed to export data: ' + err.message, 'error');
            }
        }

        async function exportAllDataPDF() {
            try {
                toast('Preparing PDF export...', 'info');
                const data = await api('export_all_data', {});
                
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'mm', 'a4'); // Landscape for more columns
                
                // Format to prevent scientific notation
                const toBirr = c => {
                    const num = c / 100;
                    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                };
                
                let yPos = 20;
                
                // Title
                doc.setFontSize(16);
                doc.setFont(undefined, 'bold');
                doc.text('INVENTORY MANAGEMENT SYSTEM', doc.internal.pageSize.getWidth() / 2, yPos, { align: 'center' });
                yPos += 7;
                doc.text('COMPLETE DATA EXPORT', doc.internal.pageSize.getWidth() / 2, yPos, { align: 'center' });
                yPos += 10;
                
                // Company info
                doc.setFontSize(10);
                doc.setFont(undefined, 'normal');
                doc.text(`Company: ${data.company?.name || 'N/A'}`, 14, yPos);
                yPos += 6;
                doc.text(`Export Date: ${data.export_date}`, 14, yPos);
                yPos += 6;
                doc.text(`Exported By: <?php echo $_SESSION["username"]; ?>`, 14, yPos);
                yPos += 10;
                
                // Summary Table
                doc.setFont(undefined, 'bold');
                doc.text('SUMMARY STATISTICS', 14, yPos);
                yPos += 5;
                
                doc.autoTable({
                    startY: yPos,
                    head: [['Category', 'Count', 'Value (Birr)']],
                    body: [
                        ['Total Items in Inventory', data.totals.total_items, toBirr(data.totals.inventory_value_cents)],
                        ['Total Purchases', data.totals.total_purchases, toBirr(data.totals.total_purchases_cents)],
                        ['Total Sales', data.totals.total_sales, toBirr(data.totals.total_sales_cents)],
                        ['Total Expenses', data.totals.total_expenses, toBirr(data.totals.total_expenses_cents)],
                        ['Total Bank Accounts', data.totals.total_banks, toBirr(data.totals.total_bank_balance_cents)]
                    ],
                    theme: 'grid',
                    headStyles: { fillColor: [96, 165, 250] },
                    styles: { fontSize: 9 }
                });
                
                // Items/Products
                doc.addPage();
                doc.setFont(undefined, 'bold');
                doc.text('ITEMS / PRODUCTS', 14, 20);
                doc.autoTable({
                    startY: 25,
                    head: [['ID', 'Product Name', 'Item #', 'Qty', 'Price (Birr)', 'Tax %', 'Total (Birr)']],
                    body: data.items.map(item => [
                        item.id,
                        item.name,
                        item.item_number || '',
                        item.qty,
                        toBirr(item.price_cents),
                        item.tax_pct,
                        toBirr(item.qty * item.price_cents)
                    ]),
                    theme: 'striped',
                    headStyles: { fillColor: [96, 165, 250] },
                    styles: { fontSize: 8 }
                });
                
                // Purchases
                doc.addPage();
                doc.setFont(undefined, 'bold');
                doc.text('PURCHASES', 14, 20);
                doc.autoTable({
                    startY: 25,
                    head: [['ID', 'Date', 'Product Name', 'Brand', 'Qty', 'Price (Birr)', 'Total (Birr)', 'Payment', 'Bank', 'Status']],
                    body: data.purchases.map(p => [
                        p.id,
                        p.date,
                        p.item_name,
                        p.brand_name || '-',
                        p.qty,
                        toBirr(p.price_cents),
                        toBirr(p.total_cents),
                        p.payment_type || 'cash',
                        p.bank_name || '-',
                        p.status || 'paid'
                    ]),
                    theme: 'striped',
                    headStyles: { fillColor: [96, 165, 250] },
                    styles: { fontSize: 7 }
                });
                
                // Sales
                doc.addPage();
                doc.setFont(undefined, 'bold');
                doc.text('SALES', 14, 20);
                doc.autoTable({
                    startY: 25,
                    head: [['ID', 'Date', 'Product Name', 'Qty', 'Price (Birr)', 'Total (Birr)', 'Payment', 'Prepayment (Birr)', 'Status']],
                    body: data.sales.map(s => [
                        s.id,
                        s.date,
                        s.item_name,
                        s.qty,
                        toBirr(s.price_cents),
                        toBirr(s.total_cents),
                        s.payment_method || 'Paid',
                        toBirr(s.prepayment_cents || 0),
                        s.status || 'paid'
                    ]),
                    theme: 'striped',
                    headStyles: { fillColor: [96, 165, 250] },
                    styles: { fontSize: 8 }
                });
                
                // Expenses
                doc.addPage();
                doc.setFont(undefined, 'bold');
                doc.text('EXPENSES', 14, 20);
                doc.autoTable({
                    startY: 25,
                    head: [['ID', 'Date', 'Name', 'Reason', 'Amount (Birr)', 'Bank']],
                    body: data.expenses.map(m => [
                        m.id,
                        m.date,
                        m.name,
                        m.reason || '-',
                        toBirr(m.amount_cents),
                        m.bank_name || 'Cash'
                    ]),
                    theme: 'striped',
                    headStyles: { fillColor: [96, 165, 250] },
                    styles: { fontSize: 8 }
                });
                
                // Banks
                doc.addPage();
                doc.setFont(undefined, 'bold');
                doc.text('BANK ACCOUNTS', 14, 20);
                doc.autoTable({
                    startY: 25,
                    head: [['ID', 'Bank Name', 'Balance (Birr)', 'Created Date']],
                    body: data.banks.map(b => [
                        b.id,
                        b.name,
                        toBirr(b.balance_cents),
                        b.created_at
                    ]),
                    theme: 'striped',
                    headStyles: { fillColor: [96, 165, 250] },
                    styles: { fontSize: 9 }
                });
                
                // Save PDF
                const fileName = `inventory-complete-export-${new Date().toISOString().split('T')[0]}.pdf`;
                doc.save(fileName);
                
                toast('Complete inventory data exported to PDF successfully!', 'success');
            } catch (err) {
                toast('Failed to export PDF: ' + err.message, 'error');
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Generate initial report
            generateReport(new FormData(document.getElementById('formReports')));

            // Form submission
            document.getElementById('formReports').addEventListener('submit', async e => {
                e.preventDefault();
                await generateReport(new FormData(e.target));
            });
        });
    </script>
</body>
