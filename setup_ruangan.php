<?php
require_once 'config.php';
$db = getDB();

$sqls = [
    // Tabel ruangan
    "CREATE TABLE IF NOT EXISTS rooms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        capacity INT NOT NULL,
        facilities TEXT,
        image VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Tabel booking/peminjaman ruangan
    "CREATE TABLE IF NOT EXISTS room_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        room_id INT NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        duration_hours INT NOT NULL,
        purpose VARCHAR(255) NOT NULL,
        briefing TEXT,
        participant_count INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
    )",
    
    // Insert default rooms
    "INSERT INTO rooms (name, description, capacity, facilities, image, is_active) 
    VALUES 
        ('Ruang Baca Utama', 'Ruang baca utama dengan kapasitas besar, cocok untuk belajar mandiri atau kelompok kecil.', 30, 'AC, WiFi, Meja Panjang, Kursi Empuk, Stopkontak', 'room1.jpg', TRUE),
        ('Ruang Diskusi 1', 'Ruang diskusi kecil untuk kelompok belajar atau rapat organisasi.', 10, 'AC, WiFi, Whiteboard, Meja Bundar', 'room2.jpg', TRUE)"
];

echo "<style>body{font-family:Inter,sans-serif;max-width:600px;margin:2rem auto;padding:1rem;}</style>";
foreach ($sqls as $sql) {
    try {
        if (strpos($sql, 'INSERT') === 0) {
            $db->exec($sql);
            echo "<p style='color:#059669'>✅ Data ruangan default ditambahkan.</p>";
        } else {
            $db->exec($sql);
            preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m);
            echo "<p style='color:#059669'>✅ Tabel <strong>{$m[1]}</strong> siap.</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red'>❌ " . $e->getMessage() . "</p>";
    }
}
echo "<p><a href='ruangan.php' style='background:#FF4D6D;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;'>→ Buka Peminjaman Ruang</a></p>";
echo "<p><a href='admin/ruangan.php' style='background:#590D22;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;'>→ Kelola Ruangan (Admin)</a></p>";