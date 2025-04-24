<?php
session_start();
// Diasumsikan config.php berisi koneksi $conn, fungsi clean_input, check_session_timeout
require_once '../../config/config.php'; 

// Pastikan fungsi clean_input ada
if (!function_exists('clean_input')) {
    function clean_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Periksa apakah pengguna sudah login dan levelnya 'admin'
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'admin') {
    header("Location: ../../pages/auth/login.php"); 
    exit();
}

// Periksa timeout sesi (gunakan implementasi dari config atau fallback)
if (function_exists('check_session_timeout')) {
    check_session_timeout();
} else {
    // Implementasi sederhana jika belum ada
    $timeout_duration = 1800; // 30 menit
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: ../../pages/auth/login.php?status=session_expired");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Ambil data pengguna
$user = $_SESSION['user'];

// Handle form submissions (CRUD Operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan koneksi $conn ada
    if (!isset($conn) || !$conn) {
       $_SESSION['error_message'] = "Koneksi database gagal. Periksa config.php";
       header("Location: meja.php"); // Redirect kembali
       exit();
    }

    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    if (isset($_POST['nomeja']) && is_numeric($_POST['nomeja']) && intval($_POST['nomeja']) > 0) {
                        $nomeja = intval(clean_input($_POST['nomeja']));
                        // Periksa duplikasi nomor meja
                        $check_sql = "SELECT id FROM meja WHERE Nomeja = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("i", $nomeja);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        if ($check_result->num_rows === 0) {
                            $sql = "INSERT INTO meja (Nomeja, status) VALUES (?, 'kosong')";
                            $stmt = $conn->prepare($sql);
                            if (!$stmt) throw new Exception("Prepare statement gagal (add): " . $conn->error);
                            $stmt->bind_param("i", $nomeja);
                            $stmt->execute();
                            $_SESSION['success_message'] = "Meja " . htmlspecialchars($nomeja) . " berhasil ditambahkan.";
                            $stmt->close();
                        } else {
                            $_SESSION['error_message'] = "Nomor meja " . htmlspecialchars($nomeja) . " sudah ada.";
                        }
                        $check_stmt->close();
                    } else {
                        $_SESSION['error_message'] = "Nomor meja tidak valid (harus angka positif).";
                    }
                    break;

                case 'edit':
                    if (isset($_POST['id'], $_POST['nomeja'], $_POST['status']) 
                        && is_numeric($_POST['id']) 
                        && is_numeric($_POST['nomeja']) && intval($_POST['nomeja']) > 0) 
                    {
                        $id = intval(clean_input($_POST['id']));
                        $nomeja = intval(clean_input($_POST['nomeja']));
                        $status = clean_input($_POST['status']);
                        // Validasi status
                        $allowed_statuses = ['kosong', 'terpakai']; // Definisikan status yang diizinkan
                        if (!in_array($status, $allowed_statuses)) {
                            $_SESSION['error_message'] = "Status tidak valid.";
                            break; 
                        }

                        // Periksa duplikasi nomor meja (kecuali untuk ID yang sama)
                        $check_sql = "SELECT id FROM meja WHERE Nomeja = ? AND id != ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("ii", $nomeja, $id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();

                        if ($check_result->num_rows === 0) {
                            $sql = "UPDATE meja SET Nomeja = ?, status = ? WHERE id = ?";
                            $stmt = $conn->prepare($sql);
                            if (!$stmt) throw new Exception("Prepare statement gagal (edit): " . $conn->error);
                            $stmt->bind_param("isi", $nomeja, $status, $id);
                            $stmt->execute();
                             $_SESSION['success_message'] = "Meja " . htmlspecialchars($nomeja) . " berhasil diperbarui.";
                            $stmt->close();
                        } else {
                            $_SESSION['error_message'] = "Nomor meja " . htmlspecialchars($nomeja) . " sudah digunakan oleh meja lain.";
                        }
                        $check_stmt->close();
                    } else {
                        $_SESSION['error_message'] = "Data edit tidak lengkap atau tidak valid.";
                    }
                    break;

                case 'delete':
                     if (isset($_POST['id']) && is_numeric($_POST['id'])) {
                        $id = intval(clean_input($_POST['id']));
                        // Optional: Periksa apakah meja sedang digunakan dalam transaksi aktif sebelum menghapus
                        // ... (logika pemeriksaan tambahan jika perlu) ...

                        $sql = "DELETE FROM meja WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) throw new Exception("Prepare statement gagal (delete): " . $conn->error);
                        $stmt->bind_param("i", $id);
                        $stmt->execute();
                         $_SESSION['success_message'] = "Meja berhasil dihapus.";
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = "ID meja untuk dihapus tidak valid.";
                    }
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Terjadi kesalahan: " . $e->getMessage();
            error_log("Database Error (meja.php): " . $e->getMessage()); // Log error
        }

        // Redirect setelah operasi POST
        header("Location: meja.php");
        exit();
    }
}

// --- Ambil Data Meja untuk Ditampilkan ---
$tables = [];
if (isset($conn) && $conn) {
    $result = $conn->query("SELECT * FROM meja ORDER BY Nomeja ASC"); 
    if ($result) {
        $tables = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    } else {
         $_SESSION['error_message'] = "Gagal mengambil data meja: " . $conn->error; // Simpan error jika query gagal
    }
    $conn->close(); // Tutup koneksi di sini setelah semua operasi DB selesai
} else {
    // Jika koneksi gagal dari awal
    $_SESSION['error_message'] = "Koneksi database tidak tersedia.";
}


// Ambil dan hapus pesan dari session
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Meja - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- Variabel Warna Tema (ASUMSI DARI sidebar.php) --- */
        :root {
            --primary-color: #06D6A0; 
            --secondary-color: #F8FFE5; 
            --background-color: #1A202C; 
            --text-color: var(--secondary-color);
            --text-muted-color: rgba(248, 255, 229, 0.7); 
            --icon-color: rgba(248, 255, 229, 0.8); 
            --border-color: rgba(6, 214, 160, 0.15); 
            --hover-bg: rgba(6, 214, 160, 0.1); 
            --active-bg: rgba(6, 214, 160, 0.2); 
            --scrollbar-thumb: var(--primary-color);
            --tooltip-bg: rgba(0, 0, 0, 0.85);
            --tooltip-text: #ffffff;
            --shadow-color: rgba(0, 0, 0, 0.06); 
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --transition-speed: 0.3s;
            --transition-type: cubic-bezier(0.4, 0, 0.2, 1);
            --content-padding: 2rem;
            --content-padding-top: 5rem; 
            --mobile-breakpoint: 768px;
            --content-bg: #f8f9fa; 
            --card-bg: #ffffff;
            --text-dark: #212529; 
            --text-light: #6c757d; 
            --border-radius: 0.5rem; 
            --teal-color: #16a085; 
            --yellow-color: #f39c12; 
            --blue-color: #3498db; 
            --purple-color: #9b59b6; 
            --red-color: #e74c3c; 
            --orange-color: #f39c12; /* Warna untuk status terpakai */
            --green-color: var(--primary-color); /* Warna untuk status kosong */
            --grey-color: #adb5bd; /* Warna untuk status tidak diketahui */
        }
        

        /* --- Styling Global & Content Wrapper --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--content-bg, #f8f9fa); 
        }

        /* Asumsikan .content-wrapper sudah diatur oleh layout utama/sidebar.php */
        .content-wrapper {
            min-height: 100vh;
            padding: var(--content-padding, 1.5rem);
            padding-top: var(--content-padding-top, 5rem); 
            margin-left: 0; /* Default untuk mobile */
            transition: margin-left var(--transition-speed, 0.3s) var(--transition-type, ease);
            position: relative;
            z-index: 1;
        }
        @media (min-width: 769px) {
            .content-wrapper {
                margin-left: var(--sidebar-width, 280px);
            }
            .content-wrapper.collapsed-sidebar {
                margin-left: var(--sidebar-collapsed-width, 80px);
            }
            .content-wrapper.shifted { 
                margin-left: 0;
            }
        }
        @media (max-width: 768px) {
             .content-wrapper {
                 padding: 1rem;
                 padding-top: var(--content-padding-top, 5rem); 
             }
        }
        /* Akhir CSS Content Wrapper */


        /* --- Styling Halaman Manajemen Meja --- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap; 
        }
        .page-header h2 {
             margin-bottom: 0.5rem; 
             font-weight: 700;
             color: var(--text-dark);
        }
        .page-header .btn-primary { /* Tombol Tambah */
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: var(--background-color); 
            font-weight: 600;
            padding: 0.6rem 1.2rem;
            border-radius: var(--border-radius);
            transition: all 0.2s ease;
        }
         .page-header .btn-primary:hover {
             background-color: #05b88a; 
             border-color: #05b88a;
             box-shadow: 0 4px 8px rgba(6, 214, 160, 0.3);
         }

        /* Styling Kartu Meja */
        .table-card {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--card-bg);
            box-shadow: 0 2px 5px var(--shadow-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%; 
            display: flex;
            flex-direction: column; 
        }
        .table-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 12px rgba(0,0,0,0.1);
        }
        .table-card .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.8rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--card-bg); 
            border-radius: var(--border-radius) var(--border-radius) 0 0; 
        }
        .table-card .table-info { /* Grup untuk ikon meja dan nomor */
             display: flex;
             align-items: center;
             font-weight: 600;
             font-size: 1.1rem;
             color: var(--text-dark);
        }
         .table-card .table-info i { /* Ikon meja */
             margin-right: 0.6rem;
             color: var(--text-light);
             font-size: 1.3em;
         }
        .table-card .status-badge { /* Badge di header */
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
            font-weight: 600;
            display: inline-flex; /* Agar ikon dan teks sejajar */
            align-items: center;
        }
         .table-card .status-badge i { /* Ikon di dalam badge */
             margin-right: 0.3rem;
             font-size: 1.1em;
         }
         /* Warna badge sesuai status */
         .badge.status-kosong { 
             background-color: var(--green-color); 
             color: var(--background-color); /* Teks gelap di hijau terang */
         }
         .badge.status-terpakai { 
             background-color: var(--orange-color); 
             color: white; /* Teks putih di oranye */
         }
         .badge.status-unknown { 
             background-color: var(--grey-color); 
             color: white;
         }

        .table-card .card-body {
            padding: 1.25rem;
            flex-grow: 1; 
            display: flex; /* Ubah ke flex */
            align-items: center; /* Pusatkan konten body jika sedikit */
            justify-content: center; /* Pusatkan konten body jika sedikit */
            text-align: center; /* Pusatkan teks jika hanya sedikit */
            min-height: 80px; /* Beri tinggi minimal */
        }
         .table-card .card-body p { /* Teks tambahan jika ada */
             font-size: 0.9rem;
             color: var(--text-light);
             margin-bottom: 0; /* Hapus margin jika hanya ini */
         }
        
        .table-card .card-footer { /* Footer untuk tombol */
            padding: 0.8rem 1.25rem;
            background-color: transparent; 
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.5rem; 
        }
        .table-card .card-footer .btn {
            flex: 1; 
            font-size: 0.85rem;
            font-weight: 500;
            padding: 0.4rem 0.8rem;
        }
        .table-card .btn-edit { 
             background-color: var(--blue-color);
             border-color: var(--blue-color);
             color: white;
        }
         .table-card .btn-edit:hover {
             background-color: #2980b9; 
             border-color: #2980b9;
         }
         .table-card .btn-delete { 
             background-color: var(--red-color);
             border-color: var(--red-color);
             color: white;
         }
         .table-card .btn-delete:hover {
              background-color: #c0392b; 
              border-color: #c0392b;
         }

        /* Styling Modal (Sama seperti sebelumnya, sudah cukup baik) */
        .modal-header {
            background-color: var(--background-color); 
            color: var(--text-color);
            border-bottom: 1px solid var(--primary-color);
        }
        .modal-header .btn-close {
             filter: invert(1) grayscale(100%) brightness(200%); 
        }
        .modal-title { font-weight: 600; }
        .modal-content { border-radius: var(--border-radius); border: none; }
        .modal-body { padding: 2rem; }
        .modal-footer {
            background-color: var(--content-bg);
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
        }
         .modal-footer .btn-secondary { 
             background-color: var(--text-light); border-color: var(--text-light); color: white;
         }
         .modal-footer .btn-secondary:hover { background-color: #5a6268; border-color: #5a6268; }
         .modal-footer .btn-primary { 
             background-color: var(--primary-color); border-color: var(--primary-color); color: var(--background-color);
         }
         .modal-footer .btn-primary:hover { background-color: #05b88a; border-color: #05b88a; }
          .modal-footer .btn-danger { 
             background-color: var(--red-color); border-color: var(--red-color); color: white;
         }
         .modal-footer .btn-danger:hover { background-color: #c0392b; border-color: #c0392b; }

        /* Styling Form dalam Modal */
        .form-label { font-weight: 500; color: var(--text-light); margin-bottom: 0.5rem; }
        .form-control, .form-select {
            border-radius: var(--border-radius); border: 1px solid var(--border-color);
            padding: 0.6rem 1rem; margin-bottom: 1rem; 
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem var(--hover-bg); outline: none;
        }
        .form-control.is-invalid, .form-select.is-invalid { border-color: var(--red-color); }
        .invalid-feedback { color: var(--red-color); font-size: 0.85em; }

        /* Styling Alert */
        .alert {
             border-radius: var(--border-radius); border: none; color: white;
             margin-bottom: 1.5rem; 
        }
        .alert-danger { background-color: var(--red-color); }
        .alert-success { background-color: var(--green-color); }
        .alert .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        /* Empty State */
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-light); }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; display: block; }

    </style>
</head>
<body>

<?php include '../../components/sidebar.php'; // Pastikan path ini benar ?>

<div class="content-wrapper" id="main-content">
    <div class="container-fluid">

        <div class="page-header">
            <h2>Manajemen Meja</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTableModal">
                <i class="bi bi-plus-lg me-2"></i>Tambah Meja
            </button>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                 <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <div class="row g-4">
            <?php if (!empty($tables)): ?>
                <?php foreach ($tables as $table): 
                    $status_icon = 'bi-question-circle-fill'; // Default
                    $status_badge_class = 'status-unknown'; // Default
                    if ($table['status'] === 'kosong') {
                        $status_icon = 'bi-check-circle-fill';
                        $status_badge_class = 'status-kosong';
                    } elseif ($table['status'] === 'terpakai') {
                        $status_icon = 'bi-person-fill'; 
                        $status_badge_class = 'status-terpakai';
                    }
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12"> 
                    <div class="card table-card h-100">
                        <div class="card-header">
                            <span class="table-info">
                                <i class="bi bi-grid-3x3-gap-fill"></i> 
                                Meja <?php echo htmlspecialchars($table['Nomeja']); ?>
                            </span>
                            <span class="badge <?php echo $status_badge_class; ?> status-badge" 
                                  title="Status: <?php echo ucfirst(htmlspecialchars($table['status'])); ?>">
                                <i class="<?php echo $status_icon; ?>"></i>
                                <?php echo ucfirst(htmlspecialchars($table['status'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                             
                             <p class="text-muted"><small>Klik tombol di bawah untuk mengelola.</small></p>
                        </div>
                         <div class="card-footer">
                            <button type="button" class="btn btn-edit" data-bs-toggle="modal" data-bs-target="#editTableModal"
                                    data-id="<?php echo htmlspecialchars($table['id']); ?>"
                                    data-nomeja="<?php echo htmlspecialchars($table['Nomeja']); ?>"
                                    data-status="<?php echo htmlspecialchars($table['status']); ?>">
                                <i class="bi bi-pencil-fill me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-delete" data-bs-toggle="modal" data-bs-target="#deleteTableModal"
                                    data-id="<?php echo htmlspecialchars($table['id']); ?>"
                                    data-nomeja="<?php echo htmlspecialchars($table['Nomeja']); ?>">
                                <i class="bi bi-trash-fill me-1"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
             <?php else: ?>
                <div class="col-12">
                    <div class="empty-state card card-body">
                         <i class="bi bi-journal-x"></i>
                        <p>Belum ada data meja yang ditambahkan.</p>
                        <p>Klik tombol "Tambah Meja" untuk memulai.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div></div><div class="modal fade" id="addTableModal" tabindex="-1" aria-labelledby="addTableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"> 
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTableModalLabel"><i class="bi bi-plus-square-fill me-2"></i>Tambah Meja Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="meja.php" class="needs-validation" novalidate> 
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="add-nomeja" class="form-label">Nomor Meja <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="add-nomeja" name="nomeja" required min="1" placeholder="Masukkan nomor meja">
                            <div class="invalid-feedback">Nomor meja harus diisi dan berupa angka positif.</div>
                        </div>
                        <small class="text-muted">Status meja baru akan otomatis menjadi "kosong".</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editTableModal" tabindex="-1" aria-labelledby="editTableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTableModalLabel"><i class="bi bi-pencil-square me-2"></i>Edit Meja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="meja.php" class="needs-validation" novalidate> 
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-nomeja" class="form-label">Nomor Meja <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="edit-nomeja" name="nomeja" required min="1" placeholder="Masukkan nomor meja">
                             <div class="invalid-feedback">Nomor meja harus diisi dan berupa angka positif.</div>
                        </div>
                        <div class="mb-3">
                            <label for="edit-status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit-status" name="status" required>
                                <option value="" disabled>Pilih Status</option> 
                                <option value="kosong">Kosong</option>
                                <option value="terpakai">Terpakai</option>
                                </select>
                             <div class="invalid-feedback">Silakan pilih status meja.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteTableModal" tabindex="-1" aria-labelledby="deleteTableModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white"> 
                    <h5 class="modal-title" id="deleteTableModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Konfirmasi Hapus Meja</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="meja.php"> 
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete-id">
                        <p class="fs-5">Anda yakin ingin menghapus <strong>Meja <span id="delete-nomeja-display"></span></strong>?</p>
                        <p class="text-danger"><small><i class="bi bi-exclamation-circle me-1"></i> Tindakan ini tidak dapat diurungkan.</small></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash-fill me-1"></i>Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // --- Modal Event Listeners & Data Population ---
            const editTableModal = document.getElementById('editTableModal');
            if (editTableModal) {
                editTableModal.addEventListener('show.bs.modal', event => {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const nomeja = button.getAttribute('data-nomeja');
                    const status = button.getAttribute('data-status');

                    const modalTitle = editTableModal.querySelector('.modal-title');
                    const modalInputId = editTableModal.querySelector('#edit-id');
                    const modalInputNomeja = editTableModal.querySelector('#edit-nomeja');
                    const modalSelectStatus = editTableModal.querySelector('#edit-status');

                    modalTitle.textContent = `Edit Meja ${nomeja}`; 
                    modalInputId.value = id;
                    modalInputNomeja.value = nomeja;
                    modalSelectStatus.value = status;

                    clearValidation(editTableModal.querySelector('form'));
                });
            }

            const deleteTableModal = document.getElementById('deleteTableModal');
            if (deleteTableModal) {
                deleteTableModal.addEventListener('show.bs.modal', event => {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const nomeja = button.getAttribute('data-nomeja');

                    const modalInputId = deleteTableModal.querySelector('#delete-id');
                    const modalNomejaDisplay = deleteTableModal.querySelector('#delete-nomeja-display'); 

                    modalInputId.value = id;
                    modalNomejaDisplay.textContent = nomeja; 
                });
            }

             const addTableModal = document.getElementById('addTableModal');
              if(addTableModal) {
                  addTableModal.addEventListener('shown.bs.modal', () => {
                       const nomejaInput = addTableModal.querySelector('#add-nomeja');
                       if(nomejaInput) {
                           nomejaInput.focus();
                       }
                       clearValidation(addTableModal.querySelector('form'));
                  });
              }


            // --- Bootstrap Client-side Validation ---
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Fungsi untuk membersihkan status validasi dari form
            function clearValidation(form) {
                 if (!form) return;
                 form.classList.remove('was-validated'); 
                 const invalidInputs = form.querySelectorAll('.is-invalid');
                 invalidInputs.forEach(input => {
                     input.classList.remove('is-invalid');
                 });
            }

            // Opsional: Reset form dan validasi saat modal ditutup
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('hidden.bs.modal', () => {
                    const form = modal.querySelector('form');
                    if (form) {
                        form.reset(); 
                        clearValidation(form); 
                    }
                });
            });

             // --- Sidebar Toggle Logic (jika diperlukan) ---
             // ... (kode toggle sidebar bisa ditambahkan di sini jika belum ada di file lain) ...


        }); // Akhir DOMContentLoaded
    </script>
</body>
</html>
