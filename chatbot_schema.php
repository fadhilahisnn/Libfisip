<?php
require_once 'config.php';
$db = getDB();

$sqls = [
    // Tabel untuk menyimpan kategori chatbot
    "CREATE TABLE IF NOT EXISTS chatbot_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(20) DEFAULT '💬',
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Tabel untuk menyimpan respon chatbot
    "CREATE TABLE IF NOT EXISTS chatbot_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        keywords VARCHAR(255),
        sort_order INT DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES chatbot_categories(id) ON DELETE CASCADE
    )",
    
    // Tabel untuk menyimpan riwayat percakapan
    "CREATE TABLE IF NOT EXISTS chatbot_conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL,
        user_message TEXT,
        bot_response TEXT,
        category_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES chatbot_categories(id) ON DELETE SET NULL,
        INDEX idx_session (session_id),
        INDEX idx_created (created_at)
    )",
    
    // Tabel untuk Live Chat
    "CREATE TABLE IF NOT EXISTS livechat_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(100) NOT NULL,
        status ENUM('active', 'closed') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_session (session_id)
    )",
    
    "CREATE TABLE IF NOT EXISTS livechat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        livechat_session_id INT NOT NULL,
        sender ENUM('user', 'admin') NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (livechat_session_id) REFERENCES livechat_sessions(id) ON DELETE CASCADE
    )",
];

echo "<style>body{font-family:Inter,sans-serif;max-width:800px;margin:2rem auto;padding:1rem;background:#f5f5f5;}</style>";
echo "<h2 style='color:#FF4D6D;margin-bottom:1rem;'>🤖 Setup Database Chatbot</h2>";

foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "<p style='color:#059669;background:#fff;padding:12px;border-radius:8px;margin:8px 0;border-left:4px solid #059669;'>✅ Tabel <strong>{$m[1]}</strong> siap.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red;background:#fff;padding:12px;border-radius:8px;margin:8px 0;border-left:4px solid red;'>❌ " . $e->getMessage() . "</p>";
    }
}

// Insert default categories if not exists
$defaultCategories = [
    ['name' => 'Mahasiswa', 'description' => 'Layanan untuk mahasiswa UNAIR', 'icon' => '🎓', 'sort_order' => 1],
    ['name' => 'Pengguna Umum', 'description' => 'Layanan untuk pengunjung umum (Non-Unair)', 'icon' => '👋', 'sort_order' => 2],
    ['name' => 'Kebutuhan Khusus', 'description' => 'Aksesibilitas dan bantuan khusus', 'icon' => '♿', 'sort_order' => 3],
];

foreach ($defaultCategories as $cat) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM chatbot_categories WHERE name = ?");
    $stmt->execute([$cat['name']]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO chatbot_categories (name, description, icon, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$cat['name'], $cat['description'], $cat['icon'], $cat['sort_order']]);
        echo "<p style='color:#2563EB;background:#fff;padding:12px;border-radius:8px;margin:8px 0;border-left:4px solid #2563EB;'>✅ Kategori '<strong>{$cat['name']}</strong>' ditambahkan.</p>";
    }
}

// Get category IDs for inserting default responses
$cats = $db->query("SELECT id, name FROM chatbot_categories ORDER BY sort_order")->fetchAll();
$catIds = [];
foreach ($cats as $c) {
    $catIds[$c['name']] = $c['id'];
}

// Insert default responses
$defaultResponses = [];

if (isset($catIds['Mahasiswa'])) {
    $defaultResponses[] = [
        'category_id' => $catIds['Mahasiswa'],
        'question' => 'Saya butuh bantuan cari buku/referensi skripsi untuk tugas/skripsi saya, bisa tolong carikan?',
        'answer' => 'Tentu! Anda bisa menggunakan fitur pencarian di menu "Koleksi" untuk menemukan buku dan referensi skripsi. Coba gunakan kata kunci sesuai topik skripsi Anda. Jika butuh bantuan lebih lanjut, silakan hubungi pustakawan di Ruang Baca FISIP Lt.2 Gedung A201.',
        'keywords' => 'buku,referensi,skripsi,tugas,cari,pencarian',
        'sort_order' => 1
    ];
    
    $defaultResponses[] = [
        'category_id' => $catIds['Mahasiswa'],
        'question' => 'Koleksi buku untuk mata kuliah [...] apa bisa dibaca/dipinjam di sini?',
        'answer' => 'Ya, koleksi buku di Ruang Baca FISIP dapat dibaca di tempat atau dipinjam untuk dibawa pulang. Masa pinjam maksimal 7 hari dan dapat diperpanjang 1x. Silakan cek ketersediaan buku di menu "Koleksi" atau tanyakan langsung ke pustakawan.',
        'keywords' => 'koleksi,buku,mata kuliah,pinjam,baca',
        'sort_order' => 2
    ];
    
    $defaultResponses[] = [
        'category_id' => $catIds['Mahasiswa'],
        'question' => 'Perpanjangan peminjaman buku bisa dilakukan di sini atau hubungi pustakawan?',
        'answer' => 'Perpanjangan peminjaman buku dapat dilakukan melalui website ini dengan login ke akun Anda, atau langsung menghubungi pustakawan di Ruang Baca FISIP. Perpanjangan hanya dapat dilakukan 1x untuk setiap peminjaman. Pastikan tidak ada denda tertunggak.',
        'keywords' => 'perpanjangan,pinjaman,denda,pustakawan',
        'sort_order' => 3
    ];
    
    $defaultResponses[] = [
        'category_id' => $catIds['Mahasiswa'],
        'question' => 'Apakah bisa saya meminjam ruang baca/hidden room di RBC?',
        'answer' => 'Ya, Anda dapat meminjam hidden room di RBC FISIP untuk keperluan diskusi kelompok atau belajar. Silakan hubungi pustakawan untuk informasi ketersediaan dan prosedur peminjaman. Hidden room tersedia dengan kapasitas terbatas.',
        'keywords' => 'ruang baca,hidden room,RBC,pinjam,diskusi',
        'sort_order' => 4
    ];
}

if (isset($catIds['Pengguna Umum'])) {
    $defaultResponses[] = [
        'category_id' => $catIds['Pengguna Umum'],
        'question' => 'Pertama kali pakai layanan kami? Selamat datang! 👋',
        'answer' => 'Selamat datang di Ruang Baca FISIP UNAIR! 🎉 Kami menyediakan ribuan koleksi buku, jurnal, dan referensi akademik. Anda bisa mencari koleksi, membaca review, dan mendapatkan informasi layanan perpustakaan. Untuk meminjam buku, silakan daftar sebagai anggota.',
        'keywords' => 'selamat datang,pertama kali,pengguna baru',
        'sort_order' => 1
    ];
    
    $defaultResponses[] = [
        'category_id' => $catIds['Pengguna Umum'],
        'question' => 'Saya kasih tour cepat tentang cara pakai website perpus',
        'answer' => 'Tentu! Berikut tour cepat website kami:
1️⃣ **Beranda** - Lihat koleksi terbaru dan rekomendasi
2️⃣ **Koleksi** - Cari buku berdasarkan judul, pengarang, atau kategori
3️⃣ **Book Talk** - Baca review dari mahasiswa lain
4️⃣ **Usulan** - Ajukan usulan buku baru
Untuk meminjam buku, Anda perlu login dengan akun mahasiswa UNAIR.',
        'keywords' => 'tour,panduan,cara pakai,tutorial',
        'sort_order' => 2
    ];
    
    $defaultResponses[] = [
        'category_id' => $catIds['Pengguna Umum'],
        'question' => 'Ada fitur apa saja di RBC FISIP UNAIR? Mau saya jelasin?',
        'answer' => 'RBC FISIP UNAIR memiliki berbagai fitur:
📚 **Koleksi Buku** - Ribuan buku Ilmu Sosial & Politik
💬 **Book Talk** - Platform review buku
🔍 **Pencarian** - Cari referensi dengan mudah
📖 **Peminjaman** - Pinjam buku hingga 7 hari
📝 **Usulan** - Ajukan buku yang Anda inginkan
🏠 **Hidden Room** - Ruang diskusi privat
Jam buka: Senin-Jumat 08.00-16.30, Sabtu 08.00-12.00',
        'keywords' => 'fitur,layanan,RBC,fasilitas',
        'sort_order' => 3
    ];
}

if (isset($catIds['Kebutuhan Khusus'])) {
    $defaultResponses[] = [
        'category_id' => $catIds['Kebutuhan Khusus'],
        'question' => 'Website ini bisa diakses dengan screen reader',
        'answer' => 'Ya, website Ruang Baca FISIP dirancang agar dapat diakses dengan screen reader. Kami menggunakan standar WCAG untuk memastikan aksesibilitas. Jika Anda mengalami kesulitan, silakan hubungi kami untuk bantuan lebih lanjut. Fitur aksesibilitas: navigasi keyboard, teks alternatif untuk gambar, dan struktur HTML semantik.',
        'keywords' => 'screen reader,aksesibilitas,disabilitas visual,tunanetra',
        'sort_order' => 1
    ];
    
    $defaultResponses[] = [
        'category_id' => $catIds['Kebutuhan Khusus'],
        'question' => 'I can help in English',
        'answer' => 'Hello! Welcome to FISIP Reading Room, Universitas Airlangga. Our library provides thousands of books, journals, and academic references in Social and Political Sciences. You can search our collection, read reviews, and get library service information. To borrow books, please register as a member. Opening hours: Mon-Fri 08:00-16:30, Sat 08:00-12:00. How can I assist you today?',
        'keywords' => 'english,inggris,non-indonesia,ESL',
        'sort_order' => 2
    ];
}

foreach ($defaultResponses as $resp) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM chatbot_responses WHERE question = ?");
    $stmt->execute([$resp['question']]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO chatbot_responses (category_id, question, answer, keywords, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$resp['category_id'], $resp['question'], $resp['answer'], $resp['keywords'], $resp['sort_order']]);
        echo "<p style='color:#2563EB;background:#fff;padding:12px;border-radius:8px;margin:8px 0;border-left:4px solid #2563EB;'>✅ Respon '<strong>" . substr($resp['question'], 0, 50) . "...</strong>' ditambahkan.</p>";
    }
}

echo "<p style='margin-top:2rem;'><a href='index.php' style='background:#FF4D6D;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;'>→ Kembali ke Beranda</a></p>";
echo "<p><a href='admin/chatbot.php' style='background:#2563EB;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;margin-left:10px;'>→ Kelola Chatbot (Admin)</a></p>";
?>