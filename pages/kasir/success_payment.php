<?php
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Berhasil - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --success-color: var(--primary-color);
        }
        body {
            background-color: var(--background-color);
            color: var(--text-color);
        }
        .success-icon {
            font-size: 5rem;
            color: var(--success-color);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .content-wrapper {
            margin-left: var(--sidebar-width);
            padding: var(--content-padding);
            padding-top: var(--content-padding-top);
            min-height: 100vh;
            transition: all var(--transition-speed) var(--transition-type);
            position: relative;
            z-index: 1;
        }
        .card {
            background: rgba(26, 32, 44, 0.8);
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px var(--shadow-color);
        }
        .card-title {
            color: var(--primary-color);
        }
        .card-text {
            color: var(--text-color);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #05b386;
            border-color: #05b386;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background-color: rgba(248, 255, 229, 0.1);
            border-color: var(--border-color);
            color: var(--text-color);
        }
        .btn-secondary:hover {
            background-color: rgba(248, 255, 229, 0.2);
            border-color: var(--border-color);
            color: var(--text-color);
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
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <i class="bi bi-check-circle-fill success-icon mb-4"></i>
                            <h2 class="card-title mb-4">Pembayaran Berhasil!</h2>
                            <p class="card-text mb-2">Nomor Meja: <?php echo htmlspecialchars($order['Nomeja']); ?></p>
                            <p class="card-text mb-2">Kode Pesanan: <?php echo htmlspecialchars($order['kode_pesanan']); ?></p>
                            <p class="card-text mb-4">Total Pembayaran: Rp <?php echo number_format($order['total'], 0, ',', '.'); ?></p>
                            
                            <div class="d-grid gap-3">
                                <a href="print_receipt.php?id=<?php echo $order_id; ?>" class="btn btn-primary">
                                    <i class="bi bi-printer"></i> Cetak Struk
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-clock-history"></i> Kembali ke Riwayat Pesanan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>