<?php
// add_to_cart.php - Aligned add to cart handler with cart_page.php logic
// Handles adding items to cart, prevents duplicates by merging similar items, updates inventory
// Converted to MySQLi from PDO
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action !== 'add') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

$product_id = $data['product_id'] ?? '';
$quantity = (int)($data['quantity'] ?? 1);
$notes = $data['notes'] ?? '';
$allergens = $data['allergens_to_avoid'] ?? [];

if (empty($product_id) || $quantity < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
    exit;
}

function getProductDetails($conn, $product_id) {
    $stmt = $conn->prepare("SELECT p.product_id, p.name, p.price, p.image_url, p.allergens, COALESCE(i.stock_level, 0) as stock_level 
                            FROM product p 
                            LEFT JOIN inventory i ON p.product_id = i.product_id 
                            WHERE p.product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

$product = getProductDetails($conn, $product_id);
if (!$product) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid product ID']);
    exit;
}

if ($quantity > $product['stock_level']) {
    echo json_encode(['status' => 'error', 'message' => 'Requested quantity exceeds available stock (' . $product['stock_level'] . ')']);
    exit;
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check for existing similar item (same product, notes, allergens)
$existing_index = null;
sort($allergens); // Sort for comparison
foreach ($_SESSION['cart'] as $i => $item) {
    $item_allergens = $item['allergens_to_avoid'];
    sort($item_allergens);
    if ($item['product_id'] == $product_id &&
        $item['notes'] == $notes &&
        $item_allergens == $allergens) {
        $existing_index = $i;
        break;
    }
}

if ($existing_index !== null) {
    // Merge quantities
    $old_quantity = $_SESSION['cart'][$existing_index]['quantity'];
    $new_quantity = $old_quantity + $quantity;
    if ($new_quantity > $product['stock_level']) {
        echo json_encode(['status' => 'error', 'message' => 'Total quantity exceeds available stock']);
        exit;
    }
    $_SESSION['cart'][$existing_index]['quantity'] = $new_quantity;
    $diff = $quantity;

    // Update inventory
    $stmt = $conn->prepare("UPDATE inventory SET stock_level = stock_level - ? WHERE product_id = ? AND stock_level >= ?");
    $stmt->bind_param("iii", $diff, $product_id, $diff);
    $updated = $stmt->execute();

    if (!$updated) {
        // Rollback
        $_SESSION['cart'][$existing_index]['quantity'] = $old_quantity;
        echo json_encode(['status' => 'error', 'message' => 'Failed to update inventory']);
        exit;
    }
} else {
    // Add new item
    $_SESSION['cart'][] = [
        'product_id' => $product_id,
        'quantity' => $quantity,
        'notes' => $notes,
        'allergens_to_avoid' => $allergens
    ];

    // Update inventory
    $stmt = $conn->prepare("UPDATE inventory SET stock_level = stock_level - ? WHERE product_id = ? AND stock_level >= ?");
    $stmt->bind_param("iii", $quantity, $product_id, $quantity);
    $updated = $stmt->execute();

    if (!$updated) {
        // Rollback
        array_pop($_SESSION['cart']);
        echo json_encode(['status' => 'error', 'message' => 'Out of stock']);
        exit;
    }
}

echo json_encode([
    'status' => 'success',
    'message' => "Added {$quantity}x {$product['name']} to cart!",
    'cartCount' => count($_SESSION['cart'])
]);
exit;
?>