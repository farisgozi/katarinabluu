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


// --- VALIDASI & GET DATA AWAL ---
if (!isset($_GET['table_id'])) {
    header("Location: dashboard.php"); // Redirect jika tidak ada ID meja
    exit();
}
$table_id = $_GET['table_id'];

// Get table information
$table_query = "SELECT * FROM meja WHERE id = ?";
$stmt_table = $conn->prepare($table_query);
if ($stmt_table === false) { die("Error preparing table query: " . $conn->error); }
$stmt_table->bind_param("i", $table_id);
$stmt_table->execute();
$table_result = $stmt_table->get_result();
$table = $table_result->fetch_assoc();
$stmt_table->close();

// Check if table exists and is available
if (!$table || $table['status'] !== 'kosong') {
    // Mungkin beri pesan error atau redirect ke dashboard dengan notifikasi
    $_SESSION['error_message'] = "Meja tidak ditemukan atau sudah terisi.";
    header("Location: dashboard.php"); 
    exit();
}

// Get menu items
$menu_query = "SELECT * FROM menu ORDER BY Namamenu";
$menu_result = $conn->query($menu_query);
if ($menu_result === false) { die("Error fetching menu: " . $conn->error); }

// Tidak perlu query pelanggan lagi karena kita akan membuat pelanggan baru
// --- AKHIR VALIDASI & GET DATA AWAL ---


// --- HANDLE FORM SUBMISSION ---
$error = null; // Inisialisasi variabel error
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start transaction
    $conn->begin_transaction();

    try {
        // Validate and create new customer
        if (!isset($_POST['nama_pelanggan']) || empty(trim($_POST['nama_pelanggan']))) {
            throw new Exception("Nama pelanggan wajib diisi.");
        }
        
        // Prepare customer data
        $nama_pelanggan = trim($_POST['nama_pelanggan']);
        $no_hp = isset($_POST['no_hp']) ? trim($_POST['no_hp']) : null;
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : null;
        $jenis_kelamin = isset($_POST['jenis_kelamin']) ? trim($_POST['jenis_kelamin']) : null;
        
        // Insert new customer
        $create_customer_query = "INSERT INTO pelanggan (Namapelanggan, Nohp, alamat, Jeniskelamin) VALUES (?, ?, ?, ?)";
        $stmt_customer = $conn->prepare($create_customer_query);
        if ($stmt_customer === false) {
            throw new Exception("Error preparing customer statement: " . $conn->error);
        }
        $stmt_customer->bind_param("ssss", $nama_pelanggan, $no_hp, $alamat, $jenis_kelamin);
        if (!$stmt_customer->execute()) {
            throw new Exception("Error creating customer: " . $stmt_customer->error);
        }
        $customer_id = $stmt_customer->insert_id;
        $stmt_customer->close();

        // Validate menu items & prepare data
        $order_items_data = [];
        $has_items = false;
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $menu_id => $quantity) {
                 // Pastikan quantity adalah angka positif
                 $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
                 if ($quantity === false) { // Jika bukan integer atau < 0
                     $quantity = 0; // Anggap 0 jika tidak valid
                 }

                if ($quantity > 0) {
                    $has_items = true;
                    // Simpan data item yang valid untuk dimasukkan
                    $order_items_data[$menu_id] = $quantity; 
                }
            }
        }

        if (!$has_items) {
            throw new Exception("Pilih minimal satu item menu dengan jumlah lebih dari 0.");
        }

        // Check if table already has an active order
        $check_order_query = "SELECT COUNT(*) as count FROM pesanan p 
            LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan 
            WHERE p.meja_id = ? AND t.idtransaksi IS NULL";
        $stmt_check = $conn->prepare($check_order_query);
        $stmt_check->bind_param("i", $table_id);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        $order_count = $check_result->fetch_assoc()['count'];
        $stmt_check->close();

        if ($order_count > 0) {
            throw new Exception("Meja ini sudah memiliki pesanan aktif.");
        }

        // Create new order code
        $kode_pesanan = 'ORD-' . date('YmdHis') . '-' . rand(100, 999);
        
        // Prepare statement for inserting order items
        $create_order_query = "INSERT INTO pesanan (meja_id, iduser, kode_pesanan, idmenu, jumlah, idpelanggan, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt_insert = $conn->prepare($create_order_query);
        if ($stmt_insert === false) {
             throw new Exception("Error preparing insert statement: " . $conn->error);
        }

        // Get user ID from session
        $user_id = $_SESSION['user']['id']; // Pastikan 'id' ada di session user

        // Insert order items
        foreach ($order_items_data as $menu_id => $quantity) {
             // Bind parameter di dalam loop karena menu_id dan quantity berubah
             $stmt_insert->bind_param("iisiis", $table_id, $user_id, $kode_pesanan, $menu_id, $quantity, $customer_id);
             if (!$stmt_insert->execute()) {
                 // Tangkap error spesifik jika perlu
                 throw new Exception("Error saat memasukkan item pesanan (Menu ID: $menu_id): " . $stmt_insert->error);
             }
        }
        $stmt_insert->close(); // Tutup statement setelah loop selesai

        // Update table status
        $update_table_query = "UPDATE meja SET status = 'terpakai' WHERE id = ?";
        $stmt_update = $conn->prepare($update_table_query);
         if ($stmt_update === false) {
             throw new Exception("Error preparing update table statement: " . $conn->error);
         }
        $stmt_update->bind_param("i", $table_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Error saat memperbarui status meja: " . $stmt_update->error);
        }
        $stmt_update->close();

        // Jika semua berhasil, commit transaksi
        $conn->commit();
        
        // Redirect ke halaman view order menggunakan kode_pesanan
        header("Location: view_order.php?kode_pesanan=" . urlencode($kode_pesanan));
        exit();

    } catch (Exception $e) {
        // Jika ada error, rollback transaksi
        $conn->rollback();
        $error = $e->getMessage(); // Simpan pesan error untuk ditampilkan
    }
}
// --- AKHIR HANDLE FORM SUBMISSION ---

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Pesanan - Meja <?php echo htmlspecialchars($table['Nomeja']); ?> - Kasir Resto</title>
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
            --color-secondary-hover: #5a6268;
            --color-success: #28a745; /* Warna sukses */
            --color-danger: #dc3545; /* Warna danger */
            --color-warning: #FFD166; /* Warna warning */
            --color-info: #118ab2; /* Warna info */
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
        @media (max-width: 992px) { /* Sesuaikan breakpoint jika perlu */
             .content-wrapper { margin-left: 0; padding: var(--content-padding-top) 1rem; }
             .sidebar { left: -var(--sidebar-width); }
             .sidebar.active { left: 0; }
             .order-summary-col { /* Agar summary pindah ke bawah di layar kecil */
                 position: relative; top: auto; height: auto; margin-top: 2rem;
             }
        }
        /* --- Akhir Styling Content Wrapper --- */

        /* --- Styling Komponen Utama --- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .page-header h2 { margin-bottom: 0; font-weight: 600; }
        .page-header .table-indicator {
             font-size: 1.5rem; font-weight: 600;
             color: var(--color-primary);
        }

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
        }
         .main-card .card-header i { color: var(--color-primary); }
         .main-card .card-body { padding: 1.5rem; }

        /* Styling Form Customer */
        .customer-select-section { margin-bottom: 2rem; }
        .form-select {
             border-color: var(--color-border);
             transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.2rem rgba(6, 214, 160, 0.25);
        }

        /* Kartu Menu Item */
        .menu-item-card {
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            background-color: var(--color-card-bg); /* Pastikan background putih */
        }
        .menu-item-card:hover {
             transform: translateY(-3px);
             box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .menu-item-card .card-img-top {
            height: 150px; /* Tinggi gambar tetap */
            object-fit: cover; /* Agar gambar tidak distorsi */
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            background-color: #eee; /* Warna background jika gambar tidak ada */
        }
        .menu-item-card .card-body {
            padding: 1rem;
            flex-grow: 1; /* Agar body mengisi ruang */
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Dorong input ke bawah */
        }
        .menu-item-card .card-title {
            font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem;
        }
        .menu-item-card .card-price {
            font-size: 0.95rem; color: var(--color-primary);
            font-weight: 500; margin-bottom: 1rem;
        }
        .menu-item-card .input-group { margin-top: auto; } /* Dorong input ke bawah */
        .menu-item-card .form-control[type="number"] {
             text-align: center;
             border-left: 0; border-right: 0;
             border-radius: 0; /* Hapus radius di tengah */
             border-color: var(--color-border); /* Pastikan border terlihat */
        }
         /* Hilangkan panah atas/bawah default pada input number */
         .menu-item-card input[type=number]::-webkit-outer-spin-button,
         .menu-item-card input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
         }
         .menu-item-card input[type=number] {
            -moz-appearance: textfield; /* Firefox */
         }

         .menu-item-card .btn-qty {
             border-color: var(--color-border);
             background-color: #f8f9fa;
             color: var(--color-text-dark);
             padding: 0.375rem 0.75rem;
             z-index: 5; /* Agar di atas input */
         }
         .menu-item-card .btn-qty:hover {
             background-color: #e9ecef;
         }


        /* Order Summary Sidebar */
        .order-summary-col {
            position: sticky; /* Membuatnya tetap saat scroll */
            top: calc(var(--content-padding-top) + 1.5rem); /* Posisi dari atas */
            /* Tinggi diatur oleh flex container di bawah */
        }
        .order-summary-container {
             height: calc(100vh - var(--content-padding-top) - 3rem); /* Tinggi maks container */
             display: flex;
             flex-direction: column;
        }

        .order-summary-card {
            /* Hapus height: 100% dari sini */
            display: flex;
            flex-direction: column;
            flex-grow: 1; /* Biarkan card mengisi ruang di container */
            overflow: hidden; /* Cegah konten meluber dari card */
            border: none; /* Hapus border jika sudah di main-card */
            box-shadow: none; /* Hapus shadow jika sudah di main-card */
        }
         .order-summary-card .card-header {
             flex-shrink: 0; /* Header tidak menyusut */
         }
         .order-summary-card .card-body {
             flex-grow: 1; /* Body mengisi ruang tersisa */
             overflow-y: auto; /* Scroll jika item banyak */
             padding: 1rem; /* Padding untuk list item */
         }
         .order-summary-card .card-footer {
             flex-shrink: 0; /* Footer tidak menyusut */
             background-color: #f8f9fa;
             border-top: 1px solid var(--color-border);
             padding: 1rem 1.5rem;
         }
         #order-summary-list {
             list-style: none;
             padding: 0;
             margin: 0;
         }
          #order-summary-list li {
             display: flex;
             justify-content: space-between;
             align-items: center;
             padding: 0.6rem 0;
             border-bottom: 1px dashed var(--color-border);
             font-size: 0.9rem;
         }
         #order-summary-list li:last-child { border-bottom: none; }
         #order-summary-list .item-name { flex-grow: 1; margin-right: 1rem; }
         #order-summary-list .item-qty { font-weight: 500; min-width: 30px; text-align: right; }
         #order-summary-list .item-price { font-weight: 500; min-width: 80px; text-align: right; }

         .summary-total {
             font-size: 1.2rem;
             font-weight: 600;
         }
         .summary-total .total-label { color: #6c757d; }
         .summary-total .total-amount { color: var(--color-primary); }

         /* Tombol Aksi di dalam Footer */
         .summary-actions {
             margin-top: 1rem; /* Jarak dari total */
             text-align: right;
         }
          .summary-actions .btn {
              margin-left: 0.5rem;
          }


         /* Tombol Primer & Sekunder */
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
         .btn-secondary {
             background-color: var(--color-secondary);
             border-color: var(--color-secondary);
             color: var(--color-text-light);
         }
          .btn-secondary:hover {
              background-color: var(--color-secondary-hover);
              border-color: var(--color-secondary-hover);
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
            <form method="POST" id="createOrderForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="page-header">
                            <h2>Buat Pesanan Baru</h2>
                            <span class="table-indicator">Meja <?php echo htmlspecialchars($table['Nomeja']); ?></span>
                        </div>

                        <div class="main-card customer-select-section">
                            <div class="card-body">
                                <div class="mb-3">
                                <label for="nama_pelanggan" class="form-label fw-bold">Nama Pelanggan <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nama_pelanggan" id="nama_pelanggan" required>
                            </div>
                            <div class="mb-3">
                                <label for="no_hp" class="form-label">Nomor HP</label>
                                <input type="text" class="form-control" name="no_hp" id="no_hp">
                            </div>
                            <div class="mb-3">
                                <label for="alamat" class="form-label">Alamat</label>
                                <textarea class="form-control" name="alamat" id="alamat" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                <select class="form-select" name="jenis_kelamin" id="jenis_kelamin">
                                    <option value="">-- Pilih jenis kelamin --</option>
                                    <option value="laki-laki">Laki-laki</option>
                                    <option value="perempuan">Perempuan</option>
                                </select>
                            </div>
                            </div>
                        </div>

                         <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>


                        <div class="main-card">
                             <div class="card-header">
                                <i class="bi bi-list-stars"></i> Pilih Item Menu
                             </div>
                             <div class="card-body">
                                 <div class="row g-3">
                                    <?php if ($menu_result && $menu_result->num_rows > 0): ?>
                                        <?php while ($menu = $menu_result->fetch_assoc()): ?>
                                            <div class="col-md-6 col-lg-4 d-flex"> 
                                                <div class="card menu-item-card flex-fill"
                                                     data-menu-id="<?php echo $menu['idmenu']; ?>" 
                                                     data-menu-name="<?php echo htmlspecialchars($menu['Namamenu']); ?>" 
                                                     data-menu-price="<?php echo $menu['Harga']; ?>">
                                                    
                                                    <img src="https://placehold.co/600x400/F8FFE5/06D6A0?text=<?php echo urlencode(htmlspecialchars($menu['Namamenu'])); ?>" 
                                                         class="card-img-top" 
                                                         alt="<?php echo htmlspecialchars($menu['Namamenu']); ?>"
                                                         onerror="this.onerror=null; this.src='https://placehold.co/600x400/cccccc/ffffff?text=Image+Error';">

                                                    <div class="card-body">
                                                        <div>
                                                             <h5 class="card-title"><?php echo htmlspecialchars($menu['Namamenu']); ?></h5>
                                                             <p class="card-price">
                                                                Rp <?php echo number_format($menu['Harga'], 0, ',', '.'); ?>
                                                             </p>
                                                        </div>
                                                        <div class="input-group input-group-sm mt-auto">
                                                            <button class="btn btn-outline-secondary btn-qty btn-minus" type="button" aria-label="Kurangi jumlah">-</button>
                                                            <input type="number" class="form-control quantity-input" 
                                                                   name="items[<?php echo $menu['idmenu']; ?>]" 
                                                                   value="0" min="0" aria-label="Jumlah <?php echo htmlspecialchars($menu['Namamenu']); ?>">
                                                            <button class="btn btn-outline-secondary btn-qty btn-plus" type="button" aria-label="Tambah jumlah">+</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                        <?php $menu_result->data_seek(0); // Reset pointer jika perlu ?>
                                    <?php else: ?>
                                         <div class="col-12">
                                             <p class="text-center text-muted">Tidak ada item menu tersedia.</p>
                                         </div>
                                    <?php endif; ?>
                                 </div>
                             </div>
                        </div>
                    </div>

                    <div class="col-lg-4 order-summary-col">
                       <div class="order-summary-container main-card">
                            <div class="order-summary-card">
                                <div class="card-header">
                                    <i class="bi bi-receipt"></i> Ringkasan Pesanan
                                </div>
                                <div class="card-body">
                                    <ul id="order-summary-list">
                                        <li id="empty-summary-placeholder">
                                            <em class="text-muted">Belum ada item dipilih.</em>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between summary-total">
                                        <span class="total-label">Total:</span>
                                        <span class="total-amount" id="order-total">Rp 0</span>
                                    </div>
                                    <div class="summary-actions">
                                        <a href="dashboard.php" class="btn btn-secondary btn-sm">
                                            <i class="bi bi-x-lg"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-check-lg"></i> Buat Pesanan
                                        </button>
                                    </div>
                                </div>
                            </div>
                       </div>
                    </div>
                    </div> </form> </div></div><script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const orderSummaryList = document.getElementById('order-summary-list');
            const orderTotalElement = document.getElementById('order-total');
            const emptyPlaceholder = document.getElementById('empty-summary-placeholder');
            const menuItems = document.querySelectorAll('.menu-item-card');
            let currentOrder = {}; // Objek untuk menyimpan item { menuId: { name, price, quantity } }

            // Fungsi format Rupiah
            function formatRupiah(number) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(number);
            }

            // Fungsi untuk update ringkasan pesanan
            function updateOrderSummary() {
                // Hapus placeholder jika ada sebelum mengosongkan
                 if (emptyPlaceholder && orderSummaryList.contains(emptyPlaceholder)) {
                    orderSummaryList.removeChild(emptyPlaceholder);
                 }
                orderSummaryList.innerHTML = ''; // Kosongkan list
                let total = 0;
                let hasItems = false;

                // Urutkan item berdasarkan nama sebelum ditampilkan
                const sortedMenuIds = Object.keys(currentOrder).sort((a, b) => {
                     // Pastikan objek ada sebelum akses properti name
                     const nameA = currentOrder[a] ? currentOrder[a].name : '';
                     const nameB = currentOrder[b] ? currentOrder[b].name : '';
                     return nameA.localeCompare(nameB);
                });

                sortedMenuIds.forEach(menuId => {
                    const item = currentOrder[menuId];
                    // Pastikan item ada dan quantity > 0
                    if (item && item.quantity > 0) { 
                        hasItems = true;
                        const listItem = document.createElement('li');
                        const itemTotal = item.price * item.quantity;
                        total += itemTotal;

                        listItem.innerHTML = `
                            <span class="item-name">${item.name}</span>
                            <span class="item-qty">x${item.quantity}</span>
                            <span class="item-price">${formatRupiah(itemTotal)}</span>
                        `;
                        orderSummaryList.appendChild(listItem);
                    }
                });

                // Tampilkan placeholder jika tidak ada item setelah loop
                if (!hasItems && emptyPlaceholder) { // Pastikan placeholder ada
                     orderSummaryList.appendChild(emptyPlaceholder);
                }

                orderTotalElement.textContent = formatRupiah(total);
            }

            // Tambahkan event listener ke setiap kartu menu
            menuItems.forEach(card => {
                const menuId = card.dataset.menuId;
                // Cek null atau undefined sebelum akses dataset
                if (!menuId) return; 

                const menuName = card.dataset.menuName || 'Unknown Item'; // Default name
                const menuPriceText = card.dataset.menuPrice;
                const menuPrice = menuPriceText ? parseFloat(menuPriceText) : 0; // Default price 0

                const quantityInput = card.querySelector('.quantity-input');
                const btnMinus = card.querySelector('.btn-minus');
                const btnPlus = card.querySelector('.btn-plus');

                // Inisialisasi item di currentOrder
                currentOrder[menuId] = {
                    name: menuName,
                    price: menuPrice,
                    quantity: parseInt(quantityInput.value) || 0
                };

                // Event listener untuk input manual
                quantityInput.addEventListener('change', function() {
                    let value = parseInt(this.value) || 0;
                    if (value < 0) value = 0;
                    this.value = value;
                    currentOrder[menuId].quantity = value;
                    updateOrderSummary();
                });

                // Event listener untuk tombol minus
                btnMinus.addEventListener('click', function() {
                    let value = parseInt(quantityInput.value) || 0;
                    if (value > 0) {
                        value--;
                        quantityInput.value = value;
                        currentOrder[menuId].quantity = value;
                        updateOrderSummary();
                    }
                });

                // Event listener untuk tombol plus
                btnPlus.addEventListener('click', function() {
                    let value = parseInt(quantityInput.value) || 0;
                    value++;
                    quantityInput.value = value;
                    currentOrder[menuId].quantity = value;
                    updateOrderSummary();
                });
            });

            // Validasi form sebelum submit
            document.getElementById('createOrderForm').addEventListener('submit', function(e) {
                let hasItems = false;
                for (let menuId in currentOrder) {
                    if (currentOrder[menuId].quantity > 0) {
                        hasItems = true;
                        break;
                    }
                }
                if (!hasItems) {
                    e.preventDefault();
                    alert('Pilih minimal satu item menu dengan jumlah lebih dari 0.');
                }
            });
        });
    </script>
</body>
</html>
