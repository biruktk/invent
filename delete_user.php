<?php
require 'bootstrap.php';

// Only admins can delete employees
if ($_SESSION['role'] !== 'admin') {
    header("Location: pro.php?error=access_denied");
    exit;
}

// Get user ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: pro.php?error=invalid_id");
    exit;
}

// Don't allow deleting the logged-in admin
if ($id === $_SESSION['user_id']) {
    header("Location: pro.php?error=cannot_delete_self");
    exit;
}

// Check if user belongs to the same company
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$id, $_SESSION['company_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: pro.php?error=user_not_found");
    exit;
}

// Don't allow deleting other admins
if ($user['role'] === 'admin') {
    header("Location: pro.php?error=cannot_delete_admin");
    exit;
}

// Delete the user
$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

// Redirect back
header("Location: pro.php?success=deleted");
exit;
