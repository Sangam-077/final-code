<?php
// checkout.php - Handles the checkout process for customer orders
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();
include 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

// Fetch cart from session
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header('Location: menu.php');
    exit;
}

// Ensure user exists in customer table
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT customer_id FROM customer WHERE customer_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User doesn't exist in customer table, insert them
    $stmt = $conn->prepare("INSERT INTO customer (customer_id, loyalty_points) VALUES (?, 0)");
    $stmt->bind_param("s", $user_id);
    if (!$stmt->execute()) {
        error_log("Failed to create customer record: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
        exit;
    }
    $stmt->close();
}

// Calculate subtotal, discount, shipping, total
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $product = getProductDetails($conn, $item['product_id']);
    if ($product) {
        $subtotal += $product['price'] * $item['quantity'];
    }
}
$shipping = (($_SESSION['shipping'] ?? 'pickup') === 'delivery') ? 5.00 : 0.00;
$discount = isset($_SESSION['promo_discount']) ? ($subtotal + $shipping) * ($_SESSION['promo_discount'] / 100) : 0;
$total = $subtotal + $shipping - $discount;

// Fetch user details for pre-fill
$stmt = $conn->prepare("SELECT name, email, address FROM users WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission (AJAX will trigger this)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_type = $_POST['shipping'] ?? 'pickup';
    $order_address = ($order_type === 'delivery') ? ($_POST['address'] ?? '') : '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $card_number = ($payment_method === 'card') ? ($_POST['card_number'] ?? '') : '';
    $card_expiry = ($payment_method === 'card') ? ($_POST['card_expiry'] ?? '') : '';
    $card_cvv = ($payment_method === 'card') ? ($_POST['card_cvv'] ?? '') : '';

    // Validation
    if ($order_type === 'delivery' && empty($order_address)) {
        echo json_encode(['success' => false, 'message' => 'Address is required for delivery.']);
        exit;
    } elseif ($payment_method === 'card' && (empty($card_number) || !preg_match('/^\d{16}$/', $card_number) || !preg_match('/^\d{2}\/\d{2}$/', $card_expiry) || !preg_match('/^\d{3}$/', $card_cvv))) {
        echo json_encode(['success' => false, 'message' => 'Invalid card details. Please ensure a 16-digit card number, MM/YY expiry, and 3-digit CVV.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // Generate order_id
        $order_id = 'ORD-' . strtoupper(uniqid());

        // Insert order
        $stmt = $conn->prepare("INSERT INTO orders (order_id, customer_id, staff_id, cashier_id, total_price, order_time, order_type, order_address, promotion_id) VALUES (?, ?, NULL, NULL, ?, NOW(), ?, ?, NULL)");
        $stmt->bind_param("ssdss", $order_id, $user_id, $total, $order_type, $order_address);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert order: " . $stmt->error);
        }
        $stmt->close();

        // Insert order_items
        foreach ($_SESSION['cart'] as $item) {
            $order_item_id = 'OI-' . uniqid();
            $stmt = $conn->prepare("INSERT INTO order_item (order_item_id, order_id, product_id, quantity, unit_price, customisations) VALUES (?, ?, ?, ?, ?, ?)");
            $unit_price = getProductDetails($conn, $item['product_id'])['price'];
            $allergens = implode(',', $item['allergens_to_avoid'] ?? []);
            $customisations = ($item['notes'] ?? '') . (!empty($allergens) ? '; Allergens to avoid: ' . $allergens : '');
            $stmt->bind_param("ssiiis", $order_item_id, $order_id, $item['product_id'], $item['quantity'], $unit_price, $customisations);
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert order item: " . $stmt->error);
            }
            $stmt->close();
        }

        // Insert payment
        $payment_id = 'PAY-' . uniqid();
        $payment_status = ($payment_method === 'cash') ? 'Pending' : 'Paid';
        $stmt = $conn->prepare("INSERT INTO payment (payment_id, order_id, amount, method, payment_status, payment_time) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssdss", $payment_id, $order_id, $total, $payment_method, $payment_status);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert payment: " . $stmt->error);
        }
        $stmt->close();

        // Update inventory with stock check
        foreach ($_SESSION['cart'] as $item) {
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = stock_level - ? WHERE product_id = ? AND stock_level >= ?");
            $stmt->bind_param("iii", $item['quantity'], $item['product_id'], $item['quantity']);
            if ($stmt->execute() === false || $stmt->affected_rows === 0) {
                throw new Exception("Insufficient stock for product ID: " . $item['product_id']);
            }
            $stmt->close();
        }

        // Add loyalty points
        $points = floor($total / 10);
        if ($points > 0) {
            $loyalty_id = 'LOY-' . uniqid();
            $stmt = $conn->prepare("INSERT INTO loyalty_program (loyalty_id, customer_id, order_id, points, description, created_at) VALUES (?, ?, ?, ?, 'Points from order', NOW())");
            $stmt->bind_param("ssis", $loyalty_id, $user_id, $order_id, $points);
            if (!$stmt->execute()) {
                error_log("Failed to insert loyalty points: " . $stmt->error);
                // Continue with order even if loyalty points fail
            }
            $stmt->close();
        }

        // Notification
        $notif_id = 'NOTIF-' . uniqid();
        $content = "Your order {$order_id} has been placed. Status: Pending";
        $stmt = $conn->prepare("INSERT INTO notification (notification_id, user_id, order_id, notif_type, content, sent_time, is_read) VALUES (?, ?, ?, 'order', ?, NOW(), 0)");
        $stmt->bind_param("ssss", $notif_id, $user_id, $order_id, $content);
        if (!$stmt->execute()) {
            error_log("Failed to insert notification: " . $stmt->error);
            // Continue with order even if notification fails
        }
        $stmt->close();

        $conn->commit();

        // Debug: Check if order was inserted
        $check_stmt = $conn->prepare("SELECT * FROM orders WHERE order_id = ?");
        $check_stmt->bind_param("s", $order_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        error_log("Order inserted check: " . $check_result->num_rows . " rows found for order_id: " . $order_id);
        $check_stmt->close();

        // Clear cart
        unset($_SESSION['cart'], $_SESSION['shipping'], $_SESSION['promo_code'], $_SESSION['promo_discount']);

        echo json_encode(['success' => true, 'order_id' => $order_id, 'message' => 'Order placed successfully!']);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Checkout transaction failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        exit;
    }
}

// Function getProductDetails
function getProductDetails($conn, $product_id) {
    $stmt = $conn->prepare("SELECT * FROM product WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Ravenhill Coffee House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body class="checkout-page">

    <!-- Navigation Bar -->
    <header id="main-header">
  <div id="header-container">
    <div id="nav-content">
      <div id="logo-group">
        <img src="https://cdn-icons-png.flaticon.com/512/924/924514.png" alt="Ravenhill Coffee Logo" id="logo-image">
        <span id="logo-text">Ravenhill Coffee House</span>
      </div>
      <button id="mobile-menu-btn">
        <i class="fas fa-bars" id="menu-icon"></i>
      </button>
      <nav id="main-nav">
        <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">Home</a>
        <a href="menu.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'menu.php' ? 'active' : ''; ?>">Menu</a>
        <a href="about.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>">About Us</a>
        <a href="contact.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">Contact</a>
      </nav>
      <div id="nav-actions">
        <a href="#" class="icon-link search-icon"><i class="fas fa-search"></i></a>
        <a href="#" id="wishlist-btn" class="icon-link">
          <i class="far fa-heart"></i>
          <?php if (isset($_SESSION['wishlist']) && count($_SESSION['wishlist']) > 0): ?>
            <span class="count-badge" id="wishlist-count"><?= count($_SESSION['wishlist']) ?></span>
          <?php endif; ?>
        </a>
        <a href="#" id="cart-btn" class="icon-link">
          <i class="fas fa-shopping-cart"></i>
          <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
            <span class="count-badge" id="cart-count"><?= count($_SESSION['cart']) ?></span>
          <?php endif; ?>
        </a>
        <div class="profile-dropdown">
          <button id="profile-btn" class="profile-btn">
            <?php echo isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['username'] ?? 'Profile') : 'Login/Register'; ?>
            <i class="fas fa-caret-down"></i>
          </button>
          <div id="account-menu" class="dropdown-menu">
            <?php if (isset($_SESSION['user_id'])): ?>
              <a href="profile.php" class="account-link">My Profile</a>
              <a href="orders.php" class="account-link">My Orders</a>
              <a href="logout.php" class="account-link">Logout</a>
            <?php else: ?>
              <a href="login.php" class="account-link">Login</a>
              <a href="register.php" class="account-link">Register</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</header>

    <!-- Checkout Section -->
    <section id="checkout-section">
        <div class="checkout-container">
            <h1 class="checkout-title">Checkout</h1>
            <div class="progress-bar">
                <div class="step active">Cart</div>
                <div class="step active">Shipping</div>
                <div class="step active">Payment</div>
                <div class="step">Confirmation</div>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <form id="checkout-form" method="POST">
                <!-- Order Summary -->
                <div class="summary-section">
                    <h2>Order Summary</h2>
                    <?php foreach ($_SESSION['cart'] as $item): 
                        $product = getProductDetails($conn, $item['product_id']);
                    ?>
                        <div class="summary-item">
                            <span><?php echo htmlspecialchars($product['name']); ?> x <?php echo $item['quantity']; ?></span>
                            <span>$<?php echo number_format($product['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-total">
                        <span>Subtotal</span>
                        <span>$<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Shipping</span>
                        <span>$<?php echo number_format($shipping, 2); ?></span>
                    </div>
                    <div class="summary-total">
                        <span>Discount</span>
                        <span>-$<?php echo number_format($discount, 2); ?></span>
                    </div>
                    <div class="summary-grand-total">
                        <span>Grand Total</span>
                        <span>$<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>

                <!-- Shipping Options -->
                <div class="shipping-section">
                    <h2>Shipping Method</h2>
                    <select name="shipping" id="shipping-select">
                        <option value="pickup" <?php echo ($shipping == 0) ? 'selected' : ''; ?>>Pickup (Free)</option>
                        <option value="delivery" <?php echo ($shipping == 5) ? 'selected' : ''; ?>>Delivery ($5.00)</option>
                    </select>
                    <div id="delivery-address" style="display: <?php echo ($shipping == 5) ? 'block' : 'none'; ?>;">
                        <label for="address">Delivery Address</label>
                        <textarea name="address" id="address" <?php echo ($shipping == 0) ? '' : 'required'; ?>><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Payment Options -->
                <div class="payment-section">
                    <h2>Payment Method</h2>
                    <select name="payment_method" id="payment-method">
                        <option value="cash">Cash on Delivery/Pickup</option>
                        <option value="card">Credit/Debit Card</option>
                    </select>
                    <div id="card-details" style="display: none;">
                        <label for="card_number">Card Number</label>
                        <input type="text" name="card_number" id="card_number" placeholder="1234 5678 9012 3456">
                        <label for="card_expiry">Expiry Date</label>
                        <input type="text" name="card_expiry" id="card_expiry" placeholder="MM/YY">
                        <label for="card_cvv">CVV</label>
                        <input type="text" name="card_cvv" id="card_cvv" placeholder="123">
                    </div>
                    <div class="trust-badges">
                        <i class="fas fa-lock"></i> Secure Payment
                    </div>
                </div>

                <button type="submit" class="place-order-btn" id="place-order-btn">Place Order</button>
            </form>
        </div>
    </section>

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
          <li><a href="#hero-section" class="footer-link">Home</a></li>
          <li><a href="#featured-section" class="footer-link">Menu</a></li>
          <li><a href="#about-section" class="footer-link">About Us</a></li>
          <li><a href="#contact-section" class="footer-link">Contact</a></li>
        </ul>
      </div>
      <div id="footer-contact">
        <h3 id="footer-contact-title">Contact Us</h3>
        <ul id="footer-contact-list">
          <li class="contact-item">
            <i class="fas fa-map-marker-alt"></i>
            <a href="https://www.google.com/maps/search/?api=1&query=-37.8182,144.9594" class="footer-link" target="_blank">Prahran Market, Melbourne</a>
          </li>
          <li class="contact-item">
            <i class="fas fa-phone-alt"></i>
            <a href="tel:+6121234567" class="footer-link">(02) 123-4567</a>
          </li>
          <li class="contact-item">
            <i class="fas fa-envelope"></i>
            <a href="mailto:hello@ravenhillcoffee.com" class="footer-link">hello@ravenhillcoffee.com</a>
          </li>
        </ul>
      </div>
      <div id="footer-hours">
        <h3 id="footer-hours-title">Opening Hours</h3>
        <ul id="footer-hours-list">
          <li class="hours-item">
            <span>Monday - Friday</span>
            <span>7:00 AM - 8:00 PM</span>
          </li>
          <li class="hours-item">
            <span>Saturday</span>
            <span>8:00 AM - 9:00 PM</span>
          </li>
          <li class="hours-item">
            <span>Sunday</span>
            <span>8:00 AM - 6:00 PM</span>
          </li>
        </ul>
      </div>
    </div>
    <div id="footer-bottom">
      <p>&copy; 2025 Ravenhill Coffee House. All rights reserved.</p>
    </div>
  </div>
</footer>

    <script src="script.js"></script>
</body>
</html>