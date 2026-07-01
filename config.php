<?php
// Koneksi Database dengan PDO (Sesuai kebutuhan file PHP Anda)
function getDB() {
    $host = 'localhost';
    $db   = 'libfisip';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage() . ". Pastikan Anda sudah membuat database 'libfisip' di phpMyAdmin.");
    }
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function currentUser() {
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function redirect($url, $message = '', $type = 'success') {
    if ($message) {
        $_SESSION['flash_msg'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit;
}

// Konstanta Sirkulasi
define('MAKS_PINJAM', 3);
define('LAMA_PINJAM', 7);
define('MAKS_PERPANJANG', 1);
define('DENDA_PER_BUKU', 3000);

function stars($rating) {
    $rating = (int) $rating;
    if ($rating < 0) $rating = 0;
    if ($rating > 5) $rating = 5;
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

function timeAgo($datetime) {
    global $currentLang;
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if (isset($currentLang) && $currentLang === 'en') {
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff / 60) . ' mins ago';
        if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
        if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
        if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
        return floor($diff / 31536000) . ' years ago';
    }
    
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit yang lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam yang lalu';
    if ($diff < 2592000) return floor($diff / 86400) . ' hari yang lalu';
    if ($diff < 31536000) return floor($diff / 2592000) . ' bulan yang lalu';
    return floor($diff / 31536000) . ' tahun yang lalu';
}
?>
