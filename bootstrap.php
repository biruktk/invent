<?php
// bootstrap.php

// Start session
session_start();

// Database connection
const DB_PATH = __DIR__ . '/inventory2.sqlite';
$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Enable foreign keys
$pdo->exec('PRAGMA foreign_keys = ON');

// If user is not logged in, redirect (optional)
if (!isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit;
}

