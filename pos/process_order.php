<?php
require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle AJAX requests (JSON input)
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['action'])) {
    header('Content-Type: application/json');
    
    if ($input['action'] === 'sync_cart') {
        // Sync cart to session
        $_SESSION['cart'] = $input['cart'] ?? [];
        error_log('Session cart synced: ' . print_r($_SESSION['cart'], true));
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($input['action'] === 'get_cashier_id') {
        // Return current cashier_id from session
        if (isset($_SESSION['user_id'])) {
            echo json_encode(['success' => true, 'cashier_id' => $_SESSION['user_id']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No active session']);
        }
        exit;
    }
    
    if ($input['action'] === 'process_order') {
        // Validate input
        if (empty($input['items'])) {
            echo json_encode(['success' => false, 'error' => 'Empty cart']);
            exit;
        }
        if (empty($input['cashier_id'])) {
            echo json_encode(['success' => false, 'error' => 'No cashier logged in']);
            exit;
        }

        // Validate cashier_id exists in users table
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$input['cashier_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Invalid or inactive cashier']);
            exit;
        }

        // Validate stock levels
        foreach ($input['items'] as $item) {
            $stmt = $pdo->prepare("SELECT stock_level FROM inventory WHERE product_id = ?");
            $stmt->execute([$item['id']]);
            $stock = $stmt->fetchColumn();
            if ($stock === false || $stock < $item['quantity']) {
                echo json_encode(['success' => false, 'error' => "Insufficient stock for {$item['name']}"]);
                exit;
            }
        }

        // Calculate totals
        $subtotal = $input['subtotal'] ?? 0;
        $tax = $input['tax'] ?? 0;
        $total = $input['total'] ?? 0;
        $payment_method = $input['payment_method'] ?? 'cash';
        $cashier_id = $input['cashier_id'];
        $staff_id = null; // Schema allows NULL
        $customer_id = null; // Schema allows NULL
        $promotion_id = null; // Schema allows NULL

        // Generate unique IDs
        $order_id = uniqid('ord_', true);
        $payment_id = uniqid('pay_', true);

        // Begin transaction
        $pdo->beginTransaction();
        try {
            // Insert into orders
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (order_id, customer_id, staff_id, cashier_id, total_price, order_time, order_type, order_address, promotion_id) 
                VALUES (?, ?, ?, ?, ?, NOW(), 'in_store', NULL, ?)
            ");
            $stmt->execute([$order_id, $customer_id, $staff_id, $cashier_id, $total, $promotion_id]);

            // Insert order items and update inventory
            $orderItemCounter = 0;
            foreach ($input['items'] as $item) {
                $order_item_id = uniqid('itm_') . '_' . sprintf('%03d', $orderItemCounter++); // e.g., itm_68d0bf92a881a7_000, itm_68d0bf92a881a7_001
                $unit_price = $item['price'];
                $quantity = $item['quantity'];
                $product_id = $item['id'];
            
                // Insert order_item
                $stmt = $pdo->prepare("
                    INSERT INTO order_item 
                    (order_item_id, order_id, product_id, quantity, unit_price, customisations) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$order_item_id, $order_id, $product_id, $quantity, $unit_price, $item['customisations'] ?? NULL]);
            
                // Update inventory stock
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET stock_level = stock_level - ? 
                    WHERE product_id = ?
                ");
                $stmt->execute([$quantity, $product_id]);
            
                // Insert inventory transaction
                $transaction_id = uniqid('trn_', true);
                $stmt = $pdo->prepare("SELECT inventory_id FROM inventory WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $inventory_id = $stmt->fetchColumn();
                if ($inventory_id === false) {
                    throw new Exception("Invalid inventory ID for product $product_id");
                }
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_transaction 
                    (transaction_id, inventory_id, quantity, type, transaction_time, staff_id) 
                    VALUES (?, ?, ?, 'sale', NOW(), ?)
                ");
                $stmt->execute([$transaction_id, $inventory_id, -$quantity, $staff_id]);
            }

            // Insert payment
            $stmt = $pdo->prepare("
                INSERT INTO payment 
                (payment_id, order_id, amount, method, payment_status, payment_time, cashier_id) 
                VALUES (?, ?, ?, ?, 'completed', NOW(), ?)
            ");
            $stmt->execute([$payment_id, $order_id, $total, $payment_method, $cashier_id]);

            // Commit transaction
            $pdo->commit();

            // Clear session cart
            unset($_SESSION['cart']);
            $_SESSION['cart'] = [];

            echo json_encode(['success' => true, 'order_id' => $order_id]);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Order processing error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;