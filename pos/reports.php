<?php
require_once 'config.php';

// Fetch sales data
$reports = $pdo->query("
    SELECT o.order_id, o.total_price, o.order_time, o.order_type, 
           p.method, p.payment_status, p.amount
    FROM orders o 
    JOIN payment p ON o.order_id = p.order_id 
    ORDER BY o.order_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_sales = $pdo->query("SELECT SUM(total_price) as total FROM orders")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$today_sales = $pdo->query("SELECT SUM(total_price) as total FROM orders WHERE DATE(order_time) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Ravenhill POS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="pos-container">
        <header class="pos-header">
            <h1><i class="fas fa-chart-bar"></i> Sales Reports</h1>
            <div class="header-actions">
                <a href="index.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Back to POS</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="header-icon" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="reports-content">
            <div class="summary-cards">
                <div class="summary-card">
                    <h3>Total Sales</h3>
                    <p>$<?= number_format($total_sales, 2) ?></p>
                </div>
                <div class="summary-card">
                    <h3>Today's Sales</h3>
                    <p>$<?= number_format($today_sales, 2) ?></p>
                </div>
            </div>

            <table id="reports-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Total</th>
                        <th>Date/Time</th>
                        <th>Type</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No sales data available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $rep): ?>
                            <tr>
                                <td><?= htmlspecialchars($rep['order_id']) ?></td>
                                <td>$<?= number_format($rep['total_price'], 2) ?></td>
                                <td><?= htmlspecialchars($rep['order_time']) ?></td>
                                <td><?= htmlspecialchars($rep['order_type']) ?></td>
                                <td><?= htmlspecialchars($rep['method']) ?></td>
                                <td><?= htmlspecialchars($rep['payment_status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>