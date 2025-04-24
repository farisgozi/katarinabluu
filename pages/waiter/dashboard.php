<?php
session_start();
require_once '../../config/config.php'; // Pastikan path ini benar

// HAPUS JIKA TIDAK ADA/DIBUTUHKAN
// require_once '../../functions/functions.php';

// Check if user is logged in and is waiter
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'waiter') {
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

// --- Get available tables ---
$tables_query = "SELECT * FROM meja ORDER BY CAST(Nomeja AS UNSIGNED)"; // Urutkan numerik
$tables_result = $conn->query($tables_query);
if ($tables_result === false) {
    die("Error fetching tables: " . $conn->error);
}

// --- Calculate table stats ---
$available_tables_count = 0;
$occupied_tables_count = 0;
if ($tables_result->num_rows > 0) {
    // Loop sementara untuk menghitung status
    while ($table_stat = $tables_result->fetch_assoc()) {
        if ($table_stat['status'] === 'kosong') {
            $available_tables_count++;
        } else {
            $occupied_tables_count++;
        }
    }
    // Kembalikan pointer ke awal untuk loop utama
    $tables_result->data_seek(0);
}


// --- Get active orders (Grouped by kode_pesanan and table) ---
// PERBAIKAN: Query diubah untuk mengelompokkan berdasarkan kode_pesanan dan meja
$active_orders_query = "SELECT
    m.Nomeja as nomor_meja,
    p.kode_pesanan, -- Kunci utama untuk grouping pesanan aktif per meja
    m.id as meja_id, -- ID Meja jika diperlukan
    MIN(p.created_at) as created_at, -- Waktu item pertama ditambahkan untuk pesanan ini
    GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' (x', p.jumlah, ')') SEPARATOR ',<br>') as items, -- Gabungkan semua item, pisahkan dengan koma dan baris baru
    SUM(p.jumlah * menu.Harga) as total_amount, -- Total harga untuk semua item dalam pesanan ini
    'Active' as status -- Status selalu 'Active' karena difilter oleh WHERE t.idtransaksi IS NULL
FROM pesanan p
JOIN meja m ON p.meja_id = m.id
JOIN menu ON p.idmenu = menu.idmenu
LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan -- Cek apakah ada item yg sudah masuk transaksi
WHERE t.idtransaksi IS NULL -- Hanya ambil pesanan yang BELUM masuk transaksi
GROUP BY m.Nomeja, p.kode_pesanan, m.id -- Kelompokkan berdasarkan Meja dan Kode Pesanan
ORDER BY MIN(p.created_at) DESC"; // Urutkan berdasarkan waktu pesanan terlama

$active_orders_result = $conn->query($active_orders_query);
if ($active_orders_result === false) {
    // Tampilkan error dan query untuk debugging
    die("Error fetching active orders: " . $conn->error . " <br>Query: <pre>" . htmlspecialchars($active_orders_query) . "</pre>");
}

// --- Calculate distinct active orders count for stats ---
// PERBAIKAN: Query ini sekarang cocok dengan logika grouping di atas
$active_orders_count_query = "SELECT COUNT(DISTINCT CONCAT(p.meja_id, '-', p.kode_pesanan)) as count
FROM pesanan p
LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan
WHERE t.idtransaksi IS NULL";
$count_result = $conn->query($active_orders_count_query);
$distinct_active_orders_count = 0;
if ($count_result) {
    $count_row = $count_result->fetch_assoc();
    $distinct_active_orders_count = $count_row['count'] ?? 0;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiter Dashboard - Kasir Resto</title>
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
            --color-secondary: #EF476F; /* Warna sekunder (contoh: merah muda untuk 'occupied') */
            --color-success: #28a745; /* Warna sukses (contoh: hijau untuk 'available') */
            --color-warning: #FFC107; /* Warna warning (Bootstrap default yellow) */
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

        /* Stat Cards (Mirip Kasir Dashboard) */
        .stat-card {
            background-color: var(--color-card-bg);
            border: none; border-radius: 0.5rem;
            box-shadow: 0 4px 12px var(--color-shadow);
            margin-bottom: 1.5rem; padding: 1.5rem;
            display: flex; align-items: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
         .stat-card:hover {
             transform: translateY(-3px);
             box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
         }
        .stat-card .icon {
            font-size: 2.5rem; margin-right: 1.5rem; padding: 0.8rem;
            border-radius: 50%; color: var(--color-text-light);
            display: flex; align-items: center; justify-content: center;
            width: 60px; height: 60px;
        }
        .stat-card .icon.bg-success { background-color: var(--color-success); }
        .stat-card .icon.bg-secondary { background-color: var(--color-secondary); }
        .stat-card .icon.bg-info { background-color: var(--color-info); }
        .stat-card .stat-info h5 {
            font-size: 0.9rem; color: #6c757d; margin-bottom: 0.25rem;
            text-transform: uppercase; font-weight: 500;
        }
        .stat-card .stat-info p {
            font-size: 1.75rem; font-weight: 600; margin-bottom: 0;
            color: var(--color-text-dark);
        }

        /* Card Utama untuk Grid/Tabel */
         .main-card {
             background-color: var(--color-card-bg);
             border: none; border-radius: 0.5rem;
             box-shadow: 0 4px 12px var(--color-shadow);
             margin-bottom: 1.5rem; overflow: hidden;
         }
         .main-card .card-header {
             background-color: var(--color-card-bg);
             border-bottom: 1px solid var(--color-border);
             padding: 1rem 1.5rem; font-weight: 600;
             display: flex; align-items: center; gap: 0.5rem;
             color: var(--color-text-dark);
         }
          .main-card .card-header i { color: var(--color-primary); }
          .main-card .card-body { padding: 1.5rem; } /* Padding untuk grid meja */
          .main-card .card-body-table { padding: 0; } /* Padding 0 untuk tabel */

        /* Kartu Status Meja */
        .table-status-card {
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            text-align: center;
            padding: 1.2rem 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%; /* Pastikan tinggi kartu sama */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: var(--color-card-bg); /* Pastikan background */
        }
        .table-status-card:hover {
             transform: translateY(-3px);
             box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .table-status-card .table-number {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--color-text-dark);
        }
         .table-status-card .status-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.7em;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
            display: inline-flex; /* Agar icon dan teks sejajar */
            align-items: center;
            gap: 0.3rem;
         }
        .table-status-card .status-badge.available {
            background-color: var(--color-success);
            color: var(--color-text-light);
        }
         .table-status-card .status-badge.occupied {
            background-color: var(--color-secondary);
            color: var(--color-text-light);
        }
        .table-status-card .btn-new-order {
            width: 100%; /* Tombol full width */
        }

        /* Tabel Pesanan Aktif */
        .table {
            margin-bottom: 0; border-color: var(--color-border);
            font-size: 0.9rem;
        }
        .table thead {
            background-color: var(--color-primary);
            color: var(--color-text-light);
        }
         .table thead th {
            border-bottom-width: 0; padding: 0.8rem 1rem;
            text-transform: uppercase; letter-spacing: 0.5px;
            font-weight: 600; white-space: nowrap; vertical-align: middle;
         }
        .table tbody tr:hover { background-color: rgba(6, 214, 160, 0.1); }
        .table td {
            vertical-align: middle; padding: 0.7rem 1rem;
            border-top: 1px solid var(--color-border);
            /* Penyesuaian untuk kolom item */
            word-break: break-word; /* Agar teks panjang bisa wrap */
        }
         .table td:nth-child(3) { /* Kolom Item Pesanan */
             min-width: 200px; /* Beri lebar minimum agar tidak terlalu sempit */
             line-height: 1.5; /* Spasi antar baris item */
         }
        .table tbody tr:first-child td { border-top: none; }
        .table .btn-group .btn { margin-right: 0.3rem; } /* Jarak antar tombol aksi */
        .table .btn-group .btn:last-child { margin-right: 0; }
        /* Styling untuk badge status pesanan */
        .badge.bg-warning { background-color: var(--color-warning) !important; color: var(--color-text-dark) !important; }
        .badge.bg-success { background-color: var(--color-success) !important; }


        /* Tombol Primer */
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
        .btn-info { background-color: var(--color-info); border-color: var(--color-info); color: var(--color-text-light); }
        .btn-info:hover { filter: brightness(90%); }
        .btn-sm { padding: 0.25rem 0.6rem; font-size: 0.75rem; }

    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../../components/sidebar.php'; // Pastikan path ini benar ?>

    <div class="content-wrapper" id="mainContent">
        <div class="container-fluid">

            <h2 class="page-title">Waiter Dashboard</h2>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="stat-card">
                        <div class="icon bg-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="stat-info">
                            <h5>Meja Tersedia</h5>
                            <p><?php echo $available_tables_count; ?></p>
                        </div>
                    </div>
                </div>
                 <div class="col-lg-4 col-md-6">
                    <div class="stat-card">
                        <div class="icon bg-secondary">
                             <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stat-info">
                            <h5>Meja Terisi</h5>
                            <p><?php echo $occupied_tables_count; ?></p>
                        </div>
                    </div>
                </div>
                 <div class="col-lg-4 col-md-12">
                    <div class="stat-card">
                        <div class="icon bg-info">
                           <i class="bi bi-card-list"></i>
                        </div>
                        <div class="stat-info">
                            <h5>Pesanan Aktif</h5>
                            <p><?php echo $distinct_active_orders_count; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                 <div class="col-12">
                    <div class="main-card">
                         <div class="card-header">
                            <i class="bi bi-grid-3x3-gap-fill"></i> Status Meja
                         </div>
                         <div class="card-body">
                             <div class="row g-3">
                                <?php if ($tables_result && $tables_result->num_rows > 0): ?>
                                    <?php // Pastikan pointer di reset sebelum loop ini
                                         $tables_result->data_seek(0);
                                    ?>
                                    <?php while ($table = $tables_result->fetch_assoc()): ?>
                                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 d-flex">
                                            <div class="table-status-card flex-fill">
                                                <div>
                                                    <div class="table-number">
                                                        <?php echo htmlspecialchars($table['Nomeja']); ?>
                                                    </div>
                                                    <span class="status-badge <?php echo $table['status'] === 'kosong' ? 'available' : 'occupied'; ?>">
                                                         <?php if ($table['status'] === 'kosong'): ?>
                                                             <i class="bi bi-check-circle"></i> Tersedia
                                                         <?php else: ?>
                                                             <i class="bi bi-x-circle"></i> Terisi
                                                         <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="mt-auto">
                                                    <?php if ($table['status'] === 'kosong'): ?>
                                                        <a href="create_order.php?table_id=<?php echo $table['id']; ?>" class="btn btn-primary btn-sm btn-new-order">
                                                            <i class="bi bi-plus-lg"></i> Buat Pesanan
                                                        </a>
                                                    <?php else: ?>
                                                         <a href="#activeOrdersTable" class="btn btn-secondary btn-sm btn-new-order disabled" aria-disabled="true">
                                                             <i class="bi bi-person-check"></i> Terisi
                                                          </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                 <?php else: ?>
                                     <div class="col-12">
                                         <p class="text-center text-muted">Tidak ada data meja.</p>
                                     </div>
                                 <?php endif; ?>
                             </div>
                         </div>
                    </div>
                 </div>
            </div>

            <div class="row mt-4">
                <div class="col-12">
                     <div class="main-card" id="activeOrdersTable">
                         <div class="card-header">
                            <i class="bi bi-journal-text"></i> Pesanan Aktif
                         </div>
                         <div class="card-body card-body-table">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Kode Pesanan</th>
                                            <th>Meja</th>
                                            <th>Item Pesanan</th>
                                            <th>Status</th>
                                            <th>Total</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($active_orders_result && $active_orders_result->num_rows > 0): ?>
                                            <?php while ($order = $active_orders_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($order['kode_pesanan']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['nomor_meja']); ?></td>
                                                    <td>
                                                        <?php
                                                        // Output items dengan <br> dari GROUP_CONCAT
                                                        echo $order['items'];
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark"> <?php echo htmlspecialchars($order['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                                    <td><?php echo date('H:i', strtotime($order['created_at'])); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                         <?php else: ?>
                                             <tr>
                                                 <td colspan="7" class="text-center py-4">Tidak ada pesanan aktif</td>
                                             </tr>
                                         <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                         </div>
                     </div>
                </div>
             </div>

        </div> </div> <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Optional: Add JS for sidebar toggle if needed
        // document.addEventListener('DOMContentLoaded', function() {
        //     // ... (kode toggle sidebar seperti sebelumnya) ...
        // });
    </script>
</body>
</html>
