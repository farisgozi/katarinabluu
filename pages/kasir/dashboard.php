<?php
session_start();
require_once '../../config/config.php'; // Pastikan path ini benar

// HAPUS JIKA TIDAK ADA/DIBUTUHKAN
// require_once '../../functions/functions.php'; 

// Check if user is logged in and is kasir
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'kasir') {
    header("Location: ../../pages/auth/login.php"); // Pastikan path ini benar
    exit();
}

// HAPUS JIKA TIDAK ADA/DIBUTUHKAN
// check_session_timeout(); 

// Get user data
$user = $_SESSION['user'];

// Pastikan koneksi $conn tersedia dari config.php
if (!$conn) {
     die("Koneksi database gagal.");
}


// --- Get orders ready for payment (LOGIKA QUERY DIPERBARUI) ---
$orders_query = "SELECT 
    m.id as meja_id, 
    m.Nomeja,
    MIN(p.created_at) as created_at,
    GROUP_CONCAT(DISTINCT p.kode_pesanan) as kode_pesanan,
    MIN(p.idpesanan) as idpesanan,
    GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' x', p.jumlah) SEPARATOR ', ') as items,
    SUM(p.jumlah * menu.Harga) as total_amount
FROM meja m
JOIN pesanan p ON p.meja_id = m.id
JOIN menu ON p.idmenu = menu.idmenu
WHERE NOT EXISTS (SELECT 1 FROM transaksi t WHERE t.idpesanan = p.idpesanan)
GROUP BY m.id, m.Nomeja
ORDER BY m.Nomeja, created_at";
$orders_result = $conn->query($orders_query);
// Hitung jumlah pesanan menunggu pembayaran
$pending_orders_count = $orders_result ? $orders_result->num_rows : 0;


// --- Get today's transactions (LOGIKA QUERY TETAP SAMA) ---
$today = date('Y-m-d');
$transactions_query = "SELECT t.*, p.kode_pesanan, m.Nomeja,
    GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' x', p2.jumlah) SEPARATOR ', ') as items,
    SUM(p2.jumlah * menu.Harga) as total_amount_per_transaction -- Alias diubah agar tidak konflik
FROM transaksi t 
JOIN pesanan p ON t.idpesanan = p.idpesanan 
JOIN meja m ON p.meja_id = m.id
JOIN pesanan p2 ON p2.kode_pesanan = p.kode_pesanan -- Join ulang untuk detail item
JOIN menu ON p2.idmenu = menu.idmenu
WHERE DATE(t.created_at) = ?
GROUP BY t.idtransaksi -- Group berdasarkan transaksi unik
ORDER BY t.created_at DESC";
$stmt_transactions = $conn->prepare($transactions_query);
if ($stmt_transactions === false) {
    die("Error preparing statement (transactions): " . $conn->error);
}
$stmt_transactions->bind_param("s", $today);
$stmt_transactions->execute();
$transactions_result = $stmt_transactions->get_result();
// Hitung jumlah transaksi hari ini
$today_transactions_count = $transactions_result ? $transactions_result->num_rows : 0;


// --- Calculate today's total revenue (LOGIKA QUERY TETAP SAMA) ---
$revenue_query = "SELECT SUM(total_amount) as total_revenue FROM (
    SELECT t.idtransaksi, SUM(p.jumlah * menu.Harga) as total_amount
    FROM transaksi t
    JOIN pesanan p ON t.idpesanan = p.idpesanan -- Join ke pesanan untuk detail harga & jumlah
    JOIN menu ON p.idmenu = menu.idmenu
    WHERE DATE(t.created_at) = ?
    GROUP BY t.idtransaksi -- Group berdasarkan transaksi unik
) as daily_totals";
$stmt_revenue = $conn->prepare($revenue_query);
if ($stmt_revenue === false) {
     die("Error preparing statement (revenue): " . $conn->error);
}
$stmt_revenue->bind_param("s", $today);
$stmt_revenue->execute();
$revenue_result = $stmt_revenue->get_result();
$revenue_data = $revenue_result->fetch_assoc();
$revenue = $revenue_data['total_revenue'] ?? 0;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Dashboard - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --content-padding: 1.5rem;
            --content-padding-top: 60px; /* Sesuaikan jika ada navbar atas */
            --transition-speed: 0.3s;
            --transition-type: ease;

            /* Tema Warna Light Yellow Emerald */
            --color-bg-light: #F8FFE5; /* Light Yellow */
            --color-primary: #06D6A0; /* Emerald */
            --color-primary-hover: #05b98a; /* Sedikit lebih gelap untuk hover */
            --color-secondary: #EF476F; /* Warna sekunder (opsional, contoh: merah muda) */
            --color-warning: #FFD166; /* Warna warning (opsional, contoh: kuning) */
            --color-info: #118ab2; /* Warna info (opsional, contoh: biru) */
            --color-text-dark: #1a1a1a; /* Teks gelap agar kontras */
            --color-text-light: #FFFFFF; /* Teks terang untuk background gelap/warna primer */
            --color-border: #dee2e6; /* Warna border default */
            --color-card-bg: #FFFFFF; /* Background card */
            --color-shadow: rgba(0, 0, 0, 0.08); /* Warna shadow */
        }

        body {
            background-color: var(--color-bg-light);
            color: var(--color-text-dark);
            font-family: 'Poppins', sans-serif; /* Contoh penggunaan font */
        }

        /* --- Styling Sidebar (Asumsi dari include) --- */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background-color: #212529; color: #fff;
            transition: all var(--transition-speed) var(--transition-type);
            z-index: 1030; padding-top: 1rem;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        /* --- Akhir Styling Sidebar --- */

        /* --- Styling Content Wrapper --- */
        .content-wrapper {
            margin-left: var(--sidebar-width);
            padding: var(--content-padding-top) var(--content-padding);
            min-height: 100vh;
            transition: margin-left var(--transition-speed) var(--transition-type);
            position: relative; z-index: 1;
        }
        .content-wrapper.collapsed-sidebar { margin-left: var(--sidebar-collapsed-width); }
        @media (max-width: 768px) {
            .content-wrapper { margin-left: 0; padding: var(--content-padding-top) 1rem; }
            .sidebar { left: -var(--sidebar-width); }
            .sidebar.active { left: 0; }
        }
        /* --- Akhir Styling Content Wrapper --- */

        /* --- Styling Komponen Utama --- */
        .page-title {
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: var(--color-text-dark);
        }

        .stat-card {
            background-color: var(--color-card-bg);
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px var(--color-shadow);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
         .stat-card:hover {
             transform: translateY(-3px);
             box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
         }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-right: 1.5rem;
            padding: 0.8rem;
            border-radius: 50%;
            color: var(--color-text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px; /* Fixed width */
            height: 60px; /* Fixed height */
        }
        .stat-card .icon.bg-primary { background-color: var(--color-primary); }
        .stat-card .icon.bg-warning { background-color: var(--color-warning); color: var(--color-text-dark); }
        .stat-card .icon.bg-info { background-color: var(--color-info); }

        .stat-card .stat-info h5 {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            font-weight: 500;
        }
        .stat-card .stat-info p {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0;
            color: var(--color-text-dark);
        }

        .main-card { /* Card untuk tabel */
            background-color: var(--color-card-bg);
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px var(--color-shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .main-card .card-header {
            background-color: var(--color-card-bg);
            border-bottom: 1px solid var(--color-border);
            padding: 1rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-text-dark);
        }
         .main-card .card-header i {
             color: var(--color-primary); /* Icon di header card */
         }


        .main-card .card-body {
            padding: 0; /* Hapus padding default agar table-responsive rapat */
        }

        .table {
            margin-bottom: 0;
            border-color: var(--color-border);
        }
        .table thead {
            background-color: var(--color-primary);
            color: var(--color-text-light);
        }
         .table thead th {
            border-bottom-width: 0;
            padding: 1rem 1.5rem; /* Sesuaikan padding header tabel */
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            white-space: nowrap; /* Agar header tidak wrap */
         }
        .table tbody tr:hover {
            background-color: rgba(6, 214, 160, 0.1);
        }
        .table td {
            vertical-align: middle;
            padding: 0.9rem 1.5rem; /* Sesuaikan padding cell tabel */
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(248, 255, 229, 0.5);
        }
        .table-hover tbody tr:hover {
            background-color: rgba(6, 214, 160, 0.15);
            color: var(--color-text-dark);
        }

        .btn-primary {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: var(--color-text-light);
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--color-primary-hover);
            border-color: var(--color-primary-hover);
            color: var(--color-text-light);
        }
        .btn-sm {
            padding: 0.3rem 0.8rem; /* Ukuran tombol kecil */
            font-size: 0.8rem;
        }

        /* Scrollable Transaction List */
        .transactions-list-container {
            max-height: 400px; /* Atur tinggi maksimal */
            overflow-y: auto; /* Tambahkan scroll vertikal jika melebihi */
        }

    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../../components/sidebar.php'; // Pastikan path ini benar ?>

    <div class="content-wrapper" id="mainContent">
        <div class="container-fluid">

            <h2 class="page-title">Kasir Dashboard</h2>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card">
                        <div class="icon bg-primary">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div class="stat-info">
                            <h5>Pendapatan Hari Ini</h5>
                            <p>Rp <?php echo number_format($revenue, 0, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
                 <div class="col-lg-4 col-md-6">
                    <div class="stat-card">
                        <div class="icon bg-warning">
                             <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-info">
                            <h5>Pesanan Menunggu</h5>
                            <p><?php echo $pending_orders_count; ?></p>
                        </div>
                    </div>
                </div>
                 <div class="col-lg-4 col-md-12">
                    <div class="stat-card">
                        <div class="icon bg-info">
                           <i class="bi bi-check2-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h5>Transaksi Hari Ini</h5>
                            <p><?php echo $today_transactions_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-7 mb-4">
                    <div class="main-card">
                        <div class="card-header">
                           <i class="bi bi-clock-history"></i> Pesanan Siap Dibayar
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kode Pesanan</th>
                                            <th>Meja</th>
                                            <th>Item Pesanan</th>
                                            <th>Total</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($pending_orders_count === 0): ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">Tidak ada pesanan menunggu pembayaran</td>
                                            </tr>
                                        <?php else:
                                            // Reset pointer hasil query jika sudah terpakai untuk count
                                            if ($orders_result) $orders_result->data_seek(0); 
                                            while ($order = $orders_result->fetch_assoc()): 
                                                $kode_pesanan_array = explode(',', $order['kode_pesanan']);
                                                $idpesanan_array = explode(',', $order['idpesanan']);
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(implode(', ', $kode_pesanan_array)); ?></td>
                                                <td><?php echo htmlspecialchars($order['Nomeja']); ?></td>
                                                <td><?php echo nl2br(htmlspecialchars($order['items'])); ?></td>
                                                <td>Rp <?php echo number_format($order['total_amount'] ?? 0, 0, ',', '.'); ?></td>
                                                <td>
                                                <a href="process_payment.php?id=<?php echo $order['idpesanan']; ?>" class="btn btn-sm btn-primary" title="Proses Pembayaran">
                                                    <i class="bi bi-cash-stack"></i> Bayar
                                                </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5 mb-4">
                    <div class="main-card">
                         <div class="card-header">
                           <i class="bi bi-receipt"></i> Transaksi Hari Ini
                        </div>
                        <div class="card-body transactions-list-container">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Total</th>
                                            <th>Waktu</th>
                                            </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($today_transactions_count === 0): ?>
                                            <tr>
                                                <td colspan="3" class="text-center py-4">Belum ada transaksi hari ini</td>
                                            </tr>
                                        <?php else: 
                                             // Reset pointer hasil query jika sudah terpakai untuk count
                                            if ($transactions_result) $transactions_result->data_seek(0);
                                            while ($transaction = $transactions_result->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($transaction['kode_pesanan'] ?: 'N/A'); ?></td>
                                                <td>Rp <?php echo number_format($transaction['total_amount_per_transaction'] ?? 0, 0, ',', '.'); ?></td>
                                                <td><?php echo date('H:i', strtotime($transaction['created_at'])); ?></td>
                                                </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div></div><script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Optional: Add JS for sidebar toggle if needed
        // document.addEventListener('DOMContentLoaded', function() {
        //     const toggleButton = document.getElementById('sidebarToggle'); // Ganti ID jika berbeda
        //     const mainContent = document.getElementById('mainContent');
        //     const sidebar = document.querySelector('.sidebar');
        //     if (toggleButton && mainContent && sidebar) {
        //         toggleButton.addEventListener('click', function() {
        //             sidebar.classList.toggle('collapsed');
        //             mainContent.classList.toggle('collapsed-sidebar');
        //         });
        //     }
        // });
    </script>
</body>
</html>