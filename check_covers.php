<?php
require_once 'config.php';
$db = getDB();
$books = $db->query("SELECT id, judul, cover_image FROM books")->fetchAll();
echo "<pre>";
foreach ($books as $b) {
    echo "ID={$b['id']} | cover_image=" . ($b['cover_image'] ?: '(kosong)') . " | {$b['judul']}\n";
}
echo "</pre>";
