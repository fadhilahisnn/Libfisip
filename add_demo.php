<?php
require 'config.php';
$db = getDB();
$pw = password_hash('password123', PASSWORD_DEFAULT);
$db->exec("INSERT IGNORE INTO users (nama, nim, prodi, email, password, role) VALUES ('Mahasiswa Demo', '071911133090', 'Ilmu Komunikasi', 'mahasiswa@demo.com', '$pw', 'mahasiswa')");
echo 'Demo user added.';
