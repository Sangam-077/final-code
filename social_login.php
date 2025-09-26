<?php
// social_login.php
ob_start(); // Start output buffering
header('Content-Type: application/json');
header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Resource-Policy: same-origin');

// Check for output before headers
if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file on line $line");
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Headers already sent']);
    exit;
}

session_start();

// Check if vendor/autoload.php exists
$autoload_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload_path)) {
    $response = ['status' => 'error', 'message' => 'Composer autoload file not found at: ' . $autoload_path];
    error_log('Composer autoload file not found at: ' . $autoload_path);
    ob_end_clean();
    echo json_encode($response);
    exit;
}

require_once $autoload_path;
require_once 'db_connect.php';

// Disable display errors, keep logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php/logs/php_error.log'); // Adjust path
error_reporting(E_ALL);

// Default response
$response = ['status' => 'error', 'message' => 'Invalid login attempt'];

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Method not allowed';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Get raw input and merge with $_POST fallback
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true) ?? [];
$post_data = $_POST;

$provider = $input['provider'] ?? $post_data['provider'] ?? '';
$credential = $input['credential'] ?? $post_data['credential'] ?? '';
$access_token = $input['access_token'] ?? $post_data['access_token'] ?? '';
$redirect = $input['redirect'] ?? $post_data['redirect'] ?? 'index.php';

// Log input for debugging
error_log("Provider: $provider, Credential: " . (substr($credential, 0, 50) ?: 'EMPTY') . ", Access Token: " . ($access_token ?: 'EMPTY') . ", Redirect: $redirect");

// Validate provider
if (empty($provider) || !in_array($provider, ['google', 'facebook'])) {
    $response['message'] = 'Invalid or missing provider';
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Check database connection
if (!$conn || $conn->connect_error) {
    $response['message'] = 'Database connection failed: ' . ($conn->connect_error ?? 'No connection');
    error_log('Database connection failed: ' . ($conn->connect_error ?? 'No connection'));
    ob_end_clean();
    echo json_encode($response);
    exit;
}

try {
    if ($provider === 'google') {
        if (empty($credential)) {
            $response['message'] = 'Missing Google credential';
            error_log('Missing Google credential');
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        error_log("Processing Google credential: " . substr($credential, 0, 50) . "...");

        // Initialize Google Client
        $client = new Google_Client([
            'client_id' => '385621142047-2ne0bkjjbb63630bej536ce25lkl24lm.apps.googleusercontent.com'
        ]);
        $payload = $client->verifyIdToken($credential);

        if ($payload === false) {
            $response['message'] = 'Token verification failed';
            error_log('Token verification failed: Invalid or expired token');
            ob_end_clean();
            echo json_encode($response);
            exit;
        }

        if (!isset($payload['email']) || !$payload['email']) {
            $response['message'] = 'No email in Google token payload';
            error_log('No email in payload: ' . json_encode($payload));
            ob_end_clean();
            echo json_encode($response);
            exit;
        }

        $email = $payload['email'];
        $name = $payload['name'] ?? trim(($payload['given_name'] ?? '') . ' ' . ($payload['family_name'] ?? ''));

        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        if ($stmt === false) {
            $response['message'] = 'Failed to prepare SELECT statement: ' . $conn->error;
            error_log('Prepare failed: ' . $conn->error);
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            $_SESSION['user_id'] = $user['user_id'];
        } else {
            $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, password, role) VALUES (?, ?, ?, '', 'customer')");
            if ($stmt === false) {
                $response['message'] = 'Failed to prepare INSERT statement: ' . $conn->error;
                error_log('Prepare failed: ' . $conn->error);
                ob_end_clean();
                echo json_encode($response);
                exit;
            }
            $user_id = uniqid('user_', true);
            $stmt->bind_param("sss", $user_id, $name, $email);
            $stmt->execute();
            $_SESSION['user_id'] = $user_id;
        }
        $stmt->close();

        $response = [
            'status' => 'success',
            'provider' => 'google',
            'email' => $email,
            'name' => $name,
            'redirect' => $redirect
        ];
    } elseif ($provider === 'facebook') {
        if (empty($access_token)) {
            $response['message'] = 'Missing Facebook access token';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }

        $fb_url = 'https://graph.facebook.com/me?fields=id,name,email&access_token=' . urlencode($access_token);
        $fb_response = @file_get_contents($fb_url);
        if ($fb_response === false) {
            $response['message'] = 'Failed to fetch Facebook user data';
            error_log('Facebook API request failed');
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        $fb_data = json_decode($fb_response, true);

        if (isset($fb_data['email'])) {
            $email = $fb_data['email'];
            $name = $fb_data['name'];

            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            if ($stmt === false) {
                $response['message'] = 'Failed to prepare SELECT statement: ' . $conn->error;
                error_log('Prepare failed: ' . $conn->error);
                ob_end_clean();
                echo json_encode($response);
                exit;
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
            } else {
                $stmt = $conn->prepare("INSERT INTO users (user_id, name, email, password, role) VALUES (?, ?, ?, '', 'customer')");
                if ($stmt === false) {
                    $response['message'] = 'Failed to prepare INSERT statement: ' . $conn->error;
                    error_log('Prepare failed: ' . $conn->error);
                    ob_end_clean();
                    echo json_encode($response);
                    exit;
                }
                $user_id = uniqid('user_', true);
                $stmt->bind_param("sss", $user_id, $name, $email);
                $stmt->execute();
                $_SESSION['user_id'] = $user_id;
            }
            $stmt->close();

            $response = [
                'status' => 'success',
                'provider' => 'facebook',
                'email' => $email,
                'name' => $name,
                'redirect' => $redirect
            ];
        } else {
            $response['message'] = 'Failed to get Facebook user data: ' . json_encode($fb_data);
            error_log($response['message']);
        }
    }
} catch (Exception $e) {
    $response['message'] = 'Server error: ' . $e->getMessage();
    error_log("Exception in social_login.php: " . $e->getMessage());
}

ob_end_clean(); // Clear buffer before sending JSON
echo json_encode($response);
exit;
?>