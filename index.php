<?php
session_start();
require_once 'config.php';
require_once 'includes/cover_helper.php';
require_once 'includes/lang.php';

$pageTitle = t('nav_home', 'Beranda');
$db = getDB();

// Stats
$totalBooks = $db->query("SELECT COUNT(*) FROM books")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn();
$totalReviews = $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn();

// Buku terbaru
$books = $db->query("SELECT b.*, COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as total_reviews 
    FROM books b LEFT JOIN reviews r ON b.id=r.book_id 
    GROUP BY b.id ORDER BY b.created_at DESC LIMIT 8")->fetchAll();

// (Editor's Pick replaced by weekly recommendation carousel in the template below)

// Review terpopuler
$reviews = $db->query("SELECT r.*, u.nama, u.prodi, u.nim, b.judul, b.pengarang, b.color1, b.color2, b.color3,
    (SELECT COUNT(*) FROM review_likes l WHERE l.review_id=r.id) as total_likes
    FROM reviews r 
    JOIN users u ON r.user_id=u.id 
    JOIN books b ON r.book_id=b.id 
    ORDER BY total_likes DESC LIMIT 3")->fetchAll();


include 'includes/header.php';
?>
<!-- LAYANAN INFORMASI STRIP -->
<?php
// === Bangun pesan informasi dinamis ===
$infoMessages = [];

// 1. Jam operasional RBC
$dayOfWeek = (int)date('N'); // 1=Senin, 7=Minggu
if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
    $infoMessages[] = ['icon' => '🕐', 'text' => t('info_open_weekday', 'Ruang Baca FISIP buka hari ini pukul 08.00 – 16.30 WIB')];
} elseif ($dayOfWeek == 6) {
    $infoMessages[] = ['icon' => '🕐', 'text' => t('info_open_saturday', 'Ruang Baca FISIP buka hari Sabtu pukul 08.00 – 12.00 WIB')];
} else {
    $infoMessages[] = ['icon' => '🚫', 'text' => t('info_closed_sunday', 'Ruang Baca FISIP tutup hari Minggu — Sampai jumpa hari Senin!')];
}

// 2. Ucapan hari-hari tertentu
$today = date('m-d');
$month = (int)date('m');
$day = (int)date('d');
$year = (int)date('Y');

// Special day messages with translation support
$hariKhususId = [
    '01-01' => ['🎆', t('new_year', 'Selamat Tahun Baru') . ' ' . $year . '! ' . ($currentLang === 'en' ? 'Happy New Year!' : 'Semoga tahun ini penuh prestasi.')],
    '02-14' => ['💝', t('valentine', 'Happy Valentine\'s Day! Cintai buku, cintai ilmu.')],
    '03-08' => ['💐', t('womens_day', 'Selamat Hari Perempuan Internasional! Perempuan hebat, FISIP kuat.')],
    '04-21' => ['🌸', t('kartini_day', 'Selamat Hari Kartini! Habis gelap terbitlah terang.')],
    '05-01' => ['✊', t('labor_day', 'Selamat Hari Buruh Internasional! Semangat berkarya.')],
    '05-02' => ['📚', t('education_day', 'Selamat Hari Pendidikan Nasional! Ilmu adalah jendela dunia.')],
    '05-20' => ['🏥', t('national_awakening', 'Selamat Hari Kebangkitan Nasional! Bangkit bersama untuk Indonesia.')],
    '06-01' => ['🌏', t('pancasila_day', 'Selamat Hari Pancasila! Pancasila sebagai pemersatu bangsa.')],
    '08-17' => ['🇮🇩', t('independence_day', 'Dirgahayu Republik Indonesia!') . ' ke-' . ($year - 1945) . '! Merdeka!'],
    '10-28' => ['🤝', t('youth_pledge', 'Selamat Hari Sumpah Pemuda! Satu nusa, satu bangsa, satu bahasa.')],
    '11-10' => ['🎖️', t('heroes_day', 'Selamat Hari Pahlawan! Mengenang jasa para pahlawan bangsa.')],
    '12-22' => ['🎄', t('year_end', 'Selamat menyambut akhir tahun! Tetap semangat menyelesaikan tugas.')],
    '12-25' => ['🎄', t('christmas', 'Selamat Hari Natal bagi yang merayakan! Damai di hati, damai di bumi.')],
];

if (isset($hariKhususId[$today])) {
    $infoMessages[] = ['icon' => $hariKhususId[$today][0], 'text' => $hariKhususId[$today][1]];
}

// 3. Menjelang UAS (perkiraan minggu UAS: minggu ke-23–25 dan 49–51)
$weekNum = (int)date('W');
$uasPeriods = [
    [21, 25], // UAS semester genap (sekitar Mei-Juni)
    [47, 51], // UAS semester ganjil (sekitar Nov-Des)
];
foreach ($uasPeriods as $period) {
    if ($weekNum >= $period[0] && $weekNum <= $period[1]) {
        $uasMessages = [
            ['icon' => '📝', 'text' => t('info_uas_near', 'Masa UAS semakin dekat! Manfaatkan koleksi Ruang Baca untuk persiapan ujian.')],
            ['icon' => '💪', 'text' => t('info_uas_spirit', 'Semangat UAS! Belajar teratur, istirahat cukup, insyaAllah sukses.')],
            ['icon' => '🎯', 'text' => t('info_uas_prepare', 'Persiapan UAS? Kunjungi Ruang Baca FISIP untuk referensi terlengkap!')],
        ];
        $infoMessages[] = $uasMessages[array_rand($uasMessages)];
        break;
    }
}

// Pesan umum tambahan
$infoMessages[] = ['icon' => '📖', 'text' => t('info_loan_period', 'Layanan peminjaman buku maksimal 7 hari. Perpanjang sebelum jatuh tempo!')];
$infoMessages[] = ['icon' => '📍', 'text' => t('info_location', 'Ruang Baca FISIP — Lt.2 Gedung A201 FISIP UNAIR, Surabaya')];
?>
<div class="trending-strip">
  <div class="strip-inner">
    <?php for($i=0;$i<3;$i++): foreach($infoMessages as $msg): ?>
      <span class="strip-item"><?= $msg['icon'] ?> <?= htmlspecialchars($msg['text']) ?></span>
      <span class="strip-dot">✦</span>
    <?php endforeach; endfor; ?>
  </div>
</div>

<!-- HERO -->
<div class="hero modern-hero">
  <!-- Decorative background elements -->
  <div class="hero-bg-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
  </div>


  <div class="hero-content">
    <div class="hero-tag"><?= t('hero_tag', '✦ Perpustakaan Digital FISIP UNAIR') ?></div>
    

    <h1 class="hero-title">
      <?= $currentLang === 'en' ? t('hero_title_en') : t('hero_title_1') ?>
    </h1>
    <p class="hero-sub"><?= t('hero_sub', 'Akses ribuan koleksi buku, jurnal, dan referensi akademik Ruang Baca FISIP Universitas Airlangga. Review, pinjam, dan diskusi — semuanya di satu tempat.') ?></p>
  </div>

  <!-- CSS 3D Bookshelf Aesthetic -->
  <div class="css-bookshelf-wrapper">
    <!-- Shelf 1 (Top) -->
    <div class="shelf-row">
      <div class="css-book b-c1 bk-tall"></div>
      <div class="css-book b-c3 bk-med"></div>
      <div class="css-book b-c6 bk-tall bk-wide" data-label="Buku Referensi"></div>
      <div class="css-book b-c2 bk-short"></div>
      <div class="css-book b-c4 bk-med bk-lean-r"></div>
      <div class="bk-stack-wrap">
        <div class="bk-stacked b-c5"></div>
        <div class="bk-stacked b-c7" data-label="Ilmu Politik"></div>
        <div class="bk-stacked b-c1"></div>
        <div class="bk-stacked b-c8"></div>
      </div>
      <div class="css-book b-c3 bk-tall"></div>
      <div class="css-book b-c4 bk-short bk-wide"></div>
      <div class="css-book b-c7 bk-med"></div>
      <div class="css-book b-c2 bk-tall bk-lean-l" data-label="Sosiologi Klasik"></div>
      <div class="css-book b-c1 bk-med bk-thin"></div>
      <div class="css-book b-c8 bk-tall"></div>
      <div class="css-book b-c6 bk-short"></div>
      <div class="css-book b-c5 bk-med bk-wide"></div>
      <div class="css-book b-c2 bk-tall"></div>
      <div class="css-book b-c4 bk-short"></div>
      <div class="css-book b-c7 bk-tall bk-lean-r"></div>
      <div class="css-book b-c3 bk-med"></div>
      <div class="css-book b-c1 bk-short bk-wide" data-label="Jurnal Internasional"></div>
      <div class="bk-stack-wrap">
        <div class="bk-stacked b-c6"></div>
        <div class="bk-stacked b-c2"></div>
        <div class="bk-stacked b-c4"></div>
      </div>
      <div class="css-book b-c8 bk-tall"></div>
      <div class="css-book b-c5 bk-med"></div>
      <div class="css-book b-c1 bk-short"></div>
      
      <!-- Duplicate to ensure it fills wide screens -->
      <div class="css-book b-c4 bk-med" style="margin-left: 20px;"></div>
      <div class="css-book b-c2 bk-tall bk-wide"></div>
      <div class="css-book b-c8 bk-short"></div>
      <div class="bk-stack-wrap">
        <div class="bk-stacked b-c1"></div>
        <div class="bk-stacked b-c3" data-label="Filsafat Ilmu"></div>
      </div>
      <div class="css-book b-c7 bk-tall bk-lean-r" data-label="Skripsi Mahasiswa"></div>
      <div class="css-book b-c6 bk-short"></div>
      <div class="css-book b-c5 bk-med"></div>
      <div class="css-book b-c1 bk-tall bk-wide"></div>
      <div class="css-book b-c3 bk-short"></div>
      <div class="css-book b-c4 bk-tall"></div>
      <div class="css-book b-c2 bk-med bk-lean-l"></div>
      <div class="css-book b-c8 bk-tall"></div>
      <div class="css-book b-c1 bk-short"></div>
      <div class="css-book b-c7 bk-med bk-wide"></div>
      <div class="bk-stack-wrap">
        <div class="bk-stacked b-c4"></div>
        <div class="bk-stacked b-c6" data-label="Metodologi Penelitian"></div>
        <div class="bk-stacked b-c2"></div>
        <div class="bk-stacked b-c5"></div>
        <div class="bk-stacked b-c3"></div>
      </div>
      <div class="css-book b-c8 bk-med"></div>
      <div class="css-book b-c1 bk-tall"></div>
      <div class="css-book b-c4 bk-short bk-lean-r"></div>
      <div class="css-book b-c7 bk-med"></div>
      <div class="css-book b-c2 bk-tall bk-wide" data-label="Hubungan Internasional"></div>
      <div class="css-book b-c3 bk-short"></div>
    </div>
    

  </div>
</div>

<main>
<div class="modern-stats">
  <div class="stat-card">
    <div class="stat-icon">📚</div>
    <div class="stat-info">
        <div class="stat-num" style="color:var(--accent);"><?= number_format($totalBooks) ?>+</div>
        <div class="stat-label"><?= t('stat_books', 'KOLEKSI BUKU') ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-info">
        <div class="stat-num" style="color:var(--accent2);"><?= number_format($totalUsers) ?>+</div>
        <div class="stat-label"><?= t('stat_users', 'ANGGOTA AKTIF') ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✍️</div>
    <div class="stat-info">
        <div class="stat-num" style="color:var(--accent3);"><?= number_format($totalReviews) ?>+</div>
        <div class="stat-label"><?= t('stat_reviews', 'REVIEW DITULIS') ?></div>
    </div>
  </div>
</div>

<!-- REKOMENDASI MINGGU INI — 3D Carousel -->
<?php
// Ambil buku untuk rekomendasi mingguan (rotasi berdasarkan minggu)
$weekNum = (int)date('W');
$yearWeekSeed = (int)date('Y') * 100 + $weekNum;
$allReko = $db->query("SELECT b.*, COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as total_reviews
    FROM books b LEFT JOIN reviews r ON b.id=r.book_id
    GROUP BY b.id HAVING avg_rating >= 3
    ORDER BY b.id")->fetchAll();

// Fallback: jika kurang dari 3 buku berrating >= 3, ambil semua buku
if (count($allReko) < 3) {
    $allReko = $db->query("SELECT b.*, COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as total_reviews
        FROM books b LEFT JOIN reviews r ON b.id=r.book_id
        GROUP BY b.id ORDER BY b.id")->fetchAll();
}

// Shuffle deterministically based on week
if (count($allReko) > 0) {
    mt_srand($yearWeekSeed);
    $keys = array_keys($allReko);
    for ($i = count($keys) - 1; $i > 0; $i--) {
        $j = mt_rand(0, $i);
        [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
    }
    $rekoBooks = [];
    foreach (array_slice($keys, 0, 3) as $k) {
        $rekoBooks[] = $allReko[$k];
    }
    mt_srand(); // Reset seed
} else {
    $rekoBooks = [];
}

// Fallback cover images
$fallbackCovers = [
    '/libfisip/assets/rekomendasi/book_politik.png',
    '/libfisip/assets/rekomendasi/book_komunikasi.png',
    '/libfisip/assets/rekomendasi/book_sosiologi.png',
];

$rekoGradients = [
    'linear-gradient(135deg, #1a0533 0%, #590D22 100%)',
    'linear-gradient(135deg, #0c2340 0%, #1b4d6e 100%)',
    'linear-gradient(135deg, #0d4f34 0%, #1a7a5c 100%)',
];
?>
<?php if (count($rekoBooks) > 0): ?>
<div class="section reko-section">
  <div class="sec-header">
    <h2 class="sec-title"><?= t('reko_section_title', '✦ Rekomendasi Minggu Ini') ?></h2>
    <div class="reko-week-badge">
      <span class="reko-week-icon">📅</span>
      <span><?= t('reko_week_badge', 'Minggu ke-') ?><?= $weekNum ?> · <?= date('F Y') ?></span>
    </div>
  </div>

  <div class="reko-carousel-wrap">
    <!-- Background glow -->
    <div class="reko-bg-glow"></div>

    <div class="swiper reko-swiper">
      <div class="swiper-wrapper">
        <?php foreach($rekoBooks as $ri => $rBook):
          $rCover = bookCoverSrc($rBook);
          $gradient = $rekoGradients[$ri % count($rekoGradients)];
        ?>
        <div class="swiper-slide">
          <div class="reko-slide-inner swiper-carousel-animate-opacity">
            <!-- Cover side -->
            <a href="search.php?detail=<?= $rBook['id'] ?>" class="reko-slide-cover" style="display: block;">
              <?php if ($rCover): ?>
                <img src="<?= $rCover ?>" alt="<?= htmlspecialchars($rBook['judul']) ?>">
              <?php else: ?>
                <div class="reko-cover-fallback" style="background:<?= $gradient ?>;">
                  <span><?= htmlspecialchars(substr($rBook['judul'], 0, 40)) ?></span>
                </div>
              <?php endif; ?>
              <div class="reko-cover-shine"></div>
            </a>
            <!-- Content side -->
            <div class="reko-slide-content">
              <div class="reko-label"><?= t('reko_label', '📌 Pilihan Minggu Ini') ?></div>
              <h3 class="reko-title"><?= htmlspecialchars($rBook['judul']) ?></h3>
              <p class="reko-desc"><?= htmlspecialchars($rBook['deskripsi'] ?: t('reko_default_desc', 'Koleksi pilihan terbaik Ruang Baca FISIP UNAIR minggu ini.')) ?></p>
              <div class="reko-meta-row">
                <div class="reko-author-info">
                  <div class="reko-author-avatar"><?= strtoupper(substr($rBook['pengarang'],0,2)) ?></div>
                  <div>
                    <div class="reko-author-name"><?= htmlspecialchars($rBook['pengarang']) ?></div>
                    <div class="reko-author-cat"><?= htmlspecialchars($rBook['kategori']) ?></div>
                  </div>
                </div>
                <div class="reko-rating">
                  <span class="reko-stars"><?= stars((int)round($rBook['avg_rating'])) ?></span>
                  <span class="reko-rating-num"><?= number_format($rBook['avg_rating'],1) ?></span>
                </div>
              </div>
              <div class="reko-actions">
                <?= $rBook['tersedia']>0 ? '<span class="status-badge status-available">' . t('status_available', '● Tersedia') . '</span>' : '<span class="status-badge status-borrowed">' . t('status_borrowed', '● Dipinjam') . '</span>' ?>
                <?php if (isLoggedIn()): ?>
                  <a href="profile.php?pinjam=<?= $rBook['id'] ?>" class="reko-btn-pinjam"><?= t('btn_borrow', '📚 Pinjam Sekarang') ?></a>
                <?php else: ?>
                  <a href="login.php" class="reko-btn-pinjam"><?= t('btn_login_borrow', '✨ Masuk & Pinjam') ?></a>
                <?php endif; ?>
                <a href="search.php?detail=<?= $rBook['id'] ?>" class="reko-btn-detail"><?= t('btn_detail', 'Lihat Detail →') ?></a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Navigation -->
    <div class="reko-nav">
      <button class="reko-nav-btn reko-prev" aria-label="Previous">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div class="reko-pagination"></div>
      <button class="reko-nav-btn reko-next" aria-label="Next">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  new Swiper('.reko-swiper', {
    effect: 'coverflow',
    grabCursor: true,
    centeredSlides: true,
    slidesPerView: 'auto',
    initialSlide: 1,
    loop: true,
    speed: 800,
    coverflowEffect: {
      rotate: 0,
      stretch: 80,
      depth: 200,
      modifier: 1,
      slideShadows: true,
    },
    autoplay: {
      delay: 5000,
      disableOnInteraction: false,
      pauseOnMouseEnter: true,
    },
    pagination: {
      el: '.reko-pagination',
      clickable: true,
      renderBullet: function(index, className) {
        return '<span class="' + className + ' reko-bullet"></span>';
      }
    },
    navigation: {
      nextEl: '.reko-next',
      prevEl: '.reko-prev',
    },
    on: {
      slideChangeTransitionStart: function() {
        var slides = document.querySelectorAll('.reko-swiper .swiper-slide');
        slides.forEach(function(slide) {
          var inner = slide.querySelector('.swiper-carousel-animate-opacity');
          if (inner) inner.style.opacity = '0.4';
        });
      },
      slideChangeTransitionEnd: function() {
        var active = document.querySelector('.reko-swiper .swiper-slide-active .swiper-carousel-animate-opacity');
        if (active) active.style.opacity = '1';
      },
      init: function() {
        var slides = document.querySelectorAll('.reko-swiper .swiper-slide');
        slides.forEach(function(slide) {
          var inner = slide.querySelector('.swiper-carousel-animate-opacity');
          if (inner) inner.style.opacity = slide.classList.contains('swiper-slide-active') ? '1' : '0.4';
        });
      }
    }
  });
});
</script>
<?php endif; ?>

<!-- KOLEKSI TERBARU - GenZ Vibes -->
<div class="section koleksi-section" style="padding-top:0;">
  <div class="sec-header">
    <h2 class="sec-title"><?= t('fresh_drop_title', '🔥 Fresh Drop') ?></h2>
    <a class="sec-link glow-link" href="search.php"><?= t('explore_all', 'Explore all →') ?></a>
  </div>
  <div class="koleksi-scroll-wrapper">
    <div class="koleksi-scroll" id="koleksiScroll">
      <?php foreach($books as $i => $b): 
        $gradients = [
          'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
          'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
          'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
          'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
          'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
          'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
          'linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)',
          'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)',
        ];
        $gradient = $gradients[$i % count($gradients)];
        $emojis = ['📖','📚','✨','🎯','💡','🔮','🌟','⚡'];
        $emoji = $emojis[$i % count($emojis)];
      ?>
      <a class="koleksi-card" href="search.php?detail=<?= $b['id'] ?>" style="--card-gradient: <?= $gradient ?>;">
        <div class="koleksi-card-visual">
          <?php $covSrc = bookCoverSrc($b); ?>
          <?php if ($covSrc): ?>
          <div class="koleksi-cover-img">
            <img src="<?= $covSrc ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
          </div>
          <?php else: ?>
          <div class="koleksi-cover-gen" style="background:<?= $gradient ?>;">
            <span class="koleksi-cover-text"><?= htmlspecialchars(substr($b['judul'],0,30)) ?></span>
          </div>
          <?php endif; ?>
          <div class="koleksi-card-emoji"><?= $emoji ?></div>
          <?php if($b['tersedia']>0): ?>
            <div class="koleksi-badge-avail"><?= t('available_badge', 'Available') ?></div>
          <?php else: ?>
            <div class="koleksi-badge-out"><?= t('borrowed_badge', 'Dipinjam') ?></div>
          <?php endif; ?>
        </div>
        <div class="koleksi-card-body">
          <div class="koleksi-category"><?= htmlspecialchars($b['kategori']) ?></div>
          <div class="koleksi-title"><?= htmlspecialchars($b['judul']) ?></div>
          <div class="koleksi-author">by <?= htmlspecialchars($b['pengarang']) ?></div>
          <div class="koleksi-card-footer">
            <div class="koleksi-rating">
              <span class="koleksi-stars"><?= stars((int)round($b['avg_rating'])) ?></span>
              <span class="koleksi-rating-num"><?= number_format($b['avg_rating'],1) ?></span>
            </div>
            <span class="koleksi-reviews"><?= $b['total_reviews'] ?> 💬</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <button class="scroll-btn scroll-left" onclick="document.getElementById('koleksiScroll').scrollBy({left:-320,behavior:'smooth'})" aria-label="Scroll left">‹</button>
    <button class="scroll-btn scroll-right" onclick="document.getElementById('koleksiScroll').scrollBy({left:320,behavior:'smooth'})" aria-label="Scroll right">›</button>
  </div>
</div>

<!-- REVIEW TERPOPULER -->
<?php if($reviews): ?>
<div class="section" style="padding-top:0;">
  <div class="sec-header">
    <h2 class="sec-title"><?= t('popular_reviews_title', '💬 Review Terpopuler') ?></h2>
    <a class="sec-link" href="booktalk.php"><?= t('view_all', 'Lihat semua →') ?></a>
  </div>
  <div class="reviews-grid">
    <?php foreach($reviews as $rv): 
      $initials = strtoupper(implode('',array_map(fn($w)=>$w[0],explode(' ',trim($rv['nama'])))));
      $initials = substr($initials,0,2);
      $tags = $rv['tags'] ? explode(',',$rv['tags']) : [];
    ?>
    <div class="review-card">
      <div class="review-header">
        <div class="reviewer-info">
          <div class="avatar" style="background:rgba(255,77,109,0.15);color:var(--accent);"><?= $initials ?></div>
          <div>
            <div class="reviewer-name"><?= htmlspecialchars($rv['nama']) ?></div>
            <div class="reviewer-meta"><?= htmlspecialchars($rv['prodi']) ?> · <?= timeAgo($rv['created_at']) ?></div>
          </div>
        </div>
        <a href="search.php?detail=<?= $rv['book_id'] ?>" class="review-book-mini" style="text-decoration:none; color:inherit; display:flex; align-items:center; gap:10px; cursor:pointer;">
          <div class="mini-cover" style="background:linear-gradient(135deg,<?= $rv['color1'] ?>,<?= $rv['color2'] ?>);color:<?= $rv['color3'] ?>;"><?= htmlspecialchars(substr($rv['judul'],0,20)) ?></div>
          <div>
            <div class="review-book-title"><?= htmlspecialchars($rv['judul']) ?></div>
            <div class="review-book-author"><?= htmlspecialchars($rv['pengarang']) ?></div>
            <div style="color:var(--accent2);font-size:0.85rem;"><?= stars($rv['rating']) ?></div>
          </div>
        </a>
      </div>
      <div class="review-body"><?= nl2br(htmlspecialchars(substr($rv['isi'],0,200))) ?>...</div>
      <div class="review-footer">
        <div class="review-tags"><?php foreach(array_slice($tags,0,3) as $tg): ?><span class="tag"><?= htmlspecialchars(trim($tg)) ?></span><?php endforeach; ?></div>
        <div class="review-likes">❤️ <span><?= $rv['total_likes'] ?></span></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

</main>
<?php include 'includes/footer.php'; ?>