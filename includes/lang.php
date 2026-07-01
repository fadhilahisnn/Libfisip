<?php
// includes/lang.php - Language Management System

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Available languages
define('AVAILABLE_LANGS', ['id', 'en']);
define('DEFAULT_LANG', 'id');

// Detect and set language
function getCurrentLang() {
    // Check URL parameter first
    if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGS)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    
    // Check session
    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], AVAILABLE_LANGS)) {
        return $_SESSION['lang'];
    }
    
    // Check browser preference
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (in_array($browserLang, AVAILABLE_LANGS)) {
            return $browserLang;
        }
    }
    
    return DEFAULT_LANG;
}

$currentLang = getCurrentLang();

// Language translations
$lang = [
    'id' => [
        // Navigation
        'nav_home' => 'Beranda',
        'nav_collection' => 'Koleksi',
        'nav_borrow_room' => 'Pinjam Ruang',
        'nav_book_talk' => 'Book Talk',
        'nav_suggestion' => 'Usulan',
        'nav_my_shelf' => 'Rak Buku Saya',
        'nav_login' => 'Masuk',
        'nav_logout' => 'Keluar',
        'nav_services' => 'Layanan Pustaka',
        'nav_about' => 'Tentang Kami',
        'nav_about_profile' => 'Profil & Identitas',
        'nav_about_vision' => 'Visi dan Misi',
        'nav_about_structure' => 'Struktur Organisasi',
        
        // Header
        'header_theme_tooltip' => 'Mode Terang',
        'header_theme_dark_tooltip' => 'Mode Gelap Aktif',
        'header_theme_light_tooltip' => 'Mode Terang Aktif',
        
        // Language Switcher
        'lang_switch' => 'Bahasa',
        'lang_indonesian' => 'Bahasa Indonesia',
        'lang_english' => 'English',
        
        // Hero Section
        'hero_tag' => '✦ Perpustakaan Digital FISIP UNAIR',
        'hero_title_1' => 'Temukan <em>cerita</em><br>di setiap <span class="outline">halaman.</span>',
        'hero_title_en' => 'Discover <em>stories</em><br>in every <span class="outline">page.</span>',
        'hero_sub' => 'Akses ribuan koleksi buku, jurnal, dan referensi akademik Ruang Baca FISIP Universitas Airlangga. Review, pinjam, dan diskusi — semuanya di satu tempat.',
        'hero_cta_search' => '🔍 Cari Koleksi',
        'hero_cta_booktalk' => '💬 Lihat Book Talk',
        
        // Stats
        'stat_books' => 'KOLEKSI BUKU',
        'stat_users' => 'ANGGOTA AKTIF',
        'stat_reviews' => 'REVIEW DITULIS',
        
        // Info Messages
        'info_open_weekday' => 'Ruang Baca FISIP buka hari ini pukul 08.00 – 16.30 WIB',
        'info_open_saturday' => 'Ruang Baca FISIP buka hari Sabtu pukul 08.00 – 12.00 WIB',
        'info_closed_sunday' => 'Ruang Baca FISIP tutup hari Minggu — Sampai jumpa hari Senin!',
        'info_loan_period' => 'Layanan peminjaman buku maksimal 7 hari. Perpanjang sebelum jatuh tempo!',
        'info_location' => 'Ruang Baca FISIP — Lt.2 Gedung A201 FISIP UNAIR, Surabaya',
        'info_uas_near' => 'Masa UAS semakin dekat! Manfaatkan koleksi Ruang Baca untuk persiapan ujian.',
        'info_uas_spirit' => 'Semangat UAS! Belajar teratur, istirahat cukup, insyaAllah sukses.',
        'info_uas_prepare' => 'Persiapan UAS? Kunjungi Ruang Baca FISIP untuk referensi terlengkap!',
        
        // Special Days
        'new_year' => 'Selamat Tahun Baru',
        'valentine' => 'Happy Valentine\'s Day! Cintai buku, cintai ilmu.',
        'womens_day' => 'Selamat Hari Perempuan Internasional! Perempuan hebat, FISIP kuat.',
        'kartini_day' => 'Selamat Hari Kartini! Habis gelap terbitlah terang.',
        'labor_day' => 'Selamat Hari Buruh Internasional! Semangat berkarya.',
        'education_day' => 'Selamat Hari Pendidikan Nasional! Ilmu adalah jendela dunia.',
        'national_awakening' => 'Selamat Hari Kebangkitan Nasional! Bangkit bersama untuk Indonesia.',
        'pancasila_day' => 'Selamat Hari Pancasila! Pancasila sebagai pemersatu bangsa.',
        'independence_day' => 'Dirgahayu Republik Indonesia!',
        'youth_pledge' => 'Selamat Hari Sumpah Pemuda! Satu nusa, satu bangsa, satu bahasa.',
        'heroes_day' => 'Selamat Hari Pahlawan! Mengenang jasa para pahlawan bangsa.',
        'christmas' => 'Selamat Hari Natal bagi yang merayakan! Damai di hati, damai di bumi.',
        'year_end' => 'Selamat menyambut akhir tahun! Tetap semangat menyelesaikan tugas.',
        
        // Weekly Recommendation
        'reko_section_title' => '✦ Rekomendasi Minggu Ini',
        'reko_week_badge' => 'Minggu ke-',
        'reko_label' => '📌 Pilihan Minggu Ini',
        'reko_default_desc' => 'Koleksi pilihan terbaik Ruang Baca FISIP UNAIR minggu ini.',
        'status_available' => '● Tersedia',
        'status_borrowed' => '● Dipinjam',
        'btn_borrow' => '📚 Pinjam Sekarang',
        'btn_login_borrow' => '✨ Masuk & Pinjam',
        'btn_detail' => 'Lihat Detail →',
        
        // New Collection Section
        'fresh_drop_title' => '🔥 Fresh Drop',
        'explore_all' => 'Explore all →',
        'available_badge' => 'Available',
        'borrowed_badge' => 'Dipinjam',
        
        // Reviews Section
        'popular_reviews_title' => '💬 Review Terpopuler',
        'view_all' => 'Lihat semua →',
        
        // Footer
        'footer_brand' => 'Ruang Baca FISIP UNAIR',
        'footer_desc' => 'Platform perpustakaan digital untuk mahasiswa dan civitas akademika FISIP Universitas Airlangga. Temukan, pinjam, dan diskusikan koleksi terbaik kami dengan gaya modern.',
        'footer_quick_links' => 'Tautan Cepat',
        'footer_contact' => 'Hubungi Kami',
        'footer_copyright' => 'Ruang Baca FISIP Universitas Airlangga. Hak Cipta Dilindungi.',
        
        // Search Page
        'search_title' => 'Cari Koleksi',
        'search_placeholder' => 'Cari judul, pengarang, atau kata kunci...',
        'search_placeholder_header' => 'Ketik kata kunci pencarian...',
        'search_button' => 'Cari',
        'search_filters' => 'Filter',
        'search_category' => 'Kategori',
        'search_all_categories' => 'Semua Kategori',
        'search_sort' => 'Urutkan',
        'search_sort_newest' => 'Terbaru',
        'search_sort_oldest' => 'Terlama',
        'search_sort_title' => 'Judul A-Z',
        'search_sort_rating' => 'Rating Tertinggi',
        'search_no_results' => 'Tidak ada hasil yang ditemukan.',
        'search_no_results_desc' => 'Coba dengan kata kunci lain atau filter yang berbeda.',
        'search_results_for' => 'Hasil pencarian untuk:',
        'search_total_results' => 'ditemukan',
        
        // Book Detail
        'book_detail_title' => 'Detail Buku',
        'book_author' => 'Pengarang',
        'book_category' => 'Kategori',
        'book_publisher' => 'Penerbit',
        'book_year' => 'Tahun',
        'book_isbn' => 'ISBN',
        'book_description' => 'Deskripsi',
        'book_rating' => 'Rating',
        'book_availability' => 'Ketersediaan',
        'book_reviews' => 'Review',
        
        // Room Booking
        'room_title' => 'Pinjam Ruang Baca',
        'room_desc' => 'Gunakan fasilitas ruang baca kami untuk kegiatan akademik, diskusi kelompok, atau belajar mandiri.',
        'room_book_now' => 'Booking Sekarang',
        'room_available' => 'Tersedia',
        'room_booked' => 'Sudah Dipesan',
        
        // Messages
        'msg_login_required' => 'Silakan login untuk mengakses fitur ini.',
        'msg_success' => 'Berhasil!',
        'msg_error' => 'Terjadi kesalahan.',
        'msg_confirm' => 'Apakah Anda yakin?',
    ],
    
    'en' => [
        // Navigation
        'nav_home' => 'Home',
        'nav_collection' => 'Collection',
        'nav_borrow_room' => 'Book Room',
        'nav_book_talk' => 'Book Talk',
        'nav_suggestion' => 'Suggestion',
        'nav_my_shelf' => 'My Shelf',
        'nav_login' => 'Login',
        'nav_logout' => 'Logout',
        'nav_services' => 'Library Services',
        'nav_about' => 'About Us',
        'nav_about_profile' => 'Profile & Identity',
        'nav_about_vision' => 'Vision and Mission',
        'nav_about_structure' => 'Organizational Structure',
        
        // Header
        'header_theme_tooltip' => 'Light Mode',
        'header_theme_dark_tooltip' => 'Dark Mode Active',
        'header_theme_light_tooltip' => 'Light Mode Active',
        
        // Language Switcher
        'lang_switch' => 'Language',
        'lang_indonesian' => 'Bahasa Indonesia',
        'lang_english' => 'English',
        
        // Hero Section
        'hero_tag' => '✦ FISIP UNAIR Digital Library',
        'hero_title_1' => 'Temukan <em>cerita</em><br>di setiap <span class="outline">halaman.</span>',
        'hero_title_en' => 'Discover <em>stories</em><br>in every <span class="outline">page.</span>',
        'hero_sub' => 'Access thousands of books, journals, and academic references from FISIP Universitas Airlangga Reading Room. Review, borrow, and discuss — all in one place.',
        'hero_cta_search' => '🔍 Search Collection',
        'hero_cta_booktalk' => '💬 View Book Talk',
        'hero_search_placeholder' => 'Search books, authors, or keywords...',
        'btn_search' => 'Search',
        
        // Stats
        'stat_books' => 'BOOK COLLECTION',
        'stat_users' => 'ACTIVE MEMBERS',
        'stat_reviews' => 'REVIEWS WRITTEN',
        
        // Info Messages
        'info_open_weekday' => 'FISIP Reading Room is open today from 08:00 – 16:30 WIB',
        'info_open_saturday' => 'FISIP Reading Room is open on Saturdays from 08:00 – 12:00 WIB',
        'info_closed_sunday' => 'FISIP Reading Room is closed on Sundays — See you Monday!',
        'info_loan_period' => 'Book loan period is maximum 7 days. Renew before due date!',
        'info_location' => 'FISIP Reading Room — 2nd Floor Building A201 FISIP UNAIR, Surabaya',
        'info_uas_near' => 'Final exams are approaching! Utilize the Reading Room collection for exam preparation.',
        'info_uas_spirit' => 'Good luck with your exams! Study regularly, rest enough, you\'ll do great.',
        'info_uas_prepare' => 'Preparing for finals? Visit FISIP Reading Room for the most complete references!',
        
        // Special Days
        'new_year' => 'Happy New Year',
        'valentine' => 'Happy Valentine\'s Day! Love books, love knowledge.',
        'womens_day' => 'Happy International Women\'s Day! Strong women, strong FISIP.',
        'kartini_day' => 'Happy Kartini Day! From darkness to light.',
        'labor_day' => 'Happy International Workers\' Day! Keep creating.',
        'education_day' => 'Happy National Education Day! Knowledge is the window to the world.',
        'national_awakening' => 'Happy National Awakening Day! Rise together for Indonesia.',
        'pancasila_day' => 'Happy Pancasila Day! Pancasila as the unifier of the nation.',
        'independence_day' => 'Happy Independence Day of Indonesia!',
        'youth_pledge' => 'Happy Youth Pledge Day! One homeland, one nation, one language.',
        'heroes_day' => 'Happy Heroes\' Day! Honoring the services of national heroes.',
        'christmas' => 'Merry Christmas to those who celebrate! Peace in heart, peace on earth.',
        'year_end' => 'Happy year-end! Keep the spirit to finish your assignments.',
        
        // Weekly Recommendation
        'reko_section_title' => '✦ This Week\'s Picks',
        'reko_week_badge' => 'Week ',
        'reko_label' => '📌 This Week\'s Pick',
        'reko_default_desc' => 'The best selected collection from FISIP UNAIR Reading Room this week.',
        'status_available' => '● Available',
        'status_borrowed' => '● Borrowed',
        'btn_borrow' => '📚 Borrow Now',
        'btn_login_borrow' => '✨ Login & Borrow',
        'btn_detail' => 'View Details →',
        
        // New Collection Section
        'fresh_drop_title' => '🔥 Fresh Drop',
        'explore_all' => 'Explore all →',
        'available_badge' => 'Available',
        'borrowed_badge' => 'Borrowed',
        
        // Reviews Section
        'popular_reviews_title' => '💬 Popular Reviews',
        'view_all' => 'View all →',
        
        // Footer
        'footer_brand' => 'FISIP UNAIR Reading Room',
        'footer_desc' => 'Digital library platform for students and academic community of FISIP Universitas Airlangga. Discover, borrow, and discuss our best collections with a modern style.',
        'footer_quick_links' => 'Quick Links',
        'footer_contact' => 'Contact Us',
        'footer_copyright' => 'FISIP UNAIR Reading Room. All Rights Reserved.',
        
        // Search Page
        'search_title' => 'Search Collection',
        'search_placeholder' => 'Search title, author, or keywords...',
        'search_placeholder_header' => 'Type keywords...',
        'search_button' => 'Search',
        'search_filters' => 'Filters',
        'search_category' => 'Category',
        'search_all_categories' => 'All Categories',
        'search_sort' => 'Sort By',
        'search_sort_newest' => 'Newest',
        'search_sort_oldest' => 'Oldest',
        'search_sort_title' => 'Title A-Z',
        'search_sort_rating' => 'Highest Rating',
        'search_no_results' => 'No results found.',
        'search_no_results_desc' => 'Try different keywords or filters.',
        'search_results_for' => 'Search results for:',
        'search_total_results' => 'found',
        
        // Book Detail
        'book_detail_title' => 'Book Details',
        'book_author' => 'Author',
        'book_category' => 'Category',
        'book_publisher' => 'Publisher',
        'book_year' => 'Year',
        'book_isbn' => 'ISBN',
        'book_description' => 'Description',
        'book_rating' => 'Rating',
        'book_availability' => 'Availability',
        'book_reviews' => 'Reviews',
        
        // Room Booking
        'room_title' => 'Book a Reading Room',
        'room_desc' => 'Use our reading room facilities for academic activities, group discussions, or independent study.',
        'room_book_now' => 'Book Now',
        'room_available' => 'Available',
        'room_booked' => 'Booked',
        
        // Messages
        'msg_login_required' => 'Please login to access this feature.',
        'msg_success' => 'Success!',
        'msg_error' => 'An error occurred.',
        'msg_confirm' => 'Are you sure?',
    ],
];

// Get translation helper function
function t($key, $default = '') {
    global $lang, $currentLang;
    
    if (isset($lang[$currentLang][$key])) {
        return $lang[$currentLang][$key];
    }
    
    // Fallback to default language
    if (isset($lang[DEFAULT_LANG][$key])) {
        return $lang[DEFAULT_LANG][$key];
    }
    
    return $default ?: $key;
}

// Get language name
function getLangName($code) {
    global $lang;
    $names = [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
    ];
    return $names[$code] ?? $code;
}

// Get language flag image
function getLangFlag($code) {
    $flags = [
        'id' => '<img src="https://flagcdn.com/w40/id.png" alt="ID" style="width: 24px; height: 16px; object-fit: cover; border-radius: 3px; display: block; border: 1px solid rgba(0,0,0,0.1);">',
        'en' => '<img src="https://flagcdn.com/w40/us.png" alt="US" style="width: 24px; height: 16px; object-fit: cover; border-radius: 3px; display: block; border: 1px solid rgba(0,0,0,0.1);">',
    ];
    return $flags[$code] ?? '';
}

// Check if language switcher should show dropdown
function getOtherLang($current) {
    foreach (AVAILABLE_LANGS as $l) {
        if ($l !== $current) return $l;
    }
    return DEFAULT_LANG;
}

// Build language switch URL
function langUrl($newLang) {
    $uri = $_SERVER['REQUEST_URI'];
    // Remove existing lang parameter
    $uri = preg_replace('/[?&]lang=[a-z]{2}/', '', $uri);
    $separator = strpos($uri, '?') !== false ? '&' : '?';
    return $uri . $separator . 'lang=' . $newLang;
}

// Category translator
function t_cat($cat) {
    global $currentLang;
    if ($currentLang !== 'en') return $cat;
    
    $ddc_translations = [
        '000 - Ilmu Komputer, Informasi, dan Karya Umum' => '000 - Computer Science, Information & General Works',
        '100 - Filsafat dan Psikologi' => '100 - Philosophy & Psychology',
        '200 - Agama' => '200 - Religion',
        '300 - Ilmu Pengetahuan Sosial' => '300 - Social Sciences',
        '400 - Bahasa' => '400 - Language',
        '500 - Sains' => '500 - Science',
        '600 - Teknologi' => '600 - Technology',
        '700 - Kesenian dan Rekreasi' => '700 - Arts & Recreation',
        '800 - Sastra' => '800 - Literature',
        '900 - Sejarah dan Geografi' => '900 - History & Geography'
    ];
    
    return $ddc_translations[$cat] ?? $cat;
}
?>