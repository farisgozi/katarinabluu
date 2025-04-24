<?php

session_start();

require_once '../../config/config.php'; // Pastikan path ini benar

// HAPUS BARIS INI JIKA functions.php TIDAK ADA ATAU TIDAK DIBUTUHKAN
// require_once '../../functions/functions.php'; 

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'admin') {
    header("Location: ../../pages/auth/login.php"); // Pastikan path ini benar
    exit();
}

// HAPUS BARIS INI JIKA fungsi check_session_timeout() TIDAK ADA ATAU TIDAK DIBUTUHKAN
// check_session_timeout(); 

// Get user data
$user = $_SESSION['user'];

// --- LOGIKA PHP UNTUK PROSES FORM (ADD, EDIT, DELETE) ---
// Pastikan koneksi $conn tersedia dari config.php
if (!$conn) {
     // Handle error jika koneksi gagal (misalnya tampilkan pesan atau log)
     die("Koneksi database gagal.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $nama = $_POST['nama'];
                $harga = $_POST['harga'];

                $stmt = $conn->prepare("INSERT INTO menu (Namamenu, Harga) VALUES (?, ?)");
                if ($stmt === false) {
                    die("Error preparing statement (add): " . $conn->error);
                }
                $stmt->bind_param("si", $nama, $harga);
                if ($stmt->execute()) {
                     $stmt->close();
                     header("Location: menu.php?success=add");
                     exit();
                } else {
                     die("Error executing statement (add): " . $stmt->error);
                }
                break; 

            case 'edit':
                $id = $_POST['id'];
                $nama = $_POST['nama'];
                $harga = $_POST['harga'];

                $stmt = $conn->prepare("UPDATE menu SET Namamenu = ?, Harga = ? WHERE idmenu = ?");
                if ($stmt === false) {
                    die("Error preparing statement (edit): " . $conn->error);
                }
                $stmt->bind_param("sii", $nama, $harga, $id);
                 if ($stmt->execute()) {
                     $stmt->close();
                     header("Location: menu.php?success=edit");
                     exit();
                } else {
                     die("Error executing statement (edit): " . $stmt->error);
                }
                break; 

            case 'delete':
                $id = $_POST['id'];

                $stmt = $conn->prepare("DELETE FROM menu WHERE idmenu = ?");
                if ($stmt === false) {
                    die("Error preparing statement (delete): " . $conn->error);
                }
                $stmt->bind_param("i", $id);
                 if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: menu.php?success=delete");
                    exit();
                 } else {
                     die("Error executing statement (delete): " . $stmt->error);
                 }
                 break; 
        }
    }
}
// --- AKHIR LOGIKA PHP UNTUK PROSES FORM ---

// --- LOGIKA PHP UNTUK FETCH DATA ---
$result = $conn->query("SELECT * FROM menu ORDER BY Namamenu");
// --- AKHIR LOGIKA PHP UNTUK FETCH DATA ---
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Menu - Kasir Resto</title>
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
            --color-text-dark: #1a1a1a; /* Teks gelap agar kontras */
            --color-text-light: #FFFFFF; /* Teks terang untuk background gelap/warna primer */
            --color-border: #dee2e6; /* Warna border default */
            --color-card-bg: #FFFFFF; /* Background card */
        }

        body {
            background-color: var(--color-bg-light);
            color: var(--color-text-dark);
            font-family: 'Poppins', sans-serif; /* Contoh penggunaan font yang lebih modern */
        }

        /* --- Styling Sidebar (Asumsi dari include) --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background-color: #212529; /* Sidebar tetap gelap untuk kontras */
            color: #fff;
            transition: all var(--transition-speed) var(--transition-type);
            z-index: 1030;
            padding-top: 1rem; /* Tambahkan sedikit padding atas */
            /* Anda bisa menambahkan styling lebih lanjut untuk item sidebar di sini */
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        /* --- Akhir Styling Sidebar --- */


        /* --- Styling Content Wrapper --- */
        .content-wrapper {
            margin-left: var(--sidebar-width);
            padding: var(--content-padding-top) var(--content-padding);
            min-height: 100vh;
            transition: margin-left var(--transition-speed) var(--transition-type);
            position: relative;
            z-index: 1;
        }

        .content-wrapper.collapsed-sidebar {
            margin-left: var(--sidebar-collapsed-width);
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: var(--content-padding-top) 1rem;
            }
            .sidebar {
                left: -var(--sidebar-width);
            }
            .sidebar.active {
                left: 0;
            }
        }
        /* --- Akhir Styling Content Wrapper --- */


        /* --- Styling Komponen Utama --- */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap; /* Agar responsif */
            gap: 1rem;
        }

         .page-header h2 {
             margin-bottom: 0;
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

        .btn-warning {
             background-color: var(--color-warning);
             border-color: var(--color-warning);
             color: var(--color-text-dark); /* Sesuaikan jika perlu */
        }
         .btn-warning:hover {
              filter: brightness(95%);
         }


        .btn-danger {
             background-color: var(--color-secondary); /* Menggunakan warna sekunder untuk hapus */
             border-color: var(--color-secondary);
             color: var(--color-text-light);
        }
         .btn-danger:hover {
             filter: brightness(95%);
         }

         .btn-secondary {
             background-color: #6c757d;
             border-color: #6c757d;
             color: var(--color-text-light);
         }


        .card {
            background-color: var(--color-card-bg);
            border: none; /* Hapus border default */
            border-radius: 0.5rem; /* Sedikit lengkungan */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            overflow: hidden; /* Penting jika table-responsive di dalam */
        }

        .card-header { /* Styling opsional untuk header card */
             background-color: var(--color-card-bg);
             border-bottom: 1px solid var(--color-border);
             padding: 1rem 1.5rem;
             font-weight: 600;
        }


        .card-body {
            padding: 1.5rem;
        }

        .alert-success {
             background-color: #d1e7dd; /* Warna hijau muda standar bootstrap */
             border-color: #badbcc;
             color: #0f5132;
             margin-bottom: 1.5rem;
        }

        .table {
             margin-bottom: 0; /* Hapus margin bawah default table */
             border-color: var(--color-border);
        }

        .table thead {
             background-color: var(--color-primary);
             color: var(--color-text-light);
        }

         .table thead th {
             border-bottom-width: 0; /* Hapus border bawah default thead */
             padding: 1rem;
             text-transform: uppercase;
             letter-spacing: 0.5px;
             font-weight: 600;
         }


        .table tbody tr:hover {
            background-color: rgba(6, 214, 160, 0.1); /* Highlight hover dengan warna primer transparan */
        }

        .table td {
            vertical-align: middle;
            padding: 0.9rem 1rem;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(248, 255, 229, 0.5); /* Stripe dengan warna bg light transparan */
        }


        .table-hover tbody tr:hover {
            background-color: rgba(6, 214, 160, 0.15); /* Hover lebih jelas */
            color: var(--color-text-dark);
        }


        /* --- Styling Modal --- */
        .modal-content {
             border: none;
             border-radius: 0.5rem;
             box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }
        .modal-header {
            background-color: var(--color-primary);
            color: var(--color-text-light);
            border-bottom: none;
            padding: 1rem 1.5rem;
        }
        .modal-header .btn-close {
             filter: invert(1) grayscale(100%) brightness(200%); /* Agar tombol close putih */
        }
        .modal-title {
             font-weight: 600;
        }
        .modal-body {
            padding: 1.5rem;
        }
        .modal-footer {
             border-top: 1px solid var(--color-border);
             padding: 1rem 1.5rem;
             background-color: #f8f9fa; /* Footer sedikit berbeda warna */
             gap: 0.5rem;
        }

        .form-label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }

        .form-control, .form-select {
            margin-bottom: 1rem;
            border: 1px solid var(--color-border);
            padding: 0.6rem 0.9rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.25rem rgba(6, 214, 160, 0.25);
        }

        /* --- Styling Komponen Tambahan: Search --- */
        .search-container {
             margin-bottom: 1.5rem;
        }
         .search-container .form-control {
             margin-bottom: 0; /* Hapus margin bawah khusus untuk search input */
         }

    </style>
     <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include '../../components/sidebar.php'; // Pastikan path ini benar dan sidebar.php ada ?>

    <div class="content-wrapper" id="mainContent">
        <div class="container-fluid">

            <div class="page-header">
                <h2>Manajemen Menu</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                    <i class="bi bi-plus-circle"></i> Tambah Menu Baru
                </button>
            </div>

            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php
                switch ($_GET['success']) {
                    case 'add': echo "Menu baru berhasil ditambahkan!"; break;
                    case 'edit': echo "Data menu berhasil diperbarui!"; break;
                    case 'delete': echo "Menu berhasil dihapus!"; break;
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

             <div class="search-container">
                 <input type="text" id="searchInput" class="form-control" placeholder="Cari nama menu...">
             </div>


            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Menu</th>
                                    <th>Harga</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="menuTableBody">
                                <?php
                                if ($result && $result->num_rows > 0):
                                    $counter = 1;
                                    while ($row = $result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($row['Namamenu']); ?></td>
                                    <td>Rp <?php echo number_format($row['Harga'], 0, ',', '.'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editMenuModal"
                                                data-id="<?php echo htmlspecialchars($row['idmenu']); ?>"
                                                data-nama="<?php echo htmlspecialchars($row['Namamenu']); ?>"
                                                data-harga="<?php echo $row['Harga']; ?>">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteMenuModal"
                                                data-id="<?php echo htmlspecialchars($row['idmenu']); ?>"
                                                data-nama="<?php echo htmlspecialchars($row['Namamenu']); ?>">
                                            <i class="bi bi-trash3"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                <?php
                                    endwhile;
                                else: // Jika tidak ada data menu
                                ?>
                                 <tr>
                                     <td colspan="4" class="text-center">Belum ada data menu.</td>
                                 </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> </div> </div> <div class="modal fade" id="addMenuModal" tabindex="-1" aria-labelledby="addMenuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMenuModalLabel">Tambah Menu Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="menu.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="add_menu_nama" class="form-label">Nama Menu</label>
                            <input type="text" class="form-control" id="add_menu_nama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="add_menu_harga" class="form-label">Harga (Rp)</label>
                            <input type="number" class="form-control" id="add_menu_harga" name="harga" required min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Menu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editMenuModal" tabindex="-1" aria-labelledby="editMenuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMenuModalLabel">Edit Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="menu.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_menu_id">
                        <div class="mb-3">
                            <label for="edit_menu_nama" class="form-label">Nama Menu</label>
                            <input type="text" class="form-control" name="nama" id="edit_menu_nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_menu_harga" class="form-label">Harga (Rp)</label>
                            <input type="number" class="form-control" name="harga" id="edit_menu_harga" required min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteMenuModal" tabindex="-1" aria-labelledby="deleteMenuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteMenuModalLabel">Konfirmasi Hapus Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                 <form method="POST" action="menu.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_menu_id">
                        <p>Apakah Anda yakin ingin menghapus menu "<strong id="delete_menu_nama"></strong>"? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Handle Edit Modal ---
            const editModal = document.getElementById('editMenuModal');
            if (editModal) { // Cek jika elemen ada
                 editModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const nama = button.getAttribute('data-nama');
                    const harga = button.getAttribute('data-harga');

                    editModal.querySelector('#edit_menu_id').value = id;
                    editModal.querySelector('#edit_menu_nama').value = nama;
                    editModal.querySelector('#edit_menu_harga').value = harga;
                 });
             }


            // --- Handle Delete Modal ---
            const deleteModal = document.getElementById('deleteMenuModal');
             if (deleteModal) { // Cek jika elemen ada
                 deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const nama = button.getAttribute('data-nama');

                    deleteModal.querySelector('#delete_menu_id').value = id;
                    // Gunakan innerHTML atau textContent untuk elemen strong/span
                    deleteModal.querySelector('#delete_menu_nama').textContent = nama;
                 });
             }


            // --- Handle Search/Filter ---
            const searchInput = document.getElementById('searchInput');
            const tableBody = document.getElementById('menuTableBody');
            const tableRows = tableBody ? tableBody.getElementsByTagName('tr') : []; // Ambil semua baris

             if (searchInput && tableBody) { // Cek jika elemen ada
                 searchInput.addEventListener('keyup', function() {
                    const searchTerm = searchInput.value.toLowerCase();

                    for (let i = 0; i < tableRows.length; i++) {
                         // Cek apakah baris ini adalah baris 'data' atau baris 'tidak ada data'
                         const cells = tableRows[i].getElementsByTagName('td');
                         if (cells.length > 1) { // Asumsi baris data punya > 1 sel
                             const menuNameCell = cells[1]; // Sel kedua adalah Nama Menu
                             if (menuNameCell) {
                                 const menuName = menuNameCell.textContent || menuNameCell.innerText;
                                 if (menuName.toLowerCase().indexOf(searchTerm) > -1) {
                                     tableRows[i].style.display = ""; // Tampilkan baris jika cocok
                                 } else {
                                     tableRows[i].style.display = "none"; // Sembunyikan jika tidak cocok
                                 }
                             }
                         }
                    }
                 });
             }

             // Optional: Handle Sidebar Toggle Class on content-wrapper
             // Jika sidebar Anda memiliki tombol toggle, tambahkan logika JS di sini
             // untuk menambahkan/menghapus class 'collapsed-sidebar' pada #mainContent
             // Contoh:
             // const toggleButton = document.getElementById('sidebarToggle');
             // const mainContent = document.getElementById('mainContent');
             // const sidebar = document.querySelector('.sidebar');
             // if (toggleButton && mainContent && sidebar) {
             //     toggleButton.addEventListener('click', function() {
             //         sidebar.classList.toggle('collapsed');
             //         mainContent.classList.toggle('collapsed-sidebar');
             //     });
             // }

        });
    </script>
</body>
</html>
