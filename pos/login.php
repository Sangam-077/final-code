<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cashier_code = trim($_POST['cashier_code'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($cashier_code) || empty($password)) {
        $error = 'Please enter cashier code and password.';
    } else {
        // Find cashier by code
        $stmt = $pdo->prepare("
            SELECT u.*, c.cashier_code, c.shift 
            FROM users u 
            JOIN cashier c ON u.user_id = c.cashier_id 
            WHERE c.cashier_code = ? AND u.status = 'active'
        ");
        $stmt->execute([$cashier_code]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['cashier_code'] = $user['cashier_code'];
            $_SESSION['shift'] = $user['shift'];
            
            // Redirect to intended page or index.php
            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . urldecode($redirect));
            exit;
        } else {
            $error = 'Invalid cashier code or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ravenhill POS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <i class="fas fa-coffee"></i>
                <h1>Ravenhill POS</h1>
            </div>
            <?php if ($error): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="cashier_code"><i class="fas fa-id-badge"></i> Cashier Code</label>
                    <input type="text" id="cashier_code" name="cashier_code" required placeholder="e.g., C001" value="<?= htmlspecialchars($_POST['cashier_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="login-btn"><i class="fas fa-sign-in-alt"></i> Login</button>
            </form>
            <p class="admin-link"><a href="manage_cashiers.php">Admin/Manager Login</a></p>
        </div>
    </div>
</body>
</html>