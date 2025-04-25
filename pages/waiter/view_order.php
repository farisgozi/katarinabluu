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
    <title>Detail Pesanan - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../css/sidebar.css" rel="stylesheet"> <!-- Assuming sidebar CSS is needed -->
    <style>
        /* Keep only necessary base styles if any, or remove entirely if Bootstrap covers it */
        body {
            background-color: #f8f9fa;
        }

        /* Remove specific .confirmation styles */
        /* Remove @media screen styles targeting .content-wrapper if they conflict */
        /* Remove .confirmation-header, .confirmation-info specific layout styles */
        /* Remove .items-table specific layout styles if Bootstrap table classes are used */
        /* Remove .signature-section styles */

        /* Ensure content wrapper works with sidebar */
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
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>

    <div class="content-wrapper">
        <!-- Remove inline style block for content-wrapper as it's now in <head> -->

        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Detail Pesanan #<?php echo htmlspecialchars($order['idpesanan']); ?></h2>
                <div>
                    <a href="edit_order.php?id=<?php echo $order_id; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edit Pesanan
                    </a>
                    <a href="print_confirmation.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-info">
                        <i class="bi bi-printer"></i> Cetak Konfirmasi
                    </a>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    Informasi Pesanan
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p><strong>Nomor Pesanan:</strong> <?php echo htmlspecialchars($order['idpesanan']); ?></p>
                            <p><strong>Meja:</strong> <?php echo htmlspecialchars($order['Nomeja']); ?></p>
                            <p><strong>Pelanggan:</strong> <?php echo htmlspecialchars($order['Namapelanggan']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p><strong>Tanggal:</strong> <?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></p>
                            <p><strong>Waiter:</strong> <?php echo htmlspecialchars($user['nama']); ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?php echo $order['status'] === 'Active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($order['status']); ?></span></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    Item Pesanan
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col" class="text-end">Jumlah</th>
                                    <th scope="col" class="text-end">Harga Satuan</th>
                                    <th scope="col" class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $items_result->data_seek(0); // Reset pointer again just in case ?>
                                <?php while ($item = $items_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                        <td class="text-end"><?php echo $item['jumlah']; ?></td>
                                        <td class="text-end">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                        <td class="text-end">Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="3" class="text-end">Total Keseluruhan</td>
                                    <td class="text-end">Rp <?php echo number_format($total, 0, ',', '.'); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Removed confirmation structure -->
            <!-- Removed signature section -->
            <!-- Removed confirmation footer -->

        </div> <!-- End container-fluid -->
    </div> <!-- End content-wrapper -->

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include sidebar JS if needed -->
    <!-- <script src="../../js/sidebar.js"></script> -->
</body>
</html>