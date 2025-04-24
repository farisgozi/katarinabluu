<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is waiter
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'waiter') {
    header("Location: ../../pages/auth/login.php");
    exit();
}

// Check session timeout
check_session_timeout();

// Get user data
$user = $_SESSION['user'];

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$order_id = $_GET['id'];

// Get order information with customer name
// Modified the SQL query to properly join the pelanggan table
$order_query = "SELECT p.*, m.Nomeja, p.created_at as order_date, pl.Namapelanggan,
    CASE 
        WHEN EXISTS (SELECT 1 FROM transaksi t WHERE t.idpesanan = p.idpesanan) THEN 'Completed'
        ELSE 'Active'
    END as status
FROM pesanan p 
JOIN meja m ON p.meja_id = m.id 
JOIN pelanggan pl ON p.idpelanggan = pl.idpelanggan
WHERE p.idpesanan = ?
LIMIT 1";

$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

// Check if order exists and is not paid
if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Get order items with proper total calculation
$items_query = "SELECT p.*, m.Namamenu as nama, m.Harga as harga, (p.jumlah * m.Harga) as subtotal 
FROM pesanan p 
JOIN menu m ON p.idmenu = m.idmenu 
WHERE p.kode_pesanan = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("s", $order['kode_pesanan']);
$stmt->execute();
$items_result = $stmt->get_result();

// Calculate total
$total = 0;
while ($item = $items_result->fetch_assoc()) {
    $total += $item['subtotal'];
}
$items_result->data_seek(0); // Reset result pointer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Base styling for both screen and print */
        body {
            background-color: #f8f9fa;
        }
        
        .confirmation {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            font-family: 'Arial', sans-serif;
        }

        /* Print-specific styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            
            body {
                margin: 0;
                padding: 0;
                background-color: #fff;
            }
            
            .no-print, .sidebar, .content-wrapper-padding {
                display: none !important;
            }
            
            .content-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .confirmation {
                width: 100%;
                max-width: 100%;
                box-shadow: none !important;
                border: none !important;
                padding: 0;
                margin: 0;
            }
            
            /* Adjust font sizes for print */
            .confirmation h4 {
                font-size: 24pt;
                margin-bottom: 10mm;
            }
            
            .confirmation table {
                width: 100%;
                margin-bottom: 5mm;
                font-size: 12pt;
            }
            
            .confirmation td, .confirmation th {
                padding: 2mm 0;
                font-size: 12pt;
            }
            
            .confirmation p, .confirmation div {
                font-size: 12pt;
                line-height: 1.5;
            }
            
            .confirmation small {
                font-size: 10pt;
            }
            
            .confirmation .text-center {
                text-align: center;
            }
            
            .border-top, .border-bottom {
                border-color: #000 !important;
                border-width: 1px !important;
            }
            
            /* Make sure the items section grows to fill available space */
            .items-section {
                min-height: 40vh;
            }

            .signature-section {
                margin-top: 20mm;
            }
        }
        
        /* Styling for the screen view */
        @media screen {
            .content-wrapper {
                margin-left: 280px;
                margin-top: 20px;
                padding: 20px;
            }
            
            @media (max-width: 768px) {
                .content-wrapper {
                    margin-left: 0;
                    padding: 15px;
                }
            }
            
            .confirmation {
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
                border: 1px solid #dee2e6;
            }
        }
        
        /* Common styling for both screen and print */
        .confirmation-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .confirmation-info {
            margin-bottom: 15px;
        }
        
        .items-table {
            width: 100%;
            margin-bottom: 15px;
        }
        
        .signature-section {
            margin-top: 50px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 150px;
            margin: 10px auto;
        }
    </style>
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>

    <div class="content-wrapper">
        <style>
            .content-wrapper {
                margin-left: var(--sidebar-width);
                padding: var(--content-padding);
                padding-top: var(--content-padding-top);
                min-height: 100vh;
                transition: all var(--transition-speed) var(--transition-type);
                position: relative;
                z-index: 1;
            }
            
            .content-wrapper.shifted {
                margin-left: 0;
            }
            
            .content-wrapper.collapsed-sidebar {
                margin-left: var(--sidebar-collapsed-width);
            }
            
            @media (max-width: 768px) {
                .content-wrapper {
                    margin-left: 0;
                    padding: 1rem;
                    padding-top: var(--content-padding-top);
                }
            }
        </style>
        <div class="no-print mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Order Confirmation</h2>
                <div>
                    <a href="edit_order.php?id=<?php echo $order_id; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edit Order
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print Confirmation
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

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
                        <!-- Fixed customer name display here -->
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
                        <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                <td class="text-end"><?php echo $item['jumlah']; ?></td>
                                <td class="text-end">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endwhile; ?>
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
                    <!-- Perbaikan untuk menampilkan nama pelanggan -->
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
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>