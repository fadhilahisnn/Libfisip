<?php
require_once 'config.php';

try {
    $db = getDB();

    // Add missing columns if not exist
    try { $db->exec("ALTER TABLE books ADD COLUMN stok INT DEFAULT 1 AFTER tersedia"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE books ADD COLUMN penerbit VARCHAR(255) NULL AFTER pengarang"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE books ADD COLUMN tahun INT NULL AFTER penerbit"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE books ADD COLUMN isbn VARCHAR(50) NULL AFTER tahun"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE books ADD COLUMN bahasa VARCHAR(50) DEFAULT 'Indonesia' AFTER isbn"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE books ADD COLUMN cover_image VARCHAR(255) NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN jatuh_tempo DATE NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN tanggal_kembali DATE NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN denda INT DEFAULT 0"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE users ADD COLUMN prodi VARCHAR(100) NULL AFTER nim"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE reviews ADD COLUMN tags VARCHAR(255) NULL"); } catch(Exception $e) {}
    // Update terlambat enum
    try { $db->exec("ALTER TABLE circulation MODIFY status ENUM('dipinjam','dikembalikan','terlambat') DEFAULT 'dipinjam'"); } catch(Exception $e) {}

    // Make email nullable (NIM-based admin)
    try { $db->exec("ALTER TABLE users MODIFY email VARCHAR(255) NULL"); } catch(Exception $e) {}

    // Add denda payment columns to circulation
    try { $db->exec("ALTER TABLE circulation ADD COLUMN denda_metode VARCHAR(10) NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN denda_status VARCHAR(20) DEFAULT 'belum_bayar'"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN denda_admin_id INT NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN denda_waktu_bayar DATETIME NULL"); } catch(Exception $e) {}
    try { $db->exec("ALTER TABLE circulation ADD COLUMN perpanjangan INT DEFAULT 0"); } catch(Exception $e) {}

    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        nim VARCHAR(50) NULL,
        prodi VARCHAR(100) NULL,
        email VARCHAR(255) NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('mahasiswa', 'admin') DEFAULT 'mahasiswa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        judul VARCHAR(255) NOT NULL,
        pengarang VARCHAR(255) NOT NULL,
        penerbit VARCHAR(255) NULL,
        tahun INT NULL,
        isbn VARCHAR(50) NULL,
        no_kelas VARCHAR(50) NULL,
        kategori VARCHAR(100) NOT NULL,
        bahasa VARCHAR(50) DEFAULT 'Indonesia',
        stok INT DEFAULT 1,
        tersedia INT DEFAULT 1,
        deskripsi TEXT NULL,
        color1 VARCHAR(20) DEFAULT '#FF4D6D',
        color2 VARCHAR(20) DEFAULT '#590D22',
        color3 VARCHAR(20) DEFAULT '#FFFFFF',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        rating INT NOT NULL DEFAULT 5,
        isi TEXT NOT NULL,
        tags VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS review_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        review_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS circulation (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        status ENUM('dipinjam', 'dikembalikan', 'terlambat') DEFAULT 'dipinjam',
        tanggal_pinjam TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        jatuh_tempo DATE NULL,
        tanggal_kembali DATE NULL,
        denda INT DEFAULT 0,
        denda_metode VARCHAR(10) NULL,
        denda_status VARCHAR(20) DEFAULT 'belum_bayar',
        denda_admin_id INT NULL,
        denda_waktu_bayar DATETIME NULL,
        perpanjangan INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS denda_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        circulation_id INT NOT NULL,
        user_id INT NOT NULL,
        nim_pembayar VARCHAR(50) NOT NULL,
        metode ENUM('cash','qris') NOT NULL,
        jumlah INT NOT NULL,
        status ENUM('berhasil','tertunda','gagal') DEFAULT 'tertunda',
        admin_id INT NULL,
        admin_nama VARCHAR(255) NULL,
        waktu_bayar DATETIME NOT NULL,
        catatan TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (circulation_id) REFERENCES circulation(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    CREATE TABLE IF NOT EXISTS shelves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        status ENUM('ingin_dibaca','sedang_dibaca','sudah_dibaca') DEFAULT 'ingin_dibaca',
        halaman_dibaca INT DEFAULT 0,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_book (user_id, book_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS antrian (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        status ENUM('menunggu','selesai') DEFAULT 'menunggu',
        tanggal_antri TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS book_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    );
    ";
    $db->exec($sql);

    // Insert sample books if empty
    $stmt = $db->query("SELECT COUNT(*) FROM books");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("
            INSERT INTO books (judul, pengarang, penerbit, tahun, no_kelas, kategori, bahasa, stok, tersedia, deskripsi, color1, color2, color3) VALUES
            ('Sosiologi Komunikasi', 'Burhan Bungin', 'Kencana', 2006, '302.2 BUN s', 'Sosiologi', 'Indonesia', 5, 5, 'Buku wajib untuk pengantar sosiologi komunikasi massa.', '#833ab4', '#fd1d1d', '#ffffff'),
            ('Pengantar Ilmu Politik', 'Miriam Budiardjo', 'Gramedia Pustaka Utama', 2008, '320 BUD p', 'Politik', 'Indonesia', 3, 3, 'Dasar-dasar ilmu politik yang sangat komprehensif.', '#00C9FF', '#92FE9D', '#000000'),
            ('Metodologi Penelitian Sosial', 'Sanapiah Faisal', 'Rajawali Pers', 2007, '300.72 FAI m', 'Sosiologi', 'Indonesia', 2, 2, 'Panduan metodologi penelitian kuantitatif dan kualitatif.', '#f12711', '#f5af19', '#ffffff'),
            ('Teori Komunikasi', 'Stephen W. Littlejohn', 'Salemba Humanika', 2009, '302.2 LIT t', 'Komunikasi', 'Indonesia', 4, 4, 'Pemahaman mendalam tentang teori-teori komunikasi.', '#11998e', '#38ef7d', '#ffffff'),
            ('Ilmu Administrasi Negara', 'Sondang P. Siagian', 'Bumi Aksara', 2011, '351 SIA i', 'Administrasi', 'Indonesia', 3, 3, 'Dasar-dasar ilmu administrasi negara modern.', '#373B44', '#4286f4', '#ffffff'),
            ('Antropologi Budaya', 'Koentjaraningrat', 'Djambatan', 2009, '306 KOE a', 'Antropologi', 'Indonesia', 2, 2, 'Pengantar kajian antropologi dan kebudayaan Indonesia.', '#e96c4c', '#f5d020', '#ffffff')
        ");
    }

    // ============================================
    // ADMIN ACCOUNTS — NIM-BASED LOGIN
    // ============================================
    $adminPassword = password_hash('nilaiperpusdigA', PASSWORD_DEFAULT);

    $admins = [
        ['177241062', 'Admin LibFISIP 1', 'admin1@libfisip.ac.id'],
        ['177241022', 'Admin LibFISIP 2', 'admin2@libfisip.ac.id'],
        ['177241023', 'Admin LibFISIP 3', 'admin3@libfisip.ac.id'],
    ];

    foreach ($admins as [$nim, $nama, $email]) {
        $check = $db->prepare("SELECT id FROM users WHERE nim=?");
        $check->execute([$nim]);
        if (!$check->fetch()) {
            $db->prepare("INSERT INTO users (nama, nim, email, password, role) VALUES (?,?,?,?,'admin')")->execute([$nama, $nim, $email, $adminPassword]);
        } else {
            // Update password if already exists
            $db->prepare("UPDATE users SET password=?, role='admin', nama=? WHERE nim=?")->execute([$adminPassword, $nama, $nim]);
        }
    }

    // Insert 1 sample mahasiswa if none
    $mhs = $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn();
    if ($mhs == 0) {
        $mhsPass = password_hash('password123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (nama, nim, prodi, email, password, role) VALUES
            ('Budi Santoso', '071911133090', 'Sosiologi', 'budi@student.unair.ac.id', '$mhsPass', 'mahasiswa'),
            ('Siti Rahayu', '071911133091', 'Ilmu Komunikasi', 'siti@student.unair.ac.id', '$mhsPass', 'mahasiswa')
        ");
    }

    echo "<style>body{font-family:Inter,sans-serif;max-width:600px;margin:3rem auto;padding:1rem;background:#f9fafb;}</style>";
    echo "<h2 style='color:#059669'>✅ Setup Database Berhasil!</h2>";
    echo "<p>Database <strong>libfisip</strong> siap digunakan.</p>";
    echo "<hr style='margin:1.5rem 0;border:none;border-top:1px solid #e5e7eb;'>";
    echo "<h3 style='margin-bottom:1rem;'>🔐 Akun Admin</h3>";
    echo "<table style='width:100%;border-collapse:collapse;font-size:0.9rem;'>";
    echo "<tr style='background:#f3f4f6;'><th style='padding:8px 12px;text-align:left;'>NIM</th><th style='padding:8px 12px;text-align:left;'>Password</th><th style='padding:8px 12px;text-align:left;'>Nama</th></tr>";
    foreach ($admins as [$nim, $nama, $email]) {
        echo "<tr><td style='padding:8px 12px;font-family:monospace;'>{$nim}</td><td style='padding:8px 12px;font-family:monospace;'>nilaiperpusdigA</td><td style='padding:8px 12px;'>{$nama}</td></tr>";
    }
    echo "</table>";
    echo "<hr style='margin:1.5rem 0;border:none;border-top:1px solid #e5e7eb;'>";
    echo "<p style='color:#6b7280;font-size:0.85rem;'>Panel admin: <a href='admin/index.php'>admin/index.php</a></p>";
    echo "<p style='margin-top:1rem;'><a href='login.php' style='background:#FF4D6D;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;'>→ Pergi ke Halaman Login</a></p>";

} catch (\PDOException $e) {
    echo "<pre style='color:red'>Gagal: " . $e->getMessage() . "</pre>";
}
?>
