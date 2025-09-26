<?php
require_once 'config.php';

// Handle stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    if ($quantity > 0) {
        // Begin transaction
        $pdo->beginTransaction();
        try {
            // Update inventory
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET stock_level = stock_level + ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$quantity, $product_id]);

            // Insert transaction
            $transaction_id = uniqid('trn_', true);
            $inventory_id = $pdo->query("SELECT inventory_id FROM inventory WHERE product_id = $product_id")->fetchColumn();
            $staff_id = 'staff1'; // Hardcoded for now
            $stmt = $pdo->prepare("
                INSERT INTO inventory_transaction 
                (transaction_id, inventory_id, quantity, type, transaction_time, staff_id) 
                VALUES (?, ?, ?, 'add', NOW(), ?)
            ");
            $stmt->execute([$transaction_id, $inventory_id, $quantity, $staff_id]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error: " . $e->getMessage();
        }
    }
}

// Fetch inventory data
$inventories = $pdo->query("
    SELECT i.*, p.name, p.description 
    FROM inventory i 
    JOIN product p ON i.product_id = p.product_id
    ORDER BY p.name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Ravenhill POS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="pos-container">
        <header class="pos-header">
            <h1><i class="fas fa-warehouse"></i> Inventory Management</h1>
            <div class="header-actions">
                <a href="index.php" class="btn-primary"><i class="fas fa-arrow-left"></i> Back to POS</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="header-icon" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <div class="inventory-content">
            <table id="inventory-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Description</th>
                        <th>Stock Level</th>
                        <th>Threshold</th>
                        <th>Add Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventories as $inv): ?>
                        <tr class="<?= $inv['stock_level'] < ($inv['threshold'] ?? 10) ? 'low-stock' : '' ?>">
                            <td><?= htmlspecialchars($inv['name']) ?></td>
                            <td><?= htmlspecialchars($inv['description']) ?></td>
                            <td><?= $inv['stock_level'] ?></td>
                            <td><?= $inv['threshold'] ?? 'N/A' ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="product_id" value="<?= $inv['product_id'] ?>">
                                    <input type="number" name="quantity" min="1" value="1" style="width: 60px;">
                                    <button type="submit" name="update_stock" class="btn-primary"><i class="fas fa-plus"></i> Add</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>