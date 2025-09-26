<?php
// cart_page.php - Handles cart and wishlist actions and HTML rendering (fixed for JSON errors)
// Converted to MySQLi from PDO to match db_connect.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display_errors for AJAX to avoid HTML output; log instead
ini_set('log_errors', 1);

session_start();

try {
    include 'db_connect.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

function getProductDetails($conn, $product_id) {
    try {
        $stmt = $conn->prepare("SELECT p.product_id, p.name, p.price, p.image_url, p.allergens, COALESCE(i.stock_level, 0) as stock_level 
                               FROM product p 
                               LEFT JOIN inventory i ON p.product_id = i.product_id 
                               WHERE p.product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    } catch (Exception $e) {
        error_log("getProductDetails error for ID $product_id: " . $e->getMessage());
        return false;
    }
}

function validatePromoCode($conn, $code) {
    $today = date('Y-m-d');
    try {
        $stmt = $conn->prepare("SELECT * FROM promotion WHERE code = ? AND start <= ? AND end >= ? AND type = 'percentage'");
        $stmt->bind_param("sss", $code, $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        $promo = $result->fetch_assoc();
        return $promo ? $promo['value'] : 0;
    } catch (Exception $e) {
        error_log("validatePromoCode error: " . $e->getMessage());
        return 0;
    }
}

// Early header for JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
}

// Read JSON body for POST
$data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true) ?? [];
    }
    error_log("Received POST data: " . print_r($data, true)); // Log for debugging
}

$action = $_GET['action'] ?? $data['action'] ?? $_POST['action'] ?? '';

try {
    if ($action === 'get_cart_html') {
        // HTML output (no JSON header)
        ob_start();
        ?>
        <div class="modal-header">
            <h2 class="modal-title">Your Cart <span class="item-count"><?= count($_SESSION['cart'] ?? []) ?> items</span></h2>
            <button class="close-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="cart-items-modal">
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p class="empty-message">Your cart is empty.</p>
                    <a href="menu.php" class="shop-now-btn">Shop Now</a>
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['cart'] as $index => $item):
                    $product = getProductDetails($conn, $item['product_id'] ?? $item['id'] ?? '');
                    if (!$product) continue;
                    $subtotal = $product['price'] * ($item['quantity'] ?? $item['qty'] ?? 1);
                ?>
                    <div class="cart-item-modal" data-index="<?= $index ?>">
                        <img src="<?= htmlspecialchars($product['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="item-image">
                        <div class="item-details">
                            <h3 class="item-name"><?= htmlspecialchars($product['name']) ?></h3>
                            <?php 
                            $notes = $item['notes'] ?? $item['custom'] ?? '';
                            $allergens = $item['allergens_to_avoid'] ?? [];
                            if (!empty($notes) || !empty($allergens)): ?>
                                <p class="item-custom">
                                    <?php if (!empty($notes)): ?>Notes: <?= htmlspecialchars($notes) ?><br><?php endif; ?>
                                    <?php if (!empty($allergens)): ?>Avoid: <?= htmlspecialchars(is_array($allergens) ? implode(', ', $allergens) : $allergens) ?><?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <p class="item-price">$<?= number_format($product['price'], 2) ?> each</p>
                        </div>
                        <div class="item-actions">
                            <div class="qty-control">
                                <button class="qty-btn minus">-</button>
                                <input type="number" class="qty-input" value="<?= $item['quantity'] ?? $item['qty'] ?? 1 ?>" min="1" readonly>
                                <button class="qty-btn plus">+</button>
                            </div>
                            <button class="remove-btn"><i class="fas fa-trash"></i></button>
                        </div>
                        <span class="item-total">$<?= number_format($subtotal, 2) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($_SESSION['cart'])): ?>
            <div class="cart-summary-modal">
                <?php 
                $subtotal = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $product = getProductDetails($conn, $item['product_id'] ?? $item['id'] ?? '');
                    if ($product) $subtotal += $product['price'] * ($item['quantity'] ?? $item['qty'] ?? 1);
                }
                $shippingCost = (($_SESSION['shipping'] ?? 'pickup') === 'delivery') ? 5.00 : 0;
                $total = $subtotal + $shippingCost;
                $discount = isset($_SESSION['promo_discount']) ? $total * ($_SESSION['promo_discount'] / 100) : 0;
                $finalTotal = $total - $discount;
                ?>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span class="summary-value">$<?= number_format($subtotal, 2) ?></span>
                </div>
                <div class="summary-row">
                    <span>Shipping</span>
                    <span class="summary-value">
                        <select class="shipping-select">
                            <option value="pickup" <?= ($_SESSION['shipping'] ?? 'pickup') === 'pickup' ? 'selected' : '' ?>>Pick-up (Free)</option>
                            <option value="delivery" <?= ($_SESSION['shipping'] ?? 'pickup') === 'delivery' ? 'selected' : '' ?>>Delivery ($5.00)</option>
                        </select>
                    </span>
                </div>
                <div class="summary-row">
                    <span>Discount</span>
                    <span class="summary-value">-$<?= number_format($discount, 2) ?></span>
                </div>
                <div class="summary-row total-row">
                    <span>Total</span>
                    <span class="summary-value">$<?= number_format($finalTotal, 2) ?></span>
                </div>
                <div class="promo-row">
                    <input type="text" class="promo-input" placeholder="Promo Code">
                    <button class="apply-promo-btn">Apply</button>
                </div>
                <a href="checkout.php" class="checkout-btn">Checkout</a>
            </div>
        <?php endif; ?>
        <a href="menu.php" class="continue-shopping">Continue Shopping</a>
        <?php
        echo ob_get_clean();
    } elseif ($action === 'get_wishlist_html') {
        // HTML output for wishlist (no JSON header)
        ob_start();
        ?>
        <div class="modal-header">
            <h2 class="modal-title">Your Wishlist <span class="item-count"><?= count($_SESSION['wishlist'] ?? []) ?> items</span></h2>
            <button class="close-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="wishlist-items-modal">
            <?php if (empty($_SESSION['wishlist'])): ?>
                <div class="empty-wishlist">
                    <i class="far fa-heart"></i>
                    <p class="empty-message">Your wishlist is empty.</p>
                    <a href="menu.php" class="shop-now-btn">Browse Menu</a>
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['wishlist'] as $index => $product_id):
                    $product = getProductDetails($conn, $product_id);
                    if (!$product) continue;
                ?>
                    <div class="wishlist-item" data-index="<?= $index ?>">
                        <img src="<?= htmlspecialchars($product['image_url'] ?? '') ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="item-image">
                        <div class="item-details">
                            <h3 class="item-name"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="item-price">$<?= number_format($product['price'], 2) ?></p>
                        </div>
                        <div class="item-actions">
                            <button class="wishlist-remove-btn"><i class="fas fa-trash"></i> Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <a href="menu.php" class="continue-shopping">Continue Shopping</a>
        <?php
        echo ob_get_clean();
    } else {
        // JSON responses for actions
        header('Content-Type: application/json');

        if ($action === 'add') {
            $product_id = $data['product_id'] ?? ''; // Changed to product_id for consistency
            $quantity = (int)($data['quantity'] ?? 1);
            $notes = $data['notes'] ?? '';
            $allergens = $data['allergens_to_avoid'] ?? [];

            if (empty($product_id) || $quantity < 1) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid input data.']);
                exit;
            }

            $product = getProductDetails($conn, $product_id);
            if (!$product) {
                echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
                exit;
            }

            if ($quantity > $product['stock_level']) {
                echo json_encode(['status' => 'error', 'message' => 'Out of stock. Available: ' . $product['stock_level']]);
                exit;
            }

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            $_SESSION['cart'][] = [
                'product_id' => $product_id, // Changed to product_id
                'quantity' => $quantity,
                'notes' => $notes,
                'allergens_to_avoid' => $allergens
            ];

            // Update inventory
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = stock_level - ? WHERE product_id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();

            echo json_encode([
                'status' => 'success',
                'message' => "Added {$quantity}x {$product['name']} to cart!",
                'cartCount' => count($_SESSION['cart'])
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ($action === 'update') {
            $index = $_POST['index'] ?? $data['index'] ?? '';
            $quantity = (int)($_POST['quantity'] ?? $data['quantity'] ?? 1);

            if (!isset($_SESSION['cart'][$index])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid cart item.']);
                exit;
            }

            $product = getProductDetails($conn, $_SESSION['cart'][$index]['product_id']);
            if ($quantity > $product['stock_level']) {
                echo json_encode(['status' => 'error', 'message' => 'Out of stock.']);
                exit;
            }

            $_SESSION['cart'][$index]['quantity'] = $quantity;
            echo json_encode(['status' => 'success', 'cartCount' => count($_SESSION['cart'])]);
            exit;
        }

        if ($action === 'remove') {
            $index = $_POST['index'] ?? $data['index'] ?? '';

            if (!isset($_SESSION['cart'][$index])) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid cart item.']);
                exit;
            }

            $quantity = $_SESSION['cart'][$index]['quantity'];
            $product_id = $_SESSION['cart'][$index]['product_id'];
            array_splice($_SESSION['cart'], $index, 1);

            // Restore stock
            $stmt = $conn->prepare("UPDATE inventory SET stock_level = stock_level + ? WHERE product_id = ?");
            $stmt->bind_param("ii", $quantity, $product_id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'cartCount' => count($_SESSION['cart'])]);
            exit;
        }

        if ($action === 'apply_promo') {
            $code = $_POST['code'] ?? $data['code'] ?? '';

            $discount = validatePromoCode($conn, $code);
            if ($discount > 0) {
                $_SESSION['promo_code'] = $code;
                $_SESSION['promo_discount'] = $discount;
                echo json_encode(['status' => 'success', 'message' => 'Promo applied!']);
            } else {
                unset($_SESSION['promo_code'], $_SESSION['promo_discount']);
                echo json_encode(['status' => 'error', 'message' => 'Invalid promo code.']);
            }
            exit;
        }

        if ($action === 'update_shipping') {
            $value = $_POST['value'] ?? $data['value'] ?? 'pickup';
            $_SESSION['shipping'] = $value;
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($action === 'add_wish') {
            $product_id = $_POST['product_id'] ?? $data['product_id'] ?? ''; // Changed to product_id
            if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];
            if (!in_array($product_id, $_SESSION['wishlist'])) {
                $_SESSION['wishlist'][] = $product_id;
                echo json_encode(['status' => 'success', 'message' => 'Added to wishlist', 'wishlistCount' => count($_SESSION['wishlist'])]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Already in wishlist']);
            }
            exit;
        }

        if ($action === 'remove_wish') {
            $index = $_POST['index'] ?? $data['index'] ?? '';
            if (isset($_SESSION['wishlist'][$index])) {
                array_splice($_SESSION['wishlist'], $index, 1);
                echo json_encode(['status' => 'success', 'wishlistCount' => count($_SESSION['wishlist'])]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid wishlist item']);
            }
            exit;
        }

        // Fallback
        echo json_encode(['status' => 'error', 'message' => 'Invalid action: ' . $action]);
    }
} catch (Exception $e) {
    error_log("cart_page.php general error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}

?>