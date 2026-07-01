<?php
require 'config.php';
$db = getDB();

// Create tables if not exist
$db->exec("CREATE TABLE IF NOT EXISTS chatbot_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(20) DEFAULT '💬',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS chatbot_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    question TEXT NOT NULL,
    answer TEXT NOT NULL,
    keywords VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(100) NOT NULL,
    user_message TEXT,
    bot_response TEXT,
    category_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$cats = $db->query("SELECT COUNT(*) FROM chatbot_categories WHERE is_active=1")->fetchColumn();
$resps = $db->query("SELECT COUNT(*) FROM chatbot_responses WHERE is_active=1")->fetchColumn();

echo "Categories: $cats\nResponses: $resps\n";

// If no categories, seed defaults
if ($cats == 0) {
    $defaultCategories = [
        ['Peminjaman Buku', 'Informasi seputar peminjaman koleksi', '📚', 1],
        ['Keanggotaan', 'Pendaftaran dan akun anggota', '👤', 2],
        ['Fasilitas Ruangan', 'Peminjaman hidden room dan ruang baca', '🏛️', 3],
        ['Koleksi & Referensi', 'Pencarian buku dan referensi', '🔍', 4],
        ['Informasi Umum', 'Jam buka, lokasi, dan info lainnya', '💬', 5],
    ];
    $stmt = $db->prepare("INSERT INTO chatbot_categories (name, description, icon, sort_order) VALUES (?,?,?,?)");
    foreach ($defaultCategories as $c) {
        $stmt->execute($c);
    }
    echo "Default categories inserted.\n";
    $cats = $db->query("SELECT COUNT(*) FROM chatbot_categories WHERE is_active=1")->fetchColumn();
}

if ($resps == 0) {
    $catRows = $db->query("SELECT id, name FROM chatbot_categories")->fetchAll();
    $catIds = [];
    foreach ($catRows as $r) $catIds[$r['name']] = $r['id'];

    $rows = [];
    if (isset($catIds['Peminjaman Buku'])) {
        $cid = $catIds['Peminjaman Buku'];
        $rows[] = [$cid, 'Bagaimana cara meminjam buku?', 'Untuk meminjam buku: (1) Login ke akun Anda, (2) Cari buku di menu Koleksi, (3) Ajukan peminjaman. Masa pinjam 7 hari dan bisa diperpanjang 1x. Denda keterlambatan Rp500/hari.', 'pinjam,cara,prosedur', 1];
        $rows[] = [$cid, 'Berapa lama masa pinjam buku?', 'Masa pinjam buku di RBC FISIP adalah 7 hari dan dapat diperpanjang 1x. Denda keterlambatan sebesar Rp500 per hari per buku.', 'lama,durasi,masa pinjam,perpanjangan', 2];
        $rows[] = [$cid, 'Bagaimana cara mengembalikan buku?', 'Pengembalian buku dilakukan langsung di Ruang Baca FISIP Lt.2 Gedung A201, Senin–Jumat 08.00–16.30. Serahkan buku ke petugas dan minta bukti pengembalian.', 'kembali,mengembalikan,return', 3];
    }
    if (isset($catIds['Keanggotaan'])) {
        $cid = $catIds['Keanggotaan'];
        $rows[] = [$cid, 'Bagaimana cara mendaftar sebagai anggota?', 'Untuk mendaftar, klik tombol Daftar di pojok kanan atas, isi data diri (NIM, nama, email), dan verifikasi. Keanggotaan gratis untuk mahasiswa UNAIR aktif.', 'daftar,register,anggota,akun', 1];
        $rows[] = [$cid, 'Saya lupa password akun saya', 'Untuk mereset password, klik "Lupa Password" di halaman login dan ikuti petunjuk yang dikirimkan ke email Anda. Jika masih kesulitan, hubungi petugas RBC.', 'lupa,password,reset,login', 2];
    }
    if (isset($catIds['Fasilitas Ruangan'])) {
        $cid = $catIds['Fasilitas Ruangan'];
        $rows[] = [$cid, 'Bagaimana cara meminjam Hidden Room?', 'Hidden Room dapat dipinjam melalui menu "Pinjam Ruang" di website ini. Isi formulir tanggal dan waktu yang diinginkan, lalu tunggu konfirmasi dari petugas. Kapasitas 6 orang.', 'hidden room,pinjam ruang,diskusi,booking', 1];
        $rows[] = [$cid, 'Apa saja fasilitas yang tersedia di ruang baca?', 'Ruang Baca Utama dilengkapi: meja baca panjang, kursi nyaman, AC, colokan listrik, dan akses WiFi. Hidden Room tersedia untuk diskusi kelompok kecil (maks. 6 orang).', 'fasilitas,ruang baca,wifi,AC', 2];
    }
    if (isset($catIds['Koleksi & Referensi'])) {
        $cid = $catIds['Koleksi & Referensi'];
        $rows[] = [$cid, 'Bagaimana cara mencari buku di katalog?', 'Gunakan menu Koleksi atau kotak pencarian di bagian atas. Anda dapat mencari berdasarkan judul, pengarang, ISBN, atau kata kunci. Filter berdasarkan kategori juga tersedia.', 'cari,katalog,pencarian,buku,referensi', 1];
        $rows[] = [$cid, 'Bisakah saya mengusulkan buku baru?', 'Ya! Klik menu Layanan Pustaka → Usulan. Isi judul buku, pengarang, dan alasan usulan Anda. Tim kami akan mempertimbangkan usulan dan memberi kabar.', 'usul,request,buku baru', 2];
    }
    if (isset($catIds['Informasi Umum'])) {
        $cid = $catIds['Informasi Umum'];
        $rows[] = [$cid, 'Apa jam operasional RBC FISIP?', 'Jam buka Ruang Baca FISIP UNAIR:\n• Senin–Jumat: 08.00–16.30 WIB\n• Sabtu: 08.00–12.00 WIB\n• Minggu & Hari Libur: Tutup', 'jam,buka,operasional,tutup,hari', 1];
        $rows[] = [$cid, 'Di mana lokasi RBC FISIP UNAIR?', 'Ruang Baca FISIP berada di Lantai 2 Gedung A201, Fakultas Ilmu Sosial dan Ilmu Politik, Universitas Airlangga, Jl. Dharmawangsa Dalam Selatan, Surabaya.', 'lokasi,alamat,gedung,lantai,tempat', 2];
        $rows[] = [$cid, 'Bagaimana cara menghubungi petugas perpustakaan?', 'Anda dapat menghubungi petugas RBC FISIP melalui:\n📧 Email: perpus@fisip.unair.ac.id\n📍 Langsung ke Gedung A201 Lt.2 FISIP UNAIR\nJam pelayanan: Senin–Jumat 08.00–16.30 WIB', 'kontak,hubungi,email,petugas', 3];
    }

    $stmt = $db->prepare("INSERT INTO chatbot_responses (category_id, question, answer, keywords, sort_order) VALUES (?,?,?,?,?)");
    foreach ($rows as $r) $stmt->execute($r);
    echo "Default responses inserted: " . count($rows) . "\n";
}

echo "Done! Categories: $cats, Responses: " . $db->query("SELECT COUNT(*) FROM chatbot_responses WHERE is_active=1")->fetchColumn();
