USE kasir_resto;

-- Data dummy untuk tabel menu
INSERT INTO menu (Namamenu, Harga) VALUES
('Nasi Goreng Spesial', 25000),
('Mie Goreng Seafood', 28000),
('Ayam Bakar', 35000),
('Soto Ayam', 20000),
('Es Teh Manis', 5000),
('Jus Alpukat', 12000),
('Sup Iga Sapi', 45000),
('Cah Kangkung', 15000),
('Udang Goreng Tepung', 40000),
('Sate Ayam', 25000);

-- Data dummy untuk tabel users
INSERT INTO users (nama, password, level) VALUES
('admin', '$2y$10$DMZeqCNB36LMJB6XdFqBI.prq0lZaV52rug/46U98tj2.MSoGl1na', 'admin'),
('waiter', '$2y$10$DMZeqCNB36LMJB6XdFqBI.prq0lZaV52rug/46U98tj2.MSoGl1na', 'waiter'),
('kasir', '$2y$10$DMZeqCNB36LMJB6XdFqBI.prq0lZaV52rug/46U98tj2.MSoGl1na', 'kasir'),
('owner', '$2y$10$DMZeqCNB36LMJB6XdFqBI.prq0lZaV52rug/46U98tj2.MSoGl1na', 'owner'),
('waiter2', '$2y$10$DMZeqCNB36LMJB6XdFqBI.prq0lZaV52rug/46U98tj2.MSoGl1na', 'waiter');

-- Data dummy untuk tabel pelanggan
INSERT INTO pelanggan (Namapelanggan, Jeniskelamin, Nohp, alamat) VALUES
('Anita Sari', 'perempuan', '081234567890', 'Jl. Mawar No. 10, Jakarta'),
('Bayu Pratama', 'laki-laki', '082345678901', 'Jl. Melati No. 5, Bandung'),
('Cindy Larasati', 'perempuan', '083456789012', 'Jl. Kenanga No. 15, Surabaya'),
('Deni Firmansyah', 'laki-laki', '084567890123', 'Jl. Dahlia No. 20, Yogyakarta'),
('Erika Putri', 'perempuan', '085678901234', 'Jl. Anggrek No. 25, Semarang'),
('Fajar Ramadhan', 'laki-laki', '086789012345', 'Jl. Tulip No. 30, Malang'),
('Gita Nuraini', 'perempuan', '087890123456', 'Jl. Kamboja No. 35, Bali'),
('Hendra Wijaya', 'laki-laki', '088901234567', 'Jl. Teratai No. 40, Makassar');

-- Data dummy untuk tabel meja
INSERT INTO meja (Nomeja, status) VALUES
(1, 'kosong'),
(2, 'kosong'),
(3, 'kosong'),
(4, 'kosong'),
(5, 'kosong'),
(6, 'kosong'),
(7, 'kosong'),
(8, 'kosong'),
(9, 'kosong'),
(10, 'kosong');

-- Menggunakan stored procedure untuk membuat pesanan
-- Catatan: Kita akan membuat beberapa pesanan menggunakan stored procedure yang telah dibuat

-- Pesanan 1
CALL create_new_order(1, 1, 2, 2, 1);

-- Pesanan 2
CALL create_new_order(3, 2, 1, 2, 2);

-- Pesanan 3
CALL create_new_order(5, 3, 4, 2, 3);

-- Pesanan 4
CALL create_new_order(7, 4, 1, 2, 4);

-- Pesanan 5
CALL create_new_order(2, 5, 2, 2, 5);

-- Pesanan 6
CALL create_new_order(4, 6, 3, 5, 6);

-- Pesanan 7
CALL create_new_order(6, 7, 2, 5, 7);

-- Pesanan 8 (tidak menggunakan stored procedure untuk variasi)
INSERT INTO pesanan (idmenu, kode_pesanan, idpelanggan, jumlah, iduser, meja_id)
VALUES (8, 'ORD-20250308-8754', 8, 2, 5, 8);

-- Menggunakan stored procedure untuk membuat transaksi pembayaran
-- Beberapa transaksi

-- Transaksi 1 - Bayar cukup
CALL process_payment(1, 55000);

-- Transaksi 2 - Bayar lebih
CALL process_payment(2, 40000);

-- Transaksi 3 - Bayar pas
CALL process_payment(3, 20000);

-- Transaksi 4 - Bayar kurang (ini seharusnya gagal)
CALL process_payment(4, 30000);

-- Transaksi 5 (tidak menggunakan stored procedure untuk variasi)
INSERT INTO transaksi (idpesanan, total, bayar)
VALUES (5, 56000, 60000);

-- Query untuk melihat data di setiap tabel
-- SELECT * FROM menu;
-- SELECT * FROM users;
-- SELECT * FROM pelanggan;
-- SELECT * FROM meja;
-- SELECT * FROM pesanan;
-- SELECT * FROM transaksi;

-- Contoh penggunaan stored procedure untuk laporan harian
-- CALL get_daily_sales_report(CURRENT_DATE());

-- Contoh pencarian pesanan berdasarkan kode
-- CALL find_order_by_code('ORD-20250308-8754');