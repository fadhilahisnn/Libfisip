<?php
// includes/footer.php

// Include language system if not already included
if (!isset($currentLang)) {
    require_once __DIR__ . '/lang.php';
}
?>
<footer class="footer">
    <div class="footer-content">
        <div class="footer-brand">
            <h3><?= t('footer_brand', 'Ruang Baca FISIP UNAIR') ?></h3>
            <p><?= t('footer_desc', 'Platform perpustakaan digital untuk mahasiswa dan civitas akademika FISIP Universitas Airlangga. Temukan, pinjam, dan diskusikan koleksi terbaik kami dengan gaya modern.') ?></p>
        </div>
        <div class="footer-links">
            <h4><?= t('footer_quick_links', 'Tautan Cepat') ?></h4>
            <a href="index.php"><?= t('nav_home', 'Beranda') ?></a>
            <a href="search.php"><?= t('nav_collection', 'Cari Koleksi') ?></a>
            <a href="booktalk.php"><?= t('nav_book_talk', 'Book Talk') ?></a>
            <a href="usulan.php"><?= t('nav_suggestion', 'Usulan Koleksi') ?></a>
        </div>
        <div class="footer-contact">
            <h4><?= t('footer_contact', 'Hubungi Kami') ?></h4>
            <p>Gedung A FISIP UNAIR<br>Jl. Dharmawangsa Dalam Selatan, Surabaya</p>
            <p>Email: perpus@fisip.unair.ac.id</p>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> <?= t('footer_copyright', 'Ruang Baca FISIP Universitas Airlangga. Hak Cipta Dilindungi.') ?>
    </div>
</footer>

<!-- Accessibility Widget -->
<script src="assets/accessibility.js?v=<?= filemtime('assets/accessibility.js') ?>"></script>

<!-- Chatbot Widget -->
<script src="assets/chatbot.js?v=<?= filemtime('assets/chatbot.js') ?>"></script>

</body>
</html>