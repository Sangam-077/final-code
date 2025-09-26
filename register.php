<?php
// register.php
ob_start(); // Start output buffering
ob_clean(); // Clear any existing buffer

// Enable error logging, disable display errors
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log');
error_reporting(E_ALL);

session_start();
require_once 'db_connect.php';

// Check for output before headers
if (headers_sent($file, $line)) {
    $error_msg = "Headers already sent in $file on line $line";
    error_log($error_msg);
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Headers already sent']);
    exit;
}

// Check database connection
if (!$conn || $conn->connect_error) {
    $error = 'Database connection failed: ' . ($conn->connect_error ?? 'No connection');
    error_log($error);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $error]);
        exit;
    }
    die("<pre>$error</pre>");
}

$sticky_first_name = '';
$sticky_last_name = '';
$sticky_email = '';
$error = '';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = filter_input(INPUT_POST, 'firstName', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'lastName', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirmPass'] ?? '';
    $terms = isset($_POST['terms']) && $_POST['terms'] === 'on';
    $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'profile.php';

    // Log input for debugging
    error_log("Register - firstName: $first_name, lastName: $last_name, email: $email, terms: " . ($terms ? 'on' : 'off') . ", redirect: $redirect");

    // Server-side validation
    if (!$first_name || strlen($first_name) < 2 || !$last_name || strlen($last_name) < 2) {
        $error = 'Names must be at least 2 characters long.';
    } elseif (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!$password || strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$terms) {
        $error = 'Please agree to the Terms of Service and Privacy Policy.';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
            error_log("Register prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = "Email already registered.";
                $sticky_email = $email;
                $sticky_first_name = $first_name;
                $sticky_last_name = $last_name;
            } else {
                // Insert new user
                $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, password, role) VALUES (?, ?, ?, ?, 'customer')");
                if ($stmt === false) {
                    $error = "Database error: " . $conn->error;
                    error_log("Register insert failed: " . $conn->error);
                } else {
                    $user_id = uniqid('user_', true);
                    $name = trim("$first_name $last_name");
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param("ssss", $user_id, $name, $email, $hashed_password);
                    if ($stmt->execute()) {
                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['just_registered'] = true; // Flag for profile message
                        if ($is_ajax) {
                            ob_end_clean();
                            header('Content-Type: application/json');
                            echo json_encode(['status' => 'success', 'redirect' => $redirect]);
                            exit;
                        } else {
                            header("Location: $redirect");
                            exit;
                        }
                    } else {
                        $error = "Failed to create account: " . $stmt->error;
                        error_log("Register execute failed: " . $stmt->error);
                    }
                }
                $stmt->close();
            }
        }
    }

    if ($is_ajax) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $error]);
        exit;
    }
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script async defer src="https://connect.facebook.net/en_US/sdk.js"></script>
</head>
<body>
    <div class="auth-container">
        <div class="visual-section" style="background-image: url('https://images.unsplash.com/photo-1517705008128-361805f42e86?ixlib=rb-4.0.3&auto=format&fit=crop&w=1887&q=80');">
            <div class="overlay-text">
                <h1>Ravenhill Coffee House</h1>
                <p>Savour the moment with every sip.</p>
            </div>
        </div>
        <div class="form-section">
            <h2>Create an Account</h2>
            <p>Join us to enjoy exclusive offers</p>
            <?php if ($error) echo "<p class='error'>$error</p>"; ?>
            <form id="signUpForm" method="POST" action="">
                <div class="input-field">
                    <label for="firstName">First Name</label>
                    <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($sticky_first_name); ?>" placeholder="Your first name" required autocomplete="given-name">
                </div>
                <div class="input-field">
                    <label for="lastName">Last Name</label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($sticky_last_name); ?>" placeholder="Your last name" required autocomplete="family-name">
                </div>
                <div class="input-field">
                    <label for="regEmail">Email</label>
                    <input type="email" id="regEmail" name="email" value="<?php echo htmlspecialchars($sticky_email); ?>" placeholder="your.email@example.com" required autocomplete="email">
                </div>
                <div class="input-field">
                    <label for="regPass">Password</label>
                    <input type="password" id="regPass" name="password" placeholder="Your secure password" required autocomplete="new-password">
                </div>
                <div class="input-field">
                    <label for="confirmPass">Confirm Password</label>
                    <input type="password" id="confirmPass" name="confirmPass" placeholder="Confirm your password" required autocomplete="new-password">
                </div>
                <div class="input-field">
                    <label>
                        <input type="checkbox" name="terms"> I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>
                    </label>
                </div>
                <button type="submit" class="login-btn">Sign Up</button>
                <div class="social-options">
                    <div id="g_id_onload"
                         data-client_id="385621142047-2ne0bkjjbb63630bej536ce25lkl24lm.apps.googleusercontent.com"
                         data-callback="handleGoogleSignIn"
                         data-auto_prompt="false">
                    </div>
                    <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="sign_up_with" data-shape="rectangular" data-logo_alignment="left"></div>
                    <button class="social-btn fb-btn" id="fb-btn"><i class="fab fa-facebook-f"></i> Facebook</button>
                </div>
                <p class="signup-text">Already have an account? <a href="login.php?redirect=<?= urlencode($_GET['redirect'] ?? $_SERVER['REQUEST_URI']) ?>">Log In</a></p>
                <button type="button" class="cancel-btn" onclick="window.location.href='<?= isset($_GET['redirect']) ? urldecode($_GET['redirect']) : 'index.php' ?>'"><i class="fas fa-times"></i></button>
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