<?php
session_start();
include 'db_connect.php';

// Check if logged in as staff
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit;
}

// Fetch user name
$username = 'Staff'; // Fallback
try {
    $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
    } else {
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $username = isset($user['name']) ? htmlspecialchars($user['name']) : 'Staff';
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
}

// Handle low-stock alerts
$lowStockItems = [];
try {
    $stmt = $conn->query("SELECT p.name, i.stock_level, i.threshold FROM inventory i JOIN product p ON i.product_id = p.product_id WHERE i.stock_level < i.threshold");
    $lowStockItems = $stmt ? $stmt->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    error_log("Error fetching low stock: " . $e->getMessage());
}

// Handle daily sales
$todaySales = 0;
try {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT SUM(total_price) as total FROM orders WHERE DATE(order_time) = ?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
    } else {
        $stmt->bind_param("s", $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $salesData = $result->fetch_assoc();
        $todaySales = isset($salesData['total']) ? floatval($salesData['total']) : 0;
        $stmt->close();
    }
} catch (Exception $e) {
    error_log("Error fetching sales: " . $e->getMessage());
}

// Total Products
$totalProducts = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM product");
    $data = $stmt->fetch_assoc();
    $totalProducts = $data['count'];
} catch (Exception $e) {
    error_log("Error fetching products: " . $e->getMessage());
}

// Total Orders
$totalOrders = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders");
    $data = $stmt->fetch_assoc();
    $totalOrders = $data['count'];
} catch (Exception $e) {
    error_log("Error fetching orders: " . $e->getMessage());
}

// Total Customers
$totalCustomers = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    $data = $stmt->fetch_assoc();
    $totalCustomers = $data['count'];
} catch (Exception $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

// Total Revenue
$totalRevenue = 0;
try {
    $stmt = $conn->query("SELECT SUM(total_price) as total FROM orders");
    $data = $stmt->fetch_assoc();
    $totalRevenue = floatval($data['total']);
} catch (Exception $e) {
    error_log("Error fetching revenue: " . $e->getMessage());
}

// Recent Orders
$recentOrders = [];
try {
    $stmt = $conn->query("
        SELECT o.order_id, u.name as customer, o.total_price, o.order_type as type, o.order_time, s.name as assigned_staff, o.staff_id
        FROM orders o
        LEFT JOIN customer c ON o.customer_id = c.customer_id
        LEFT JOIN users u ON c.customer_id = u.user_id
        LEFT JOIN users s ON o.staff_id = s.user_id
        ORDER BY o.order_time DESC LIMIT 5
    ");
    if ($stmt === false) {
        error_log("Query failed: " . $conn->error);
    } else {
        $recentOrders = $stmt->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching recent orders: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
                $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
                $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $category_id = filter_input(INPUT_POST, 'category_id', FILTER_SANITIZE_NUMBER_INT);
                $image = 'default.jpg'; // Default image if no file uploaded
                if (isset($_FILES['image']) && is_array($_FILES['image']) && isset($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $image = basename($_FILES['image']['name']);
                    $target_dir = 'Uploads/';
                    $target_file = $target_dir . $image;
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        error_log("Failed to move uploaded file to $target_file");
                        $image = 'default.jpg';
                    }
                } elseif (isset($_FILES['image']) && is_array($_FILES['image']) && array_key_exists('error', $_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    error_log("File upload error for image: " . $_FILES['image']['error']);
                }
                $stmt = $conn->prepare("INSERT INTO product (name, description, price, category_id, image_url) VALUES (?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("ssdss", $name, $description, $price, $category_id, $image);
                    $stmt->execute();
                    $product_id = $conn->insert_id;
                    $stmt = $conn->prepare("INSERT INTO inventory (product_id, stock_level, threshold) VALUES (?, 100, 20)");
                    if ($stmt === false) {
                        error_log("Prepare failed: " . $conn->error);
                    } else {
                        $stmt->bind_param("i", $product_id);
                        $stmt->execute();
                    }
                }
                break;

            case 'delete_product':
                $product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $conn->prepare("DELETE FROM inventory WHERE product_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("i", $product_id);
                    $stmt->execute();
                }
                $stmt = $conn->prepare("DELETE FROM product WHERE product_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("i", $product_id);
                    $stmt->execute();
                }
                break;

            case 'update_stock':
                $inventory_id = filter_input(INPUT_POST, 'inventory_id', FILTER_SANITIZE_STRING);
                $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
                $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
                if ($type === 'in' || $type === 'out') {
                    $stmt = $conn->prepare("INSERT INTO inventory_transaction (transaction_id, inventory_id, quantity, type, transaction_time, staff_id) VALUES (?, ?, ?, ?, NOW(), ?)");
                    if ($stmt === false) {
                        error_log("Prepare failed: " . $conn->error);
                    } else {
                        $transaction_id = 'trans_' . uniqid();
                        $stmt->bind_param("ssiss", $transaction_id, $inventory_id, $quantity, $type, $_SESSION['user_id']);
                        $stmt->execute();
                        $operator = ($type === 'in') ? '+' : '-';
                        $stmt = $conn->prepare("UPDATE inventory SET stock_level = stock_level $operator ? WHERE inventory_id = ?");
                        if ($stmt === false) {
                            error_log("Prepare failed: " . $conn->error);
                        } else {
                            $stmt->bind_param("is", $quantity, $inventory_id);
                            $stmt->execute();
                        }
                    }
                }
                break;

            case 'approve_review':
                $review_id = filter_input(INPUT_POST, 'review_id', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("UPDATE review SET approved = 1 WHERE review_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("s", $review_id);
                    $stmt->execute();
                }
                break;

            case 'delete_review':
                $review_id = filter_input(INPUT_POST, 'review_id', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("DELETE FROM review WHERE review_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("s", $review_id);
                    $stmt->execute();
                }
                break;

            case 'update_order':
                $order_id = filter_input(INPUT_POST, 'order_id', FILTER_SANITIZE_STRING);
                $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
                $assigned_staff = filter_input(INPUT_POST, 'assigned_staff', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("UPDATE orders SET order_type = ?, staff_id = ? WHERE order_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("sss", $status, $assigned_staff, $order_id);
                    $stmt->execute();
                }
                break;

            case 'send_message':
                $recipient_id = filter_input(INPUT_POST, 'recipient_id', FILTER_SANITIZE_STRING);
                $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
                $notif_type = 'message';
                $sent_time = date('Y-m-d H:i:s');
                $is_read = 0;
                $notification_id = 'notif_' . uniqid();
                $stmt = $conn->prepare("INSERT INTO notification (notification_id, user_id, notif_type, content, sent_time, is_read) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("sssssi", $notification_id, $recipient_id, $notif_type, $content, $sent_time, $is_read);
                    $stmt->execute();
                }
                break;

            case 'delete_message':
                $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("DELETE FROM notification WHERE notification_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("s", $notification_id);
                    $stmt->execute();
                }
                break;

            case 'mark_read':
                $notification_id = filter_input(INPUT_POST, 'notification_id', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("UPDATE notification SET is_read = 1 WHERE notification_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("s", $notification_id);
                    $stmt->execute();
                }
                break;

            case 'update_reservation':
                $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_SANITIZE_STRING);
                $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("UPDATE reservations SET status = ?, updated_at = NOW() WHERE reservation_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("ss", $status, $reservation_id);
                    $stmt->execute();
                }
                break;

            case 'delete_reservation':
                $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_SANITIZE_STRING);
                $stmt = $conn->prepare("DELETE FROM reservations WHERE reservation_id = ?");
                if ($stmt === false) {
                    error_log("Prepare failed: " . $conn->error);
                } else {
                    $stmt->bind_param("s", $reservation_id);
                    $stmt->execute();
                }
                break;
        }
    }
    header('Location: staff.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Ravenhill Coffee House</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="script.js" defer></script>
</head>
<body>
    <div class="sidebar">
        <div id="logo-group">
            <img src="https://cdn-icons-png.flaticon.com/512/924/924514.png" alt="Ravenhill Coffee Logo">
            <span id="logo-text">Ravenhill</span>
        </div>
        <nav>
            <a href="#staff-dashboard" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="#products" class="nav-item"><i class="fas fa-coffee"></i> Products</a>
            <a href="#inventory" class="nav-item"><i class="fas fa-warehouse"></i> Inventory</a>
            <a href="#orders" class="nav-item"><i class="fas fa-shopping-cart"></i> Orders</a>
            <a href="#user-messages" class="nav-item"><i class="fas fa-envelope"></i> User Messages</a>
            <a href="#reservations" class="nav-item"><i class="fas fa-calendar-check"></i> Reservations</a>
            <a href="#subscribers" class="nav-item"><i class="fas fa-users"></i> Subscribers</a>
            <a href="#reports" class="nav-item"><i class="fas fa-chart-bar"></i> Reports</a>
        </nav>
    </div>

    <div class="main-content">
        <div class="top-bar">
            <h1>Staff Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?= $username ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="welcome-bar">
            <h2>Welcome to Ravenhill Staff</h2>
            <p>Manage your café operations with ease</p>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <h3><?= $totalProducts ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-box">
                <h3><?= $totalOrders ?></h3>
                <p>Total Orders</p>
            </div>
            <div class="stat-box">
                <h3><?= $totalCustomers ?></h3>
                <p>Total Customers</p>
            </div>
            <div class="stat-box">
                <h3>$<?= number_format($totalRevenue, 2) ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>

        <div class="dashboard-bottom">
            <div class="recent-orders">
                <h3>Recent Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Assigned Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars($order['order_id']) ?></td>
                                <td><?= htmlspecialchars($order['customer']) ?></td>
                                <td>$<?= number_format($order['total_price'], 2) ?></td>
                                <td><?= htmlspecialchars($order['type']) ?></td>
                                <td><?= htmlspecialchars($order['order_time']) ?></td>
                                <td><?= htmlspecialchars($order['assigned_staff'] ?? 'Unassigned') ?></td>
                                <td>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="update_order">
                                        <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">
                                        <select name="status">
                                            <option value="pending" <?= $order['type'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="processing" <?= $order['type'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                            <option value="completed" <?= $order['type'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="cancelled" <?= $order['type'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                        <select name="assigned_staff">
                                            <option value="">Unassigned</option>
                                            <?php
                                            $staffStmt = $conn->query("SELECT user_id, name FROM users WHERE role = 'staff'");
                                            while ($staff = $staffStmt->fetch_assoc()) {
                                                $selected = ($order['staff_id'] == $staff['user_id']) ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($staff['user_id']) . "' $selected>" . htmlspecialchars($staff['name']) . "</option>";
                                            }
                                            ?>
                                        </select>
                                        <button type="submit">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alerts">
                <div class="low-stock">
                    <h4><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h4>
                    <ul>
                        <?php if (empty($lowStockItems)): ?>
                            <li>No low stock items</li>
                        <?php else: ?>
                            <?php foreach ($lowStockItems as $item): ?>
                                <li><?= htmlspecialchars($item['name']) ?>: <?= $item['stock_level'] ?> units</li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="system-status">
                    <h4><i class="fas fa-check-circle"></i> System Status</h4>
                    <p>All systems operational. Last backup: Sep 26, 2025 8:28 PM</p>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <section id="products" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Product Management</h2>
                <form method="post" enctype="multipart/form-data" class="staff-form">
                    <input type="hidden" name="action" value="add_product">
                    <label>Name:</label>
                    <input type="text" name="name" required>
                    <label>Description:</label>
                    <textarea name="description"></textarea>
                    <label>Price:</label>
                    <input type="number" step="0.01" name="price" required>
                    <label>Category:</label>
                    <select name="category_id" required>
                        <?php
                        $stmt = $conn->query("SELECT category_id, name FROM category");
                        if ($stmt === false) {
                            error_log("Query failed: " . $conn->error);
                        } else {
                            while ($cat = $stmt->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($cat['category_id']) . "'>" . htmlspecialchars($cat['name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                    <label>Image:</label>
                    <input type="file" name="image">
                    <button type="submit">Add Product</button>
                </form>

                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Image</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->query("SELECT p.product_id, p.name, p.description, p.price, c.name as category_name, p.image_url FROM product p JOIN category c ON p.category_id = c.category_id");
                        if ($stmt === false) {
                            error_log("Query failed: " . $conn->error);
                        } else {
                            while ($prod = $stmt->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($prod['product_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($prod['name']) . "</td>";
                                echo "<td>" . htmlspecialchars($prod['description']) . "</td>";
                                echo "<td>$" . number_format($prod['price'], 2) . "</td>";
                                echo "<td>" . htmlspecialchars($prod['category_name']) . "</td>";
                                echo "<td><img src='" . htmlspecialchars($prod['image_url']) . "' alt='Product Image' style='width:50px; height:auto;'></td>";
                                echo "<td>
                                        <form method='post' style='display:inline'>
                                            <input type='hidden' name='action' value='delete_product'>
                                            <input type='hidden' name='product_id' value='" . htmlspecialchars($prod['product_id']) . "'>
                                            <button type='submit' class='delete-link'>Delete</button>
                                        </form>
                                      </td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Inventory Section -->
        <section id="inventory" style="padding: 80px 0; background: var(--background-cream);">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Inventory Management</h2>
                <form method="post" class="staff-form">
                    <input type="hidden" name="action" value="update_stock">
                    <select name="inventory_id" required>
                        <?php
                        $stmt = $conn->query("SELECT i.inventory_id, i.product_id, i.stock_level FROM inventory i");
                        if ($stmt === false) {
                            error_log("Query failed: " . $conn->error);
                        } else {
                            $prod_stmt = $conn->prepare("SELECT name FROM product WHERE product_id = ?");
                            while ($inv = $stmt->fetch_assoc()) {
                                if ($prod_stmt === false) {
                                    error_log("Prepare failed: " . $conn->error);
                                } else {
                                    $prod_stmt->bind_param("i", $inv['product_id']);
                                    $prod_stmt->execute();
                                    $prod = $prod_stmt->get_result()->fetch_assoc();
                                    echo "<option value='" . htmlspecialchars($inv['inventory_id']) . "'>" . htmlspecialchars($prod['name']) . " (Stock: " . $inv['stock_level'] . ")</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                    <input type="number" name="quantity" placeholder="Quantity" required>
                    <select name="type" required>
                        <option value="in">Stock In</option>
                        <option value="out">Stock Out</option>
                    </select>
                    <button type="submit">Update Stock</button>
                </form>
            </div>
        </section>

        <!-- Order Management Section -->
        <section id="orders" style="padding: 80px 0; background: var(--background-cream);">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Order Management</h2>
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total Price</th>
                            <th>Order Time</th>
                            <th>Assigned Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->query("SELECT o.order_id, u.name as customer, o.total_price, o.order_time, s.name as assigned_staff, o.staff_id FROM orders o JOIN users u ON o.customer_id = u.user_id LEFT JOIN users s ON o.staff_id = s.user_id");
                        if ($stmt === false) {
                            error_log("Query failed: " . $conn->error);
                        } else {
                            while ($order = $stmt->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($order['order_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($order['customer']) . "</td>";
                                echo "<td>$" . number_format($order['total_price'], 2) . "</td>";
                                echo "<td>" . htmlspecialchars($order['order_time']) . "</td>";
                                echo "<td>" . htmlspecialchars($order['assigned_staff'] ?? 'Unassigned') . "</td>";
                                echo "<td>
                                    <form method='post' style='display:inline'>
                                        <input type='hidden' name='action' value='update_order'>
                                        <input type='hidden' name='order_id' value='" . htmlspecialchars($order['order_id']) . "'>
                                        <select name='status'>
                                            <option value='pending' " . ($order['type'] == 'pending' ? 'selected' : '') . ">Pending</option>
                                            <option value='processing' " . ($order['type'] == 'processing' ? 'selected' : '') . ">Processing</option>
                                            <option value='completed' " . ($order['type'] == 'completed' ? 'selected' : '') . ">Completed</option>
                                            <option value='cancelled' " . ($order['type'] == 'cancelled' ? 'selected' : '') . ">Cancelled</option>
                                        </select>
                                        <select name='assigned_staff'>
                                            <option value=''>Unassigned</option>";
                                $staffStmt = $conn->query("SELECT user_id, name FROM users WHERE role = 'staff'");
                                while ($staff = $staffStmt->fetch_assoc()) {
                                    $selected = ($order['staff_id'] == $staff['user_id']) ? 'selected' : '';
                                    echo "<option value='" . htmlspecialchars($staff['user_id']) . "' $selected>" . htmlspecialchars($staff['name']) . "</option>";
                                }
                                echo "</select>
                                        <button type='submit'>Update</button>
                                    </form>
                                </td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- User Messages Section -->
        <section id="user-messages" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">User Message Management</h2>
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sender Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Sent Time</th>
                            <th>Read</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->query("SELECT * FROM notification WHERE notif_type = 'message' AND content LIKE 'Contact Form Submission:%' ORDER BY sent_time DESC");
                        if ($stmt === false) {
                            error_log("Query failed: " . $conn->error);
                        } else {
                            while ($msg = $stmt->fetch_assoc()) {
                                $content = $msg['content'];
                                preg_match('/Name: (.*)\\nEmail: (.*)\\nSubject: (.*)\\nMessage: (.*)/s', $content, $matches);
                                $sender_name = $matches[1] ?? 'Unknown';
                                $email = $matches[2] ?? 'Unknown';
                                $subject = $matches[3] ?? 'Unknown';
                                $message_text = $matches[4] ?? 'Unknown';
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($msg['notification_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($sender_name) . "</td>";
                                echo "<td>" . htmlspecialchars($email) . "</td>";
                                echo "<td>" . htmlspecialchars($subject) . "</td>";
                                echo "<td>" . htmlspecialchars($message_text) . "</td>";
                                echo "<td>" . htmlspecialchars($msg['sent_time']) . "</td>";
                                echo "<td>" . ($msg['is_read'] ? 'Yes' : 'No') . "</td>";
                                echo "<td>
                                    <form method='post' style='display:inline'>
                                        <input type='hidden' name='action' value='mark_read'>
                                        <input type='hidden' name='notification_id' value='" . htmlspecialchars($msg['notification_id']) . "'>
                                        <button type='submit'>Mark Read</button>
                                    </form>
                                    <form method='post' style='display:inline'>
                                        <input type='hidden' name='action' value='delete_message'>
                                        <input type='hidden' name='notification_id' value='" . htmlspecialchars($msg['notification_id']) . "'>
                                        <button type='submit' class='delete-link'>Delete</button>
                                    </form>
                                </td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Reservations Section -->
        <section id="reservations" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Reservation Management</h2>
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Party Size</th>
                            <th>Special Requests</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT r.reservation_id, r.name, r.email, r.phone, r.reservation_date, r.reservation_time, r.party_size, r.special_requests, r.status, r.created_at, r.updated_at FROM reservations r ORDER BY r.reservation_date DESC, r.reservation_time DESC");
                            if ($stmt === false) {
                                error_log("Query failed: " . $conn->error);
                            } else {
                                if ($stmt->num_rows === 0) {
                                    echo "<tr><td colspan='12'>No reservations found</td></tr>";
                                } else {
                                    while ($res = $stmt->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($res['reservation_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['name']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['email']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['phone']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['reservation_date']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['reservation_time']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['party_size']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['special_requests'] ?? 'None') . "</td>";
                                        echo "<td>" . htmlspecialchars($res['status']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['created_at']) . "</td>";
                                        echo "<td>" . htmlspecialchars($res['updated_at'] ?? 'N/A') . "</td>";
                                        echo "<td>
                                            <form method='post' style='display:inline'>
                                                <input type='hidden' name='action' value='update_reservation'>
                                                <input type='hidden' name='reservation_id' value='" . htmlspecialchars($res['reservation_id']) . "'>
                                                <select name='status'>
                                                    <option value='pending' " . ($res['status'] == 'pending' ? 'selected' : '') . ">Pending</option>
                                                    <option value='confirmed' " . ($res['status'] == 'confirmed' ? 'selected' : '') . ">Confirmed</option>
                                                    <option value='cancelled' " . ($res['status'] == 'cancelled' ? 'selected' : '') . ">Cancelled</option>
                                                </select>
                                                <button type='submit'>Update</button>
                                            </form>
                                            <form method='post' style='display:inline'>
                                                <input type='hidden' name='action' value='delete_reservation'>
                                                <input type='hidden' name='reservation_id' value='" . htmlspecialchars($res['reservation_id']) . "'>
                                                <button type='submit' class='delete-link'>Delete</button>
                                            </form>
                                        </td>";
                                        echo "</tr>";
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching reservations: " . $e->getMessage());
                            echo "<tr><td colspan='12'>Error fetching reservations</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Reviews Section -->
        <section id="reviews" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Review Management</h2>
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer ID</th>
                            <th>Product ID</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Approved</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->query("SELECT * FROM review ORDER BY created_at DESC");
                        if ($stmt === false) {
                            error_log("Query failed: " . $conn->error);
                        } else {
                            while ($rev = $stmt->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($rev['review_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($rev['customer_id']) . "</td>";
                                echo "<td>" . htmlspecialchars($rev['product_id'] ?? 'N/A') . "</td>";
                                echo "<td>" . htmlspecialchars($rev['rating']) . "</td>";
                                echo "<td>" . htmlspecialchars($rev['comment']) . "</td>";
                                echo "<td>" . ($rev['approved'] ? 'Yes' : 'No') . "</td>";
                                echo "<td>" . htmlspecialchars($rev['created_at']) . "</td>";
                                echo "<td>";
                                if (!$rev['approved']) {
                                    echo "<form method='post' style='display:inline'>
                                        <input type='hidden' name='action' value='approve_review'>
                                        <input type='hidden' name='review_id' value='" . htmlspecialchars($rev['review_id']) . "'>
                                        <button type='submit'>Approve</button>
                                    </form>";
                                }
                                echo "<form method='post' style='display:inline'>
                                    <input type='hidden' name='action' value='delete_review'>
                                    <input type='hidden' name='review_id' value='" . htmlspecialchars($rev['review_id']) . "'>
                                    <button type='submit' class='delete-link'>Delete</button>
                                </form>
                            </td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Subscribers Section -->
        <section id="subscribers" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Subscriber Management</h2>
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email</th>
                            <th>Subscribed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->query("SELECT id, email, subscribed_at FROM subscribers ORDER BY subscribed_at DESC");
                            if ($stmt === false) {
                                error_log("Query failed: " . $conn->error);
                            } else {
                                if ($stmt->num_rows === 0) {
                                    echo "<tr><td colspan='3'>No subscribers found</td></tr>";
                                } else {
                                    while ($sub = $stmt->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($sub['id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($sub['email']) . "</td>";
                                        echo "<td>" . htmlspecialchars($sub['subscribed_at']) . "</td>";
                                        echo "</tr>";
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching subscribers: " . $e->getMessage());
                            echo "<tr><td colspan='3'>Error fetching subscribers</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Reports Section -->
        <section id="reports" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--text-dark);">Sales Reports</h2>
                <p>Today's Total Sales: $<?= number_format($todaySales, 2) ?></p>
                <a href="generate_report.php?type=daily" class="btn">Download Daily Report</a>
                <a href="generate_report.php?type=weekly" class="btn">Download Weekly Report</a>
                <a href="generate_report.php?type=monthly" class="btn">Download Monthly Report</a>
                <a href="generate_report.php?type=inventory" class="btn">Inventory Usage Report</a>
            </div>
        </section>
    </div>

    <!-- Footer -->
    <footer id="footer-section">
        <div id="footer-container">
            <div id="footer-grid">
                <div id="footer-about">
                    <div id="footer-logo">
                        <img src="https://cdn-icons-png.flaticon.com/512/924/924514.png" alt="Ravenhill Coffee Logo" id="footer-logo-image">
                        <span id="footer-logo-text">Ravenhill</span>
                    </div>
                    <p id="footer-about-text">Crafting exceptional coffee experiences since 2009. Ethically sourced, expertly roasted.</p>
                    <div id="footer-social">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                <div id="footer-links">
                    <h3 id="footer-links-title">Quick Links</h3>
                    <ul id="footer-links-list">
                        <li><a href="#staff-dashboard" class="footer-link">Dashboard</a></li>
                        <li><a href="#products" class="footer-link">Products</a></li>
                        <li><a href="#inventory" class="footer-link">Inventory</a></li>
                        <li><a href="#orders" class="footer-link">Orders</a></li>
                    </ul>
                </div>
                <div id="footer-contact">
                    <h3>Contact Us</h3>
                    <p>Email: hello@ravenhillcoffee.com</p>
                    <p>Phone: +61 3 1234 5678</p>
                </div>
            </div>
        </div>
    </footer>
    <style>
/* Staff Dashboard Styles */
/* Uniform Café Color Palette */
:root {
    --primary-brown: #3E1F1E;
    --secondary-beige: #C7A17A;
    --accent-gold: #E89C1A;
    --highlight-red: #8F1E1E;
    --background-cream: #FDFAF3;
    --text-dark: #3A1D1C;
    --text-light: #FEFEFE;
    --text-gray: #8C8C8C;
    --shadow-light: 0 6px 20px rgba(0, 0, 0, 0.08);
    --shadow-heavy: 0 12px 35px rgba(0, 0, 0, 0.15);
    --gradient-primary: linear-gradient(145deg, #3E1F1E, #5C3D3C);
    --gradient-accent: linear-gradient(145deg, #E89C1A, #D07A00);
    --gradient-hover: linear-gradient(145deg, #D07A00, #E89C1A);
    --border-radius: 20px;
    --transition-fast: all 0.2s ease;
    --transition-smooth: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}

/* General Styles */
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, var(--background-cream), #FFF5E1);
    margin: 0;
    overflow-x: hidden;
    color: var(--text-dark);
    line-height: 1.7;
    background-image: url('https://www.transparenttextures.com/patterns/paper-fibers.png');
    background-blend-mode: overlay;
}

/* Sidebar Styles */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 280px;
    background: var(--gradient-primary);
    padding: 40px 25px;
    box-sizing: border-box;
    overflow-y: auto;
    box-shadow: var(--shadow-heavy);
    transition: var(--transition-smooth);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar:hover {
    transform: translateX(0);
}

#logo-group {
    display: flex;
    align-items: center;
    margin-bottom: 50px;
    animation: fadeInLogo 1s ease-out;
}

@keyframes fadeInLogo {
    from { opacity: 0; transform: translateY(-30px); }
    to { opacity: 1; transform: translateY(0); }
}

#logo-group img {
    width: 70px;
    height: auto;
    margin-right: 20px;
    filter: drop-shadow(0 0 8px rgba(232, 156, 26, 0.6));
    transition: var(--transition-smooth);
}

#logo-group img:hover {
    transform: scale(1.15) rotate(10deg);
}

#logo-text {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    color: var(--text-light);
    text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.4);
}

.sidebar nav {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.sidebar nav a {
    color: var(--text-light);
    text-decoration: none;
    padding: 15px 25px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-radius: var(--border-radius);
    transition: var(--transition-smooth);
    font-weight: 500;
    position: relative;
    overflow: hidden;
}

.sidebar nav a::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.15);
    transition: var(--transition-smooth);
}

.sidebar nav a:hover::before {
    left: 0;
}

.sidebar nav a i {
    color: var(--accent-gold);
    transition: var(--transition-smooth);
}

.sidebar nav a:hover {
    color: var(--accent-gold);
    background: rgba(255, 255, 255, 0.15);
    transform: translateX(15px);
}

.sidebar nav a:hover i {
    transform: rotate(20deg) scale(1.2);
}

.sidebar nav a.active {
    background: var(--gradient-accent);
    color: var(--text-dark);
    font-weight: 700;
    box-shadow: var(--shadow-light);
}

/* Main Content */
.main-content {
    margin-left: 280px;
    min-height: 100vh;
    padding: 40px;
    background: url('https://www.transparenttextures.com/patterns/coffee.png'), var(--background-cream);
    background-blend-mode: overlay;
    transition: margin-left 0.3s ease;
}

.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--text-light);
    padding: 25px 35px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    margin-bottom: 25px;
    animation: fadeIn 0.6s ease-in-out;
    border: 1px solid rgba(232, 156, 26, 0.2);
}

.top-bar h1 {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    color: var(--primary-brown);
    text-transform: uppercase;
    letter-spacing: 3px;
    text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 25px;
}

.user-info span {
    color: var(--text-dark);
    font-weight: 600;
    font-size: 20px;
}

.logout-btn {
    background: var(--gradient-accent);
    color: var(--text-light);
    padding: 12px 25px;
    border-radius: 30px;
    text-decoration: none;
    transition: var(--transition-smooth);
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: var(--shadow-light);
}

.logout-btn:hover {
    background: var(--gradient-hover);
    transform: scale(1.08) rotate(2deg);
    box-shadow: var(--shadow-heavy);
}

.logout-btn i {
    transition: var(--transition-smooth);
}

.logout-btn:hover i {
    transform: rotate(360deg);
}

.welcome-bar {
    background: var(--gradient-primary);
    color: var(--text-light);
    padding: 30px;
    border-radius: var(--border-radius);
    text-align: center;
    margin-bottom: 35px;
    box-shadow: var(--shadow-heavy);
    border: 3px solid rgba(232, 156, 26, 0.4);
    animation: pulseWelcome 2s infinite alternate;
}

@keyframes pulseWelcome {
    from { box-shadow: var(--shadow-heavy); }
    to { box-shadow: 0 0 20px rgba(232, 156, 26, 0.5); }
}

.welcome-bar h2 {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    margin-bottom: 12px;
    text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.5);
}

.welcome-bar p {
    font-size: 18px;
    opacity: 0.95;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 35px;
}

.stat-box {
    background: var(--text-light);
    padding: 30px;
    border-radius: var(--border-radius);
    text-align: center;
    box-shadow: var(--shadow-light);
    transition: var(--transition-smooth);
    border: 2px solid rgba(232, 156, 26, 0.25);
    overflow: hidden;
    position: relative;
}

.stat-box::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(232, 156, 26, 0.1), transparent);
    transition: var(--transition-smooth);
}

.stat-box:hover::before {
    top: 0;
    left: 0;
}

.stat-box:hover {
    transform: translateY(-8px) rotate(1deg);
    box-shadow: var(--shadow-heavy);
}

.stat-box h3 {
    font-size: 42px;
    color: var(--primary-brown);
    margin-bottom: 12px;
    font-family: 'Playfair Display', serif;
    animation: pulse 1.8s infinite alternate;
}

.stat-box p {
    font-size: 18px;
    color: var(--text-gray);
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

/* Dashboard Bottom */
.dashboard-bottom {
    display: flex;
    gap: 35px;
    margin-bottom: 35px;
}

.recent-orders, .alerts {
    background: var(--text-light);
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    border: 2px solid rgba(232, 156, 26, 0.25);
    transition: var(--transition-smooth);
}

.recent-orders:hover, .alerts:hover {
    box-shadow: var(--shadow-heavy);
}

.recent-orders {
    flex: 3;
}

.recent-orders h3, .alerts h4 {
    font-size: 24px;
    margin-bottom: 18px;
    color: var(--primary-brown);
    font-family: 'Playfair Display', serif;
    border-bottom: 3px solid var(--accent-gold);
    padding-bottom: 8px;
    transition: var(--transition-fast);
}

.recent-orders table, .staff-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 12px;
    background: transparent;
}

.recent-orders th, .recent-orders td, .staff-table th, .staff-table td {
    padding: 18px;
    text-align: left;
    background: rgba(255, 255, 255, 0.95);
    border-radius: var(--border-radius);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.06);
    transition: var(--transition-smooth);
}

.recent-orders th, .staff-table th {
    background: var(--gradient-accent);
    color: var(--text-light);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}

.recent-orders td:hover, .staff-table td:hover {
    transform: scale(1.03);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
}

.alerts {
    flex: 1;
}

.low-stock, .system-status {
    margin-bottom: 25px;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    transition: var(--transition-smooth);
}

.low-stock {
    background: linear-gradient(135deg, #FFF3CD, #FFEAA7);
    border-left: 6px solid #D39E00;
}

.low-stock:hover {
    transform: scale(1.02);
}

.low-stock h4, .system-status h4 {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #856404;
}

.low-stock i {
    color: #D39E00;
    animation: iconPulse 1.5s infinite;
}

@keyframes iconPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.low-stock ul {
    list-style: none;
    padding: 0;
    margin: 12px 0 0;
}

.low-stock li {
    padding: 8px 12px;
    background: rgba(255, 245, 205, 0.6);
    border-radius: 8px;
    margin-bottom: 8px;
    transition: var(--transition-smooth);
}

.low-stock li:hover {
    background: #FFE699;
    transform: translateX(8px);
}

.system-status {
    background: linear-gradient(135deg, #D4EDDA, #C3E6CB);
    border-left: 6px solid #28A745;
}

.system-status:hover {
    transform: scale(1.02);
}

.system-status h4 {
    color: #155724;
}

.system-status i {
    color: #28A745;
    animation: iconPulse 1.5s infinite;
}

.system-status p {
    margin: 12px 0 0;
    color: #155724;
}

/* Staff Form and Tables */
.container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 0 25px;
}

.staff-form {
    display: flex;
    flex-direction: column;
    max-width: 650px;
    margin-bottom: 45px;
    gap: 18px;
    background: var(--text-light);
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    border: 2px solid rgba(232, 156, 26, 0.25);
    transition: var(--transition-smooth);
}

.staff-form:hover {
    box-shadow: var(--shadow-heavy);
}

.staff-form label {
    font-weight: 600;
    color: var(--text-dark);
}

.staff-form input, .staff-form textarea, .staff-form select {
    padding: 15px;
    border: 2px solid var(--secondary-beige);
    border-radius: 12px;
    font-family: 'Poppins', sans-serif;
    transition: var(--transition-smooth);
    background: rgba(255, 255, 255, 0.8);
}

.staff-form input:focus, .staff-form textarea:focus, .staff-form select:focus {
    border-color: var(--accent-gold);
    box-shadow: 0 0 15px rgba(232, 156, 26, 0.6);
    outline: none;
    background: white;
}

.staff-form button {
    background: var(--gradient-accent);
    color: var(--text-light);
    border: none;
    padding: 15px;
    cursor: pointer;
    border-radius: 30px;
    font-weight: 700;
    transition: var(--transition-smooth);
    box-shadow: var(--shadow-light);
}

.staff-form button:hover {
    background: var(--gradient-hover);
    transform: scale(1.08);
    box-shadow: var(--shadow-heavy);
}

.staff-table {
    width: 100%;
    background: transparent;
}

.staff-table th {
    background: var(--gradient-primary);
    color: var(--text-light);
}

.staff-table td {
    background: rgba(255, 255, 255, 0.85);
}

.delete-link {
    color: var(--highlight-red);
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 8px;
    transition: var(--transition-smooth);
    background: rgba(143, 30, 30, 0.1);
}

.delete-link:hover {
    background: rgba(143, 30, 30, 0.3);
    transform: scale(1.15);
    text-decoration: underline;
}

.btn {
    display: inline-block;
    padding: 15px 30px;
    background: var(--gradient-accent);
    color: var(--text-light);
    text-decoration: none;
    border-radius: 30px;
    margin: 8px;
    transition: var(--transition-smooth);
    font-weight: 700;
    box-shadow: var(--shadow-light);
}

.btn:hover {
    background: var(--gradient-hover);
    transform: scale(1.08) rotate(1deg);
    box-shadow: var(--shadow-heavy);
}

/* Footer */
#footer-section {
    background: var(--gradient-primary);
    padding: 50px 0;
    color: var(--text-light);
    border-top: 3px solid rgba(232, 156, 26, 0.4);
}

#footer-container {
    max-width: 1500px;
    margin: 0 auto;
    padding: 0 25px;
}

#footer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 35px;
}

#footer-about, #footer-links, #footer-contact {
    padding: 25px;
    border-radius: var(--border-radius);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: var(--shadow-light);
    transition: var(--transition-smooth);
}

#footer-about:hover, #footer-links:hover, #footer-contact:hover {
    box-shadow: var(--shadow-heavy);
}

#footer-logo img {
    width: 70px;
    filter: drop-shadow(0 0 8px rgba(232, 156, 26, 0.6));
}

#footer-logo-text {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    margin-left: 15px;
}

#footer-about-text {
    font-size: 16px;
    opacity: 0.85;
}

#footer-social a {
    color: var(--text-light);
    font-size: 24px;
    margin-right: 20px;
    transition: var(--transition-smooth);
}

#footer-social a:hover {
    color: var(--accent-gold);
    transform: scale(1.3) rotate(15deg);
}

#footer-links-title {
    font-size: 20px;
    margin-bottom: 18px;
}

#footer-links-list li {
    margin-bottom: 12px;
}

.footer-link {
    color: var(--text-light);
    text-decoration: none;
    transition: var(--transition-smooth);
}

.footer-link:hover {
    color: var(--accent-gold);
    padding-left: 10px;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .sidebar {
        transform: translateX(-280px);
        width: 280px;
    }
    .main-content {
        margin-left: 0;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .top-bar {
        flex-direction: column;
        gap: 20px;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .dashboard-bottom {
        flex-direction: column;
    }
    .staff-form {
        max-width: 100%;
    }
}
</style>
<script src="script.js"></script>
</body>
</html>