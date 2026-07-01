<?php
require_once 'config.php';
$db = getDB();

$sqls = [
    "CREATE TABLE IF NOT EXISTS shelves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        status ENUM('ingin_dibaca','sedang_dibaca','sudah_dibaca') DEFAULT 'ingin_dibaca',
        halaman_dibaca INT DEFAULT 0,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_book (user_id, book_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS antrian (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        status ENUM('menunggu','selesai') DEFAULT 'menunggu',
        tanggal_antri TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    )",
];

echo "<style>body{font-family:Inter,sans-serif;max-width:600px;margin:2rem auto;padding:1rem;}</style>";
foreach ($sqls as $sql) {
    try {
        $db->exec($sql);
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
        echo "<p style='color:#059669'>✅ Tabel <strong>{$m[1]}</strong> siap.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
    }
}
echo "<p><a href='rakku.php' style='background:#FF4D6D;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;'>→ Buka Rak Ku</a></p>";
