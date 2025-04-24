-- Stored Procedure untuk mendapatkan pesanan yang siap dibayar berdasarkan meja
DELIMITER $$
CREATE PROCEDURE get_orders_ready_for_payment(
    IN p_meja_id BIGINT UNSIGNED
)
BEGIN
    SELECT 
        p.idpesanan,
        p.kode_pesanan,
        m.Nomeja,
        GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' x', p.jumlah) SEPARATOR ', ') as items,
        SUM(p.jumlah * menu.Harga) as total_amount,
        MIN(p.created_at) as order_time
    FROM pesanan p
    JOIN meja m ON p.meja_id = m.id
    JOIN menu ON p.idmenu = menu.idmenu
    LEFT JOIN transaksi t ON p.idpesanan = t.idtransaksi
    WHERE p.meja_id = p_meja_id
    AND t.idtransaksi IS NULL
    GROUP BY p.kode_pesanan;
END$$
DELIMITER ;

-- Stored Procedure untuk mendapatkan laporan transaksi berdasarkan rentang tanggal
DELIMITER $$
CREATE PROCEDURE get_transactions_report(
    IN start_date DATE,
    IN end_date DATE
)
BEGIN
    SELECT 
        t.idtransaksi,
        t.created_at,
        p.kode_pesanan,
        m.Nomeja,
        GROUP_CONCAT(DISTINCT CONCAT(menu.Namamenu, ' x', p2.jumlah) SEPARATOR ', ') as items,
        SUM(p2.jumlah * menu.Harga) as total_amount,
        t.bayar,
        t.kembalian
    FROM transaksi t
    JOIN pesanan p ON t.idpesanan = p.idpesanan
    JOIN meja m ON p.meja_id = m.id
    JOIN pesanan p2 ON p2.kode_pesanan = p.kode_pesanan
    JOIN menu ON p2.idmenu = menu.idmenu
    WHERE DATE(t.created_at) BETWEEN start_date AND end_date
    GROUP BY t.idtransaksi, p.kode_pesanan
    ORDER BY t.created_at DESC;
END$$
DELIMITER ;

-- Stored Procedure untuk memproses pembayaran multi-pesanan
DELIMITER $$
CREATE PROCEDURE process_table_payment(
    IN p_meja_id BIGINT UNSIGNED,
    IN p_bayar INT
)
BEGIN
    DECLARE total_tagihan INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE curr_pesanan_id BIGINT UNSIGNED;
    DECLARE pesanan_cursor CURSOR FOR
        SELECT p.idpesanan
        FROM pesanan p
        LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan
        WHERE p.meja_id = p_meja_id AND t.idtransaksi IS NULL;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Hitung total tagihan untuk semua pesanan di meja
    SELECT COALESCE(SUM(p.jumlah * menu.Harga), 0) INTO total_tagihan
    FROM pesanan p
    JOIN menu ON p.idmenu = menu.idmenu
    LEFT JOIN transaksi t ON p.idpesanan = t.idpesanan
    WHERE p.meja_id = p_meja_id AND t.idtransaksi IS NULL;
    
    -- Cek apakah pembayaran cukup
    IF p_bayar >= total_tagihan THEN
        -- Buat transaksi untuk setiap pesanan
        OPEN pesanan_cursor;
        read_loop: LOOP
            FETCH pesanan_cursor INTO curr_pesanan_id;
            IF done THEN
                LEAVE read_loop;
            END IF;
            
            -- Hitung total untuk pesanan ini
            SET @pesanan_total = (
                SELECT SUM(p.jumlah * menu.Harga)
                FROM pesanan p
                JOIN menu ON p.idmenu = menu.idmenu
                WHERE p.idpesanan = curr_pesanan_id
            );
            
            -- Buat transaksi
            INSERT INTO transaksi (idpesanan, total, bayar)
            VALUES (curr_pesanan_id, @pesanan_total, p_bayar);
        END LOOP;
        CLOSE pesanan_cursor;
        
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

-- Trigger untuk memvalidasi stok menu sebelum pesanan
DELIMITER $$
CREATE TRIGGER validate_menu_before_order
BEFORE INSERT ON pesanan
FOR EACH ROW
BEGIN
    DECLARE menu_exists INT;
    
    -- Cek apakah menu masih ada
    SELECT COUNT(*) INTO menu_exists
    FROM menu
    WHERE idmenu = NEW.idmenu;
    
    IF menu_exists = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Menu tidak tersedia';
    END IF;
END$$
DELIMITER ;

-- Trigger untuk memvalidasi pembayaran
DELIMITER $$
CREATE TRIGGER validate_payment
BEFORE INSERT ON transaksi
FOR EACH ROW
BEGIN
    IF NEW.bayar < NEW.total THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pembayaran kurang dari total tagihan';
    END IF;
END$$
DELIMITER ;