<?php

// Fungsi untuk mendapatkan item menu berdasarkan level pengguna
function get_menu_items($user_level) {
    $menu_items = [];
    
    // Tentukan item menu berdasarkan level user
    switch ($user_level) {
        case 'admin':
            $menu_items = [
                ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'link' => '/pages/admin/dashboard.php'],
                ['icon' => 'bi bi-grid-3x3-gap-fill', 'text' => 'Manajemen Meja', 'link' => '/pages/admin/meja.php'],
                ['icon' => 'bi-menu-button-wide', 'text' => 'Manajemen Menu', 'link' => '/pages/admin/menu.php'],
                // Tambahkan menu admin lainnya di sini
            ];
            break;
        case 'kasir':
            $menu_items = [
                ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'link' => '/pages/kasir/dashboard.php'],
                ['icon' => 'bi-cash-stack', 'text' => 'Transaksi', 'link' => '/pages/kasir/transactions.php'],
                // Tambahkan menu kasir lainnya di sini
            ];
            break;
        case 'owner':
            $menu_items = [
                ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'link' => '/pages/owner/dashboard.php'],
                ['icon' => 'bi-graph-up', 'text' => 'Laporan', 'link' => '/pages/owner/laporan.php'],
                // Tambahkan menu owner lainnya di sini
            ];
            break;
        case 'waiter':
            $menu_items = [
                ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'link' => '/pages/waiter/dashboard.php'],
                ['icon' => 'bi-cart', 'text' => 'Pesanan', 'link' => '/pages/waiter/orders.php'],
                // Tambahkan menu waiter lainnya di sini
            ];
            break;
        default:
             // Menu default jika level tidak dikenali
             $menu_items = [
                 ['icon' => 'bi-speedometer2', 'text' => 'Dashboard', 'link' => '/default_dashboard.php']
             ];
            break;
    }
    
    // Selalu tambahkan item logout di akhir
    $menu_items[] = ['icon' => 'bi-box-arrow-right', 'text' => 'Logout', 'link' => '/pages/auth/logout.php'];
    
    return $menu_items;
}

// --- Bagian Utama Sidebar ---
// Asumsikan $_SESSION['user']['level'] sudah di-set setelah login
$user_level = isset($_SESSION['user']['level']) ? $_SESSION['user']['level'] : 'guest'; // Default ke 'guest' jika tidak ada session
$menu_items = get_menu_items($user_level);
$current_page = $_SERVER['PHP_SELF']; // Dapatkan path halaman saat ini
?>

<link href="../../css/sidebar.css" rel="stylesheet">

<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span>Kasir Resto</span> </div>

    <ul class="sidebar-menu">
        <?php foreach ($menu_items as $item): ?>
        <li class="sidebar-item">
            <a href="<?php echo htmlspecialchars($item['link']); ?>" 
               class="sidebar-link <?php echo ($current_page == $item['link']) ? 'active' : ''; ?>"
               data-tooltip="<?php echo htmlspecialchars($item['text']); // Tooltip untuk mode collapsed ?>">
                <i class="bi <?php echo htmlspecialchars($item['icon']); ?> sidebar-icon"></i>
                <span class="sidebar-text"><?php echo htmlspecialchars($item['text']); ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <span> <?php echo htmlspecialchars(ucfirst($user_level)); ?> Panel
        </span>
    </div>
</div>

<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="bi bi-list"></i> </button>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const contentWrapper = document.querySelector('.content-wrapper'); // Targetkan content wrapper jika ada
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = toggleBtn.querySelector('i');

    // Fungsi untuk mengatur state sidebar berdasarkan localStorage dan lebar layar
    function initializeSidebar() {
        const sidebarState = localStorage.getItem('sidebarState'); // 'active', 'inactive' (desktop hidden)
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed'); // 'true', 'false' (desktop collapsed)

        if (window.innerWidth <= 768) {
            // --- Mobile View Initialization ---
            sidebar.classList.remove('inactive', 'collapsed'); // Hapus state desktop
            if (contentWrapper) contentWrapper.classList.remove('shifted', 'collapsed-sidebar');

            if (sidebarState === 'active') {
                sidebar.classList.add('active');
                toggleIcon.className = 'bi bi-x-lg'; // Ganti ikon jadi close
                toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim() || '280px'} + 1rem)`;
            } else {
                sidebar.classList.remove('active');
                toggleIcon.className = 'bi bi-list'; // Ikon default
                toggleBtn.style.left = '1rem';
            }
        } else {
            // --- Desktop View Initialization ---
            sidebar.classList.remove('active'); // Hapus state mobile

            // 1. Handle Inactive State (Hidden)
            if (sidebarState === 'inactive') {
                sidebar.classList.add('inactive');
                if (contentWrapper) contentWrapper.classList.add('shifted');
                toggleIcon.className = 'bi bi-arrow-right-short'; // Ikon panah kanan
                toggleBtn.style.left = '1rem';
            } else {
                sidebar.classList.remove('inactive');
                if (contentWrapper) contentWrapper.classList.remove('shifted');
                toggleIcon.className = 'bi bi-arrow-left-short'; // Ikon panah kiri default
                toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim() || '280px'} + 1rem)`;

                // 2. Handle Collapsed State (Only if not inactive)
                if (sidebarCollapsed === 'true') {
                    sidebar.classList.add('collapsed');
                    if (contentWrapper) contentWrapper.classList.add('collapsed-sidebar');
                    toggleIcon.className = 'bi bi-arrows-angle-expand'; // Ikon expand
                    toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-collapsed-width').trim() || '80px'} + 1rem)`;
                } else {
                    sidebar.classList.remove('collapsed');
                    if (contentWrapper) contentWrapper.classList.remove('collapsed-sidebar');
                    // Ikon sudah diatur ke panah kiri di atas
                }
            }
        }
    }

    // Fungsi utama untuk toggle sidebar
    function toggleSidebar() {
        if (window.innerWidth <= 768) {
            // --- Mobile Toggle ---
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                localStorage.setItem('sidebarState', 'active');
                toggleIcon.className = 'bi bi-x-lg';
                toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim() || '280px'} + 1rem)`;
            } else {
                localStorage.setItem('sidebarState', 'inactive'); // Di mobile, inactive = tidak aktif
                toggleIcon.className = 'bi bi-list';
                toggleBtn.style.left = '1rem';
            }
        } else {
            // --- Desktop Toggle (Prioritaskan Collapse/Expand jika aktif) ---
            if (!sidebar.classList.contains('inactive')) {
                // Jika sidebar terlihat, toggle collapse
                sidebar.classList.toggle('collapsed');
                if (contentWrapper) contentWrapper.classList.toggle('collapsed-sidebar');

                if (sidebar.classList.contains('collapsed')) {
                    localStorage.setItem('sidebarCollapsed', 'true');
                    toggleIcon.className = 'bi bi-arrows-angle-expand';
                    toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-collapsed-width').trim() || '80px'} + 1rem)`;
                } else {
                    localStorage.setItem('sidebarCollapsed', 'false');
                    toggleIcon.className = 'bi bi-arrow-left-short';
                    toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim() || '280px'} + 1rem)`;
                }
                // Pastikan state inactive dihapus jika kita expand/collapse
                localStorage.setItem('sidebarState', 'active'); 
                sidebar.classList.remove('inactive');
                 if (contentWrapper) contentWrapper.classList.remove('shifted');

            } else {
                // Jika sidebar tersembunyi (inactive), tampilkan (expand)
                sidebar.classList.remove('inactive');
                if (contentWrapper) contentWrapper.classList.remove('shifted');
                localStorage.setItem('sidebarState', 'active');
                localStorage.setItem('sidebarCollapsed', 'false'); // Pastikan tidak collapsed saat muncul
                sidebar.classList.remove('collapsed');
                if (contentWrapper) contentWrapper.classList.remove('collapsed-sidebar');
                toggleIcon.className = 'bi bi-arrow-left-short';
                toggleBtn.style.left = `calc(${getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width').trim() || '280px'} + 1rem)`;
            }
        }
    }
    
    // Fungsi untuk menyembunyikan sidebar di desktop
    function hideSidebarDesktop() {
         if (window.innerWidth > 768 && !sidebar.classList.contains('inactive')) {
             sidebar.classList.add('inactive');
             if (contentWrapper) contentWrapper.classList.add('shifted');
             localStorage.setItem('sidebarState', 'inactive');
             localStorage.setItem('sidebarCollapsed', 'false'); // Reset collapsed state
             sidebar.classList.remove('collapsed');
             if (contentWrapper) contentWrapper.classList.remove('collapsed-sidebar');
             toggleIcon.className = 'bi bi-arrow-right-short';
             toggleBtn.style.left = '1rem';
         }
    }

    // Event Listener untuk Tombol Toggle
    toggleBtn.addEventListener('click', function(event) {
        event.stopPropagation(); // Hentikan propagasi agar klik luar tidak terpicu
        // Di desktop, klik kiri toggle collapse/expand, klik kanan (contextmenu) hide/show
        if (window.innerWidth > 768 && event.button === 2) { // Klik kanan
             event.preventDefault(); // Cegah menu konteks default
             if(sidebar.classList.contains('inactive')) {
                 toggleSidebar(); // Tampilkan jika tersembunyi
             } else {
                 hideSidebarDesktop(); // Sembunyikan jika terlihat
             }
        } else if (event.button === 0) { // Klik kiri
            toggleSidebar();
        }
    });
    
    // Event Listener untuk klik kanan pada toggle (alternatif untuk hide/show di desktop)
    toggleBtn.addEventListener('contextmenu', function(event) {
        if (window.innerWidth > 768) {
            event.preventDefault();
            if(sidebar.classList.contains('inactive')) {
                 toggleSidebar(); // Tampilkan jika tersembunyi
             } else {
                 hideSidebarDesktop(); // Sembunyikan jika terlihat
             }
        }
    });


    // Event Listener untuk menutup sidebar di mobile saat klik di luar area
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 &&
            sidebar.classList.contains('active') &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target)) {
            
            sidebar.classList.remove('active');
            localStorage.setItem('sidebarState', 'inactive');
            toggleIcon.className = 'bi bi-list';
            toggleBtn.style.left = '1rem';
        }
    });

    // Event Listener untuk resize window
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            initializeSidebar(); // Re-inisialisasi state saat ukuran jendela berubah
        }, 250); // Debounce untuk performa
    });

    // Inisialisasi sidebar saat halaman dimuat
    initializeSidebar();
});
</script>
