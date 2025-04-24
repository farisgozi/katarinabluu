<?php
// Existing PHP code remains unchanged 
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is kasir
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'kasir') {
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

// Get order information
$order_query = "SELECT p.*, m.Nomeja, t.total, t.bayar, t.created_at as transaction_date
FROM pesanan p 
JOIN meja m ON p.meja_id = m.id 
JOIN transaksi t ON p.idpesanan = t.idpesanan 
WHERE p.idpesanan = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

// Check if order exists and is paid
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

// Get transaction information
$transaction_query = "SELECT * FROM transaksi WHERE idpesanan = ?";
$stmt = $conn->prepare($transaction_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        /* Base styling for both screen and print */
        body {
            background-color: #f8f9fa;
        }
        
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            font-family: 'Courier New', monospace;
        }

        /* Print-specific styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
            
            /* Hide ALL navigation elements when printing */
            .sidebar,
            .sidebar-toggle,
            .sidebar-brand,
            .sidebar-menu,
            .sidebar-item,
            .sidebar-link,
            .sidebar-icon,
            .sidebar-text,
            .sidebar-footer,
            #sidebar,
            #sidebarToggle,
            .navbar,
            .nav-link,
            .hamburger-menu,
            .no-print,
            .sidebar-wrapper,
            .navbar-toggler,
            .navbar-collapse,
            button[data-bs-toggle="collapse"],
            .offcanvas,
            .offcanvas-collapse,
            [class*="sidebar"],
            [id*="sidebar"],
            [class*="menu"],
            [class*="nav"],
            [class*="navbar"],
            [class*="bi"],
            [class^="bi"],
            .bi,
            /* Additional selectors for navigation elements */
            .nav-toggle,
            .menu-toggle,
            .menu-button,
            .menu-icon,
            .toggle-button,
            .toggle-icon,
            /* Hide ALL icons and buttons */
            [class*="icon"],
            [class*="btn"],
            button:not([type="submit"]) {
                display: none !important;
                width: 0 !important;
                height: 0 !important;
                position: absolute !important;
                overflow: hidden !important;
                clip: rect(0, 0, 0, 0) !important;
            }

            body {
                margin: 0;
                padding: 0;
                background-color: #fff;
                width: 100% !important;
            }
            
            .no-print, .sidebar, .content-wrapper-padding {
                display: none !important;
            }
            
            .content-wrapper {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .receipt {
                width: 100%;
                max-width: 100%;
                box-shadow: none !important;
                border: none !important;
                padding: 0;
                margin: 0;
            }
            
            /* Adjust font sizes for print */
            .receipt h4 {
                font-size: 32pt;
                margin-bottom: 10mm;
            }
            
            .receipt table {
                width: 100%;
                margin-bottom: 5mm;
                font-size: 18pt;
            }
            
            .receipt td, .receipt th {
                padding: 2mm 0;
                font-size: 18pt;
            }
            
            .receipt p, .receipt div {
                font-size: 18pt;
                line-height: 1.5;
            }
            
            .receipt small {
                font-size: 10pt;
            }
            
            .receipt .text-center {
                text-align: center;
            }
            
            .border-top, .border-bottom {
                border-color: #000 !important;
                border-width: 1px !important;
            }
            
            /* Make sure the items section grows to fill available space */
            .items-section {
                min-height: 50vh;
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
            
            .receipt {
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
                border: 1px solid #dee2e6;
            }
        }
        
        /* Common styling for both screen and print */
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .receipt-info {
            margin-bottom: 15px;
        }
        
        .items-table {
            width: 100%;
            margin-bottom: 15px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
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
                <h2>Receipt</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print Receipt
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="receipt">
            <div class="receipt-header">
                <h4 class="mb-1">Kasir Resto</h4>
                <p class="mb-0">Jl. Restaurant No. 123</p>
                <p>Phone: (123) 456-7890</p>
            </div>

            <div class="receipt-info">
                <div class="row">
                    <div class="col-6">
                        <p class="mb-0">Order #<?php echo htmlspecialchars($order['idpesanan']); ?></p>
                        <p>Table #<?php echo htmlspecialchars($order['Nomeja']); ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <p><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></p>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                <td class="text-end"><?php echo $item['jumlah']; ?></td>
                                <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="mb-4">
                <div class="row fw-bold">
                    <div class="col-6">Total</div>
                    <div class="col-6 text-end">Rp <?php echo number_format($total, 0, ',', '.'); ?></div>
                </div>
                <div class="row">
                    <div class="col-6">Payment</div>
                    <div class="col-6 text-end">Rp <?php echo number_format($order['bayar'], 0, ',', '.'); ?></div>
                </div>
                <div class="row fw-bold">
                    <div class="col-6">Change</div>
                    <div class="col-6 text-end">Rp <?php echo number_format($order['bayar'] - $total, 0, ',', '.'); ?></div>
                </div>
            </div>

            <div class="receipt-footer">
                <p class="mb-0">Thank you for dining with us!</p>
                <p>Please come again</p>
            </div>
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>