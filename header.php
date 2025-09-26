<?php
// Check if session is already started to avoid the notice
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// Fetch user details if logged in
$user_name = 'Account'; // Default text when not logged in
$user_email = '';
$user_phone = '';
$debug_info = "Initial state: No user_id\n";
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $debug_info .= "Session user_id: $user_id\n";
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        if ($user) {
            $user_name = htmlspecialchars($user['name'] ?? 'User'); // Use fetched name
            $user_email = htmlspecialchars($user['email'] ?? 'Not set');
            $user_phone = htmlspecialchars($user['phone'] ?? 'Not set');
            $debug_info .= "Fetched name: $user_name, email: $user_email\n";
        } else {
            $debug_info .= "No user found for user_id: $user_id\n";
        }
        $stmt->close();
    } else {
        $debug_info .= "Query failed: " . $conn->error . "\n";
    }
    error_log("Header debug - " . $debug_info); // Detailed debug log
} else {
    $debug_info .= "No user_id in session\n";
}
?>

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
                <a href="index.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">Home</a>
                <a href="menu.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'menu.php' ? 'active' : ''; ?>">Menu</a>
                <a href="about.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'active' : ''; ?>">About Us</a>
                <a href="contact.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'active' : ''; ?>">Contact</a>
                <a href="gc_sangam.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'gc_sangam.php' ? 'active' : ''; ?>">GC Sangam</a>
            </nav>
            <div id="nav-actions">
                <a href="#" class="icon-link"><i class="fas fa-search"></i></a>
                <a href="#" class="icon-link" id="wishlist-btn"><i class="far fa-heart"></i><span class="count-badge" id="wishlist-count"><?= count($_SESSION['wishlist'] ?? []) ?></span></a>
                <a href="#" id="cart-btn" class="icon-link"><i class="fas fa-shopping-cart"></i> <span class="count-badge" id="cart-count"><?= count($_SESSION['cart'] ?? []) ?></span></a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="profile-dropdown" id="profile-dropdown">
                        <button id="profile-btn" class="account-btn" style="position: relative; z-index: 1000;">
                            <span id="user-name-display"><?= $user_name ?></span> <i class="fas fa-caret-down"></i>
                            <img src="https://www.gravatar.com/avatar/<?= md5(strtolower(trim($user_email))) ?>?s=32" alt="Profile Icon" class="profile-icon" style="vertical-align: middle; margin-left: 5px;">
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <strong>Name:</strong> <?= $user_name ?><br>
                                <strong>Email:</strong> <?= $user_email ?><br>
                                <strong>Phone:</strong> <?= $user_phone ?>
                            </li>
                            <li><a href="profile.php" class="account-link">Profile</a></li>
                            <li><a href="account_details.php" class="account-link">Edit Details</a></li>
                            <li><a href="view_orders.php" class="account-link">Order History</a></li>
                            <li><a href="change_password.php" class="account-link">Change Password</a></li>
                            <li><a href="logout.php" class="account-link">Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="account-dropdown" id="account-dropdown">
                        <button id="account-btn" class="account-btn">Account <i class="fas fa-caret-down"></i></button>
                        <div id="account-menu">
                            <a href="login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="account-link">Login</a>
                            <a href="register.php?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>" class="account-link">Register</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<style>
    #main-nav .nav-item {
        position: relative;
    }
    #profile-dropdown {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 5px;
    }
    .dropdown-menu {
        display: none;
        position: absolute;
        right: 0;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        z-index: 1000;
        min-width: 200px;
    }
    .dropdown-menu li {
        padding: 10px;
    }
    .dropdown-menu a {
        display: block;
        color: #333;
        text-decoration: none;
    }
    .dropdown-menu a:hover {
        background-color: #f5f5f5;
    }
    .account-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        display: flex;
        align-items: center;
        padding: 5px 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    #user-name-display {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .profile-icon {
        border-radius: 50%;
        margin-left: 5px;
    }
</style>