<?php
// menu.php - Full code for the menu page
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for cart and wishlist functionality
session_start();
include 'db_connect.php';

// Initialize cart and wishlist sessions if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
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

// Fetch all categories from the database
try {
    $stmt = $conn->prepare("SELECT * FROM category ORDER BY name");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    die("Error fetching categories: " . $e->getMessage());
}

// Fetch items with category and stock information - FIXED QUERY
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT p.*, c.name as category_name, 
               COALESCE(i.stock_level, 0) as stock
        FROM product p 
        JOIN category c ON p.category_id = c.category_id 
        LEFT JOIN inventory i ON p.product_id = i.product_id
        WHERE p.available = 1
        ORDER BY c.name, p.name
    ");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    die("Error fetching items: " . $e->getMessage());
}

// Group items by category for display
$groupedItems = [];
foreach ($items as $item) {
    $groupedItems[$item['category_name']][] = $item;
}

// Calculate cart and wishlist counts for display
$cartCount = count($_SESSION['cart']);
$wishlistCount = count($_SESSION['wishlist']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu - Ravenhill Coffee House</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body class="menu-page">

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
  <!-- Menu Section -->
  <section id="menu-section">
    <div class="menu-container">
      <h1 class="menu-title">Our Menu</h1>
      
      <!-- Debug Information for categories -->
      <?php if (count($categories) === 0): ?>
        <div style="background: #ffebee; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
          <p><strong>Debug:</strong> No categories found in database.</p>
        </div>
      <?php endif; ?>
      
      <!-- Debug Information for items -->
      <?php if (count($items) === 0): ?>
        <div style="background: #ffebee; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
          <p><strong>Debug:</strong> No items found in database.</p>
        </div>
      <?php endif; ?>

      <div class="menu-layout">
        <div class="menu-filters">
          <button class="filter-btn active" data-category="All">
            <i class="fas fa-th-large"></i>
            <span>All</span>
          </button>
          <?php foreach ($categories as $cat): ?>
            <button class="filter-btn" data-category="<?php echo htmlspecialchars($cat['name']); ?>">
              <i class="fas fa-<?php 
                $categoryName = strtolower($cat['name']);
                if ($categoryName === 'coffee') echo 'coffee';
                elseif ($categoryName === 'drinks') echo 'glass-martini';
                elseif ($categoryName === 'breakfast') echo 'utensils';
                elseif ($categoryName === 'lunch') echo 'hamburger';
                elseif ($categoryName === 'sides') echo 'seedling';
                elseif ($categoryName === 'pastries') echo 'cookie';
                else echo 'utensils';
              ?>"></i>
              <span><?php echo htmlspecialchars($cat['name']); ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <div class="menu-content">
          <?php if (count($groupedItems) > 0): ?>
            <?php foreach ($groupedItems as $catName => $catItems): ?>
              <div class="category-section" data-category="<?php echo htmlspecialchars($catName); ?>">
                <h2 class="category-title"><?php echo htmlspecialchars($catName); ?></h2>
                <div class="menu-grid">
                  <?php foreach ($catItems as $item): ?>
                    <div class="menu-card">
                      <div class="img-wrap">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             onerror="this.src='https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'">
                        <span class="price-badge">$<?php echo number_format($item['price'], 2); ?></span>
                        <button class="wishlist-heart <?= in_array($item['product_id'], $_SESSION['wishlist']) ? 'active' : '' ?>" 
                                data-id="<?= $item['product_id'] ?>">
                          <i class="<?= in_array($item['product_id'], $_SESSION['wishlist']) ? 'fas' : 'far' ?> fa-heart"></i>
                        </button>
                      </div>
                      <div class="card-body">
                        <h3 class="item-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                        <p class="item-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                        <?php if (!empty($item['allergens']) && $item['allergens'] !== ''): ?>
                          <span class="allergy-chip">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Contains: <?php echo htmlspecialchars($item['allergens']); ?>
                          </span>
                        <?php endif; ?>
                        <p class="stock-info <?= $item['stock'] <= 0 ? 'out-of-stock' : '' ?>">
                          <?php echo $item['stock'] > 0 ? 'In Stock: ' . $item['stock'] : 'Out of Stock'; ?>
                        </p>
                      </div>
                      <div class="card-actions">
                        <button class="add-btn" 
                                data-id="<?php echo $item['product_id']; ?>" 
                                data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                data-price="<?php echo $item['price']; ?>"
                                data-allergy="<?php echo htmlspecialchars($item['allergens'] ?? 'None'); ?>"
                                <?php if ($item['stock'] <= 0) echo 'disabled'; ?>>
                          <i class="fas fa-cart-plus"></i> 
                          <?= $item['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' ?>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="text-align: center; padding: 40px;">
              <h3>No menu items available at the moment.</h3>
              <p>Please check back later or contact us for more information.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- Allergy Modal -->
  <div id="allergy-modal" class="modal" aria-hidden="true">
    <div class="modal-dialog">
      <button class="modal-close"><i class="fas fa-times"></i></button>
      <h3 id="modal-item-name" class="modal-title"></h3>
      <p id="modal-inherent" class="inherent-allergy"></p>
      <div class="section-label">Customize Allergens (Select to Avoid):</div>
      <div class="chip-grid">
        <label class="chip"><input type="checkbox" value="Dairy"><span>Dairy</span></label>
        <label class="chip"><input type="checkbox" value="Gluten"><span>Gluten</span></label>
        <label class="chip"><input type="checkbox" value="Nuts"><span>Nuts</span></label>
        <label class="chip"><input type="checkbox" value="Eggs"><span>Eggs</span></label>
        <label class="chip"><input type="checkbox" value="Soy"><span>Soy</span></label>
      </div>
      <label class="notes-label" for="special-notes">Special Notes:</label>
      <textarea id="special-notes" placeholder="Any special requests or notes..."></textarea>
      <div class="qty-row">
        <span class="qty-label">Quantity:</span>
        <button class="qty-btn" data-step="-1">-</button>
        <input type="number" id="item-qty" value="1" min="1">
        <button class="qty-btn" data-step="1">+</button>
      </div>
      <div class="modal-actions">
        <button id="confirm-add" class="confirm-btn"><i class="fas fa-check"></i> Add to Cart</button>
      </div>
    </div>
  </div>

  <!-- Cart Modal -->
  <div id="cart-modal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <!-- Cart content will be loaded here via AJAX -->
      <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Loading cart...</p>
      </div>
    </div>
  </div>

  <!-- Wishlist Modal -->
  <div id="wishlist-modal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <!-- Wishlist content will be loaded here via AJAX -->
      <div class="loading-spinner">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Loading wishlist...</p>
      </div>
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