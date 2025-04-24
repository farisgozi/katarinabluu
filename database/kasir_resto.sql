-- Membuat Database
CREATE DATABASE kasir_resto;
USE kasir_resto;

-- Membuat Tabel menu
CREATE TABLE menu (
    idmenu BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    Namamenu VARCHAR(255) NOT NULL,
    Harga INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Membuat Tabel users
CREATE TABLE users (
    iduser BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nama VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    level ENUM('admin', 'waiter', 'kasir', 'owner') NOT NULL,
    remember_token VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Membuat Tabel pelanggan
CREATE TABLE pelanggan (
    idpelanggan BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    Namapelanggan VARCHAR(255) NOT NULL,
    Jeniskelamin ENUM('laki-laki', 'perempuan') NOT NULL,
    Nohp VARCHAR(255),
    alamat VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Membuat Tabel meja
CREATE TABLE meja (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    Nomeja INT NOT NULL,
    status ENUM('kosong', 'terpakai') DEFAULT 'kosong',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Membuat Tabel pesanan
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
    FOREIGN KEY (idmenu) REFERENCES menu(idmenu) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (idpelanggan) REFERENCES pelanggan(idpelanggan) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (iduser) REFERENCES users(iduser) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (meja_id) REFERENCES meja(id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Membuat Tabel transaksi
CREATE TABLE transaksi (
    idtransaksi BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    idpesanan BIGINT UNSIGNED NOT NULL,
    total INT NOT NULL,
    bayar INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    kembalian BIGINT GENERATED ALWAYS AS (bayar - total) STORED,
    Kurang BIGINT GENERATED ALWAYS AS (total - bayar) STORED,
    FOREIGN KEY (idpesanan) REFERENCES pesanan(idpesanan) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Trigger untuk mengubah status meja saat pesanan dibuat
DELIMITER $$
CREATE TRIGGER update_meja_status_on_pesanan
AFTER INSERT ON pesanan
FOR EACH ROW
BEGIN
    UPDATE meja SET status = 'terpakai' WHERE id = NEW.meja_id;
END$$
DELIMITER ;

-- Trigger untuk mengembalikan status meja ke 'kosong' saat transaksi selesai
DELIMITER $$
CREATE TRIGGER reset_meja_status_on_transaksi
AFTER INSERT ON transaksi
FOR EACH ROW
BEGIN
    DECLARE meja_pesanan BIGINT UNSIGNED;
    
    SELECT meja_id INTO meja_pesanan 
    FROM pesanan 
    WHERE idpesanan = NEW.idpesanan;
    
    UPDATE meja SET status = 'kosong' WHERE id = meja_pesanan;
END$$
DELIMITER ;

-- Trigger untuk menghitung total harga pesanan secara otomatis
DELIMITER $$
CREATE TRIGGER calculate_total_harga
BEFORE INSERT ON transaksi
FOR EACH ROW
BEGIN
    DECLARE total_harga INT;
    
    SELECT SUM(m.Harga * p.jumlah) INTO total_harga
    FROM pesanan p
    JOIN menu m ON p.idmenu = m.idmenu
    WHERE p.idpesanan = NEW.idpesanan;
    
    SET NEW.total = total_harga;
END$$
DELIMITER ;

-- Stored Procedure untuk membuat pesanan baru
DELIMITER $$
CREATE PROCEDURE create_new_order(
    IN p_idmenu BIGINT UNSIGNED,
    IN p_idpelanggan BIGINT UNSIGNED,
    IN p_jumlah INT,
    IN p_iduser BIGINT UNSIGNED,
    IN p_meja_id BIGINT UNSIGNED
)
BEGIN
    DECLARE kode VARCHAR(25);
    
    -- Generate kode pesanan: ORD-YYYYMMDD-RANDOM
    SET kode = CONCAT('ORD-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', FLOOR(RAND() * 10000));
    
    -- Cek apakah meja tersedia
    IF (SELECT status FROM meja WHERE id = p_meja_id) = 'kosong' THEN
        -- Insert pesanan baru
        INSERT INTO pesanan (idmenu, kode_pesanan, idpelanggan, jumlah, iduser, meja_id)
        VALUES (p_idmenu, kode, p_idpelanggan, p_jumlah, p_iduser, p_meja_id);
        
        SELECT 'Pesanan berhasil dibuat' AS message, kode AS kode_pesanan;
    ELSE
        SELECT 'Meja sudah terpakai' AS message;
    END IF;
END$$
DELIMITER ;

-- Stored Procedure untuk membuat transaksi pembayaran
DELIMITER $$
CREATE PROCEDURE process_payment(
    IN p_idpesanan BIGINT UNSIGNED,
    IN p_bayar INT
)
BEGIN
    DECLARE total_tagihan INT;
    
    -- Hitung total tagihan
    SELECT SUM(m.Harga * p.jumlah) INTO total_tagihan
    FROM pesanan p
    JOIN menu m ON p.idmenu = m.idmenu
    WHERE p.idpesanan = p_idpesanan;
    
    -- Cek apakah pembayaran cukup
    IF p_bayar >= total_tagihan THEN
        -- Buat transaksi
        INSERT INTO transaksi (idpesanan, total, bayar)
        VALUES (p_idpesanan, total_tagihan, p_bayar);
        
        SELECT 
            'Pembayaran berhasil' AS message, 
            total_tagihan AS total, 
            p_bayar AS bayar, 
            (p_bayar - total_tagihan) AS kembalian;
    ELSE
        SELECT 
            'Pembayaran kurang' AS message, 
            total_tagihan AS total, 
            p_bayar AS bayar, 
            (total_tagihan - p_bayar) AS kurang;
    END IF;
END$$
DELIMITER ;

-- Stored Procedure untuk mendapatkan laporan penjualan harian
DELIMITER $$
CREATE PROCEDURE get_daily_sales_report(
    IN report_date DATE
)
BEGIN
    SELECT 
        DATE(t.created_at) AS tanggal,
        m.Namamenu,
        p.jumlah,
        m.Harga,
        (p.jumlah * m.Harga) AS subtotal,
        u.nama AS kasir
    FROM transaksi t
    JOIN pesanan p ON t.idpesanan = p.idpesanan
    JOIN menu m ON p.idmenu = m.idmenu
    JOIN users u ON p.iduser = u.iduser
    WHERE DATE(t.created_at) = report_date
    ORDER BY t.created_at;
    
    -- Menampilkan total penjualan hari tersebut
    SELECT 
        DATE(created_at) AS tanggal,
        SUM(total) AS total_penjualan,
        COUNT(*) AS jumlah_transaksi
    FROM transaksi
    WHERE DATE(created_at) = report_date
    GROUP BY DATE(created_at);
END$$
DELIMITER ;

-- Stored Procedure untuk mencari pesanan berdasarkan kode
DELIMITER $$
CREATE PROCEDURE find_order_by_code(
    IN p_kode VARCHAR(25)
)
BEGIN
    SELECT 
        p.idpesanan,
        p.kode_pesanan,
        m.Namamenu,
        m.Harga,
        p.jumlah,
        (m.Harga * p.jumlah) AS subtotal,
        pl.Namapelanggan,
        mj.Nomeja,
        p.created_at
    FROM pesanan p
    JOIN menu m ON p.idmenu = m.idmenu
    JOIN pelanggan pl ON p.idpelanggan = pl.idpelanggan
    JOIN meja mj ON p.meja_id = mj.id
    WHERE p.kode_pesanan = p_kode;
END$$
DELIMITER ;