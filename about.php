<?php
// about.php - About page without hero section
session_start();
include 'db_connect.php';

// Initialize variables
$username = '';
$isLoggedIn = isset($_SESSION['user_id']);
$wishlistCount = 0;
$cartCount = 0;

// Verify database connection
if (!$conn) {
    error_log("Database connection failed in about.php");
    $username = 'Guest'; // Fallback for display
} else {
    // Ensure MySQLi error reporting is enabled
    $conn->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;

    // Fetch user info if logged in
    if ($isLoggedIn) {
        try {
            $stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
            if ($stmt === false) {
                error_log("Prepare failed for user query: " . $conn->error);
                $username = 'User'; // Fallback
            } else {
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $username = $user['name'] ?? 'User';
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching user: " . $e->getMessage());
            $username = 'User'; // Fallback
        }

        // Fetch wishlist count
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM wishlist WHERE user_id = ?");
            if ($stmt === false) {
                error_log("Prepare failed for wishlist query: " . $conn->error);
            } else {
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $wishlistCount = $result->fetch_assoc()['count'] ?? 0;
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching wishlist count: " . $e->getMessage());
        }

        // Fetch cart count
        try {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
            if ($stmt === false) {
                error_log("Prepare failed for cart query: " . $conn->error);
            } else {
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $cartCount = $result->fetch_assoc()['count'] ?? 0;
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching cart count: " . $e->getMessage());
        }
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
            <span class="count-badge" id="wishlist-count"><?php echo $wishlistCount; ?></span>
          <?php endif; ?>
        </a>
        <a href="#" id="cart-btn" class="icon-link">
          <i class="fas fa-shopping-cart"></i>
          <?php if ($cartCount > 0): ?>
            <span class="count-badge" id="cart-count"><?php echo $cartCount; ?></span>
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

  <!-- Welcome to Ravenhill Coffee House Section -->
  <section id="welcome-section">
    <div id="welcome-container">
      <div id="welcome-content">
        <h2 id="welcome-title">Welcome to Ravenhill Coffee House</h2>
        <p class="welcome-text">Founded in 2009 by Fleur Studd and Jason Scheltus, Ravenhill Coffee House began at Prahran Market with a mission to redefine specialty coffee in Melbourne.</p>
        <p class="welcome-text">We source traceable, high-quality beans through direct trade partnerships, ensuring sustainability and fairness in every cup we serve.</p>
        <p class="welcome-text">Now in Melbourne CBD, our cozy cafés are community hubs where coffee lovers enjoy workshops, tastings, and exceptional brews.</p>
      </div>
      <div id="welcome-image-container">
        <img src="Images/interior.png" id="welcome-image">
      </div>
    </div>
  </section>

  <!-- Timeline Section -->
  <section id="timeline-section">
    <div id="timeline-container">
      <h2 id="timeline-title">Our Journey Through Time</h2>
      <div class="timeline">
        <div class="timeline-line"></div>
        <div class="timeline-item" data-year="2009">
          <div>
            <h3>Founded Ravenhill</h3>
            <p>Fleur Studd and Jason Scheltus founded Ravenhill Coffee House in Melbourne, with a mission to bring fresh, traceable, high-quality coffee to the city.</p>
          </div>
        </div>
        <div class="timeline-item" data-year="2010">
          <div>
            <h3>Prahran Market Opens</h3>
            <p>Opened our first shop and roastery at the vibrant Prahran Market, connecting with a community passionate about quality and sustainability.</p>
          </div>
        </div>
        <div class="timeline-item" data-year="2015">
          <div>
            <h3>Direct Farmer Partnerships</h3>
            <p>Established direct trade relationships with coffee farmers worldwide, ensuring fair practices and premium beans.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Team Section -->
  <section id="team-section">
    <div class="team-container">
      <h2 class="team-title">Meet Our Team</h2>
      <div class="team-grid">
        <div class="team-card">
          <img src="https://randomuser.me/api/portraits/women/68.jpg" alt="Fleur Studd">
          <div>
            <h3>Fleur Studd</h3>
            <p>Co-Founder & CEO</p>
            <p>Passionate about sustainable sourcing and community building.</p>
          </div>
        </div>
        <div class="team-card">
          <img src="https://randomuser.me/api/portraits/men/43.jpg" alt="Jason Scheltus">
          <div>
            <h3>Jason Scheltus</h3>
            <p>Co-Founder & Head Roaster</p>
            <p>Expert in coffee roasting with over 20 years of experience.</p>
          </div>
        </div>
        <div class="team-card">
          <img src="Images/sangam.jpg" alt="Sangam GC">
          <div>
            <h3>Sangam GC</h3>
            <p>Full Stack Developer</p>
            <p>Skilled in building robust and scalable web applications.</p>
          </div>
        </div>
        <div class="team-card">
          <img src="https://media-syd2-1.cdn.whatsapp.net/v/t61.24694-24/543989817_1359037632899086_144186471661813051_n.jpg?ccb=11-4&oh=01_Q5Aa2gHE6jJyIswdWFkyxdqRuuPcWHQ3kXhuGeE0zoU6DXSIQw&oe=68E234DD&_nc_sid=5e03e0&_nc_cat=106" alt="Vishal Kunwar">
          <div>
            <h3>Vishal Kunwar</h3>
            <p>Frontend Developer</p>
            <p>Creates intuitive and engaging user interfaces.</p>
          </div>
        </div>
        <div class="team-card">
          <img src="https://media-syd2-1.cdn.whatsapp.net/v/t61.24694-24/475795574_2299193013782957_4159012016487775836_n.jpg?ccb=11-4&oh=01_Q5Aa2gFu6ZXHvAbRAU4gTnCel_Pm__D3F1vRKJrp-YuC1nWp4Q&oe=68E252D5&_nc_sid=5e03e0&_nc_cat=107" alt="Aakriti B.C.">
          <div>
            <h3>Aakriti B.C.</h3>
            <p>UI/UX Designer</p>
            <p>Designs user-friendly and visually appealing experiences.</p>
          </div>
        </div>
        <div class="team-card">
          <img src="Images/bishal.jpg" alt="Bishal Kadel">
          <div>
            <h3>Bishal Kadel</h3>
            <p>Backend Developer</p>
            <p>Ensures seamless server-side functionality and performance.</p>
          </div>
        </div>
        <div class="team-card">
          <img src="Images/binay.jpg" alt="Binay Chaudhary">
          <div>
            <h3>Binay Chaudhary</h3>
            <p>Project Manager</p>
            <p>Guides the team to deliver projects on time and with excellence.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Values Section -->
  <section id="values-section">
    <div class="values-container">
      <h2 class="values-title">Our Core Values</h2>
      <div class="values-grid">
        <div class="value-card">
          <i class="fas fa-leaf value-icon"></i>
          <h3>Sustainability</h3>
          <p>Committed to eco-friendly practices and ethical sourcing for a better planet.</p>
        </div>
        <div class="value-card">
          <i class="fas fa-coffee value-icon"></i>
          <h3>Quality</h3>
          <p>Delivering exceptional coffee through meticulous roasting and brewing.</p>
        </div>
        <div class="value-card">
          <i class="fas fa-users value-icon"></i>
          <h3>Community</h3>
          <p>Building connections through shared love for coffee and local engagement.</p>
        </div>
      </div>
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
        <a href="contact.php" class="cta-button">Get in Touch</a>
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