<?php
// Start session
session_start();

// Include database connection
include 'db_connect.php';

// Restrict access to staff role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: login.php');
    exit;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check for report type
if (!isset($_GET['type'])) {
    die('Report type not specified.');
}

// Include Composer's autoloader
if (!file_exists('vendor/autoload.php')) {
    die('Autoloader not found. Run "composer install" in C:\\xampp\\htdocs\\project\\');
}
require 'vendor/autoload.php';

// Verify Fpdi class exists
if (!class_exists('setasign\Fpdi\Fpdi')) {
    die('Fpdi class not found. Ensure setasign/fpdi is installed via Composer.');
}

// Use the Fpdi class
use setasign\Fpdi\Fpdi;

try {
    // Initialize PDF
    $pdf = new Fpdi();
    $pdf->AddPage();
    // Set font (use Helvetica as a fallback)
    $pdf->SetFont('Helvetica', 'B', 16);
    // Add report title
    $pdf->Cell(40, 10, 'Ravenhill Coffee - Staff Report');

    // Handle report type
    switch ($_GET['type']) {
        case 'daily':
            $pdf->Ln();
            $pdf->Cell(40, 10, 'Daily Sales Report - ' . date('Y-m-d'));
            $stmt = $conn->prepare("SELECT order_id, total_price FROM orders WHERE DATE(order_time) = ?");
            $today = date('Y-m-d');
            $stmt->bind_param("s", $today);
            break;
        case 'weekly':
            $pdf->Ln();
            $pdf->Cell(40, 10, 'Weekly Sales Report - ' . date('Y-m-d', strtotime('-7 days')) . ' to ' . date('Y-m-d'));
            $stmt = $conn->prepare("SELECT order_id, total_price FROM orders WHERE order_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            break;
        case 'monthly':
            $pdf->Ln();
            $pdf->Cell(40, 10, 'Monthly Sales Report - ' . date('Y-m'));
            $stmt = $conn->prepare("SELECT order_id, total_price FROM orders WHERE YEAR(order_time) = YEAR(NOW()) AND MONTH(order_time) = MONTH(NOW())");
            break;
        case 'inventory':
            $pdf->Ln();
            $pdf->Cell(40, 10, 'Inventory Usage Report');
            $stmt = $conn->query("SELECT p.name, i.stock_level FROM inventory i JOIN product p ON i.product_id = p.product_id");
            break;
        default:
            die('Invalid report type');
    }

    // Process query results
    if (isset($stmt) && $stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $pdf->SetFont('Helvetica', '', 12);
        $total = 0;
        while ($row = $result->fetch_assoc()) {
            $pdf->Ln();
            if ($_GET['type'] === 'inventory') {
                $pdf->Cell(40, 10, $row['name'] . ': ' . $row['stock_level']);
            } else {
                $pdf->Cell(40, 10, $row['order_id'] . ' - $' . $row['total_price']);
                $total += $row['total_price'];
            }
        }
        if ($_GET['type'] !== 'inventory') {
            $pdf->Ln();
            $pdf->Cell(40, 10, 'Total: $' . number_format($total, 2));
        }
        if (isset($stmt) && method_exists($stmt, 'close')) {
            $stmt->close();
        }
    } else {
        $pdf->Ln();
        $pdf->Cell(40, 10, 'No data available for this report.');
    }

    // Output PDF
    $pdf->Output('D', 'report_' . $_GET['type'] . '_' . date('Ymd') . '.pdf');
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
} finally {
    // Close database connection if open
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>