<?php
session_start();
// Diasumsikan config.php berisi koneksi $conn dan fungsi check_session_timeout()
require_once '../../config/config.php'; 

// Periksa apakah pengguna sudah login dan levelnya 'admin'
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'admin') {
    // Jika tidak, arahkan ke halaman login
    header("Location: ../../pages/auth/login.php");
    exit();
}

// Periksa timeout sesi
// check_session_timeout(); // Pastikan fungsi ini ada di config.php atau di-include

// Ambil data pengguna dari session
$user = $_SESSION['user'];

// --- Ambil Data untuk Dashboard ---

// 1. Data Count untuk Kartu Ringkasan
$total_meja = 0;
$total_menu = 0;
$total_users = 0;

// Hitung total meja
$result_meja = $conn->query("SELECT COUNT(*) as total FROM meja");
if ($result_meja) {
    $row_meja = $result_meja->fetch_assoc();
    $total_meja = $row_meja['total'] ?? 0;
    $result_meja->free();
}

// Hitung total menu
$result_menu = $conn->query("SELECT COUNT(*) as total FROM menu");
if ($result_menu) {
    $row_menu = $result_menu->fetch_assoc();
    $total_menu = $row_menu['total'] ?? 0;
    $result_menu->free();
}

// Hitung total pengguna
$result_users = $conn->query("SELECT COUNT(*) as total FROM users");
if ($result_users) {
    $row_users = $result_users->fetch_assoc();
    $total_users = $row_users['total'] ?? 0;
    $result_users->free();
}

// 2. Data Pengguna Terbaru (Contoh: 5 terbaru)
// Asumsi ada kolom 'created_at' atau 'tanggal_daftar' di tabel users
$recent_users = [];
$query_recent_users = "SELECT nama, level, created_at FROM users ORDER BY created_at DESC LIMIT 5"; 
// Ganti 'created_at' dengan kolom tanggal pendaftaran yang sesuai
$result_recent_users = $conn->query($query_recent_users);
if ($result_recent_users) {
    while ($row = $result_recent_users->fetch_assoc()) {
        $recent_users[] = $row;
    }
    $result_recent_users->free();
}


$conn->close(); // Tutup koneksi setelah semua data diambil
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Kasir Resto</title>
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
            --border-color: rgba(6, 214, 160, 0.15); /* Sedikit lebih jelas */
            --hover-bg: rgba(6, 214, 160, 0.1); 
            --active-bg: rgba(6, 214, 160, 0.2); 
            --scrollbar-thumb: var(--primary-color);
            --tooltip-bg: rgba(0, 0, 0, 0.85);
            --tooltip-text: #ffffff;
            --shadow-color: rgba(0, 0, 0, 0.08); /* Shadow lebih halus */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --transition-speed: 0.3s;
            --transition-type: cubic-bezier(0.4, 0, 0.2, 1);
            --content-padding: 2rem;
            --content-padding-top: 5rem; 
            --mobile-breakpoint: 768px;
            --content-bg: #f8f9fa; /* Background konten sedikit abu */
            --card-bg: #ffffff;
            --text-dark: #212529; /* Hitam standar bootstrap */
            --text-light: #6c757d; /* Abu standar bootstrap */
            --border-radius: 0.5rem; 
            --teal-color: #16a085; 
            --yellow-color: #f39c12; 
            --blue-color: #3498db; 
            --purple-color: #9b59b6; /* Warna tambahan */
            --red-color: #e74c3c; /* Warna tambahan */
        }
        

        /* --- Styling Global & Content Wrapper --- */
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--content-bg, #f8f9fa); 
        }

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

        /* --- Styling Card Umum --- */
        .card {
            border: none; 
            border-radius: var(--border-radius, 0.5rem);
            box-shadow: 0 3px 8px var(--shadow-color, rgba(0, 0, 0, 0.06)); /* Shadow sedikit lebih halus */
            margin-bottom: 1.5rem;
            background-color: var(--card-bg, #ffffff);
            height: 100%; /* Pastikan card mengisi tinggi kolom */
        }
        .card-header {
            background-color: var(--card-bg, #ffffff); /* Samakan dengan body */
            border-bottom: 1px solid var(--border-color, rgba(6, 214, 160, 0.15));
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--text-dark, #212529);
            display: flex;
            align-items: center;
        }
         .card-header i { /* Styling ikon di header */
            margin-right: 0.5rem;
            font-size: 1.1em;
            color: var(--text-light); /* Warna ikon abu */
         }
        .card-title {
             margin-bottom: 0;
             font-size: 1.05rem; /* Sedikit lebih kecil */
        }
        .card-body {
            padding: 1.5rem;
        }
        .card-footer {
             background-color: var(--content-bg, #f8f9fa);
             border-top: 1px solid var(--border-color, rgba(6, 214, 160, 0.15));
        }

        /* --- Styling Kartu Ringkasan Admin --- */
        .admin-summary-card {
            position: relative;
            overflow: hidden; 
            border-left: 5px solid transparent; /* Ganti border atas jadi kiri */
            border-top: none; /* Hapus border atas */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .admin-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px var(--shadow-color, rgba(0, 0, 0, 0.1));
        }
        .admin-summary-card .card-body {
            display: flex;
            align-items: center; 
            justify-content: space-between; 
            flex-wrap: wrap; 
        }
        .admin-summary-card .summary-icon {
            width: 56px; /* Sedikit lebih kecil */
            height: 56px; 
            font-size: 1.8rem; /* Sedikit lebih kecil */
            border-radius: var(--border-radius, 0.5rem); /* Kotak rounded, bukan lingkaran */
            color: #fff; 
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem; 
            margin-bottom: 0.5rem; 
        }
        .admin-summary-card .summary-content {
             flex-grow: 1; 
             margin-right: 1rem; 
             margin-bottom: 0.5rem; 
        }
        .admin-summary-card .summary-content h5 { 
            font-size: 0.85rem; /* Lebih kecil */
            font-weight: 500;
            color: var(--text-light, #6c757d);
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-summary-card .summary-content .value { 
            font-size: 1.8rem; 
            font-weight: 700;
            color: var(--text-dark, #212529);
            margin-bottom: 0;
        }
        .admin-summary-card .card-action {
             flex-shrink: 0; 
        }

        /* Warna spesifik untuk admin summary cards (border kiri & ikon) */
        .admin-summary-card.meja { border-left-color: var(--primary-color, #06D6A0); }
        .admin-summary-card.meja .summary-icon { background-color: var(--primary-color, #06D6A0); }

        .admin-summary-card.menu { border-left-color: var(--teal-color, #16a085); }
        .admin-summary-card.menu .summary-icon { background-color: var(--teal-color, #16a085); }

        .admin-summary-card.users { border-left-color: var(--blue-color, #3498db); }
        .admin-summary-card.users .summary-icon { background-color: var(--blue-color, #3498db); }

        /* Styling Tombol Kelola di dalam Card */
        .admin-summary-card .btn-link-theme {
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.2s ease;
        }
         .admin-summary-card .btn-link-theme i {
             font-size: 0.9em; /* Ikon sedikit lebih kecil */
             vertical-align: middle;
         }
        .admin-summary-card.meja .btn-link-theme:hover { color: var(--primary-color); }
        .admin-summary-card.menu .btn-link-theme:hover { color: var(--teal-color); }
        .admin-summary-card.users .btn-link-theme:hover { color: var(--blue-color); }


        /* --- Styling Kartu Aksi Cepat --- */
        .quick-actions .btn {
            margin-bottom: 0.75rem; /* Jarak antar tombol */
            text-align: left; /* Ratakan teks kiri */
            display: flex; /* Gunakan flex untuk ikon dan teks */
            align-items: center;
            font-weight: 500;
        }
         .quick-actions .btn i {
             margin-right: 0.75rem;
             font-size: 1.2em;
             width: 20px; /* Lebar tetap untuk ikon */
             text-align: center;
         }
         /* Warna tombol aksi cepat */
         .btn-outline-primary-theme {
             color: var(--primary-color); border-color: var(--primary-color);
         }
         .btn-outline-primary-theme:hover {
             background-color: var(--primary-color); color: var(--card-bg);
         }
         .btn-outline-teal-theme {
             color: var(--teal-color); border-color: var(--teal-color);
         }
         .btn-outline-teal-theme:hover {
             background-color: var(--teal-color); color: var(--card-bg);
         }
         .btn-outline-blue-theme {
             color: var(--blue-color); border-color: var(--blue-color);
         }
         .btn-outline-blue-theme:hover {
             background-color: var(--blue-color); color: var(--card-bg);
         }
         .btn-outline-purple-theme {
             color: var(--purple-color); border-color: var(--purple-color);
         }
         .btn-outline-purple-theme:hover {
             background-color: var(--purple-color); color: var(--card-bg);
         }


        /* --- Styling Kartu Pengguna Terbaru --- */
        .recent-users .list-group-item {
            border: none; /* Hapus border list group */
            padding: 0.8rem 0; /* Sesuaikan padding */
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color); /* Garis pemisah tipis */
        }
        .recent-users .list-group-item:last-child {
             border-bottom: none; /* Hapus border item terakhir */
        }
        .recent-users .user-info {
            display: flex;
            align-items: center;
        }
        .recent-users .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--hover-bg); /* Background avatar default */
            color: var(--primary-color); /* Warna ikon avatar */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-weight: 600;
        }
        .recent-users .user-details .user-name {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0;
            font-size: 0.95rem;
        }
        .recent-users .user-details .user-level {
            font-size: 0.8rem;
            color: var(--text-light);
            text-transform: capitalize;
        }
        .recent-users .registration-time {
            font-size: 0.8rem;
            color: var(--text-light);
            text-align: right;
        }

        /* Utility */
        .fw-bold { font-weight: 700 !important; }
        .text-dark { color: var(--text-dark, #212529) !important; }
        .text-secondary { color: var(--text-light, #6c757d) !important; }

    </style>
</head>
<body>
    <?php 
        // Include sidebar
        include '../../components/sidebar.php'; 
    ?>

    <div class="content-wrapper" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="fw-bold text-dark">Dashboard Admin</h2>
                    <p class="text-secondary">Selamat datang kembali, <?php echo htmlspecialchars($user['nama']); ?>!</p>
                </div>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card admin-summary-card meja h-100">
                        <div class="card-body">
                            <div class="summary-icon">
                                <i class="bi bi-grid-3x3-gap-fill"></i> 
                            </div>
                            <div class="summary-content">
                                <h5>Total Meja</h5>
                                <p class="value"><?php echo $total_meja; ?></p>
                            </div>
                            <div class="card-action">
                                <a href="meja.php" class="btn-link-theme">
                                    Kelola <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card admin-summary-card menu h-100">
                        <div class="card-body">
                            <div class="summary-icon">
                                <i class="bi bi-book-half"></i> 
                            </div>
                            <div class="summary-content">
                                <h5>Total Menu</h5>
                                <p class="value"><?php echo $total_menu; ?></p>
                            </div>
                             <div class="card-action">
                                <a href="menu.php" class="btn-link-theme">
                                     Kelola <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                 <div class="col-lg-4 col-md-6">
                    <div class="card admin-summary-card users h-100">
                        <div class="card-body">
                            <div class="summary-icon">
                                <i class="bi bi-people-fill"></i> 
                            </div>
                            <div class="summary-content">
                                <h5>Total Pengguna</h5>
                                <p class="value"><?php echo $total_users; ?></p>
                            </div>
                             <div class="card-action">
                                <a href="users.php" class="btn-link-theme"> 
                                     Kelola <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                     <div class="card quick-actions h-100">
                         <div class="card-header">
                             <i class="bi bi-lightning-charge-fill"></i>
                             <h5 class="card-title">Aksi Cepat</h5>
                         </div>
                         <div class="card-body d-flex flex-column">
                             <a href="menu.php?action=add" class="btn btn-outline-primary-theme w-100">
                                 <i class="bi bi-plus-circle-fill"></i> Tambah Menu Baru
                             </a>
                             <a href="meja.php?action=add" class="btn btn-outline-teal-theme w-100">
                                 <i class="bi bi-plus-square-fill"></i> Tambah Meja Baru
                             </a>
                              <a href="users.php?action=add" class="btn btn-outline-blue-theme w-100">
                                 <i class="bi bi-person-plus-fill"></i> Tambah Pengguna Baru
                             </a>
                         </div>
                     </div>
                </div>

                <div class="col-lg-8">
                    <div class="card recent-users h-100">
                        <div class="card-header">
                             <i class="bi bi-person-check-fill"></i>
                             <h5 class="card-title">Pengguna Terbaru</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_users)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_users as $r_user): ?>
                                        <li class="list-group-item">
                                            <div class="user-info">
                                                <span class="user-avatar">
                                                    <?php echo strtoupper(substr($r_user['nama'], 0, 1)); // Inisial ?>
                                                </span>
                                                <div class="user-details">
                                                    <p class="user-name"><?php echo htmlspecialchars($r_user['nama']); ?></p>
                                                    <span class="user-level badge bg-secondary"><?php echo htmlspecialchars($r_user['level']); ?></span>
                                                </div>
                                            </div>
                                            <div class="registration-time">
                                                <?php 
                                                // Format waktu pendaftaran (misal: 2 jam lalu, kemarin, 17 Apr 2025)
                                                $reg_time = strtotime($r_user['created_at']);
                                                $now = time();
                                                $diff = $now - $reg_time;

                                                if ($diff < 60) {
                                                    echo $diff . ' detik lalu';
                                                } elseif ($diff < 3600) {
                                                    echo floor($diff / 60) . ' menit lalu';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' jam lalu';
                                                } elseif ($diff < 172800) {
                                                    echo 'Kemarin';
                                                } else {
                                                    echo date('d M Y', $reg_time);
                                                }
                                                ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                             <?php else: ?>
                                <p class="text-center text-secondary mt-3">Belum ada data pengguna baru.</p>
                             <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    {/* Tambahkan script JS custom jika diperlukan */}
</body>
</html>
