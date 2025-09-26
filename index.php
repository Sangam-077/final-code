<?php
// index.php with added POST handlers for review and subscribe
session_start();
include 'db_connect.php';

// Check database connection
if (!$conn || $conn->connect_error) {
    error_log("Database connection failed in index.php: " . ($conn ? $conn->connect_error : 'No connection object'));
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Create subscribers table if not exists
$create_table = $conn->query("CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
if ($create_table === false) {
    error_log("Failed to create subscribers table: " . $conn->error);
}

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

// Handle POST requests for review and subscribe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    error_log("POST request received with action: $action");

    if ($action === 'submit_review') {
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        error_log("Review submission: rating=$rating, comment=" . (empty($comment) ? 'empty' : 'valid'));

        if ($rating < 1 || $rating > 5 || empty($comment)) {
            error_log("Review validation failed: rating=$rating, comment=" . (empty($comment) ? 'empty' : 'valid'));
            echo json_encode(['status' => 'error', 'message' => 'Invalid review data']);
            exit;
        }

        if (!$isLoggedIn) {
            error_log("Review submission denied: User not logged in");
            echo json_encode(['status' => 'error', 'message' => 'Please log in to submit a review']);
            exit;
        }

        try {
            $customer_id = $_SESSION['user_id'];

            // Check if customer_id exists in customer table, insert if not
            $check_stmt = $conn->prepare("SELECT customer_id FROM customer WHERE customer_id = ?");
            $check_stmt->bind_param("s", $customer_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            if ($result->num_rows === 0) {
                $insert_stmt = $conn->prepare("INSERT INTO customer (customer_id, loyalty_points) VALUES (?, 0)");
                $insert_stmt->bind_param("s", $customer_id);
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to insert customer: " . $insert_stmt->error);
                }
                $insert_stmt->close();
                error_log("Created customer entry for: $customer_id");
            }
            $check_stmt->close();

            $review_id = uniqid('rev_');
            $product_id = NULL; // No product_id collected yet, set to NULL
            $approved = 0; // Default to unapproved
            $stmt = $conn->prepare("INSERT INTO review (review_id, customer_id, product_id, rating, comment, approved, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssisi", $review_id, $customer_id, $product_id, $rating, $comment, $approved);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
            error_log("Review submitted successfully: review_id=$review_id, customer_id=$customer_id");
            echo json_encode(['status' => 'success', 'message' => 'Review submitted successfully']);
        } catch (Exception $e) {
            error_log("Review submission error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to submit review: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'subscribe') {
        $email = trim($_POST['email'] ?? '');

        error_log("Subscribe attempt: email=$email");

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Subscribe failed: Invalid email - $email");
            echo json_encode(['status' => 'error', 'message' => 'Invalid email']);
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO subscribers (email) VALUES (?)");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("s", $email);
            if ($stmt->execute()) {
                $stmt->close();
                error_log("Subscribed successfully: $email");
                echo json_encode(['status' => 'success', 'message' => 'Subscribed successfully']);
            } else {
                if ($conn->errno === 1062) { // Duplicate entry
                    error_log("Subscribe failed: Email already subscribed - $email");
                    echo json_encode(['status' => 'error', 'message' => 'You are already subscribed']);
                } else {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
            }
        } catch (Exception $e) {
            error_log("Subscribe error: " . $e->getMessage());
            echo json_encode(['status' => 'error', 'message' => 'Failed to subscribe: ' . $e->getMessage()]);
        }
        exit;
    }

    error_log("Unknown POST action: $action");
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
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

// Fetch featured items from the database
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, COALESCE(i.stock_level, 0) as stock
        FROM product p 
        JOIN category c ON p.category_id = c.category_id 
        LEFT JOIN inventory i ON p.product_id = i.product_id
        WHERE p.featured = 1
        ORDER BY p.name
    ");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $featuredItems = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching featured items: " . $e->getMessage());
    $featuredItems = [];
}

// Fetch recent reviews from the database
try {
    $stmt = $conn->prepare("SELECT * FROM review ORDER BY created_at DESC LIMIT 3");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Process reviews: add name and random image
    foreach ($reviews as &$review) {
        if (is_numeric($review['customer_id'])) {
            $userStmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
            if ($userStmt) {
                $userStmt->bind_param("i", $review['customer_id']);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $user = $userResult->fetch_assoc();
                $review['name'] = $user['name'] ?? 'User';
                $userStmt->close();
            } else {
                $review['name'] = 'User';
            }
        } else {
            $review['name'] = 'Guest';
        }
        $gender = rand(0, 1) ? 'men' : 'women';
        $num = rand(1, 99);
        $review['image'] = "https://randomuser.me/api/portraits/$gender/$num.jpg";
    }
    unset($review);
} catch (Exception $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    $reviews = [];
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

   <!-- Hero Section -->
  <section id="hero-section">
    <div id="hero-container">
      <div id="hero-content">
        <h1 id="hero-title">Best Coffee, <span id="hero-highlight">Make Your Day Great</span></h1>
        <p id="hero-text">Welcome to our coffee paradise, where every bean tells a story and every cup sparks joy.</p>
        <div id="hero-buttons">
          <a id="order-now-btn" href="menu.php">Order Now</a>
          <a id="book-table-btn" href="book_table.php">Book Table</a>
        </div>
      </div>
      <div id="hero-image-container">
        <img src="https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80" alt="Coffee pouring scene" id="hero-image">
      </div>
    </div>
  </section>

  <!-- About Us Section -->
  <section id="about-us">
    <div id="about-us-container">
      <div id="about-us-image">
        <img src="Images/landing.jpeg" alt="Coffee beans" id="about-us-img">
      </div>
      <div id="about-us-content">
        <h2 id="about-us-title">Our Story</h2>
        <p id="about-us-text">Ravenhill Coffee House was founded in 2009 with a simple vision: to create the perfect cup of coffee that brings people together. From our humble beginnings in Melbourne's Prahran Market, we've grown into a beloved destination for coffee lovers. We source our beans from ethical farms around the world, ensuring every sip supports sustainable practices. Our expert roasters craft each batch with precision, blending tradition with innovation to deliver flavors that delight and inspire. At Ravenhill, it's not just about coffee—it's about creating moments that last.</p>
        <a href="about.php" id="about-us-link">More About Us</a>
      </div>
    </div>
  </section>

  <!-- Menu Jumbotron Section -->
  <section style="padding: 80px 0; background: var(--background-cream);">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px;">
      <div style="text-align: center; margin-bottom: 50px;">
        <span style="color: var(--accent-gold); font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">OUR MENU</span>
        <h2 style="font-family: 'Playfair Display', serif; font-size: 36px; font-weight: 700; color: var(--text-dark);">Discover Our Delights</h2>
      </div>
      <div style="position: relative; height: 500px; overflow: hidden; border-radius: 15px; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);">
        <img src="https://images.unsplash.com/photo-1504754524776-8f4f37790ca0?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80&utm_source=chatgpt.com" alt="Coffee and Pastries" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
          <a href="menu.php" style="background: var(--accent-gold); color: var(--text-dark); padding: 20px 40px; text-decoration: none; border-radius: 30px; font-size: 24px; font-weight: 600; transition: transform 0.3s ease, background 0.3s ease; display: inline-block;">View Full Menu</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Testimonials Section -->
  <section id="testimonials-section">
    <div id="testimonials-container">
      <div id="testimonials-header">
        <p id="testimonials-subtitle">What Our Customers Say</p>
        <h2 id="testimonials-title">Testimonials</h2>
      </div>
      <div class="swiper mySwiper">
        <div class="swiper-wrapper">
          <?php if (!empty($reviews)): ?>
            <?php foreach ($reviews as $review): ?>
              <div class="swiper-slide testimonial-item">
                <div class="testimonial-content">
                  <img class="testimonial-image" src="<?php echo htmlspecialchars($review['image']); ?>" alt="<?php echo htmlspecialchars($review['name']); ?>">
                  <h3 class="testimonial-name"><?php echo htmlspecialchars($review['name']); ?></h3>
                  <div class="testimonial-rating">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                    <?php endfor; ?>
                  </div>
                  <p class="testimonial-text">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="swiper-slide testimonial-item">
              <div class="testimonial-content">
                <img class="testimonial-image" src="https://randomuser.me/api/portraits/women/44.jpg" alt="Asmita">
                <h3 class="testimonial-name">Asmita</h3>
                <div class="testimonial-rating">
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                </div>
                <p class="testimonial-text">"Amazing coffee and cozy atmosphere! The staff is always so welcoming, and the Honey Lavender Latte is my go-to."</p>
              </div>
            </div>
            <div class="swiper-slide testimonial-item">
              <div class="testimonial-content">
                <img class="testimonial-image" src="https://randomuser.me/api/portraits/men/32.jpg" alt="Mehedi">
                <h3 class="testimonial-name">Mehedi</h3>
                <div class="testimonial-rating">
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star-half-alt"></i>
                </div>
                <p class="testimonial-text">"Best cappuccino in town! The quality of the beans really shines through in every sip."</p>
              </div>
            </div>
            <div class="swiper-slide testimonial-item">
              <div class="testimonial-content">
                <img class="testimonial-image" src="https://randomuser.me/api/portraits/women/65.jpg" alt="Aakriti">
                <h3 class="testimonial-name">Aakriti</h3>
                <div class="testimonial-rating">
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                  <i class="fas fa-star"></i>
                </div>
                <p class="testimonial-text">"Friendly staff and delicious snacks. The croissants are to die for, and the ambiance is perfect for relaxing."</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </section>

  <!-- Review Section -->
  <section id="review-section">
    <div id="review-container">
      <h3 id="review-title">Share Your Feedback</h3>
      <form id="review-form">
        <div class="rating-container">
          <label for="rating">Your Rating:</label>
          <div class="star-rating">
            <input type="radio" name="rating" id="star5" value="5"><label for="star5"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star4" value="4"><label for="star4"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star3" value="3"><label for="star3"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star2" value="2"><label for="star2"><i class="fas fa-star"></i></label>
            <input type="radio" name="rating" id="star1" value="1"><label for="star1"><i class="fas fa-star"></i></label>
          </div>
        </div>
        <textarea id="review-text" placeholder="Write your review here..." required></textarea>
        <button type="submit" id="submit-review-btn">Submit Review</button>
      </form>
    </div>
  </section>

  <!-- Newsletter Section -->
  <section id="newsletter-section">
    <div id="newsletter-container">
      <h2 id="newsletter-title">Join Our Coffee Community</h2>
      <p id="newsletter-text">Subscribe for new blends, events, and offers.</p>
      <form id="newsletter-form">
        <input type="email" id="newsletter-email" placeholder="Your email address" required>
        <button type="submit" id="subscribe-btn">Subscribe</button>
      </form>
    </div>
  </section>
  
  <!-- cart modal -->
   <div id="cart-modal" class="modal">
    <div class="modal-content">
        <!-- Cart content will be loaded dynamically via JS -->
    </div>
</div>

<!-- wishlist modal -->
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

  <script>
  // Opens the allergy modal with product details
  function openAllergyModal(id, name) {
      console.log('Opening modal for product:', { id: id, name: name });
      window.currentProduct = { id: id, name: name };
      
      const modal = document.getElementById('allergy-modal');
      if (!modal) {
          console.error('Modal element #allergy-modal not found! Check HTML ID.');
          alert('Customization modal not found. Please refresh.');
          return;
      }
      
      document.getElementById('modal-item-name').textContent = `Customize ${name}`;
      document.getElementById('modal-inherent').textContent = `Contains: ${window.currentProduct.allergy || 'None'}`;
      
      document.getElementById('item-qty').value = 1;
      document.getElementById('special-notes').value = '';
      document.querySelectorAll('#allergy-modal input[type="checkbox"]').forEach(cb => cb.checked = false);
      
      modal.classList.add('show');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
  }
  </script>
  <script src="script.js"></script>
  <!-- Start of Tawk.to Script -->
  <script type="text/javascript">
  var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
  (function(){
  var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
  s1.async=true;
  s1.src='https://embed.tawk.to/68c53396f7ec01191ca3d73d/1j51531s8';
  s1.charset='UTF-8';
  s1.setAttribute('crossorigin','*');
  s0.parentNode.insertBefore(s1,s0);
  })();
  </script>
  <!-- End of Tawk.to Script -->
</body>
</html>