<?php
require_once 'config.php';
$db = getDB();

echo "<style>body{font-family:Inter,sans-serif;max-width:600px;margin:2rem auto;padding:1rem;}</style>";

try {
    // Get all rooms to show what will be deleted
    $rooms = $db->query("SELECT * FROM rooms ORDER BY id")->fetchAll();
    
    echo "<h2>🏛️ Pembersihan Ruangan</h2>";
    echo "<p>Ruangan yang tersedia saat ini:</p><ol>";
    foreach ($rooms as $r) {
        echo "<li><strong>{$r['name']}</strong> (ID: {$r['id']})</li>";
    }
    echo "</ol><hr>";

    // Keep only the first 2 rooms (Ruang Baca Utama & Ruang Diskusi 1)
    $keep = $db->query("SELECT id FROM rooms ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($keep) < 2) {
        echo "<p style='color:orange'>⚠️ Hanya ada " . count($keep) . " ruangan, tidak ada yang perlu dihapus.</p>";
    } else {
        $placeholders = implode(',', array_fill(0, count($keep), '?'));
        
        // Delete bookings for rooms that will be removed
        $stmt = $db->prepare("DELETE FROM room_bookings WHERE room_id NOT IN ($placeholders)");
        $stmt->execute($keep);
        $deletedBookings = $stmt->rowCount();
        
        // Delete rooms
        $stmt = $db->prepare("DELETE FROM rooms WHERE id NOT IN ($placeholders)");
        $stmt->execute($keep);
        $deletedRooms = $stmt->rowCount();
        
        echo "<p style='color:#059669'>✅ Berhasil menghapus <strong>$deletedRooms ruangan</strong> dan <strong>$deletedBookings booking</strong> terkait.</p>";
        
        // Show remaining rooms
        $remaining = $db->query("SELECT * FROM rooms ORDER BY id")->fetchAll();
        echo "<p>Ruangan yang tersisa:</p><ol>";
        foreach ($remaining as $r) {
            echo "<li style='color:#059669'><strong>{$r['name']}</strong> (Kapasitas: {$r['capacity']})</li>";
        }
        echo "</ol>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='admin/ruangan.php' style='background:#FF4D6D;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;'>→ Kembali ke Admin Ruangan</a></p>";
