<?php
// login.php
ob_start(); // Start output buffering

// Enable error logging, disable display errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');
error_reporting(E_ALL);

// Clear any existing output buffer to prevent headers issues
if (ob_get_length()) {
    ob_clean();
}

session_start();
require_once 'db_connect.php';

// Check for output before headers
if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file on line $line");
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => 'Headers already sent']);
    exit;
}

// Check database connection early
if (!$conn || $conn->connect_error) {
    $error = 'Database connection failed: ' . ($conn->connect_error ?? 'No connection');
    error_log($error);
    ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'error', 'message' => $error]);
    exit;
}

// Determine if request is AJAX
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
error_log("Is AJAX request: " . ($is_ajax ? 'Yes' : 'No'));

// Get redirect parameter from GET or POST or use referrer
$redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? '';
if (empty($redirect) && !empty($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $query = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_QUERY);
    if ($query) {
        $referer .= '?' . $query;
    }
    if (basename($referer) !== 'login.php' && basename($referer) !== 'register.php') {
        $redirect = $referer;
    } else {
        $redirect = 'index.php';
    }
} elseif (empty($redirect)) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
    $password = $_POST['password'] ?? '';

    // Log input for debugging
    error_log("Login - email: $email, redirect: $redirect");

    if (empty($email) || empty($password)) {
        $error = "Please fill all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, password, role FROM users WHERE email = ?");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
            error_log("Login prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];  // Set the role in session

                // Role-based redirection
                switch ($user['role']) {
                    case 'admin':
                        $redirect = 'admin_dashboard.php';  // Admin dashboard
                        break;
                    case 'staff':
                        $redirect = 'staff.php';  // Staff dashboard
                        break;
                    case 'cashier':
                        $redirect = 'pos/index.php';  // Cashier dashboard
                        break;
                    case 'customer':
                    default:
                        // Keep the original redirect for customers
                        break;
                }

                ob_end_clean();
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['status' => 'success', 'redirect' => $redirect]);
                exit;
            } else {
                $error = "Invalid email or password.";
            }
            $stmt->close();
        }
    }

    // For AJAX requests, always return JSON and exit
    if ($is_ajax) {
        ob_end_clean();
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'error', 'message' => $error ?? 'Unknown error', 'sticky_email' => $email]);
        exit;
    }
}

// For non-AJAX requests, render HTML
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script async defer src="https://connect.facebook.net/en_US/sdk.js"></script>
</head>
<body>
    <div class="auth-container">
        <div class="visual-section" style="background-image: url('https://images.unsplash.com/photo-1517705008128-361805f42e86?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1887&q=80');">
            <div class="overlay-text">
                <h1>Ravenhill Coffee House</h1>
                <p>Savour the moment with every sip.</p>
            </div>
        </div>
        <div class="form-section">
            <h2>Welcome Back</h2>
            <p>Enter your details to access your account</p>
            <?php if (!empty($error)) echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; ?>
            <form id="signInForm" method="POST" action="">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <div class="input-field">
                    <label for="userEmail">Email</label>
                    <input type="email" id="userEmail" name="email" value="<?= htmlspecialchars($email ?? '') ?>" placeholder="your.email@example.com" required autocomplete="email">
                </div>
                <div class="input-field">
                    <label for="userPass">Password</label>
                    <input type="password" id="userPass" name="password" placeholder="Your secure password" required autocomplete="current-password">
                </div>
                <a href="reset_password.php?redirect=<?= urlencode($redirect) ?>" class="reset-link">Forgot Password?</a>
                <button type="submit" class="login-btn">Log In</button>
                <div class="social-options">
                    <div id="g_id_onload"
                         data-client_id="385621142047-2ne0bkjjbb63630bej536ce25lkl24lm.apps.googleusercontent.com"
                         data-callback="handleGoogleSignIn"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="sign_in_with" data-shape="rectangular" data-logo_alignment="left"></div>
                    <button class="social-btn fb-btn" id="fb-btn"><i class="fab fa-facebook-f"></i> Facebook</button>
                </div>
                <p class="signup-text">New here? <a href="register.php?redirect=<?= urlencode($redirect) ?>">Create an Account</a></p>
                <button type="button" class="cancel-btn" onclick="window.location.href='<?= htmlspecialchars($redirect) ?>'"><i class="fas fa-times"></i></button>
            </form>
        </div>
    </div>
    <script>
        window.fbAsyncInit = function() {
            FB.init({
                appId: 'your-facebook-app-id', // Replace with your actual Facebook App ID
                cookie: true,
                xfbml: true,
                version: 'v19.0'
            });
        };
    </script>
    <script src="script.js"></script>
</body>
</html>