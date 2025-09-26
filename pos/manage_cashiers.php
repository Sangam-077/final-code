<?php
require_once 'config.php';

// Manual admin check (or use requireLogin with ['admin'])
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=admin_only');
    exit;
}

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_cashier'])) {
        $user_id = uniqid('cash_');
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $cashier_code = trim($_POST['cashier_code']);
        $shift = trim($_POST['shift']);
        
        if (!empty($name) && !empty($email) && !empty($password) && !empty($cashier_code) && !empty($shift)) {
            $pdo->beginTransaction();
            try {
                // Insert user
                $stmt = $pdo->prepare("INSERT INTO users (user_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'cashier', 'active')");
                $stmt->execute([$user_id, $name, $email, $password]);
                
                // Insert cashier
                $stmt = $pdo->prepare("INSERT INTO cashier (cashier_id, cashier_code, shift) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $cashier_code, $shift]);
                
                $pdo->commit();
                $message = 'Cashier added successfully.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
            }
        } else {
            $message = 'All fields are required.';
        }
    }
}

// Fetch all cashiers
$cashiers = $pdo->query("
    SELECT u.*, c.cashier_code, c.shift 
    FROM users u 
    JOIN cashier c ON u.user_id = c.cashier_id 
    WHERE u.role = 'cashier'
    ORDER BY u.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cashiers - Ravenhill POS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="pos-container">
        <header class="pos-header">
            <h1><i class="fas fa-users-cog"></i> Manage Cashiers</h1>
            <div class="header-actions">
                <a href="index.php" class="btn-primary">Back to POS</a>
                <a href="logout.php" class="btn-danger">Logout</a>
            </div>
        </header>

        <div class="manage-content">
            <?php if ($message): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <!-- Add Cashier Form -->
            <section class="add-section">
                <h2>Add New Cashier</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" name="name" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label>Cashier Code</label>
                            <input type="text" name="cashier_code" required placeholder="e.g., C003">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Shift</label>
                        <input type="text" name="shift" required placeholder="e.g., Morning">
                    </div>
                    <button type="submit" name="add_cashier" class="btn-primary">Add Cashier</button>
                </form>
            </section>

            <!-- Cashiers List -->
            <section class="list-section">
                <h2>Current Cashiers</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Cashier Code</th>
                            <th>Shift</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashiers as $cashier): ?>
                            <tr>
                                <td><?= htmlspecialchars($cashier['name']) ?></td>
                                <td><?= htmlspecialchars($cashier['email']) ?></td>
                                <td><?= htmlspecialchars($cashier['cashier_code']) ?></td>
                                <td><?= htmlspecialchars($cashier['shift']) ?></td>
                                <td><?= htmlspecialchars($cashier['status']) ?></td>
                                <td>
                                    <a href="#" class="btn-small">Edit</a>
                                    <a href="#" class="btn-danger-small">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</body>
</html>