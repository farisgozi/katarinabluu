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

// --- LOGIKA FILTER DAN QUERY ---
// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Query untuk riwayat pesanan
// Tetap group by kode_pesanan untuk agregasi, tapi pilih satu idpesanan untuk link
$orders_query = "SELECT
    p.kode_pesanan,
    m.Nomeja,
    MIN(p.created_at) as order_time,
    MIN(p.idpesanan) as representative_idpesanan, -- <<< Pilih satu ID Pesanan untuk link
    GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' (x', p.jumlah, ')') SEPARATOR ',<br>') as items,
    SUM(p.jumlah * menu.Harga) as total_amount,
    CASE
        WHEN EXISTS (
            SELECT 1
            FROM transaksi t_check
            JOIN pesanan p_check ON t_check.idpesanan = p_check.idpesanan
            WHERE p_check.kode_pesanan = p.kode_pesanan
        ) THEN 'Completed'
        ELSE 'Active'
    END as status_pesanan
FROM
    pesanan p
JOIN
    meja m ON p.meja_id = m.id
JOIN
    menu ON p.idmenu = menu.idmenu
WHERE
    DATE(p.created_at) BETWEEN ? AND ?
GROUP BY
    p.kode_pesanan, m.Nomeja -- Tetap group by kode_pesanan dan meja
ORDER BY
    order_time DESC;";

$stmt = $conn->prepare($orders_query);
if ($stmt === false) {
    die("Error preparing orders query: " . $conn->error . " <br>Query: <pre>" . htmlspecialchars($orders_query) . "</pre>");
}
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$orders_result = $stmt->get_result();
// --- AKHIR LOGIKA FILTER DAN QUERY ---

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Waiter Dashboard</title>
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
            --color-secondary: #6c757d; /* Warna abu-abu standar */
            --color-success: #198754; /* Warna hijau sukses (Bootstrap 5) */
            --color-info: #0dcaf0;    /* Warna info (Bootstrap 5) */
            --color-text-dark: #1a1a1a; /* Teks gelap agar kontras */
            --color-text-light: #FFFFFF; /* Teks terang */
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
        @media (max-width: 992px) {
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

         .main-card { /* Card untuk tabel */
             background-color: var(--color-card-bg);
             border: none;
             border-radius: 0.5rem;
             box-shadow: 0 4px 12px var(--color-shadow);
             margin-bottom: 1.5rem;
             overflow: hidden;
         }
         .main-card .card-header { /* Header Opsional */
             background-color: var(--color-card-bg);
             border-bottom: 1px solid var(--color-border);
             padding: 1rem 1.5rem;
             font-weight: 600;
             display: flex;
             align-items: center;
             gap: 0.5rem;
         }
          .main-card .card-header i { color: var(--color-primary); }
          .main-card .card-body { padding: 0; } /* Padding 0 untuk tabel rapat */

         /* Tabel */
         .table {
             margin-bottom: 0;
             border-color: var(--color-border);
             font-size: 0.9rem; /* Ukuran font tabel */
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
             background-color: rgba(6, 214, 160, 0.1); /* Warna hover */
         }
         .table td {
             vertical-align: middle;
             padding: 0.75rem 1rem; /* Padding cell tabel */
             border-top: 1px solid var(--color-border); /* Garis antar baris */
             word-break: break-word; /* Agar teks panjang bisa wrap */
         }
         /* Penyesuaian untuk kolom item */
         .table td:nth-child(4) { /* Kolom Item (kolom ke-4) */
             min-width: 250px; /* Beri lebar minimum agar tidak terlalu sempit */
             line-height: 1.5; /* Spasi antar baris item */
             white-space: normal; /* Biarkan teks wrap */
         }
          .table tbody tr:first-child td { border-top: none; }
         .table .text-end { text-align: right !important; }
         .table .text-center { text-align: center !important; }

         /* Status Badge */
         .badge { font-weight: 500; }
         .badge.status-active { background-color: var(--color-info); color: var(--color-text-light); }
         .badge.status-completed { background-color: var(--color-success); color: var(--color-text-light); }

         /* Tombol */
          .btn-primary {
             background-color: var(--color-primary);
             border-color: var(--color-primary);
             color: var(--color-text-light);
         }
         .btn-primary:hover {
             background-color: var(--color-primary-hover);
             border-color: var(--color-primary-hover);
         }
         .btn-info { background-color: var(--color-info); border-color: var(--color-info); color: var(--color-text-light); }
         .btn-info:hover { filter: brightness(90%); }
         .btn-success { background-color: var(--color-success); border-color: var(--color-success); color: var(--color-text-light); }
         .btn-success:hover { filter: brightness(90%); }
         .btn-sm { padding: 0.25rem 0.6rem; font-size: 0.75rem; }
          .btn-action { /* Tombol ikon kecil */
             width: 30px;
             height: 30px;
             display: inline-flex;
             align-items: center;
             justify-content: center;
             padding: 0;
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
                <h2>Riwayat Pesanan</h2>
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
                    <div class="main-card">
                         <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Kode Pesanan</th>
                                            <th>Meja</th>
                                            <th>Item</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/y H:i', strtotime($order['order_time'])); ?></td>
                                                    <td><?php echo htmlspecialchars($order['kode_pesanan']); ?></td>
                                                    <td><?php echo htmlspecialchars($order['Nomeja']); ?></td>
                                                    <td>
                                                        <?php echo $order['items']; // Items sudah diformat dengan <br> ?>
                                                    </td>
                                                    <td class="text-end">Rp <?php echo number_format($order['total_amount'] ?? 0, 0, ',', '.'); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge <?php echo $order['status_pesanan'] === 'Completed' ? 'status-completed' : 'status-active'; ?>">
                                                            <?php echo htmlspecialchars($order['status_pesanan']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                        // PERBAIKAN: Link ke view_order.php menggunakan ID Pesanan representatif
                                                        $view_url = '#'; // Default URL
                                                        // Gunakan kolom 'representative_idpesanan' dari query
                                                        if (!empty($order['representative_idpesanan'])) {
                                                            // Menggunakan 'id' sebagai parameter GET
                                                            $view_url = "view_order.php?id=" . urlencode($order['representative_idpesanan']);
                                                        }
                                                        ?>
                                                        <a href="<?php echo $view_url; ?>"
                                                           class="btn btn-sm btn-info btn-action me-1 <?php echo empty($order['representative_idpesanan']) ? 'disabled' : ''; ?>"
                                                           title="Lihat Detail Pesanan">
                                                            <i class="bi bi-eye-fill"></i>
                                                        </a>
                                                        <?php // Tombol Complete hanya muncul jika status Active ?>
                                                        <?php if ($order['status_pesanan'] === 'Active'): ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                         <?php else: ?>
                                             <tr>
                                                 <td colspan="7" class="text-center py-4">Tidak ada pesanan ditemukan untuk periode yang dipilih.</td>
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
