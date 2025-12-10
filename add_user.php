<?php
require 'bootstrap.php';

// Only admins can add employees
if ($_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

// Collect input
$username = trim($_POST['username']);
$email = trim($_POST['email']);

$password = $_POST['password'];
$role = $_POST['role'];

// Collect permissions (array from checkboxes)
$permissions = $_POST['permissions'] ?? [];
$permissions_json = json_encode($permissions);

// Insert user into database
$stmt = $pdo->prepare("
    INSERT INTO users (
        username,
        email,
        pstatus,
        password_hash,
        txn,
        pkg,
        company_id,
        role,
        permissions
    ) VALUES (?, ?, 'paid', ?, ?, ?, ?, ?, ?)
");


// Ensure company_id is valid
$company_id = $_SESSION['company_id'] ?? null;

// If admin's company_id is not set, get it from users table
if (!$company_id) {
    $stmt_comp = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
    $stmt_comp->execute([$_SESSION['user_id']]);
    $company_id = $stmt_comp->fetchColumn();
}

// If still no company_id, create a default company
if (!$company_id) {
    $pdo->prepare("INSERT INTO companies (name, owner_id) VALUES (?, ?)")
        ->execute(['Default Company', $_SESSION['user_id']]);
    $company_id = $pdo->lastInsertId();
    
    // Update session
    $_SESSION['company_id'] = $company_id;
}

$stmt->execute([
    $username,
    $email,
    password_hash($password, PASSWORD_DEFAULT),
    '6', // txn placeholder
    '6', // pkg placeholder
    $company_id,
    $role,
    $permissions_json
]);

// Redirect back to employee management page
header("Location: pro.php?msg=Employee+added+successfully");
exit;

