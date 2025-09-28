<?php

require_once 'config.php';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}


// Fetch categories and products
$categories = $pdo->query("SELECT * FROM category ORDER BY name")->fetchAll();
$products = $pdo->query("
    SELECT p.*, c.name as category_name, COALESCE(i.stock_level, 0) as stock 
    FROM product p 
    JOIN category c ON p.category_id = c.category_id 
    LEFT JOIN inventory i ON p.product_id = i.product_id 
    WHERE p.available = 1
    ORDER BY c.name, p.name
")->fetchAll();

// Group products by category
$groupedProducts = [];
foreach ($products as $product) {
    $groupedProducts[$product['category_name']][] = $product;
}

// Process checkout if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    require_once 'process_order.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ravenhill Coffee - POS System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="pos-container">
        <header class="pos-header">
            <h1><i class="fas fa-cash-register"></i> Ravenhill Coffee POS</h1>
            <div class="header-info">
                <span id="current-date"></span>
                <span id="current-time"></span>
                <div class="header-actions">
                    <a href="inventory.php" class="header-icon" title="Inventory Management">
                        <i class="fas fa-warehouse"></i>
                    </a>
                    <a href="reports.php" class="header-icon" title="Sales Reports">
                        <i class="fas fa-chart-bar"></i>
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="../login.php?logout=1" class="header-icon" title="Logout">
    <i class="fas fa-sign-out-alt"></i>
</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="pos-content">
            <!-- Product Selection -->
            <div class="product-section">
                <div class="category-tabs">
                    <button class="category-tab active" data-category="All">All Items</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="category-tab" data-category="<?= htmlspecialchars($category['name']) ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="product-grid">
                    <?php foreach ($groupedProducts as $categoryName => $categoryProducts): ?>
                        <div class="product-category" data-category="<?= htmlspecialchars($categoryName) ?>">
                            <h3><?= htmlspecialchars($categoryName) ?></h3>
                            <div class="products">
                                <?php foreach ($categoryProducts as $product): ?>
                                    <div class="product-card" data-id="<?= $product['product_id'] ?>" 
                                         data-name="<?= htmlspecialchars($product['name']) ?>" 
                                         data-price="<?= $product['price'] ?>"
                                         data-stock="<?= $product['stock'] ?>">
                                        <div class="product-image">
                                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                                 onerror="this.src='https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80'">
                                        </div>
                                        <div class="product-info">
                                            <h4><?= htmlspecialchars($product['name']) ?></h4>
                                            <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>
                                            <div class="product-meta">
                                                <span class="price">$<?= number_format($product['price'], 2) ?></span>
                                                <span class="stock <?= $product['stock'] > 0 ? 'in-stock' : 'out-of-stock' ?>">
                                                    <?= $product['stock'] > 0 ? $product['stock'] . ' in stock' : 'Out of stock' ?>
                                                </span>
                                            </div>
                                        </div>
                                        <button class="add-to-cart" onclick="openAllergyModal(<?= $product['product_id'] ?>, '<?= addslashes($product['name']) ?>', <?= $product['price'] ?>, '<?= htmlspecialchars($product['allergens']) ?>')" data-id="<?= $product['product_id'] ?>" data-name="<?= htmlspecialchars($product['name']) ?>" data-price="<?= $product['price'] ?>" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
    <i class="fas fa-plus"></i> Add
</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="order-section">
                <div class="order-header">
                    <h2>Current Order</h2>
                    <button id="clear-order" class="btn-danger">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>

                <div class="order-items">
                    <table id="order-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="order-items-body">
                            <!-- Cart items will be added here dynamically -->
                        </tbody>
                    </table>

                    <!-- Allergen Customization Modal -->
<div id="allergy-modal" class="modal" aria-hidden="true">
    <div class="modal-dialog">
        <button class="modal-close"><i class="fas fa-times"></i></button>
        <h3 id="modal-item-name" class="modal-title"></h3>
        <p id="modal-inherent" class="inherent-allergy"></p>
        <div class="section-label">Allergens to Avoid (Select to Remove):</div>
        <div class="chip-grid">
            <label class="chip"><input type="checkbox" value="Dairy"><span>Dairy</span></label>
            <label class="chip"><input type="checkbox" value="Gluten"><span>Gluten</span></label>
            <label class="chip"><input type="checkbox" value="Nuts"><span>Nuts</span></label>
            <label class="chip"><input type="checkbox" value="Eggs"><span>Eggs</span></label>
            <label class="chip"><input type="checkbox" value="Soy"><span>Soy</span></label>
        </div>
        <div class="section-label">Substitutions (Add Ingredients):</div>
        <select id="substitution-select" class="substitution-dropdown">
            <option value="">Select a substitution...</option>
            <optgroup label="Milk Alternatives (for Dairy)">
                <option value="oat milk">Oat Milk</option>
                <option value="almond milk">Almond Milk (Nut-Free Note: Check for nut allergy)</option>
                <option value="soy milk">Soy Milk</option>
                <option value="coconut milk">Coconut Milk</option>
            </optgroup>
            <optgroup label="Bread/Flour (for Gluten)">
                <option value="gluten-free bread">Gluten-Free Bread</option>
                <option value="lettuce wrap">Lettuce Wrap</option>
            </optgroup>
            <optgroup label="Egg Replacements">
                <option value="tofu scramble">Tofu Scramble</option>
                <option value="no eggs">Omit Eggs</option>
            </optgroup>
            <optgroup label="Other">
                <option value="vegan cheese">Vegan Cheese (for Dairy/Gluten)</option>
                <option value="nut-free topping">Nut-Free Topping</option>
            </optgroup>
        </select>
        <label class="notes-label" for="special-notes">Special Notes (e.g., "Remove eggs, add tofu"):</label>
        <textarea id="special-notes" placeholder="Any additional requests..."></textarea>
        <div class="qty-row">
            <span class="qty-label">Quantity:</span>
            <button class="qty-btn" data-step="-1">-</button>
            <input type="number" id="item-qty" value="1" min="1">
            <button class="qty-btn" data-step="1">+</button>
        </div>
        <div class="modal-actions">
            <button id="confirm-customize" class="confirm-btn"><i class="fas fa-check"></i> Add to Cart with Customizations</button>
        </div>
    </div>
</div>
                   
                    <div id="empty-cart-message">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No items in order</p>
                    </div>
                </div>

                <div class="order-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">$0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (10%):</span>
                        <span id="tax">$0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="total">$0.00</span>
                    </div>

                    <div class="payment-section">
                        <h3>Payment Method</h3>
                        <div class="payment-methods">
                            <label class="payment-method">
                                <input type="radio" name="payment-method" value="cash" checked>
                                <i class="fas fa-money-bill-wave"></i> Cash
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment-method" value="card">
                                <i class="fas fa-credit-card"></i> Card
                            </label>
                            <label class="payment-method">
                                <input type="radio" name="payment-method" value="mobile">
                                <i class="fas fa-mobile-alt"></i> Mobile
                            </label>
                        </div>

                        <div class="payment-actions">
                            <button id="checkout-btn" class="btn-primary">
                                <i class="fas fa-check-circle"></i> Process Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Complete Payment</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="payment-details">
                    <p>Total Amount: <strong id="payment-total">$0.00</strong></p>
                    <p>Payment Method: <strong id="payment-method">Cash</strong></p>
                    
                    <div id="cash-payment">
                        <label for="cash-received">Amount Received:</label>
                        <input type="number" id="cash-received" step="0.01" min="0">
                        <p>Change: <span id="change-amount">$0.00</span></p>
                    </div>
                    
                    <div id="card-payment" style="display: none;">
                        <p>Please swipe or insert card...</p>
                        <div class="card-processing">
                            <i class="fas fa-credit-card fa-2x"></i>
                            <p>Processing...</p>
                        </div>
                    </div>
                    
                    <div id="mobile-payment" style="display: none;">
                        <p>Scan QR code to pay:</p>
                        <div class="qrcode">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=RavenhillPayment" alt="QR Code">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="confirm-payment" class="btn-success">
                    <i class="fas fa-check"></i> Confirm Payment
                </button>
                <button id="cancel-payment" class="btn-danger">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
