<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is waiter
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'waiter') {
    // For print page, maybe just show an error or blank page if not authorized
    die('Unauthorized access.');
}

// Get user data
$user = $_SESSION['user'];

// Check if order ID is provided
if (!isset($_GET['id'])) {
    die('Order ID not provided.');
}

$order_id = $_GET['id'];

// Get order information with customer name
$order_query = "SELECT p.*, m.Nomeja, p.created_at as order_date, pl.Namapelanggan
FROM pesanan p 
JOIN meja m ON p.meja_id = m.id 
JOIN pelanggan pl ON p.idpelanggan = pl.idpelanggan
WHERE p.idpesanan = ?
LIMIT 1";

$stmt = $conn->prepare($order_query);
if (!$stmt) {
    die('Prepare failed (order): (' . $conn->errno . ') ' . $conn->error);
}
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();
$stmt->close();

// Check if order exists
if (!$order) {
    die('Order not found.');
}

// Get order items
$items_query = "SELECT p.*, m.Namamenu as nama, m.Harga as harga, (p.jumlah * m.Harga) as subtotal 
FROM pesanan p 
JOIN menu m ON p.idmenu = m.idmenu 
WHERE p.kode_pesanan = ?";
$stmt_items = $conn->prepare($items_query);
if (!$stmt_items) {
    die('Prepare failed (items): (' . $conn->errno . ') ' . $conn->error);
}
$stmt_items->bind_param("s", $order['kode_pesanan']);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

// Calculate total
$total = 0;
$items_data = []; // Store items for iteration later
while ($item = $items_result->fetch_assoc()) {
    $items_data[] = $item;
    $total += $item['subtotal'];
}
$stmt_items->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order Confirmation - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Base styling for print */
        body {
            background-color: #fff;
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
        }
        
        .confirmation {
            width: 100%;
            max-width: 100%;
            padding: 0;
            margin: 0;
            border: none;
        }

        /* Print-specific styles */
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        
        /* Adjust font sizes for print */
        .confirmation h4 {
            font-size: 18pt; /* Slightly smaller for better fit */
            margin-bottom: 8mm;
        }
        
        .confirmation table {
            width: 100%;
            margin-bottom: 5mm;
            font-size: 10pt; /* Smaller font for items */
        }
        
        .confirmation td, .confirmation th {
            padding: 1.5mm 0;
            font-size: 10pt;
        }
        
        .confirmation p, .confirmation div {
            font-size: 10pt;
            line-height: 1.4;
        }
        
        .confirmation small {
            font-size: 8pt;
        }
        
        .confirmation .text-center {
            text-align: center;
        }
        
        .border-top, .border-bottom {
            border-color: #000 !important;
            border-width: 1px !important;
        }
        
        .items-section {
            min-height: 40vh; /* Keep minimum height */
        }

        .signature-section {
            margin-top: 15mm;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .confirmation-info {
            margin-bottom: 10px;
        }
        
        .items-table {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 120px; /* Slightly smaller */
            margin: 8px auto;
        }
    </style>
</head>
<body>
    <div class="confirmation">
        <div class="confirmation-header">
            <h4 class="mb-1">Kasir Resto</h4>
            <p class="mb-0">Jl. Restaurant No. 123</p>
            <p>Phone: (123) 456-7890</p>
            <h5 class="mt-3">ORDER CONFIRMATION</h5>
        </div>

        <div class="confirmation-info">
            <div class="row">
                <div class="col-6">
                    <p class="mb-0">Order #<?php echo htmlspecialchars($order['idpesanan']); ?></p>
                    <p class="mb-0">Table #<?php echo htmlspecialchars($order['Nomeja']); ?></p>
                    <p>Customer: <?php echo htmlspecialchars($order['Namapelanggan']); ?></p>
                </div>
                <div class="col-6 text-end">
                    <p>Date: <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></p>
                    <p>Waiter: <?php echo htmlspecialchars($user['nama']); ?></p>
                </div>
            </div>
        </div>

        <div class="border-top border-bottom py-3 mb-3 items-section">
            <table class="items-table table-sm table-borderless mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Price</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items_data as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['nama']); ?></td>
                            <td class="text-end"><?php echo $item['jumlah']; ?></td>
                            <td class="text-end">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-4">
            <div class="row fw-bold">
                <div class="col-6">Total Amount</div>
                <div class="col-6 text-end">Rp <?php echo number_format($total, 0, ',', '.'); ?></div>
            </div>
        </div>

        <div class="signature-section row text-center">
            <div class="col-6">
                <p>Customer</p>
                <div class="signature-line"></div>
                <p><?php echo isset($order['Namapelanggan']) ? htmlspecialchars($order['Namapelanggan']) : 'Guest'; ?></p>
            </div>
            <div class="col-6">
                <p>Waiter</p>
                <div class="signature-line"></div>
                <p><?php echo htmlspecialchars($user['nama']); ?></p>
            </div>
        </div>

        <div class="confirmation-footer text-center mt-4">
            <p class="mb-0">Thank you for choosing Kasir Resto!</p>
            <small>This is not a payment receipt</small>
        </div>
    </div>

    <script>
        // Automatically trigger print dialog when the page loads
        window.onload = function() {
            window.print();
            // Optional: Close the window/tab after printing or delay
            // Consider adding a small delay or user interaction before closing
            // setTimeout(function(){ window.close(); }, 500);
        }
    </script>
</body>
</html>
