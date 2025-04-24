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

// --- Get Order ID ---
// Menggunakan 'id' sesuai referensi dashboard waiter terakhir
if (!isset($_GET['id'])) { 
    $_SESSION['error_message'] = "ID Pesanan tidak valid.";
    header("Location: dashboard.php");
    exit();
}
$order_id = $_GET['id'];

// --- Get Order Details ---
// Query untuk mendapatkan detail dasar pesanan dan nama pelanggan
$order_query = "SELECT p.*, m.Nomeja, p.created_at as order_date, pl.Namapelanggan 
FROM pesanan p 
JOIN meja m ON p.meja_id = m.id 
LEFT JOIN pelanggan pl ON p.idpelanggan = pl.idpelanggan -- Gunakan LEFT JOIN jika pelanggan bisa null
WHERE p.idpesanan = ? 
LIMIT 1"; // Ambil satu baris saja sebagai referensi pesanan

$stmt_order = $conn->prepare($order_query);
if ($stmt_order === false) { die("Error preparing order query: " . $conn->error); }
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order_result = $stmt_order->get_result();
$order = $order_result->fetch_assoc();
$stmt_order->close();

// Check if order exists
if (!$order) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan.";
    header("Location: dashboard.php");
    exit();
}

// --- Get Order Items ---
// Query untuk mendapatkan semua item berdasarkan kode_pesanan dari pesanan yang ditemukan
$items_query = "SELECT p.jumlah, m.Namamenu as nama, m.Harga as harga, (p.jumlah * m.Harga) as subtotal 
FROM pesanan p 
JOIN menu m ON p.idmenu = m.idmenu 
WHERE p.kode_pesanan = ?"; // Gunakan kode_pesanan untuk mengambil semua item terkait

$stmt_items = $conn->prepare($items_query);
if ($stmt_items === false) { die("Error preparing items query: " . $conn->error); }
$stmt_items->bind_param("s", $order['kode_pesanan']);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

// Calculate total
$total = 0;
$items_data = []; // Simpan item untuk loop di HTML
while ($item = $items_result->fetch_assoc()) {
    $items_data[] = $item; // Simpan data item
    $total += $item['subtotal'];
}
$stmt_items->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pesanan - #<?php echo htmlspecialchars($order['kode_pesanan'] ?? $order_id); ?> - Kasir Resto</title>
    
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Source+Code+Pro:wght@400;500&display=swap" rel="stylesheet">

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
            --color-secondary-hover: #5a6268;
            --color-text-dark: #1a1a1a; /* Teks gelap agar kontras */
            --color-text-light: #FFFFFF; /* Teks terang */
            --color-border: #dee2e6; /* Warna border default */
            --color-card-bg: #FFFFFF; /* Background card */
            --color-shadow: rgba(0, 0, 0, 0.08); /* Warna shadow */
            
            /* Font untuk struk */
            --font-receipt: 'Source Code Pro', monospace; 
        }

        body.screen-view {
            background-color: var(--color-bg-light);
            color: var(--color-text-dark);
            font-family: 'Poppins', sans-serif; 
        }

        /* --- Styling Sidebar (Hanya untuk screen) --- */
        .sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-width);
            background-color: #212529; color: #fff;
            transition: all var(--transition-speed) var(--transition-type);
            z-index: 1030; padding-top: 1rem;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed-width); }
        /* --- Akhir Styling Sidebar --- */

        /* --- Styling Content Wrapper (Hanya untuk screen) --- */
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

        /* --- Styling Kontainer Preview Cetak (Hanya untuk screen) --- */
        .print-preview-container {
            max-width: 800px; /* Lebar A4 kira-kira */
            margin: 2rem auto;
            padding: 2rem;
            background-color: var(--color-card-bg);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
        }

        /* --- Styling Tombol Aksi (Hanya untuk screen) --- */
        .action-buttons {
            margin-bottom: 1.5rem;
            text-align: right;
        }
         .action-buttons .btn { margin-left: 0.5rem; }
         .btn-primary {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: var(--color-text-light);
        }
        .btn-primary:hover {
            background-color: var(--color-primary-hover);
            border-color: var(--color-primary-hover);
        }
         .btn-secondary {
             background-color: var(--color-secondary);
             border-color: var(--color-secondary);
             color: var(--color-text-light);
         }
          .btn-secondary:hover {
              background-color: var(--color-secondary-hover);
              border-color: var(--color-secondary-hover);
          }

        /* --- Styling Struk Dasar (Berlaku di layar dan cetak) --- */
        .receipt {
            font-family: var(--font-receipt); /* Font khusus struk */
            color: #000; /* Warna teks hitam untuk cetak */
            line-height: 1.4; /* Jarak antar baris */
            font-size: 10pt; /* Ukuran font dasar struk */
        }
        .receipt .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px dashed #000; /* Garis putus-putus */
            padding-bottom: 10px;
        }
         .receipt .header h2 {
            margin: 0;
            font-size: 14pt; /* Ukuran judul resto */
            font-weight: 600;
         }
          .receipt .header p {
            margin: 2px 0;
            font-size: 9pt; /* Ukuran info alamat/telp */
          }
          .receipt .header h3 {
             margin: 10px 0 0 0;
             font-size: 11pt; /* Ukuran judul konfirmasi */
             font-weight: 600;
          }

        .receipt .info {
            margin-bottom: 15px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        .receipt .info p {
            margin: 3px 0;
            display: flex;
            justify-content: space-between;
        }
         .receipt .info p span:first-child { font-weight: 500; } /* Label tebal */

        .receipt table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .receipt th, .receipt td {
            text-align: left;
            padding: 4px 2px; /* Padding lebih rapat */
            vertical-align: top; /* Rata atas */
        }
         .receipt th {
             border-bottom: 1px solid #000; /* Garis bawah header tabel */
             font-weight: 600;
             font-size: 9.5pt;
         }
        .receipt td { font-size: 9.5pt; }
        .receipt .amount { text-align: right; }
        .receipt .col-item { width: 45%; }
        .receipt .col-qty { width: 10%; text-align: center; }
        .receipt .col-price { width: 20%; }
        .receipt .col-subtotal { width: 25%; }

        .receipt .total-section {
             border-top: 1px solid #000; /* Garis atas total */
             padding-top: 5px;
             margin-top: 5px;
        }
        .receipt .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 11pt;
        }

        .receipt .signature-section {
            margin-top: 20px;
            display: flex;
            justify-content: space-around; /* Jarak merata */
            font-size: 9pt;
        }
        .receipt .signature-box {
            text-align: center;
            width: 40%; /* Lebar area tanda tangan */
        }
        .receipt .signature-line {
            border-top: 1px dotted #000; /* Garis titik-titik */
            margin: 15px auto 5px; /* Jarak atas/bawah */
            width: 90%;
        }

        .receipt .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 9pt;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
         .receipt .footer p { margin: 3px 0; }
         .receipt .footer small { font-size: 8pt; }

        /* --- Print Specific Styles --- */
        @media print {
            @page {
                size: 80mm auto; /* Ukuran kertas thermal */
                /* Atau gunakan A4 jika perlu: size: A4 portrait; */
                margin: 5mm; /* Margin cetak */
            }

            body, html {
                background-color: #fff !important; /* Paksa background putih */
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important; /* Tampilkan semua konten */
                 -webkit-print-color-adjust: exact !important; /* Paksa cetak warna (jika ada) */
                 print-color-adjust: exact !important;
            }
            
            /* Sembunyikan elemen yang tidak perlu dicetak */
            .no-print, 
            .sidebar, 
            [class*="sidebar"], 
            #sidebar, 
            .navbar, 
            [class*="navbar"],
            .action-buttons /* Sembunyikan tombol aksi */
             {
                display: none !important;
                visibility: hidden !important;
                width: 0 !important; height: 0 !important;
                position: absolute !important; overflow: hidden !important;
                clip: rect(0, 0, 0, 0) !important; opacity: 0 !important;
                pointer-events: none !important; max-height: 0 !important; max-width: 0 !important;
            }

            /* Pastikan konten utama mengisi halaman cetak */
            .content-wrapper, 
            .print-preview-container, 
            .container-fluid {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                background-color: transparent !important;
            }

            .receipt {
                width: 100% !important; /* Struk mengisi lebar area cetak */
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                font-size: 9pt; /* Sesuaikan ukuran font cetak jika perlu */
            }
             /* Sesuaikan ukuran font spesifik untuk cetak jika perlu */
             .receipt .header h2 { font-size: 12pt; }
             .receipt .header h3 { font-size: 10pt; }
             .receipt .info p { font-size: 9pt; }
             .receipt th, .receipt td { font-size: 9pt; padding: 2px; }
             .receipt .total-row { font-size: 10pt; }
             .receipt .signature-section { font-size: 8pt; margin-top: 10mm;}
             .receipt .footer { font-size: 8pt; margin-top: 10mm;}
             .receipt .footer small { font-size: 7pt; }
        }
    </style>
</head>
<body class="screen-view">

    <?php include '../../components/sidebar.php'; // Sidebar hanya tampil di layar ?>

    <div class="content-wrapper">
        <div class="container-fluid">

             <div class="no-print mb-4">
                 <div class="d-flex justify-content-between align-items-center">
                     <h2>Pratinjau Konfirmasi Pesanan</h2>
                     <div class="action-buttons">
                         <a href="dashboard.php" class="btn btn-secondary">
                             <i class="bi bi-arrow-left-circle"></i> Kembali ke Dashboard
                         </a>
                         <button class="btn btn-primary" onclick="window.print();">
                             <i class="bi bi-printer-fill"></i> Cetak Konfirmasi
                         </button>
                     </div>
                 </div>
                 <hr>
            </div>


            <div class="print-preview-container">
                
                <div class="receipt">
                    <div class="header">
                        <h2>Kasir Resto</h2>
                        <p>Jl. Contoh Restoran No. 101, Jakarta</p>
                        <p>Telp: (021) 555-1234</p>
                        <h3>KONFIRMASI PESANAN</h3>
                    </div>

                    <div class="info">
                        <p><span>No. Pesanan:</span> <span><?php echo htmlspecialchars($order['kode_pesanan'] ?? $order_id); ?></span></p>
                        <p><span>Tanggal:</span> <span><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></span></p>
                        <p><span>Meja:</span> <span><?php echo htmlspecialchars($order['Nomeja']); ?></span></p>
                        <p><span>Pelanggan:</span> <span><?php echo isset($order['Namapelanggan']) ? htmlspecialchars($order['Namapelanggan']) : 'Tamu'; ?></span></p>
                        <p><span>Waiter:</span> <span><?php echo isset($user['nama']) ? htmlspecialchars($user['nama']) : 'N/A'; ?></span></p>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th class="col-item">Item</th>
                                <th class="col-qty">Jml</th>
                                <th class="col-price amount">Harga</th>
                                <th class="col-subtotal amount">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($items_data)): ?>
                                <?php foreach ($items_data as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['nama']); ?></td>
                                    <td class="col-qty" style="text-align: center;"><?php echo htmlspecialchars($item['jumlah']); ?></td>
                                    <td class="amount">Rp<?php echo number_format($item['harga'], 0, ',', '.'); ?></td>
                                    <td class="amount">Rp<?php echo number_format($item['subtotal'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;"><em>Tidak ada item pesanan.</em></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="total-section">
                        <div class="total-row">
                            <span>TOTAL</span>
                            <span class="amount">Rp<?php echo number_format($total, 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="signature-section">
                        <div class="signature-box">
                            <p>Pelanggan</p>
                            <div class="signature-line"></div>
                            <p>( <?php echo isset($order['Namapelanggan']) ? htmlspecialchars($order['Namapelanggan']) : 'Tamu'; ?> )</p>
                        </div>
                        <div class="signature-box">
                            <p>Waiter</p>
                            <div class="signature-line"></div>
                            <p>( <?php echo isset($user['nama']) ? htmlspecialchars($user['nama']) : 'N/A'; ?> )</p>
                        </div>
                    </div>

                    <div class="footer">
                        <p>Terima kasih atas kunjungan Anda!</p>
                        <p><small>Ini adalah konfirmasi pesanan, bukan bukti bayar.</small></p>
                    </div>
                </div>
                </div>
            </div></div><script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    
    </body>
</html>
