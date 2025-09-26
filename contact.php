<?php
// contact.php - Contact page without hero section
session_start();
include 'db_connect.php';

// Fetch user info if logged in
$username = '';
$isLoggedIn = isset($_SESSION['user_id']);
if ($isLoggedIn) {
    try {
        $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $username = $user['name'] ?? 'User';
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
    }
}

// Initialize cart and wishlist sessions if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Calculate cart and wishlist counts for display
$cartCount = count($_SESSION['cart']);
$wishlistCount = count($_SESSION['wishlist']);

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

    if ($name && $email && $subject && $message) {
        $content = "Contact Form Submission:\nName: $name\nEmail: $email\nSubject: $subject\nMessage: $message";
        $notification_id = 'NOT-' . strtoupper(uniqid());
        // Get a recipient, e.g., first staff
        $recip_stmt = $conn->query("SELECT user_id FROM users WHERE role = 'staff' LIMIT 1");
        if ($recip_stmt && $row = $recip_stmt->fetch_assoc()) {
            $recipient_id = $row['user_id'];
        } else {
            error_log("No staff found for notification");
            echo json_encode(['status' => 'error', 'message' => 'No recipient available. Please try later.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO notification (notification_id, user_id, notif_type, content, sent_time, is_read) VALUES (?, ?, 'message', ?, NOW(), 0)");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['status' => 'error', 'message' => 'Database prepare error. Please try again.']);
            exit;
        }
        $stmt->bind_param("sss", $notification_id, $recipient_id, $content);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['status' => 'error', 'message' => 'Database execute error. Please try again.']);
            exit;
        }
        echo json_encode(['status' => 'success', 'message' => 'Message sent successfully!']);
        exit;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ravenhill Coffee House</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
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
          <?php if ($wishlistCount > 0): ?>
            <span class="count-badge" id="wishlist-count"><?= $wishlistCount ?></span>
          <?php endif; ?>
        </a>
        <a href="#" id="cart-btn" class="icon-link">
          <i class="fas fa-shopping-cart"></i>
          <?php if ($cartCount > 0): ?>
            <span class="count-badge" id="cart-count"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>
        <div class="profile-dropdown">
          <button id="profile-btn" class="profile-btn">
            <?php echo $isLoggedIn ? htmlspecialchars($username) : 'Login/Register'; ?>
            <i class="fas fa-caret-down"></i>
          </button>
          <div id="account-menu" class="dropdown-menu">
            <?php if ($isLoggedIn): ?>
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

  <!-- Connect with Us Section -->
  <section id="connect-section">
    <div id="connect-container">
      <div id="connect-content">
        <h2 id="connect-title">Connect with Ravenhill Coffee House</h2>
        <p class="connect-text">Our passion for exceptional coffee extends to our commitment to community. Whether you have a question, feedback, or want to learn more about our sustainable practices, we’d love to hear from you.</p>
        <ul id="connect-info">
          <li class="connect-item">
            <i class="fas fa-map-marker-alt"></i>
            <a href="https://www.google.com/maps/search/?api=1&query=-37.8182,144.9594" class="connect-link" target="_blank">Prahran Market, 163 Commercial Rd, South Yarra VIC 3141</a>
          </li>
          <li class="connect-item">
            <i class="fas fa-phone-alt"></i>
            <a href="tel:+61312345678" class="connect-link">(02) 123-4567</a>
          </li>
          <li class="connect-item">
            <i class="fas fa-envelope"></i>
            <a href="mailto:hello@ravenhillcoffee.com" class="connect-link" target="_blank">hello@ravenhillcoffee.com</a>
          </li>
        </ul>
      </div>
      <div id="connect-image-container">
        <img src="Images/background1.jpg" alt="Ravenhill Coffee House counter" id="connect-image">
      </div>
    </div>
  </section>

  <!-- Contact Form Section -->
  <section id="contact-form-section">
    <div id="contact-form-container">
      <h2 id="contact-form-title">Send Us a Message</h2>
      <p id="contact-form-text">Have a question or feedback? Fill out the form below, and we’ll get back to you as soon as possible.</p>
      <form id="contact-form">
        <div class="form-group">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" placeholder="Your Name" required>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Your Email" required>
        </div>
        <div class="form-group">
          <label for="subject">Subject</label>
          <input type="text" id="subject" name="subject" placeholder="Subject" required>
        </div>
        <div class="form-group">
          <label for="message">Message</label>
          <textarea id="message" name="message" placeholder="Your Message" rows="5" required></textarea>
        </div>
        <button type="submit" id="submit-btn">Send Message</button>
      </form>
    </div>
  </section>

  <!-- CTA Section -->
  <section id="cta-section">
    <div class="cta-container">
      <div class="coffee-bean">
        <i class="fas fa-coffee"></i>
      </div>
      <h2 class="cta-title">Join Our Coffee Journey</h2>
      <p class="cta-subtitle">Visit us at Prahran Market or Melbourne CBD to experience coffee crafted with care.</p>
      <div class="cta-buttons">
        <a href="menu.php" class="cta-button">Explore Menu</a>
        <a href="about.php" class="cta-button">Learn About Us</a>
      </div>
    </div>
  </section>

  <!-- Cart Modal -->
  <div id="cart-modal" class="modal">
    <div class="modal-content">
      <!-- Cart content will be loaded dynamically via JS -->
    </div>
  </div>

  <!-- Wishlist Modal -->
  <div id="wishlist-modal" class="modal">
    <div class="modal-content">
      <!-- Wishlist content will be loaded dynamically via JS -->
    </div>
  </div>

  <!-- Footer Section -->
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
            <li><a href="index.php" class="footer-link">Home</a></li>
            <li><a href="menu.php" class="footer-link">Menu</a></li>
            <li><a href="about.php" class="footer-link">About Us</a></li>
            <li><a href="contact.php" class="footer-link">Contact</a></li>
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