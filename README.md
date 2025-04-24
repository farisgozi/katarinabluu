# Dokumentasi Aplikasi Kasir Restoran

## Daftar Isi
1. [Gambaran Umum](#gambaran-umum)
2. [Struktur Database](#struktur-database)
3. [Arsitektur Aplikasi](#arsitektur-aplikasi)
4. [Alur Bisnis](#alur-bisnis)
5. [Implementasi](#implementasi)
6. [Panduan Pengembangan](#panduan-pengembangan)

## Gambaran Umum
Aplikasi Kasir Restoran adalah sistem manajemen restoran berbasis web yang menangani proses pemesanan, pembayaran, dan pelaporan. Aplikasi ini dirancang dengan mempertimbangkan berbagai peran pengguna dan alur kerja restoran.

### Fitur Utama
- Manajemen menu
- Sistem pemesanan
- Proses pembayaran
- Manajemen meja
- Pelaporan
- Multi-level user access

## Struktur Database

### Tabel-tabel Utama

#### 1. Menu
```sql
CREATE TABLE menu (
    idmenu BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    Namamenu VARCHAR(255) NOT NULL,
    Harga INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. Users
```sql
CREATE TABLE users (
    iduser BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    level ENUM('admin', 'waiter', 'kasir', 'owner') NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 3. Pelanggan
```sql
CREATE TABLE pelanggan (
    idpelanggan BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    Namapelanggan VARCHAR(255) NOT NULL,
    Jeniskelamin ENUM('laki-laki', 'perempuan') NOT NULL,
    Nohp VARCHAR(255),
    alamat VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 4. Meja
```sql
CREATE TABLE meja (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    Nomeja INT NOT NULL,
    status ENUM('kosong', 'terpakai') DEFAULT 'kosong',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 5. Pesanan
```sql
CREATE TABLE pesanan (
    idpesanan BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    idmenu BIGINT UNSIGNED NOT NULL,
    kode_pesanan VARCHAR(25) NOT NULL,
    idpelanggan BIGINT UNSIGNED NOT NULL,
    jumlah INT NOT NULL,
    iduser BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    meja_id BIGINT UNSIGNED NOT NULL,
    FOREIGN KEY (idmenu) REFERENCES menu(idmenu),
    FOREIGN KEY (idpelanggan) REFERENCES pelanggan(idpelanggan),
    FOREIGN KEY (iduser) REFERENCES users(iduser),
    FOREIGN KEY (meja_id) REFERENCES meja(id)
);
```

#### 6. Transaksi
```sql
CREATE TABLE transaksi (
    idtransaksi BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    idpesanan BIGINT UNSIGNED NOT NULL,
    total INT NOT NULL,
    bayar INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    kembalian BIGINT GENERATED ALWAYS AS (bayar - total) STORED,
    Kurang BIGINT GENERATED ALWAYS AS (total - bayar) STORED,
    FOREIGN KEY (idpesanan) REFERENCES pesanan(idpesanan)
);
```

## Arsitektur Aplikasi

### Struktur Folder
```
/
├── assets/              # Static files (CSS, JS, Icons)
├── components/          # Reusable PHP components
├── config/             # Database and configuration files
├── database/           # SQL files
├── pages/              # Main application pages
│   ├── admin/          # Admin-specific pages
│   ├── auth/           # Authentication pages
│   ├── kasir/          # Cashier-specific pages
│   ├── owner/          # Owner-specific pages
│   └── waiter/         # Waiter-specific pages
└── index.php           # Application entry point
```

## Alur Bisnis

### 1. Proses Pemesanan
1. Pelanggan datang dan memilih meja
2. Waiter mencatat pesanan pelanggan
3. Sistem membuat kode pesanan unik
4. Status meja diupdate menjadi 'terpakai'
5. Pesanan masuk ke sistem

### 2. Proses Pembayaran
1. Kasir menerima pesanan yang sudah selesai
2. Sistem menghitung total pembayaran
3. Kasir input jumlah pembayaran
4. Sistem menghitung kembalian/kekurangan
5. Transaksi selesai, status meja kembali 'kosong'

### 3. Pelaporan
1. Owner dapat melihat laporan penjualan
2. Sistem menyediakan data transaksi
3. Laporan dapat dicetak

## Implementasi

### Komponen Utama

#### 1. Autentikasi (pages/auth/)
- Login dengan multi-level user
- Validasi credentials
- Session management

#### 2. Manajemen Menu (pages/admin/menu.php)
- CRUD operasi untuk menu
- Validasi input

#### 4. Manajemen Meja (pages/admin/meja.php)
- CRUD operasi untuk meja
- Validasi input


#### 5. Sistem Pemesanan (pages/waiter/)
- Form pemesanan
- Generasi kode pesanan
- Update status meja

#### 4. Proses Pembayaran (pages/kasir/)
- Kalkulasi total
- Validasi pembayaran
- Cetak struk

#### 5. Pelaporan (pages/owner/)
- Generate laporan
- Filter berdasarkan periode
