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

// --- LOGIKA FILTER DAN QUERY (TETAP SAMA) ---
// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get transactions for the selected date range
$transactions_query = "SELECT t.*, p.kode_pesanan, m.Nomeja, 
    GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' x', p2.jumlah) SEPARATOR ', ') as items,
    SUM(p2.jumlah * menu.Harga) as total_amount, -- Ini adalah total harga pesanan
    t.bayar - SUM(p2.jumlah * menu.Harga) as kembalian -- Hitung kembalian
FROM transaksi t 
JOIN pesanan p ON t.idpesanan = p.idpesanan 
JOIN meja m ON p.meja_id = m.id
JOIN pesanan p2 ON p2.kode_pesanan = p.kode_pesanan -- Join ulang untuk detail item
JOIN menu ON p2.idmenu = menu.idmenu
WHERE DATE(t.created_at) BETWEEN ? AND ?
GROUP BY t.idtransaksi -- Group berdasarkan transaksi unik
ORDER BY t.created_at DESC";

$stmt_transactions = $conn->prepare($transactions_query);
if ($stmt_transactions === false) {
    die("Error preparing statement (transactions): " . $conn->error);
}
$stmt_transactions->bind_param("ss", $start_date, $end_date);
$stmt_transactions->execute();
$transactions_result = $stmt_transactions->get_result();

// Calculate total revenue for the selected period with proper grouping
$revenue_query = "SELECT SUM(total_amount) as total_revenue FROM (
    SELECT t.idtransaksi, SUM(p.jumlah * menu.Harga) as total_amount
    FROM transaksi t
    JOIN pesanan p ON t.idpesanan = p.idpesanan -- Join ke pesanan untuk detail harga & jumlah
    JOIN menu ON p.idmenu = menu.idmenu
    WHERE DATE(t.created_at) BETWEEN ? AND ?
    GROUP BY t.idtransaksi -- Group berdasarkan transaksi unik
) as daily_totals";
$stmt_revenue = $conn->prepare($revenue_query);
if ($stmt_revenue === false) {
     die("Error preparing statement (revenue): " . $conn->error);
}
$stmt_revenue->bind_param("ss", $start_date, $end_date);
$stmt_revenue->execute();
$revenue_result = $stmt_revenue->get_result();
$revenue_data = $revenue_result->fetch_assoc();
$revenue = $revenue_data['total_revenue'] ?? 0;
// --- AKHIR LOGIKA FILTER DAN QUERY ---

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Kasir Resto</title>
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
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Agar responsif */
            gap: 1rem; /* Jarak antar elemen */
            margin-bottom: 1.5rem;
        }
        .page-header h2 {
             margin-bottom: 0;
             font-weight: 600;
             color: var(--color-text-dark);
        }

        .filter-form {
            display: flex;
            gap: 0.5rem; /* Jarak antar input form */
            align-items: center;
        }
        .filter-form .form-control {
            max-width: 160px; /* Batasi lebar input tanggal */
            font-size: 0.9rem;
        }
        .filter-form .btn {
            padding: 0.375rem 0.8rem; /* Sesuaikan padding tombol filter */
        }

        .revenue-card {
            background: linear-gradient(45deg, var(--color-primary), var(--color-primary-hover));
            color: var(--color-text-light);
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px var(--color-shadow);
            margin-bottom: 1.5rem;
            padding: 1.5rem;
        }
        .revenue-card .card-title {
            font-weight: 500;
            margin-bottom: 0.5rem;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .revenue-card .revenue-amount {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .revenue-card .period-info {
            font-size: 0.85rem;
            opacity: 0.8;
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
            font-size: 0.9rem; /* Ukuran font tabel sedikit lebih kecil */
        }
        .table thead {
            background-color: var(--color-primary);
            color: var(--color-text-light);
        }
         .table thead th {
            border-bottom-width: 0;
            padding: 0.8rem 1rem; /* Padding header tabel */
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            white-space: nowrap;
            vertical-align: middle;
         }
        .table tbody tr:hover {
            background-color: rgba(6, 214, 160, 0.1);
        }
        .table td {
            vertical-align: middle;
            padding: 0.7rem 1rem; /* Padding cell tabel */
            border-top: 1px solid var(--color-border); /* Garis antar baris */
        }
        .table tbody tr:first-child td {
             border-top: none; /* Hapus border atas pada baris pertama */
        }
        .table .items-list {
            max-width: 250px; /* Batasi lebar kolom item */
            white-space: normal; /* Biarkan teks wrap */
            font-size: 0.85rem;
            line-height: 1.4;
        }
        .table .text-end { text-align: right !important; }
        .table .text-center { text-align: center !important; }


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
            padding: 0.25rem 0.6rem; /* Ukuran tombol kecil */
            font-size: 0.75rem;
        }
        .btn-action {
             width: 32px; /* Lebar tetap untuk tombol aksi */
             height: 32px;
             display: inline-flex;
             align-items: center;
             justify-content: center;
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

            <div class="page-header">
                <h2>Riwayat Transaksi</h2>
                <form class="filter-form" method="GET">
                    <label for="start_date" class="form-label mb-0 small">Dari:</label>
                    <input type="date" class="form-control form-control-sm" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <label for="end_date" class="form-label mb-0 small">Sampai:</label>
                    <input type="date" class="form-control form-control-sm" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel-fill"></i> Filter
                    </button>
                </form>
            </div>

            <div class="row">
                <div class="col-12">
                     <div class="revenue-card">
                        <div class="card-title">
                             <i class="bi bi-wallet2"></i> Total Pendapatan
                        </div>
                        <p class="revenue-amount">Rp <?php echo number_format($revenue, 0, ',', '.'); ?></p>
                        <p class="period-info mb-0">
                            Periode: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="main-card">
                         <div class="card-header">
                           <i class="bi bi-list-ul"></i> Detail Transaksi
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Kode Pesanan</th>
                                            <th>Meja</th>
                                            <th>Item</th>
                                            <th class="text-end">Total Harga</th>
                                            <th class="text-end">Dibayar</th>
                                            <th class="text-end">Kembalian</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($transactions_result && $transactions_result->num_rows > 0): ?>
                                            <?php while ($transaction = $transactions_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/y H:i', strtotime($transaction['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['kode_pesanan']); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['Nomeja']); ?></td>
                                                    <td>
                                                        <div class="items-list">
                                                            <?php 
                                                            // Ganti koma dengan line break untuk tampilan lebih baik
                                                            $items_formatted = str_replace(', ', '<br>', htmlspecialchars($transaction['items']));
                                                            echo $items_formatted; 
                                                            ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">Rp <?php echo number_format($transaction['total_amount'] ?? 0, 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($transaction['bayar'] ?? 0, 0, ',', '.'); ?></td>
                                                    <td class="text-end">Rp <?php echo number_format($transaction['kembalian'] ?? 0, 0, ',', '.'); ?></td>
                                                    <td class="text-center">
                                                    <a href="print_receipt.php?id=<?php echo $transaction['idpesanan']; ?>"
                                                           class="btn btn-sm btn-primary btn-action" 
                                                           title="Cetak Struk">
                                                            <i class="bi bi-printer-fill"></i>
                                                        </a>
                                                        </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4">Tidak ada transaksi ditemukan untuk periode yang dipilih.</td>
                                            </tr>
                                        <?php endif; ?>
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
        //     // ... (kode toggle sidebar seperti sebelumnya) ...
        // });
    </script>
</body>
</html>
