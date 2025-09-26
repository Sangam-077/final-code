<?php
// db_connect.php - Database connection handler

// Enable error reporting for debugging (DISABLE IN PRODUCTION!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection configuration
$host = 'localhost';
$dbname = 'ravenhill_final';
$username = 'root';
$password = ''; // Change this to your MySQL password if set

// Create MySQLi connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $error_msg = "Database connection failed: " . $conn->connect_error;
    error_log($error_msg);
    die($error_msg . ". Please check your database credentials and ensure the 'ravenhill_final' database exists.");
}

// Verify connection is active
if ($conn->ping() === false) {
    $error_msg = "Database connection inactive: " . $conn->error;
    error_log($error_msg);
    die($error_msg);
}

// Set charset to utf8mb4
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error setting charset: " . $conn->error);
    // Proceed with default charset, but log the issue
}

// Check and create users table columns if missing
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'security_question'");
if ($result === false) {
    error_log("Error checking for 'security_question' column: " . $conn->error);
} elseif ($result->num_rows === 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN security_question VARCHAR(255) DEFAULT NULL") === false) {
        error_log("Failed to add 'security_question' column: " . $conn->error);
    } else {
        error_log("Added 'security_question' column to 'users' table.");
    }
}
if ($result) $result->free();

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'security_answer'");
if ($result === false) {
    error_log("Error checking for 'security_answer' column: " . $conn->error);
} elseif ($result->num_rows === 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN security_answer VARCHAR(255) DEFAULT NULL") === false) {
        error_log("Failed to add 'security_answer' column: " . $conn->error);
    } else {
        error_log("Added 'security_answer' column to 'users' table.");
    }
}
if ($result) $result->free();

// Check and create password_resets table and column if missing
$result = $conn->query("SHOW TABLES LIKE 'password_resets'");
if ($result === false) {
    error_log("Error checking for 'password_resets' table: " . $conn->error);
} elseif ($result->num_rows === 0) {
    if ($conn->query("CREATE TABLE password_resets (
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expiry DATETIME DEFAULT NULL,
        PRIMARY KEY (email)
    )") === false) {
        error_log("Failed to create 'password_resets' table: " . $conn->error);
    } else {
        error_log("Created 'password_resets' table.");
    }
} else {
    $result2 = $conn->query("SHOW COLUMNS FROM password_resets LIKE 'expiry'");
    if ($result2 === false) {
        error_log("Error checking for 'expiry' column: " . $conn->error);
    } elseif ($result2->num_rows === 0) {
        if ($conn->query("ALTER TABLE password_resets ADD COLUMN expiry DATETIME DEFAULT NULL") === false) {
            error_log("Failed to add 'expiry' column: " . $conn->error);
        } else {
            error_log("Added 'expiry' column to 'password_resets' table.");
        }
    }
    if ($result2) $result2->free();
}
if ($result) $result->free();

// Check if users table exists (critical for social_login.php)
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result === false) {
    error_log("Error checking for 'users' table: " . $conn->error);
} elseif ($result->num_rows === 0) {
    error_log("Warning: 'users' table does not exist in database 'ravenhill_final'. Please create it.");
}
if ($result) $result->free();

// Optional: Check product table (for your app's context)
$result = $conn->query("SELECT COUNT(*) as count FROM product");
if ($result === false) {
    error_log("Error querying product count: " . $conn->error);
} else {
    $row = $result->fetch_assoc();
    if ($row['count'] === 0) {
        error_log("Warning: No products found in database.");
    }
    $result->free();
}

// Log successful connection
error_log("Database connection successful to '$dbname'");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart and wishlist if they don't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}
?>