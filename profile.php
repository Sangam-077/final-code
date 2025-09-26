<?php
// profile.php
session_start();
include 'db_connect.php';

// Redirect to login if not logged in, preserving the current page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Verify database connection
if (!$conn || $conn->connect_error) {
    error_log("Database connection failed in profile.php: " . ($conn->connect_error ?? 'No connection'));
    die("Database connection error. Please try again later.");
}

$user_id = $_SESSION['user_id'];

// Handle profile update
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE user_id = ?");
        if ($stmt === false) {
            $error = "Failed to prepare update statement: " . $conn->error;
            error_log($error);
            $update_message = "Error: $error";
        } else {
            $stmt->bind_param("ssss", $name, $phone, $address, $user_id);
            if ($stmt->execute()) {
                $update_message = "Profile updated successfully!";
            } else {
                $update_message = "Failed to update profile: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $update_message = "Name is required.";
    }
}

// Fetch user data
$stmt = $conn->prepare("SELECT name, email, phone, address FROM users WHERE user_id = ?");
if ($stmt === false) {
    $error = "Failed to prepare SELECT statement: " . $conn->error;
    error_log($error);
    die("Database error: $error");
}

$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    error_log("User not found for user_id: $user_id");
    session_destroy();
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$stmt->close();

// Check for success message
$success_message = '';
if (isset($_SESSION['just_logged_in'])) {
    $success_message = 'Successfully logged in!';
    unset($_SESSION['just_logged_in']);
} elseif (isset($_SESSION['just_registered'])) {
    $success_message = 'Account registered successfully!';
    unset($_SESSION['just_registered']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Ravenhill Coffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        /* Inline CSS for this example; move to style.css later */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .profile-container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            text-align: center;
        }
        .header {
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .success-message, .update-message {
            color: #28a745;
            margin-bottom: 15px;
        }
        .profile-section, .update-profile, .order-history {
            margin-bottom: 20px;
        }
        .profile-icon img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .edit-icon {
            color: #007bff;
            text-decoration: none;
            margin-left: 10px;
        }
        .profile-info p {
            margin: 5px 0;
            color: #666;
        }
        .update-profile form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .input-field {
            text-align: left;
        }
        .input-field label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        .input-field input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .update-btn, .profile-link, .logout-btn {
            display: inline-block;
            padding: 10px 20px;
            color: #fff;
            background-color: #007bff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .update-btn:hover, .profile-link:hover, .logout-btn:hover {
            background-color: #0056b3;
        }
        .logout-btn {
            background-color: #dc3545;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }
        .order-history p {
            margin: 5px 0;
            color: #666;
        }
        @media (max-width: 600px) {
            .profile-container {
                padding: 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="header">
            <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?></h2>
            <?php if ($success_message): ?>
                <p class="success-message"><?php echo $success_message; ?></p>
            <?php endif; ?>
        </div>
        <div class="profile-section">
            <div class="profile-icon">
                <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user['email']))); ?>?s=200" alt="Profile Icon" class="profile-img">
                <a href="upload_profile.php" class="edit-icon"><i class="fas fa-camera"></i></a>
            </div>
            <div class="profile-info">
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? 'Not set'); ?></p>
                <a href="change_password.php" class="profile-link">Change Password</a>
            </div>
        </div>
        <div class="update-profile">
            <h3>Update Profile</h3>
            <?php if ($update_message): ?>
                <p class="update-message"><?php echo $update_message; ?></p>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="input-field">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                <div class="input-field">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                <div class="input-field">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                </div>
                <button type="submit" class="update-btn">Update Profile</button>
            </form>
        </div>
        <div class="order-history">
            <h3>Order History</h3>
            <?php
            $stmt = $conn->prepare("SELECT order_id, total_price, order_time FROM orders WHERE customer_id = ? ORDER BY order_time DESC");
            if ($stmt === false) {
                $error = "Failed to prepare order SELECT statement: " . $conn->error;
                error_log($error);
                echo "<p>Unable to load order history: $error</p>";
            } else {
                $stmt->bind_param("s", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($order = $result->fetch_assoc()) {
                        echo "<p>Order #{$order['order_id']} - $" . number_format($order['total_price'], 2) . " on " . date('Y-m-d', strtotime($order['order_time'])) . "</p>";
                    }
                } else {
                    echo "<p>No orders yet.</p>";
                }
                $stmt->close();
            }
            ?>
            <a href="orders.php" class="profile-link">View Orders</a>
        </div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <script src="script.js"></script>
</body>
</html>