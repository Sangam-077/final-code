<?php
// orders.php - Customer order history and tracking page
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

session_start();
include 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=orders.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Debug: Log user ID
error_log("Fetching orders for user_id: " . $user_id);

// Fetch all orders for user
$stmt = $conn->prepare("
    SELECT o.*, p.payment_status AS status 
    FROM orders o 
    LEFT JOIN payment p ON o.order_id = p.order_id 
    WHERE o.customer_id = ? 
    ORDER BY o.order_time DESC
");
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    die("Database error. Please try again later.");
}
$stmt->bind_param("s", $user_id);
if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die("Database error. Please try again later.");
}
$result = $stmt->get_result();
error_log("Found " . $result->num_rows . " orders for user");
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// If specific order_id, focus on it
$order_id = $_GET['order_id'] ?? null;
$success = isset($_GET['success']);
$focused_order = null;

if ($order_id) {
    foreach ($orders as $order) {
        if ($order['order_id'] === $order_id) {
            $focused_order = $order;
            break;
        }
    }
    if ($focused_order) {
        // Fetch order items
        $stmt = $conn->prepare("SELECT oi.*, p.name FROM order_item oi JOIN product p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
        $stmt->bind_param("s", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $focused_order['items'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Order not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Ravenhill Coffee House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
</head>
<body class="orders-page">
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

    <!-- Orders Section -->
    <section id="orders-section">
        <div class="orders-container">
            <h1 class="orders-title">My Orders</h1>

            <?php if ($success): ?>
                <div class="success-message">Order placed successfully! Track your order below.</div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div id="loading" style="display: none; text-align: center; padding: 20px;">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>

            <?php if ($focused_order): ?>
                <div class="order-details">
                    <h2>Order #<?php echo htmlspecialchars($focused_order['order_id']); ?></h2>
                    <p>Status: <span class="status-<?php echo strtolower($focused_order['status'] ?? 'pending'); ?>"><?php echo htmlspecialchars($focused_order['status'] ?? 'Pending'); ?></span></p>
                    <p>Date: <?php echo date('F j, Y g:i A', strtotime($focused_order['order_time'] ?? 'now')); ?></p>
                    <p>Type: <?php echo ucfirst($focused_order['order_type'] ?? 'Unknown'); ?></p>
                    <?php if (isset($focused_order['order_type']) && $focused_order['order_type'] === 'delivery'): ?>
                        <p>Address: <?php echo htmlspecialchars($focused_order['order_address'] ?? 'N/A'); ?></p>
                    <?php endif; ?>
                    <p>Total: $<?php echo number_format($focused_order['total_price'] ?? 0, 2); ?></p>
                    <h3>Items</h3>
                    <ul>
                        <?php if (empty($focused_order['items'])): ?>
                            <li>No items found for this order.</li>
                        <?php else: ?>
                            <?php foreach ($focused_order['items'] as $item): ?>
                                <li>
                                    <?php echo htmlspecialchars($item['name'] ?? 'Unknown'); ?> x <?php echo $item['quantity'] ?? 0; ?> - $<?php echo number_format(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0), 2); ?>
                                    <?php if (!empty($item['customisations'])): ?>
                                        <br><small>Customisations: <?php echo htmlspecialchars($item['customisations']); ?></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <a href="orders.php" class="back-btn">Back to Orders</a>
                </div>
            <?php else: ?>
                <?php if (empty($orders)): ?>
                    <p class="empty-message">You have no orders yet.</p>
                <?php else: ?>
                    <div class="orders-list">
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <h3>Order #<?php echo htmlspecialchars($order['order_id']); ?></h3>
                                <p>Status: <span class="status-<?php echo strtolower($order['status'] ?? 'pending'); ?>"><?php echo htmlspecialchars($order['status'] ?? 'Pending'); ?></span></p>
                                <p>Date: <?php echo date('F j, Y', strtotime($order['order_time'] ?? 'now')); ?></p>
                                <p>Total: $<?php echo number_format($order['total_price'] ?? 0, 2); ?></p>
                                <a href="orders.php?order_id=<?php echo urlencode($order['order_id']); ?>" class="view-details">View Details</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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