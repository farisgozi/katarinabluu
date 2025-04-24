<?php
session_start();
// Diasumsikan config.php berisi koneksi $conn dan fungsi check_session_timeout()
require_once '../../config/config.php'; 

// Periksa apakah pengguna sudah login dan levelnya 'owner'
if (!isset($_SESSION['user']) || $_SESSION['user']['level'] !== 'owner') {
    // Jika tidak, arahkan ke halaman login
    header("Location: ../../pages/auth/login.php");
    exit();
}

// Periksa timeout sesi
// check_session_timeout(); // Pastikan fungsi ini ada di config.php atau di-include

// Ambil data pengguna dari session
$user = $_SESSION['user'];

// --- Pengaturan Filter Tanggal ---
// Ambil nilai filter dari GET request atau gunakan default
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'daily';
$start_date_input = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date_input = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Tentukan rentang tanggal berdasarkan tipe filter
$start_date = $start_date_input;
$end_date = $end_date_input;

switch ($filter_type) {
    case 'daily':
        // Jika harian, gunakan tanggal yang dipilih (atau hari ini defaultnya)
        $start_date = $start_date_input;
        $end_date = $start_date_input; // Harian hanya perlu satu tanggal
         // Set input end_date sama dengan start_date untuk konsistensi tampilan form
        $end_date_input = $start_date_input; 
        break;
    case 'weekly':
        // Minggu ini (Senin sampai Minggu dari tanggal yang dipilih)
        $start_date = date('Y-m-d', strtotime('monday this week', strtotime($start_date_input)));
        $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($start_date_input)));
        break;
    case 'monthly':
        // Bulan ini (Tanggal 1 sampai akhir bulan dari tanggal yang dipilih)
        $start_date = date('Y-m-01', strtotime($start_date_input));
        $end_date = date('Y-m-t', strtotime($start_date_input));
        break;
    case 'yearly':
        // Tahun ini (1 Januari sampai 31 Desember dari tanggal yang dipilih)
        $start_date = date('Y-01-01', strtotime($start_date_input));
        $end_date = date('Y-12-31', strtotime($start_date_input));
        break;
    case 'custom':
        // Gunakan tanggal dari input form
        $start_date = $start_date_input;
        $end_date = $end_date_input;
        break;
}

// Format tanggal untuk tampilan yang lebih ramah pengguna
$formatted_start_date = date('d M Y', strtotime($start_date));
$formatted_end_date = date('d M Y', strtotime($end_date));
$display_period = ($start_date == $end_date) ? $formatted_start_date : $formatted_start_date . " - " . $formatted_end_date;

// --- Pengambilan Data ---

// 1. Data Penjualan Harian (untuk tabel dan kalkulasi total)
$sales_query = "SELECT 
                    DATE(t.created_at) as transaction_date,
                    SUM(t.total) as total_sales,
                    COUNT(DISTINCT t.idtransaksi) as total_transactions
                FROM transaksi t
                WHERE DATE(t.created_at) BETWEEN ? AND ?
                GROUP BY DATE(t.created_at)
                ORDER BY transaction_date ASC"; // Urutkan menaik untuk grafik

$stmt = $conn->prepare($sales_query);
if ($stmt === false) { die("Error preparing query: " . $conn->error); }
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_result = $stmt->get_result();

// Kalkulasi total dan persiapan data untuk tabel & grafik
$total_sales = 0;
$total_transactions_period = 0; // Total transaksi dalam periode
$sales_data_table = []; // Untuk tabel detail
$sales_dates_chart = []; // Label tanggal untuk grafik
$sales_amounts_chart = []; // Data penjualan untuk grafik

while ($row = $sales_result->fetch_assoc()) {
    $total_sales += $row['total_sales'];
    $total_transactions_period += $row['total_transactions'];
    $sales_data_table[] = $row; // Simpan data mentah untuk tabel
    // Format tanggal sesuai locale Indonesia untuk grafik (misal: 16 Apr)
    $sales_dates_chart[] = date('d M', strtotime($row['transaction_date'])); 
    $sales_amounts_chart[] = $row['total_sales'];
}
$sales_result->free(); // Bebaskan memori

// 2. Total Item Terjual
$items_query = "SELECT 
                    SUM(p.jumlah) as total_items
                FROM pesanan p
                JOIN transaksi t ON p.idpesanan = t.idpesanan -- Asumsi relasi ini benar
                WHERE DATE(t.created_at) BETWEEN ? AND ?";

$stmt = $conn->prepare($items_query);
if ($stmt === false) { die("Error preparing query: " . $conn->error); }
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$items_result = $stmt->get_result();
$items_row = $items_result->fetch_assoc();
$total_items = $items_row['total_items'] ?? 0;
$items_result->free();

// 3. Item Terlaris (Top 5)
$popular_items_query = "SELECT 
                            m.Namamenu as menu_name,
                            SUM(p.jumlah) as total_ordered
                        FROM pesanan p
                        JOIN menu m ON p.idmenu = m.idmenu
                        JOIN transaksi t ON p.idpesanan = t.idpesanan -- Asumsi relasi ini benar
                        WHERE DATE(t.created_at) BETWEEN ? AND ?
                        GROUP BY p.idmenu, m.Namamenu -- Group by nama juga
                        ORDER BY total_ordered DESC
                        LIMIT 5"; // Batasi 5 teratas

$stmt = $conn->prepare($popular_items_query);
if ($stmt === false) { die("Error preparing query: " . $conn->error); }
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$popular_items_result = $stmt->get_result();

// Persiapan data untuk tabel & grafik item terlaris
$popular_items_table = [];
$popular_item_names_chart = [];
$popular_item_counts_chart = [];

while ($row = $popular_items_result->fetch_assoc()) {
    $popular_items_table[] = $row; // Untuk tabel
    $popular_item_names_chart[] = $row['menu_name']; // Untuk label grafik
    $popular_item_counts_chart[] = $row['total_ordered']; // Untuk data grafik
}
// Tidak perlu free result di sini karena akan digunakan lagi di tabel

$stmt->close(); // Tutup statement setelah semua query selesai
$conn->close(); // Tutup koneksi database
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Owner Page</title>
    <link href="../../assets/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../css/owner.css" rel="stylesheet">
</head>
<body>
<?php 
        // Include sidebar - pastikan path ini benar
        // Asumsikan sidebar.php sudah berisi tag <style> dengan variabel :root
        include '../../components/sidebar.php'; 
    ?>
    <div class="content-wrapper">
        <style>
            .content-wrapper {
                margin-left: var(--sidebar-width);
                padding: var(--content-padding);
                padding-top: var(--content-padding-top);   
            }
            </style>
<div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title"><i class="bi bi-filter me-2"></i>Filter Laporan</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3 col-sm-6">
                            <label for="filter_type" class="form-label">Periode</label>
                            <select class="form-select" id="filter_type" name="filter_type" onchange="toggleDateInputs()">
                                <option value="daily" <?php echo $filter_type == 'daily' ? 'selected' : ''; ?>>Harian</option>
                                <option value="weekly" <?php echo $filter_type == 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                                <option value="monthly" <?php echo $filter_type == 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                                <option value="yearly" <?php echo $filter_type == 'yearly' ? 'selected' : ''; ?>>Tahunan</option>
                                <option value="custom" <?php echo $filter_type == 'custom' ? 'selected' : ''; ?>>Kustom</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6 date-input" id="start_date_container">
                            <label for="start_date" class="form-label" id="start_date_label">Tanggal</label> 
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date_input); ?>">
                        </div>
                        <div class="col-md-3 col-sm-6 date-input" id="end_date_container" style="display: none;">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date_input); ?>">
                        </div>
                        <div class="col-md-3 col-sm-12">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-check-lg me-2"></i>Terapkan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div>
                    <button class="btn btn-outline-primary" onclick="printReport()">
                        <i class="bi bi-printer me-2"></i>Cetak Laporan
                    </button>
                </div>
                <script>
                    function toggleDateInputs() {
            const filterType = document.getElementById('filter_type').value;
            const startDateContainer = document.getElementById('start_date_container');
            const endDateContainer = document.getElementById('end_date_container');
            const startDateLabel = document.getElementById('start_date_label');

            // Selalu tampilkan start date container
            startDateContainer.style.display = 'block'; 

            if (filterType === 'custom') {
                endDateContainer.style.display = 'block';
                startDateLabel.textContent = 'Tanggal Mulai'; // Label untuk custom
            } else {
                endDateContainer.style.display = 'none';
                 // Ubah label start date sesuai konteks
                if (filterType === 'daily') {
                     startDateLabel.textContent = 'Tanggal';
                } else if (filterType === 'weekly') {
                     startDateLabel.textContent = 'Pilih Tanggal (Minggu)'; // Pilih tanggal mana saja dalam minggu tsb
                } else if (filterType === 'monthly') {
                     startDateLabel.textContent = 'Pilih Tanggal (Bulan)'; // Pilih tanggal mana saja dalam bulan tsb
                } else if (filterType === 'yearly') {
                     startDateLabel.textContent = 'Pilih Tanggal (Tahun)'; // Pilih tanggal mana saja dalam tahun tsb
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleDateInputs(); // Panggil saat load untuk state awal form

            // --- Sales Chart (Line) ---
            const salesCtx = document.getElementById('salesChart')?.getContext('2d');
            if (salesCtx) {
                const salesChart = new Chart(salesCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($sales_dates_chart); ?>,
                        datasets: [{
                            label: 'Penjualan',
                            data: <?php echo json_encode($sales_amounts_chart); ?>,
                            backgroundColor: 'rgba(6, 214, 160, 0.1)', // Warna area dari --primary-color
                            borderColor: 'var(--primary-color, #06D6A0)', // Warna garis dari --primary-color
                            borderWidth: 2.5, // Garis sedikit lebih tebal
                            tension: 0.4, // Buat garis lebih melengkung
                            pointBackgroundColor: 'var(--primary-color, #06D6A0)', // Warna titik
                            pointBorderColor: '#fff',
                            pointHoverRadius: 7,
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'var(--primary-color, #06D6A0)',
                            fill: true // Isi area di bawah garis
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Izinkan chart menyesuaikan tinggi
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { 
                                    color: 'rgba(0, 0, 0, 0.05)' // Warna grid lebih halus
                                },
                                ticks: {
                                    color: 'var(--text-light, #5a6a85)', // Warna teks sumbu Y
                                    callback: function(value) { // Format Rupiah
                                        return 'Rp ' + value.toLocaleString('id-ID');
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false // Sembunyikan grid sumbu X
                                },
                                ticks: {
                                     color: 'var(--text-light, #5a6a85)' // Warna teks sumbu X
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false // Sembunyikan legenda jika hanya 1 dataset
                            },
                            tooltip: {
                                backgroundColor: 'var(--background-color, #1A202C)', // Tooltip gelap
                                titleColor: 'var(--text-muted-color, rgba(248, 255, 229, 0.7))',
                                bodyColor: 'var(--text-color, #F8FFE5)',
                                boxPadding: 8,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        if (context.parsed.y !== null) {
                                            label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // --- Popular Items Chart (Bar) ---
            const popularItemsCtx = document.getElementById('popularItemsChart')?.getContext('2d');
            if (popularItemsCtx) {
                // Definisikan palet warna yang konsisten dengan tema
                 const themeColors = [
                    'rgba(6, 214, 160, 0.8)',   // Emerald (Primary)
                    'rgba(22, 160, 133, 0.8)',  // Teal
                    'rgba(243, 156, 18, 0.8)',  // Yellow
                    'rgba(46, 204, 113, 0.8)', // Emerald Light
                    'rgba(52, 152, 219, 0.8)', // Blue Light
                 ];

                const popularItemsChart = new Chart(popularItemsCtx, {
                    type: 'bar', 
                    data: {
                        labels: <?php echo json_encode($popular_item_names_chart); ?>,
                        datasets: [{
                            label: 'Jumlah Terjual',
                            data: <?php echo json_encode($popular_item_counts_chart); ?>,
                            backgroundColor: themeColors, // Gunakan palet tema
                            borderColor: themeColors.map(color => color.replace('0.8', '1')), // Border solid
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y', // Buat jadi horizontal bar chart
                        responsive: true,
                        maintainAspectRatio: false, // Izinkan chart menyesuaikan tinggi
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { 
                                    color: 'rgba(0, 0, 0, 0.05)' 
                                },
                                ticks: {
                                    color: 'var(--text-light, #5a6a85)'
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                     color: 'var(--text-light, #5a6a85)'
                                }
                            }
                        },
                         plugins: {
                            legend: {
                                display: false // Sembunyikan legenda
                            },
                             tooltip: {
                                backgroundColor: 'var(--background-color, #1A202C)',
                                titleColor: 'var(--text-muted-color, rgba(248, 255, 229, 0.7))',
                                bodyColor: 'var(--text-color, #F8FFE5)',
                                boxPadding: 8,
                                callbacks: {
                                    label: function(context) {
                                        return ' Terjual: ' + context.parsed.x.toLocaleString('id-ID');
                                    }
                                }
                            }
                        }
                    }
                });
            }
        });

                function printReport() {
            // Ambil nilai filter saat ini dari form
            const filterType = document.getElementById('filter_type').value;
            const startDate = document.getElementById('start_date').value;
            // Sesuaikan end date berdasarkan filter type untuk URL cetak
            let endDate;
            if (filterType === 'custom') {
                endDate = document.getElementById('end_date').value;
            } else if (filterType === 'daily') {
                endDate = startDate;
            } else if (filterType === 'weekly') {
                 // Hitung end date berdasarkan start date untuk konsistensi
                 const start = new Date(startDate);
                 const end = new Date(start);
                 // Cari hari Minggu di minggu yang sama atau minggu depannya jika start date adalah Minggu
                 const dayOfWeek = start.getDay(); // 0 = Minggu, 1 = Senin, ...
                 const diff = start.getDate() - dayOfWeek + (dayOfWeek === 0 ? 0 : 7); // Jika start Minggu, gunakan Minggu itu, jika tidak, cari Minggu berikutnya
                 end.setDate(diff);
                 endDate = end.toISOString().split('T')[0]; // Format YYYY-MM-DD
            } else if (filterType === 'monthly') {
                 const start = new Date(startDate);
                 const end = new Date(start.getFullYear(), start.getMonth() + 1, 0); // Tanggal terakhir bulan
                 endDate = end.toISOString().split('T')[0];
            } else if (filterType === 'yearly') {
                 const start = new Date(startDate);
                 endDate = `${start.getFullYear()}-12-31`;
            } else {
                 endDate = startDate; // Fallback
            }
            
            // Bangun URL dengan parameter filter
            const printUrl = `print_report.php?filter_type=${filterType}&start_date=${startDate}&end_date=${endDate}`;
            
            // Buka di tab baru atau arahkan window saat ini
            // window.open(printUrl, '_blank'); 
             window.location.href = printUrl;
        }
    </script>
</body>
</html>