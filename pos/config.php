<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'ravenhill_final';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Login check function
function requireLogin($pdo, $allowedRoles = ['cashier', 'admin']) {
    if (!isset($_SESSION['user_id'])) {
        // Store the intended page in the URL
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: login.php?redirect=$redirect");
        exit;
    }
    // Verify user exists and is active
    $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || !in_array($user['role'], $allowedRoles)) {
        session_destroy();
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: login.php?error=unauthorized&redirect=$redirect");
        exit;
    }
}