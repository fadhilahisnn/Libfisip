<?php
require 'config.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE fisip_repository ADD COLUMN semester VARCHAR(20) DEFAULT 'Genap'");
    $db->exec("ALTER TABLE fisip_repository ADD COLUMN jenis_tugas_akhir VARCHAR(50) DEFAULT 'Skripsi'");
    echo "Columns added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
