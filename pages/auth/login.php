<?php
session_start();
// Diasumsikan file config.php berisi koneksi database ($conn) dan fungsi clean_input()
require_once '../../config/config.php'; 

// HAPUS JIKA TIDAK ADA/DIBUTUHKAN
// require_once '../../functions/functions.php'; // Jika check_session_timeout ada di sini

// Periksa apakah pengguna sudah login, jika ya, arahkan ke halaman utama
if (isset($_SESSION['user'])) {
    header("Location: ../../index.php");
    exit();
}

// HAPUS JIKA TIDAK ADA/DIBUTUHKAN
// check_session_timeout(); // Panggil fungsi jika ada

$error = ''; // Variabel untuk menyimpan pesan error

// Proses form jika metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pastikan koneksi $conn tersedia
    if (!$conn) {
        die("Koneksi database gagal.");
    }
    
    // Fungsi clean_input (contoh implementasi jika tidak ada di config.php)
    if (!function_exists('clean_input')) {
        function clean_input($data) {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data); // Dasar sanitasi
            return $data;
        }
    }

    // Bersihkan input username (menggunakan fungsi clean_input)
    $username = clean_input($_POST['username']);
    $password = $_POST['password']; // Ambil password

    // Siapkan query SQL untuk mencari user berdasarkan username
    $sql = "SELECT * FROM users WHERE nama = ?"; // Pastikan nama tabel dan kolom benar
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        // Penanganan error jika prepare statement gagal
        // Log error daripada die() di production
        error_log("Error preparing statement: " . $conn->error);
        $error = "Terjadi kesalahan pada sistem. Silakan coba lagi nanti.";
        // die("Error preparing statement: " . $conn->error); 
    } else {
        $stmt->bind_param("s", $username); // Bind parameter username
        $stmt->execute();
        $result = $stmt->get_result(); // Ambil hasil query

        // Periksa apakah user ditemukan (satu baris hasil)
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc(); // Ambil data user
            
            // Verifikasi password menggunakan password_verify
            // Pastikan kolom password di DB namanya 'password'
            if (isset($user_data['password']) && password_verify($password, $user_data['password'])) {
                // Jika password cocok, simpan data user ke session
                // Pastikan nama kolom id, nama, level sesuai dengan DB Anda
                $_SESSION['user'] = [
                    'id' => $user_data['iduser'] ?? $user_data['id'], // Sesuaikan nama kolom ID
                    'nama' => $user_data['nama'],
                    'level' => $user_data['level']
                ];
                $_SESSION['last_activity'] = time(); // Catat waktu aktivitas terakhir
                
                // Arahkan ke halaman utama setelah login berhasil
                header("Location: ../../index.php");
                exit();
            } else {
                // Jika password salah
                $error = 'Password yang Anda masukkan salah!';
            }
        } else {
            // Jika username tidak ditemukan
            $error = 'Username tidak ditemukan!';
        }
        $stmt->close(); // Tutup statement
    }
    $conn->close(); // Tutup koneksi database
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir Resto</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Tema Warna Light Yellow Emerald */
            --color-bg-light: #F8FFE5; /* Light Yellow */
            --color-primary: #06D6A0; /* Emerald */
            --color-primary-hover: #05b98a; /* Sedikit lebih gelap untuk hover */
            --color-secondary: #6c757d; /* Warna abu-abu standar */
            --color-danger: #dc3545; /* Warna danger */
            --color-text-dark: #1a1a1a; /* Teks gelap agar kontras */
            --color-text-light: #FFFFFF; /* Teks terang */
            --color-border: #dee2e6; /* Warna border default */
            --color-card-bg: #FFFFFF; /* Background card */
            --color-shadow: rgba(0, 0, 0, 0.1); /* Warna shadow lebih halus */
            --font-default: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--color-bg-light);
            font-family: var(--font-default);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh; 
            padding: 1rem; /* Padding agar card tidak menempel di tepi layar kecil */
        }

        .login-card {
            border: none; 
            border-radius: 1rem; 
            padding: 1.5rem; 
            background-color: var(--color-card-bg);
            box-shadow: 0 8px 25px var(--color-shadow);
            max-width: 450px; /* Batasi lebar maksimum card */
            width: 100%; /* Agar responsif */
        }

        .login-card .card-body {
            padding: 2rem; 
        }

        .login-logo {
             text-align: center; /* Pusatkan logo */
             margin-bottom: 1.5rem;
        }
        .login-logo img {
            max-width: 80px; /* Ukuran logo disesuaikan */
            height: auto;
        }

        .login-title {
             color: var(--color-text-dark);
        }
        .login-subtitle {
             font-size: 0.95rem;
        }

        .form-label { /* Style untuk label jika ditambahkan */
             font-weight: 500;
             margin-bottom: 0.5rem;
        }

        .input-group {
             border: 1px solid var(--color-border);
             border-radius: 0.5rem; /* Radius pada group */
             transition: border-color 0.2s ease, box-shadow 0.2s ease;
             overflow: hidden; /* Agar radius input konsisten */
        }
        .input-group:focus-within { /* Style saat group/input di dalamnya fokus */
             border-color: var(--color-primary);
             box-shadow: 0 0 0 0.2rem rgba(6, 214, 160, 0.25);
        }

        .input-group .input-group-text {
            background-color: #f8f9fa;
            border: none; /* Hapus border default */
            padding: 0.75rem 1rem;
            color: var(--color-secondary);
        }
        .input-group .form-control {
            border: none; /* Hapus border default input */
            box-shadow: none; /* Hapus shadow default saat fokus */
            padding: 0.75rem 1rem; 
        }
        .input-group .form-control:focus {
             box-shadow: none; /* Pastikan tidak ada shadow saat fokus */
        }

        .btn-primary {
            padding: 0.75rem 1.5rem; /* Padding tombol */
            border-radius: 0.5rem; 
            font-weight: 600; 
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: var(--color-text-light);
            transition: background-color 0.2s ease, border-color 0.2s ease;
        }
        .btn-primary:hover {
            background-color: var(--color-primary-hover);
            border-color: var(--color-primary-hover);
        }

        .alert {
            border-radius: 0.5rem; 
            font-size: 0.9rem;
        }
        .alert-danger {
             background-color: #f8d7da;
             border-color: #f5c2c7;
             color: #842029;
        }

        .forgot-password-link a {
             color: var(--color-primary);
             font-size: 0.9rem;
             text-decoration: none;
        }
        .forgot-password-link a:hover {
             text-decoration: underline;
             color: var(--color-primary-hover);
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6 col-xl-5">
                <div class="card shadow-lg login-card">
                    <div class="card-body">

                        <h3 class="text-center mb-2 fw-bold login-title">Selamat Datang!</h3>
                        <p class="text-center text-muted mb-4 login-subtitle">Silakan login ke akun Kasir Resto Anda.</p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label visually-hidden">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required aria-label="Username">
                                </div>
                            </div>

                            <div class="mb-4">
                                 <label for="password" class="form-label visually-hidden">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required aria-label="Password">
                                </div>
                            </div>

                            <div class="d-grid mb-3"> 
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>
                    </div> 
                </div> 
            </div> 
        </div> 
    </div> 
    <script src="../../assets/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
