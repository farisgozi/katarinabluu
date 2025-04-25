<?php
session_start();
require_once '../../config/config.php';

// Cek session
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'waiter') {
    header("Location: ../../pages/auth/login.php");
    exit();
}

// HAPUS JIKA TIDAK ADA/DIBUTUHKAN
// require_once '../../functions/functions.php';
// check_session_timeout(); // Jika ada fungsi ini

// Get user data
$user = $_SESSION['user'];
// PERBAIKAN: Pastikan ID user ada di session. Sesuaikan key jika perlu (misal: 'id', 'user_id', 'iduser')
if (!isset($user['id'])) {
     // Handle error jika ID user tidak ada di session
     die("Error: User ID tidak ditemukan di session.");
}
$logged_in_user_id = $user['id']; // Simpan ID user ke variabel

// Inisialisasi variabel
$error_message = '';
$success_message = '';
$order = null;
$order_items = [];

// Cek apakah ID pesanan tersedia
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "ID Pesanan tidak valid.";
    header("Location: dashboard.php"); // Redirect ke dashboard atau halaman riwayat
    exit();
}

$order_id = $_GET['id'];

// Ambil informasi pesanan awal (termasuk kode_pesanan, meja_id, idpelanggan)
$order_query = "SELECT p.idpesanan, p.kode_pesanan, p.meja_id, p.idpelanggan, m.Nomeja, p.created_at as order_date, pl.Namapelanggan
FROM pesanan p
JOIN meja m ON p.meja_id = m.id
LEFT JOIN pelanggan pl ON p.idpelanggan = pl.idpelanggan
WHERE p.idpesanan = ? LIMIT 1"; // Filter berdasarkan ID yang diedit

$stmt_order = $conn->prepare($order_query);
if ($stmt_order === false) { die("Error preparing order query: " . $conn->error); }
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$order_result = $stmt_order->get_result();
$order = $order_result->fetch_assoc();
$stmt_order->close();

// Cek apakah pesanan ada
if (!$order) {
    $_SESSION['error_message'] = "Pesanan tidak ditemukan.";
    header("Location: dashboard.php"); // Redirect ke dashboard atau halaman riwayat
    exit();
}

// Ambil daftar menu
$menu_query = "SELECT * FROM menu ORDER BY Namamenu";
$menu_result = $conn->query($menu_query);
if ($menu_result === false) { die("Error fetching menu: " . $conn->error); }

// Proses form submission untuk update pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();

        // Ambil kode pesanan dari data $order yang sudah diambil
        $kode_pesanan_to_update = $order['kode_pesanan'];
        $meja_id_to_update = $order['meja_id'];
        $idpelanggan_to_update = $order['idpelanggan']; // Ambil idpelanggan

        // Hapus semua item pesanan LAMA yang memiliki kode_pesanan yang sama
        $delete_query = "DELETE FROM pesanan WHERE kode_pesanan = ?";
        $stmt_delete = $conn->prepare($delete_query);
        if ($stmt_delete === false) { throw new Exception("Error preparing delete statement: " . $conn->error); }
        $stmt_delete->bind_param("s", $kode_pesanan_to_update);
        if (!$stmt_delete->execute()) { throw new Exception("Error deleting old items: " . $stmt_delete->error); }
        $stmt_delete->close();

        // Insert pesanan BARU berdasarkan input form
        // PERBAIKAN: Menambahkan kolom iduser ke query INSERT
        $create_order_query = "INSERT INTO pesanan (meja_id, iduser, kode_pesanan, idmenu, jumlah, idpelanggan, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt_insert = $conn->prepare($create_order_query);
        if ($stmt_insert === false) { throw new Exception("Error preparing insert statement: " . $conn->error); }

        $has_items = false; // Flag untuk cek apakah ada item yang diinput
        foreach ($_POST['menu_quantity'] as $menu_id => $quantity) {
            $quantity = intval($quantity); // Pastikan integer
            if ($quantity > 0) {
                $has_items = true;
                // PERBAIKAN: Menambahkan tipe 'i' dan nilai $logged_in_user_id ke bind_param
                // Tipe data: i (meja_id), i (iduser), s (kode_pesanan), i (idmenu), i (jumlah), i (idpelanggan)
                $stmt_insert->bind_param("iisiii",
                    $meja_id_to_update,
                    $logged_in_user_id, // Masukkan ID user yang login
                    $kode_pesanan_to_update,
                    $menu_id,
                    $quantity,
                    $idpelanggan_to_update // Gunakan idpelanggan yang sudah ada (bisa null)
                );
                if (!$stmt_insert->execute()) { throw new Exception("Error inserting new item (Menu ID: $menu_id): " . $stmt_insert->error); }
            }
        }

        $stmt_insert->close();

        // Jika tidak ada item yang dipilih setelah edit, mungkin batalkan transaksi? (Opsional)
        if (!$has_items) {
             // Anda bisa throw exception atau handle sesuai kebutuhan
             // throw new Exception("Tidak ada item yang dipilih dalam pesanan.");
             // Atau biarkan kosong jika memang boleh
        }

        // Jika semua berhasil, commit transaksi
        $conn->commit();

        $_SESSION['success_message'] = "Pesanan berhasil diperbarui!";
        // Redirect kembali ke view_order.php menggunakan ID pesanan LAMA
        header("Location: view_order.php?id=" . $order_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback(); // Batalkan transaksi jika ada error
        // Simpan pesan error untuk ditampilkan
        $_SESSION['error_message'] = "Gagal memperbarui pesanan: " . $e->getMessage();
        // Redirect kembali ke halaman edit agar pengguna bisa mencoba lagi
        header("Location: edit_order.php?id=" . $order_id);
        exit();
    }
}


// Ambil item pesanan saat ini (setelah potensi redirect karena error POST)
// Gunakan kode_pesanan dari $order yang sudah diambil di awal
$order_items = []; // Kosongkan array sebelum mengisi ulang
$items_query = "SELECT p.idmenu, p.jumlah, m.Namamenu, m.Harga
FROM pesanan p
JOIN menu m ON p.idmenu = m.idmenu
WHERE p.kode_pesanan = ?";
$stmt_items = $conn->prepare($items_query);
if ($stmt_items === false) { die("Error preparing current items query: " . $conn->error); }
// Pastikan $order['kode_pesanan'] tidak null sebelum bind
if (empty($order['kode_pesanan'])) { die("Error: Kode pesanan tidak valid untuk mengambil item saat ini."); }
$stmt_items->bind_param("s", $order['kode_pesanan']);
$stmt_items->execute();
$items_result = $stmt_items->get_result();

// Mapping item pesanan ke array untuk mudah diakses di form
while ($item = $items_result->fetch_assoc()) {
    $order_items[$item['idmenu']] = $item; // Gunakan idmenu sebagai key
}
$stmt_items->close();

// Ambil pesan error/sukses dari session jika ada (setelah redirect)
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pesanan - Meja <?php echo htmlspecialchars($order['Nomeja']); ?> - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #06D6A0;
            --primary-dark: #05BF8E;
            --secondary-color: #6c757d; /* Warna abu-abu standar */
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --color-border: #dee2e6;
            /* Tambahkan variabel dari file lain jika diperlukan */
            --sidebar-width: 280px;
            --content-padding: 1.5rem;
            --content-padding-top: 60px;
            --transition-speed: 0.3s;
            --transition-type: ease;
        }
        body { background-color: var(--light-gray); }

        /* Asumsi sidebar.php punya style sendiri */
        .main-content {
            padding: var(--content-padding);
            padding-top: var(--content-padding-top);
            margin-left: var(--sidebar-width); /* Sesuaikan dengan lebar sidebar */
            transition: margin-left var(--transition-speed) var(--transition-type);
        }
        /* Style untuk layar kecil jika sidebar disembunyikan */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
        }


        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); /* Sedikit lebih kecil */
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .menu-card {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
        }

        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .menu-card .card-header {
            background: none;
            border-bottom: 1px solid var(--color-border);
            padding: 0.75rem 1rem; /* Padding header card */
        }
         .menu-card .card-title {
             font-size: 1rem; /* Ukuran font nama menu */
             font-weight: 600;
             margin-bottom: 0.25rem;
         }

        .menu-card .card-body {
            padding: 1rem;
            flex-grow: 1; /* Agar body mengisi ruang */
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Dorong kontrol ke bawah */
        }
        .menu-card .card-text {
             font-size: 0.9rem;
             color: #6c757d;
             margin-bottom: 0.75rem;
         }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: auto; /* Dorong ke bawah */
        }

        .quantity-control input {
            width: 50px; /* Lebar input qty */
            text-align: center;
            border: 1px solid var(--color-border);
            border-radius: 0.25rem;
            padding: 0.3rem;
            font-size: 0.9rem;
            /* Mencegah panah default browser */
            -moz-appearance: textfield;
        }
         .quantity-control input::-webkit-outer-spin-button,
         .quantity-control input::-webkit-inner-spin-button {
             -webkit-appearance: none;
             margin: 0;
         }


        .btn-quantity {
            padding: 0.3rem 0.6rem; /* Padding tombol +/- */
            font-size: 0.8rem;
            border-radius: 0.25rem;
            line-height: 1; /* Rapikan ikon */
        }

        .order-summary {
            background: white;
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            padding: 1.5rem;
            position: sticky;
            top: 1rem; /* Jarak dari atas saat scroll */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
         .order-summary h4 {
             font-size: 1.2rem;
             margin-bottom: 1rem;
             color: var(--dark-gray);
             border-bottom: 1px solid var(--color-border);
             padding-bottom: 0.75rem;
         }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }
         .btn-secondary:hover {
             filter: brightness(90%);
         }

        #orderSummaryList {
            list-style: none;
            padding: 0;
            margin: 0 0 1rem 0; /* Margin bawah sebelum total */
            max-height: 300px; /* Batasi tinggi ringkasan jika terlalu panjang */
            overflow-y: auto; /* Tambahkan scroll jika perlu */
        }

        #orderSummaryList li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0; /* Padding item ringkasan */
            border-bottom: 1px dashed var(--color-border);
            font-size: 0.9rem;
        }
         #orderSummaryList li span:first-child { flex-grow: 1; margin-right: 1rem; }
         #orderSummaryList li span:last-child { font-weight: 500; white-space: nowrap; }


        #orderSummaryList li:last-child {
            border-bottom: none;
        }
         #orderSummaryList .summary-item-name {
             color: var(--dark-gray);
         }
         #orderSummaryList .summary-item-subtotal {
             color: var(--primary-dark);
         }
         #orderSummaryList .empty-summary {
             text-align: center;
             color: #6c757d;
             font-style: italic;
             padding: 1rem 0;
         }
         .total-section h5 {
             font-size: 1.1rem;
             font-weight: 600;
             color: var(--dark-gray);
         }

    </style>
</head>
<body>
    <?php include '../../components/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); // Selalu escape output ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
             <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); // Selalu escape output ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>


            <div class="row">
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>Edit Pesanan - Meja <?php echo htmlspecialchars($order['Nomeja']); ?></h2>
                        <div>
                            <a href="view_order.php?id=<?php echo $order_id; ?>" class="btn btn-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> Kembali ke Detail
                            </a>
                        </div>
                    </div>

                    <form method="POST" id="editOrderForm" action="edit_order.php?id=<?php echo $order_id; // Pastikan action mengarah ke halaman yang benar ?>">
                        <div class="menu-grid">
                            <?php if ($menu_result->num_rows > 0): ?>
                                <?php while ($menu = $menu_result->fetch_assoc()):
                                    $menu_id = $menu['idmenu'];
                                    // Ambil jumlah saat ini dari array $order_items
                                    $current_quantity = isset($order_items[$menu_id]) ? $order_items[$menu_id]['jumlah'] : 0;
                                ?>
                                    <div class="card menu-card">
                                        <div class="card-header">
                                            <h5 class="card-title"><?php echo htmlspecialchars($menu['Namamenu']); ?></h5>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text">Rp <?php echo number_format($menu['Harga'], 0, ',', '.'); ?></p>
                                            <div class="quantity-control">
                                                <button type="button" class="btn btn-outline-danger btn-quantity" onclick="updateQuantity(<?php echo $menu_id; ?>, -1)">
                                                    <i class="bi bi-dash-lg"></i>
                                                </button>
                                                <input type="number" name="menu_quantity[<?php echo $menu_id; ?>]"
                                                       value="<?php echo $current_quantity; ?>"
                                                       min="0" class="form-control form-control-sm quantity-input"
                                                       data-price="<?php echo $menu['Harga']; ?>"
                                                       data-name="<?php echo htmlspecialchars($menu['Namamenu']); ?>"
                                                       onchange="updateOrderSummary()"
                                                       aria-label="Jumlah <?php echo htmlspecialchars($menu['Namamenu']); ?>">
                                                <button type="button" class="btn btn-outline-success btn-quantity" onclick="updateQuantity(<?php echo $menu_id; ?>, 1)">
                                                    <i class="bi bi-plus-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted">Tidak ada menu yang tersedia.</p>
                            <?php endif; ?>
                        </div>
                        </form>
                </div>

                <div class="col-lg-4">
                    <div class="order-summary">
                        <h4><i class="bi bi-receipt"></i> Ringkasan Pesanan</h4>
                        <ul id="orderSummaryList">
                            <li class="empty-summary"><em>Belum ada item</em></li>
                        </ul>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3 total-section">
                            <h5>Total:</h5>
                            <h5 id="orderTotal">Rp 0</h5>
                        </div>
                        <button type="submit" form="editOrderForm" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Simpan Perubahan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function formatRupiah(amount) {
            // Format ke Rupiah tanpa desimal
            return 'Rp ' + amount.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function updateQuantity(menuId, change) {
            const input = document.querySelector(`input[name="menu_quantity[${menuId}]"]`);
            if (!input) return; // Handle jika input tidak ditemukan

            const currentValue = parseInt(input.value || 0);
            let newValue = currentValue + change;

            // Pastikan nilai tidak negatif
            if (newValue < 0) {
                newValue = 0;
            }

            input.value = newValue;
            // Trigger change event secara manual untuk memastikan onchange terpanggil
            input.dispatchEvent(new Event('change'));
        }

        function updateOrderSummary() {
            const inputs = document.querySelectorAll('input[name^="menu_quantity"]');
            const summaryList = document.getElementById('orderSummaryList');
            const totalElement = document.getElementById('orderTotal');
            let total = 0;
            let itemCount = 0; // Hitung jumlah item yang ada di ringkasan

            summaryList.innerHTML = ''; // Kosongkan ringkasan sebelum mengisi ulang

            inputs.forEach(input => {
                const quantity = parseInt(input.value || 0);
                if (quantity > 0) {
                    itemCount++; // Tambah hitungan item
                    const price = parseInt(input.dataset.price || 0);
                    const name = input.dataset.name || 'Unknown Item';
                    const subtotal = quantity * price;
                    total += subtotal;

                    const li = document.createElement('li');
                    li.innerHTML = `
                        <span class="summary-item-name">${name} x ${quantity}</span>
                        <span class="summary-item-subtotal">${formatRupiah(subtotal)}</span>
                    `;
                    summaryList.appendChild(li);
                }
            });

            // Tampilkan pesan jika tidak ada item
            if (itemCount === 0) {
                summaryList.innerHTML = '<li class="empty-summary"><em>Belum ada item</em></li>';
            }

            totalElement.textContent = formatRupiah(total);
        }

        // Initialize order summary on page load
        document.addEventListener('DOMContentLoaded', updateOrderSummary);

        // Tambahkan event listener ke semua input quantity untuk update summary saat nilainya berubah
        const quantityInputs = document.querySelectorAll('.quantity-input');
        quantityInputs.forEach(input => {
            input.addEventListener('change', updateOrderSummary);
            input.addEventListener('keyup', updateOrderSummary); // Update saat mengetik juga
        });

    </script>
</body>
</html>
