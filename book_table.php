<?php
// Database connection
include 'db_connect.php';

// Initialize variables for header - use session variables if they exist
$wishlistCount = isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0;
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$isLoggedIn = isset($_SESSION['user_id']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars(trim($_POST['name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $date = htmlspecialchars(trim($_POST['date']));
    $time = htmlspecialchars(trim($_POST['time']));
    $guests = intval($_POST['guests']);
    $requests = htmlspecialchars(trim($_POST['special_requests']));
    
    // Initialize error variable
    $error = '';

    // Basic validation
    if (empty($name) || empty($email) || empty($phone) || empty($date) || empty($time) || $guests < 1) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!preg_match('/^[0-9+\s\(\)\-]{10,20}$/', $phone)) {
        $error = "Invalid phone number format.";
    } elseif (strtotime($date) < strtotime('today')) {
        $error = "Reservation date must be in the future.";
    } else {
        // Convert time to proper format
        $time_formatted = date('H:i:s', strtotime($time));
        
        try {
            // Check if reservations table exists, create if not
            $table_check = $conn->query("SHOW TABLES LIKE 'reservations'");
            if ($table_check->num_rows === 0) {
                // Create reservations table
                $create_table = $conn->query("CREATE TABLE IF NOT EXISTS reservations (
                    reservation_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    reservation_date DATE NOT NULL,
                    reservation_time TIME NOT NULL,
                    party_size INT NOT NULL,
                    special_requests TEXT,
                    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
                
                if (!$create_table) {
                    throw new Exception("Failed to create reservations table: " . $conn->error);
                }
            }
            
            // Insert into database using prepared statement
            $stmt = $conn->prepare("INSERT INTO reservations (name, email, phone, reservation_date, reservation_time, party_size, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt) {
                $stmt->bind_param("sssssis", $name, $email, $phone, $date, $time_formatted, $guests, $requests);
                
                if ($stmt->execute()) {
                    $success = "Your table has been reserved successfully! We'll confirm via email shortly.";
                    
                    // Clear form fields
                    $name = $email = $phone = $date = $time = $requests = '';
                    $guests = 1;
                } else {
                    $error = "An error occurred. Please try again later. Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Database preparation error: " . $conn->error;
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

    // Handle AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $response = [
            'status' => isset($success) ? 'success' : (isset($error) ? 'error' : 'unknown'),
            'message' => isset($success) ? $success : (isset($error) ? $error : 'Unknown error occurred.')
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Table - Ravenhill Coffee House</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="style.css">
  
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
  <!-- Include Header -->
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

  <!-- Reservation Section -->
  <section id="reservation-section">
    <div id="reservation-container">
      <div id="reservation-form-container">
        <h1 id="reservation-title">Reserve Your Table</h1>
        <p id="reservation-subtitle">Experience the culinary excellence of Ravenhill. Book your table today for an unforgettable dining experience.</p>
        
        <?php if (isset($success)): ?>
          <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif (isset($error)): ?>
          <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form id="reservation-form" method="POST" action="book_table.php">
          <div class="form-group">
            <label for="name">Name *</label>
            <input type="text" id="name" name="name" required placeholder="Enter your full name" value="<?php echo isset($name) ? $name : ''; ?>">
          </div>
          <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required placeholder="Enter your email address" value="<?php echo isset($email) ? $email : ''; ?>">
          </div>
          <div class="form-group">
            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone" required placeholder="Enter your phone number" value="<?php echo isset($phone) ? $phone : ''; ?>">
          </div>
          <div class="form-group">
            <label for="date">Date *</label>
            <input type="date" id="date" name="date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo isset($date) ? $date : ''; ?>">
          </div>
          <div class="form-group">
            <label for="time">Time *</label>
            <select id="time" name="time" required>
              <option value="">Select Time</option>
              <?php
                $times = [
                    '9:00' => '9:00 AM',
                    '10:00' => '10:00 AM',
                    '11:00' => '11:00 AM',
                    '11:30' => '11:30 AM',
                    '12:00' => '12:00 PM',
                    '12:30' => '12:30 PM',
                    '13:00' => '1:00 PM',
                    '13:30' => '1:30 PM',
                    '14:00' => '2:00 PM',
                    '14:30' => '2:30 PM',
                    '15:00' => '3:00 PM',
                    '15:30' => '3:30 PM',
                    '16:00' => '4:00 PM',
                    '16:30' => '4:30 PM',
                    '17:00' => '5:00 PM',
                    '17:30' => '5:30 PM',
                    '18:00' => '6:00 PM',
                    '18:30' => '6:30 PM',
                    '19:00' => '7:00 PM',
                    '19:30' => '7:30 PM',
                    '20:00' => '8:00 PM'
                ];
                foreach ($times as $value => $label) {
                    $selected = (isset($time) && $time == $value) ? 'selected' : '';
                    echo "<option value='$value' $selected>$label</option>";
                }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label for="guests">Number of Guests *</label>
            <select id="guests" name="guests" required>
              <option value="">Select guests</option>
              <?php for ($i = 1; $i <= 10; $i++): ?>
                <?php $selected = (isset($guests) && $guests == $i) ? 'selected' : ''; ?>
                <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo $i; ?> <?php echo $i === 1 ? 'Guest' : 'Guests'; ?></option>
              <?php endfor; ?>
              <option value="11" <?php echo (isset($guests) && $guests == 11) ? 'selected' : ''; ?>>11+ Guests (Contact Us)</option>
            </select>
          </div>
          <div class="form-group">
            <label for="special_requests">Special Requests</label>
            <textarea id="special_requests" name="special_requests" placeholder="Any special requests? (e.g., Window seat, Birthday celebration)"><?php echo isset($requests) ? $requests : ''; ?></textarea>
          </div>
          <button type="submit" id="reserve-btn">Reserve Now</button>
        </form>
      </div>

      <div id="reservation-info">
        <h2 id="info-title">Something Special About Us</h2>
        <p id="info-text">Our restaurant offers an intimate experience with seasonal menus crafted from locally sourced ingredients.</p>
        <div class="info-item">
          <i class="fas fa-clock"></i>
          <span>Mon-Fri 7:00 AM - 8:00 PM, Sat-Sun 7:00 AM - 6:00 PM</span>
        </div>
        <div class="info-item">
          <i class="fas fa-map-marker-alt"></i>
          <span>Prahran Market, Melbourne</span>
        </div>
        <div class="info-item">
          <i class="fas fa-phone-alt"></i>
          <span>(231) 456-7890</span>
        </div>
        <div class="info-item">
          <i class="fas fa-envelope"></i>
          <span>reservations@ravenhillcoffee.com</span>
        </div>

        <div id="popular-slots">
          <h3 id="slots-title">Popular Time Slots</h3>
          <div class="slot-buttons">
            <button type="button" class="slot-btn" data-time="11:00">11:00 AM</button>
            <button type="button" class="slot-btn" data-time="12:00">12:00 PM</button>
            <button type="button" class="slot-btn" data-time="14:00">2:00 PM</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Include Footer -->
  <footer id="footer-section">
    <div id="footer-container">
      <div id="footer-grid">
        <div id="footer-about">
          <div id="footer-logo">
            <img src="https://cdn-icons-png.flaticon.com/512/924/924514.png" alt="Ravenhill Coffee Logo" id="footer-logo-image">
            <span id="footer-logo-text">Ravenhill</span>
          </div>
          <p id="footer-about-text">Crafting exceptional coffee since 2009 with ethically sourced beans.</p>
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
              <span>Prahran Market, Melbourne</span>
            </li>
            <li class="contact-item">
              <i class="fas fa-phone-alt"></i>
              <span>(02) 123-4567</span>
            </li>
            <li class="contact-item">
              <i class="fas fa-envelope"></i>
              <span>hello@ravenhillcoffee.com</span>
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
              <span>8:00 AM - 6:00 PM</span>
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
