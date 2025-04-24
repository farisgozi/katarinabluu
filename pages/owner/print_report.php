<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is owner
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'owner') {
    header("Location: ../../pages/auth/login.php");
    exit();
}

// Check session timeout
check_session_timeout();

// Get user data
$user = $_SESSION['user'];

// Get filter parameters
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'daily';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Adjust dates based on filter type
switch ($filter_type) {
    case 'weekly':
        // Current week
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'monthly':
        // Current month
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
        break;
    case 'yearly':
        // Current year
        $start_date = date('Y-01-01');
        $end_date = date('Y-12-31');
        break;
    case 'custom':
        // Custom date range from form
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        break;
}

// Format dates for display
$formatted_start_date = date('d F Y', strtotime($start_date));
$formatted_end_date = date('d F Y', strtotime($end_date));
$report_title = "Laporan Penjualan Periode: $formatted_start_date - $formatted_end_date";

// Get sales data for the selected period
$sales_query = "SELECT 
    MIN(t.created_at) as transaction_date,
    SUM(t.total) as total_sales,
    COUNT(DISTINCT t.idtransaksi) as total_transactions
    FROM transaksi t
    WHERE DATE(t.created_at) BETWEEN ? AND ?
    GROUP BY DATE(t.created_at)
    ORDER BY transaction_date";

$stmt = $conn->prepare($sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_result = $stmt->get_result();

// Calculate total sales and transactions
$total_sales = 0;
$total_transactions = 0;
$sales_data = [];

while ($row = $sales_result->fetch_assoc()) {
    $total_sales += $row['total_sales'];
    $total_transactions += $row['total_transactions'];
    $sales_data[] = $row;
}

// Get total items ordered
$items_query = "SELECT 
    SUM(p.jumlah) as total_items
    FROM pesanan p
    JOIN transaksi t ON p.idpesanan = t.idpesanan
    WHERE DATE(t.created_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($items_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$items_result = $stmt->get_result();
$items_row = $items_result->fetch_assoc();
$total_items = $items_row['total_items'] ?? 0;

// Get most popular items
$popular_items_query = "SELECT 
    m.Namamenu as menu_name,
    SUM(p.jumlah) as total_ordered
    FROM pesanan p
    JOIN menu m ON p.idmenu = m.idmenu
    JOIN transaksi t ON p.idpesanan = t.idpesanan
    WHERE DATE(t.created_at) BETWEEN ? AND ?
    GROUP BY p.idmenu
    ORDER BY total_ordered DESC
    LIMIT 10";

$stmt = $conn->prepare($popular_items_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$popular_items_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $report_title; ?></title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../css/report.css" rel="stylesheet">
    <link href="../../css/owner.css" rel="stylesheet">
</head>
<body class="bg-light">
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
                <h2>Laporan Penjualan</h2>
                <div>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="report shadow-sm border">
            <div class="text-center mb-4">
                <h2 class="mb-0">Kasir Resto</h2>
                <p class="mb-1">Jl. Restaurant No. 123</p>
                <p class="mb-3">Phone: (123) 456-7890</p>
                <h4><?php echo $report_title; ?></h4>
            </div>

            <div class="mb-4">
                <h5>Ringkasan Penjualan</h5>
                <table class="table table-bordered">
                    <tr>
                        <td width="30%">Total Penjualan</td>
                        <td>Rp <?php echo number_format($total_sales, 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>Total Transaksi</td>
                        <td><?php echo number_format($total_transactions, 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>Total Item Terjual</td>
                        <td><?php echo number_format($total_items, 0, ',', '.'); ?></td>
                    </tr>
                </table>
            </div>

            <div class="mb-4">
                <h5>Detail Penjualan Harian</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th class="text-end">Jumlah Transaksi</th>
                            <th class="text-end">Total Penjualan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_data as $sale): ?>
                            <tr>
                                <td><?php echo date('d F Y', strtotime($sale['transaction_date'])); ?></td>
                                <td class="text-end"><?php echo number_format($sale['total_transactions'], 0, ',', '.'); ?></td>
                                <td class="text-end">Rp <?php echo number_format($sale['total_sales'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end"><?php echo number_format($total_transactions, 0, ',', '.'); ?></td>
                            <td class="text-end">Rp <?php echo number_format($total_sales, 0, ',', '.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mb-4">
                <h5>Item Terlaris</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Nama Menu</th>
                            <th class="text-end">Jumlah Terjual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $popular_items_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['menu_name']); ?></td>
                                <td class="text-end"><?php echo number_format($item['total_ordered'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-4">
                <p class="mb-0"><small>Laporan ini dicetak pada <?php echo date('d F Y H:i'); ?></small></p>
            </div>
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>