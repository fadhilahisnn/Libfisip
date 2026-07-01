<?php
session_start();
require_once 'config.php';
require_once 'includes/cover_helper.php';
require_once 'includes/lang.php';
$pageTitle = t('nav_collection', 'Koleksi');
$db = getDB();

$q      = trim($_GET['q'] ?? '');
$kat    = $_GET['kat'] ?? '';
$status = $_GET['status'] ?? '';
$bahasa = $_GET['bahasa'] ?? '';
$sort   = $_GET['sort'] ?? 'terbaru';

$where  = ['1=1'];
$params = [];
if ($q) {
    $where[] = '(b.judul LIKE ? OR b.pengarang LIKE ? OR b.no_kelas LIKE ? OR b.isbn LIKE ?)';
    $params  = array_merge($params, ["%$q%","%$q%","%$q%","%$q%"]);
}
if ($kat) { 
    $firstChar = substr($kat, 0, 1);
    if (is_numeric($firstChar)) {
        $where[] = '(b.kategori = ? OR b.no_kelas LIKE ?)';
        $params[] = $kat;
        $params[] = $firstChar . '%';
    } else {
        $where[] = 'b.kategori = ?'; 
        $params[] = $kat; 
    }
}
if ($bahasa) { $where[] = 'b.bahasa = ?';   $params[] = $bahasa; }
if ($status === 'tersedia') { $where[] = 'b.tersedia > 0'; }
if ($status === 'dipinjam') { $where[] = 'b.id IN (SELECT book_id FROM circulation WHERE status IN (\'dipinjam\',\'terlambat\'))'; }

$orderMap = [
    'terbaru' => 'b.created_at DESC',
    'rating'  => 'avg_rating DESC',
    'populer' => 'total_pinjam DESC',
    'judul'   => 'b.judul ASC',
];
$orderBy = $orderMap[$sort] ?? 'b.created_at DESC';

$sql = "SELECT b.*,
    COALESCE(AVG(r.rating),0) as avg_rating,
    COUNT(DISTINCT r.id) as total_reviews,
    COUNT(DISTINCT c.id) as total_pinjam
    FROM books b
    LEFT JOIN reviews r ON b.id = r.book_id
    LEFT JOIN circulation c ON b.id = c.book_id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY b.id ORDER BY $orderBy";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
  
  $targetCategories = [
      '000 - Ilmu Komputer, Informasi, dan Karya Umum',
      '100 - Filsafat dan Psikologi',
      '200 - Agama',
      '300 - Ilmu Pengetahuan Sosial',
      '400 - Bahasa',
      '500 - Sains',
      '600 - Teknologi',
      '700 - Kesenian dan Rekreasi',
      '800 - Sastra',
      '900 - Sejarah dan Geografi'
  ];
  
  $categories = [];
  foreach ($targetCategories as $tc) {
      $firstChar = substr($tc, 0, 1);
      $stmtCnt = $db->prepare("SELECT COUNT(*) FROM books WHERE kategori = ? OR no_kelas LIKE ?");
      $stmtCnt->execute([$tc, $firstChar . '%']);
      $cnt = $stmtCnt->fetchColumn();
      $categories[] = [
          'kategori' => $tc,
          'cnt' => $cnt
      ];
  }

  $stats = $db->query("SELECT 
    (SELECT COUNT(*) FROM books) as total,
    (SELECT COUNT(*) FROM books WHERE tersedia > 0) as tersedia,
    (SELECT COUNT(*) FROM circulation WHERE status IN ('dipinjam','terlambat')) as dipinjam
    ")->fetch();

$grads = [
  'linear-gradient(135deg,#667eea,#764ba2)',
  'linear-gradient(135deg,#f093fb,#f5576c)',
  'linear-gradient(135deg,#4facfe,#00f2fe)',
  'linear-gradient(135deg,#43e97b,#38f9d7)',
  'linear-gradient(135deg,#fa709a,#fee140)',
  'linear-gradient(135deg,#a18cd1,#fbc2eb)',
  'linear-gradient(135deg,#fccb90,#d57eeb)',
  'linear-gradient(135deg,#e0c3fc,#8ec5fc)',
];

include 'includes/header.php';
?>

<style>
/* ============ KOLEKSI PAGE — SELF-CONTAINED ============ */

/* Override main padding for this page */
main { padding: 0 !important; max-width: 100% !important; }

/* ---- SEARCH HERO ---- */
.kol-hero {
  background: linear-gradient(160deg, #fff0f3 0%, #fce7f3 50%, #fff5f7 100%);
  padding: 3.5rem 5% 2.5rem;
  position: relative;
  overflow: hidden;
  border-bottom: 1px solid #fce7f3;
}
.kol-hero::before {
  content: 'KOLEKSI';
  position: absolute;
  right: -20px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14rem;
  font-weight: 900;
  color: rgba(255,77,109,0.04);
  line-height: 1;
  letter-spacing: -5px;
  pointer-events: none;
  font-family: 'Space Mono', monospace;
}
.kol-hero-inner { max-width: 1400px; margin: 0 auto; }
.kol-hero-top {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
}
.kol-hero-left {}
.kol-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,77,109,0.1);
  color: #FF4D6D;
  border: 1px solid rgba(255,77,109,0.2);
  padding: 5px 14px;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.8px;
  margin-bottom: 1rem;
}
.kol-title {
  display: inline-block;
  font-size: 3.2rem;
  font-weight: 900;
  letter-spacing: -1.5px;
  line-height: 1.2;
  background: linear-gradient(135deg, #FF4D6D 0%, #800F2F 100%);
  color: white;
  padding: 8px 30px;
  border-radius: 20px;
  box-shadow: 0 10px 25px rgba(255, 77, 109, 0.3);
  margin-bottom: 0.8rem;
  border: 2px solid rgba(255,255,255,0.4);
}
.kol-title em {
  font-family: 'Space Mono', monospace;
  font-style: italic;
  color: rgba(255,255,255,0.85);
}
.kol-sub {
  color: #6B7280;
  font-size: 1rem;
}
/* Stat pills */
.kol-stats {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}
.kol-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: white;
  border: 1.5px solid #F3F4F6;
  border-radius: 16px;
  padding: 1rem 1.4rem;
  text-decoration: none;
  transition: all 0.2s;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  min-width: 100px;
}
.kol-stat:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(255,77,109,0.15); border-color: rgba(255,77,109,0.3); }
.kol-stat.active { background: #FF4D6D; border-color: #FF4D6D; box-shadow: 0 4px 18px rgba(255,77,109,0.35); }
.kol-stat-num {
  font-size: 1.7rem;
  font-weight: 900;
  font-family: 'Space Mono', monospace;
  color: #111827;
  line-height: 1;
}
.kol-stat.active .kol-stat-num { color: #fff; }
.kol-stat-lbl { font-size: 0.7rem; font-weight: 700; color: #6B7280; margin-top: 3px; letter-spacing: 0.3px; }
.kol-stat.active .kol-stat-lbl { color: rgba(255,255,255,0.8); }

/* Search form */
.kol-search-form { position: relative; }
.kol-search-box {
  display: flex;
  align-items: center;
  background: white;
  border: 2px solid #E5E7EB;
  border-radius: 14px;
  padding: 5px 5px 5px 20px;
  gap: 10px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.06);
  transition: border-color 0.25s, box-shadow 0.25s;
}
.kol-search-box:focus-within {
  border-color: #FF4D6D;
  box-shadow: 0 4px 24px rgba(255,77,109,0.18);
}
.kol-search-icon { color: #9CA3AF; font-size: 1.1rem; flex-shrink: 0; }
.kol-search-input {
  flex: 1;
  border: none;
  outline: none;
  background: transparent;
  font-family: inherit;
  font-size: 1rem;
  color: #111827;
  padding: 10px 0;
  min-width: 0;
}
.kol-search-input::placeholder { color: #9CA3AF; }
.kol-search-btn {
  padding: 11px 26px;
  background: #111827;
  color: white;
  border: none;
  border-radius: 10px;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.25s;
  flex-shrink: 0;
}
.kol-search-btn:hover { background: #FF4D6D; box-shadow: 0 4px 14px rgba(255,77,109,0.35); }

/* ---- FILTER BAR ---- */
.kol-filter-bar {
  background: white;
  border-bottom: 1px solid #F3F4F6;
  padding: 0.85rem 5%;
  position: relative;
  z-index: 90;
  box-shadow: 0 2px 16px rgba(0,0,0,0.04);
}
.kol-filter-inner {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 1rem;
}
.kol-chips {
  display: flex;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
  flex: 1;
  align-items: center;
  padding-bottom: 2px;
}
.kol-chips::-webkit-scrollbar { display: none; }
.kol-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 14px;
  border-radius: 99px;
  font-size: 0.8rem;
  font-weight: 600;
  color: #6B7280;
  background: #F9FAFB;
  border: 1.5px solid #E5E7EB;
  white-space: nowrap;
  text-decoration: none;
  transition: all 0.18s;
  flex-shrink: 0;
}
.kol-chip:hover { border-color: #FF4D6D; color: #FF4D6D; background: rgba(255,77,109,0.06); }
.kol-chip.on  { background: #111827; color: white; border-color: #111827; }
.kol-chip.on:hover { background: #FF4D6D; border-color: #FF4D6D; }
.kol-chip-n {
  background: rgba(0,0,0,0.08);
  color: inherit;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 99px;
}
.kol-chip.on .kol-chip-n { background: rgba(255,255,255,0.2); }
.kol-divider { color: #E5E7EB; font-size: 1.1rem; flex-shrink: 0; }

/* Sort */
.kol-sort {
  flex-shrink: 0;
  position: relative;
}
.kol-sort select {
  background: #F9FAFB;
  border: 1.5px solid #E5E7EB;
  border-radius: 99px;
  padding: 7px 14px;
  font-family: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  color: #374151;
  cursor: pointer;
  outline: none;
  transition: border-color 0.2s;
  -webkit-appearance: none;
  padding-right: 28px;
}
.kol-sort select:focus { border-color: #FF4D6D; }

/* ---- RESULTS BAR ---- */
.kol-results-bar {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1rem 5% 0.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.85rem;
  color: #6B7280;
}
.kol-results-bar strong { color: #111827; }
.kol-clear {
  background: rgba(255,77,109,0.08);
  color: #FF4D6D;
  font-weight: 700;
  font-size: 0.78rem;
  padding: 4px 12px;
  border-radius: 99px;
  text-decoration: none;
}
.kol-clear:hover { background: rgba(255,77,109,0.16); }

/* ---- BOOK GRID ---- */
.kol-grid {
  max-width: 1400px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(195px, 1fr));
  gap: 1.4rem;
  padding: 1.5rem 5% 5rem;
}

/* Book card */
.kol-card {
  background: white;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid #F3F4F6;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s, border-color 0.3s;
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: inherit;
  position: relative;
}
.kol-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: 0 24px 48px rgba(0,0,0,0.12);
  border-color: rgba(255,77,109,0.25);
}

/* Gradient accent line on top */
.kol-card::before {
  content: '';
  display: block;
  height: 3px;
  background: var(--cg, linear-gradient(90deg,#FF4D6D,#ff8fa3));
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.3s ease;
}
.kol-card:hover::before { transform: scaleX(1); }

/* Cover */
.kol-cover {
  height: 225px;
  position: relative;
  overflow: hidden;
  background: #f3f4f6;
  flex-shrink: 0;
}
.kol-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.45s ease;
}
.kol-card:hover .kol-cover img { transform: scale(1.07); }
.kol-cover-gen {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.4rem;
}
.kol-cover-gen-text {
  color: rgba(255,255,255,0.95);
  font-weight: 800;
  font-size: 0.95rem;
  line-height: 1.35;
  text-align: center;
  text-shadow: 0 2px 10px rgba(0,0,0,0.25);
  display: -webkit-box;
  -webkit-line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Status badge */
.kol-status {
  position: absolute;
  top: 10px;
  left: 10px;
  font-size: 0.62rem;
  font-weight: 800;
  padding: 3px 9px;
  border-radius: 99px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.kol-status.avail { background: rgba(16,185,129,0.9); color: white; box-shadow: 0 2px 8px rgba(16,185,129,0.4); }
.kol-status.out   { background: rgba(244,63,94,0.88);  color: white; box-shadow: 0 2px 8px rgba(244,63,94,0.3); }

/* Hover actions overlay */
.kol-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(to top, rgba(17,24,39,0.92) 0%, rgba(17,24,39,0.3) 55%, transparent 100%);
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 1rem;
  opacity: 0;
  transition: opacity 0.28s ease;
}
.kol-card:hover .kol-overlay { opacity: 1; }
.kol-overlay-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.kol-ov-btn {
  padding: 7px 13px;
  border-radius: 9px;
  font-size: 0.76rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.15s;
  border: 1.5px solid rgba(255,255,255,0.25);
  color: white;
  background: rgba(255,255,255,0.12);
  backdrop-filter: blur(8px);
  white-space: nowrap;
}
.kol-ov-btn.pk { background: #FF4D6D; border-color: #FF4D6D; box-shadow: 0 2px 10px rgba(255,77,109,0.5); }
.kol-ov-btn.pk:hover { background: #e6395e; }
.kol-ov-btn:hover { background: rgba(255,255,255,0.22); }
.kol-overlay-callnum {
  font-family: 'Space Mono', monospace;
  font-size: 0.62rem;
  color: rgba(255,255,255,0.5);
  margin-top: 6px;
}

/* Card body */
.kol-body {
  padding: 0.9rem 1rem 1rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.kol-cat {
  font-size: 0.6rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: #FF4D6D;
  background: rgba(255,77,109,0.08);
  display: inline-block;
  padding: 2px 8px;
  border-radius: 99px;
  margin-bottom: 5px;
}
.kol-name {
  font-weight: 800;
  font-size: 0.88rem;
  line-height: 1.3;
  color: #111827;
  margin-bottom: 3px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.kol-author {
  font-size: 0.73rem;
  color: #6B7280;
  font-style: italic;
  margin-bottom: auto;
  padding-bottom: 8px;
}
.kol-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-top: 1px solid #F3F4F6;
  padding-top: 8px;
  margin-top: 6px;
}
.kol-rating { display: flex; align-items: center; gap: 4px; }
.kol-star { color: #FBBF24; font-size: 0.8rem; }
.kol-rnum { font-weight: 700; font-size: 0.78rem; color: #111827; }
.kol-rcnt { font-size: 0.68rem; color: #9CA3AF; }
.kol-pinjam { font-size: 0.68rem; color: #9CA3AF; font-weight: 600; }

/* Empty */
.kol-empty {
  max-width: 1400px;
  margin: 0 auto;
  text-align: center;
  padding: 5rem 5%;
  color: #6B7280;
}
.kol-empty-icon { font-size: 4rem; margin-bottom: 1rem; }
.kol-empty-h { font-size: 1.3rem; font-weight: 800; color: #111827; margin-bottom: 0.5rem; }
.kol-empty p { margin-bottom: 1.5rem; }
.kol-empty-cta {
  display: inline-block;
  padding: 12px 28px;
  background: #111827;
  color: white;
  border-radius: 99px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.25s;
}
.kol-empty-cta:hover { background: #FF4D6D; transform: translateY(-2px); }

/* Responsive */
@media (max-width: 768px) {
  .kol-hero { padding: 2.5rem 5% 2rem; }
  .kol-title { font-size: 2.2rem; }
  .kol-hero-top { flex-direction: column; align-items: flex-start; }
  .kol-stats { display: none; }
  .kol-filter-bar { top: 56px; }
  .kol-grid { grid-template-columns: repeat(2, 1fr); gap: 1rem; padding: 1rem 4% 3rem; }
  .kol-cover { height: 175px; }
}
@media (min-width: 1280px) {
  .kol-grid { grid-template-columns: repeat(6, 1fr); }
}
</style>

<main>

<style>
.illustration-stack { position: relative; width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; padding-bottom: 10px; }
.ill-book { background: white; border: 3px solid #111827; border-radius: 8px; position: relative; margin-bottom: -3px; box-shadow: -4px 6px 0 rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; font-family: 'Space Mono', monospace; font-weight: 900; color: #111827; font-size: 1.1rem; letter-spacing: 3px; }
.ill-book.pages::after { content: ''; position: absolute; left: 6px; right: 6px; top: 6px; bottom: 6px; border-top: 2px solid #E5E7EB; border-bottom: 2px solid #E5E7EB; border-radius: 2px; }
.ib-1 { width: 90%; height: 50px; background: var(--accent); border-color: #111827; z-index: 5; color: white;}
.ib-2 { width: 100%; height: 65px; background: white; z-index: 4; }
.ib-3 { width: 85%; height: 45px; background: #FFD166; transform: translateX(-15px); z-index: 3;}
.ib-4 { width: 95%; height: 55px; background: var(--accent2); transform: translateX(10px); z-index: 2; color: white;}
.ib-5 { width: 80%; height: 40px; background: white; z-index: 1; transform: translateX(-10px);}
.ill-ladder { position: absolute; right: 30px; bottom: 10px; width: 35px; height: 200px; border: 3px solid #111827; border-top: none; border-bottom: none; background: repeating-linear-gradient(to bottom, transparent, transparent 20px, #111827 20px, #111827 23px); transform: rotate(8deg); transform-origin: bottom center; z-index: 10; }
.ill-door { position: absolute; left: 20px; bottom: 0; width: 35px; height: 50px; background: #E5E7EB; border: 3px solid #111827; border-bottom: none; border-radius: 15px 15px 0 0; z-index: 6; }
.ill-door::before { content: ''; position: absolute; right: 5px; top: 50%; width: 6px; height: 6px; background: #111827; border-radius: 50%; }
.ill-svg { position: absolute; z-index: 11; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15)); }
.p-sit { bottom: 100%; left: 30px; transform: translateY(12px); width: 35px; }
.p-climb { bottom: 60px; right: -5px; width: 45px; transform: rotate(-5deg); }
.p-read { bottom: 100%; left: 50%; transform: translateX(-50%) translateY(5px); width: 55px; }
.ill-plant-svg { position: absolute; left: 15px; bottom: 100%; width: 30px; transform: translateY(2px); z-index: 6; }
.ill-cloud { position: absolute; z-index: 0; filter: drop-shadow(-4px 6px 0px rgba(0,0,0,0.1)); }
.c1 { top: 20px; left: -30px; width: 100px; }
.c2 { top: 80px; right: -30px; width: 80px; }
.c3 { top: 0px; right: 20px; width: 65px; }
@media (max-width: 900px) {
  .hero-illustration { display: none; }
  .profile-header-inner { flex-direction: column; text-align: center !important; }
  .hero-text { text-align: center !important; }
  .kol-search-form { margin: 0 auto !important; }
}
</style>

<!-- ===== HERO ===== -->
<div class="profile-header" style="padding: 8rem 2rem 5rem; background: var(--hero-gradient); position: relative; overflow: hidden; border-bottom: 1px solid var(--border);">
    <!-- Glowing Orbs Background -->
    <div style="position: absolute; top: -50%; left: -10%; width: 350px; height: 350px; background: rgba(255, 77, 109, 0.15); filter: blur(80px); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; bottom: -50%; right: -10%; width: 350px; height: 350px; background: rgba(131, 56, 236, 0.15); filter: blur(80px); border-radius: 50%; pointer-events: none;"></div>
    
    <div class="profile-header-inner" style="display: flex; align-items: center; justify-content: space-between; max-width: 1300px; margin: 0 auto; position: relative; z-index: 1; gap: 2rem; text-align: left;">
        
        <!-- Left Text Content -->
        <div class="hero-text" style="flex: 1;">
            <div style="display: inline-block; padding: 6px 16px; background: var(--card-bg); border: 1px solid var(--border); color: var(--accent); border-radius: 30px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1rem; letter-spacing: 1px; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                ✦ Perpustakaan Digital
            </div>
            
            <h1 style="font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.8rem; letter-spacing: -1.5px; line-height: 1.1;">
                <?= $currentLang === 'en' ? 'Find the Collection<br>You Want' : 'Cari Koleksi<br>yang Kamu Mau' ?>
            </h1>
            <p style="font-size: 1.15rem; color: var(--muted); max-width: 550px; margin-bottom: 2.5rem; line-height: 1.6;">
                <?= $currentLang === 'en' ? 'Complete books, journals & academic references for FISIP Universitas Airlangga community' : 'Buku, jurnal & referensi akademik lengkap untuk civitas FISIP Universitas Airlangga' ?>
            </p>

            <!-- Search Form -->
            <form method="GET" action="search.php" class="kol-search-form" style="max-width: 600px; position: relative; z-index: 1;">
              <div class="kol-search-box" style="padding: 8px 8px 8px 24px; border-radius: 20px;">
                <span class="kol-search-icon" style="font-size: 1.3rem;">🔍</span>
                <input type="text" name="q" class="kol-search-input" style="font-size: 1.1rem; padding: 12px 0;" placeholder="<?= t('search_placeholder', 'Cari buku, pengarang, ISBN...') ?>" value="<?= htmlspecialchars($q) ?>" autocomplete="off">
                <?php if($kat):    ?><input type="hidden" name="kat"    value="<?= htmlspecialchars($kat) ?>"><?php endif; ?>
                <?php if($status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
                <button type="submit" class="kol-search-btn" style="border-radius: 14px; padding: 14px 32px; font-size: 1rem;"><?= t('search_button', 'Cari Sekarang') ?></button>
              </div>
            </form>
        </div>

        <!-- Right Illustration Content -->
        <div class="hero-illustration" style="flex-shrink: 0; width: 400px; height: 320px; position: relative;">
            <div class="illustration-stack">
                <svg class="ill-cloud c1" viewBox="0 0 100 65" xmlns="http://www.w3.org/2000/svg"><path d="M 27,60 a 20,20 0 0,1 0,-40 c 8,-20 36,-20 44,0 a 20,20 0 0,1 0,40 z" fill="white" stroke="#111827" stroke-width="3" stroke-linejoin="round"/></svg>
                <svg class="ill-cloud c2" viewBox="0 0 100 65" xmlns="http://www.w3.org/2000/svg"><path d="M 27,60 a 20,20 0 0,1 0,-40 c 8,-20 36,-20 44,0 a 20,20 0 0,1 0,40 z" fill="white" stroke="#111827" stroke-width="3" stroke-linejoin="round"/></svg>
                <svg class="ill-cloud c3" viewBox="0 0 100 65" xmlns="http://www.w3.org/2000/svg"><path d="M 27,60 a 20,20 0 0,1 0,-40 c 8,-20 36,-20 44,0 a 20,20 0 0,1 0,40 z" fill="white" stroke="#111827" stroke-width="3" stroke-linejoin="round"/></svg>
                
                <div class="ill-ladder">
                    <svg class="ill-svg p-climb" viewBox="0 0 50 60" xmlns="http://www.w3.org/2000/svg">
                      <path d="M25,25 L15,5" stroke="#F72585" stroke-width="6" stroke-linecap="round"/>
                      <path d="M25,25 L40,30 L45,20" stroke="#F72585" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M25,40 L15,55 L25,55" stroke="#111827" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M25,40 L35,45 L35,60" stroke="#111827" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M25,20 L25,40" stroke="#F72585" stroke-width="14" stroke-linecap="round"/>
                      <circle cx="25" cy="10" r="8" fill="#FFD166"/>
                    </svg>
                </div>
                
                <div class="ill-book ib-5 pages">
                    <svg class="ill-svg p-read" viewBox="0 0 60 40" xmlns="http://www.w3.org/2000/svg">
                      <path d="M20,40 C15,20 45,20 40,40" fill="#FF4D6D"/>
                      <circle cx="30" cy="15" r="10" fill="#FFD166"/>
                      <path d="M20,15 C20,5 40,5 40,15 C45,15 45,25 40,25 C35,25 35,15 30,15 C25,15 25,25 20,25 C15,25 15,15 20,15 Z" fill="#111827"/>
                      <path d="M15,35 L30,40 L45,35 L30,25 Z" fill="white" stroke="#111827" stroke-width="2" stroke-linejoin="round"/>
                      <path d="M30,25 L30,40" stroke="#111827" stroke-width="2"/>
                    </svg>
                </div>
                <div class="ill-book ib-4">READING</div>
                <div class="ill-book ib-3 pages">
                    <svg class="ill-svg p-sit" viewBox="0 0 40 55" xmlns="http://www.w3.org/2000/svg">
                      <rect x="10" y="20" width="20" height="20" rx="5" fill="#4361EE"/>
                      <circle cx="20" cy="12" r="9" fill="#FFD166"/>
                      <path d="M11,12 C11,2 29,2 29,12" fill="#111827"/>
                      <path d="M15,35 L15,50 L20,50" stroke="#111827" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M25,35 L25,45 L30,45" stroke="#111827" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                      <rect x="6" y="32" width="28" height="6" rx="2" fill="white" stroke="#111827" stroke-width="2"/>
                    </svg>
                </div>
                <div class="ill-book ib-2 pages">
                    <div class="ill-door"></div>
                    <svg class="ill-plant-svg" viewBox="0 0 40 50" xmlns="http://www.w3.org/2000/svg">
                      <path d="M20,35 C10,25 15,5 20,35" fill="#06D6A0" stroke="#111827" stroke-width="2"/>
                      <path d="M20,35 C30,25 25,5 20,35" fill="#06D6A0" stroke="#111827" stroke-width="2"/>
                      <path d="M20,35 C0,30 0,15 20,35" fill="#06D6A0" stroke="#111827" stroke-width="2"/>
                      <path d="M20,35 C40,30 40,15 20,35" fill="#06D6A0" stroke="#111827" stroke-width="2"/>
                      <path d="M12,35 L28,35 L24,50 L16,50 Z" fill="#F4A261" stroke="#111827" stroke-width="2" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="ill-book ib-1">UNAIR</div>
            </div>
        </div>

    </div>
</div>

<!-- ===== FILTER BAR ===== -->
<div class="kol-filter-bar">
  <div class="kol-filter-inner">
    <div class="kol-chips">
      <a class="kol-chip <?= !$kat && !$status && !$bahasa ? 'on' : '' ?>" href="search.php?q=<?= urlencode($q) ?>">🎒 <?= t('search_all_categories', 'Semua') ?></a>
      <?php foreach($categories as $c): ?>
      <a class="kol-chip <?= $kat===$c['kategori'] ? 'on' : '' ?>"
         href="search.php?q=<?= urlencode($q) ?>&kat=<?= urlencode($c['kategori']) ?>&status=<?= urlencode($status) ?>">
        <?= htmlspecialchars(t_cat($c['kategori'])) ?>
        <span class="kol-chip-n"><?= $c['cnt'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="kol-sort">
      <form method="GET">
        <?php if($q):      ?><input type="hidden" name="q"      value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
        <?php if($kat):    ?><input type="hidden" name="kat"    value="<?= htmlspecialchars($kat) ?>"><?php endif; ?>
        <?php if($status): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>"><?php endif; ?>
        <select name="sort" onchange="this.form.submit()">
          <option value="terbaru" <?= $sort==='terbaru' ? 'selected' : '' ?>>🕐 <?= $currentLang === 'en' ? 'Newest' : 'Terbaru' ?></option>
          <option value="rating"  <?= $sort==='rating'  ? 'selected' : '' ?>>⭐ Rating</option>
          <option value="populer" <?= $sort==='populer' ? 'selected' : '' ?>>🔥 <?= $currentLang === 'en' ? 'Popular' : 'Populer' ?></option>
          <option value="judul"   <?= $sort==='judul'   ? 'selected' : '' ?>>🔤 A–Z</option>
        </select>
      </form>
    </div>
  </div>
</div>

<!-- ===== RESULTS INFO ===== -->
<div class="kol-results-bar">
  <?php if($q): ?>
    <span><?= $currentLang === 'en' ? 'Results for' : 'Hasil untuk' ?> <strong>"<?= htmlspecialchars($q) ?>"</strong> — <?= count($books) ?> <?= $currentLang === 'en' ? 'collections' : 'koleksi' ?></span>
    <a class="kol-clear" href="search.php">✖ <?= $currentLang === 'en' ? 'Clear search' : 'Hapus pencarian' ?></a>
  <?php else: ?>
    <span><?= $currentLang === 'en' ? 'Showing' : 'Menampilkan' ?> <strong><?= count($books) ?></strong> <?= $currentLang === 'en' ? 'collections' : 'koleksi' ?><?= $kat ? ' • <strong>'.htmlspecialchars(t_cat($kat)).'</strong>' : '' ?><?= $status ? ' • '.($status==='tersedia' ? '✅ Available' : '⏳ Borrowed') : '' ?></span>
    <?php if($kat || $status || $bahasa): ?>
      <a class="kol-clear" href="search.php?q=<?= urlencode($q) ?>">✕ <?= $currentLang === 'en' ? 'Reset filter' : 'Reset filter' ?></a>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- ===== BOOK GRID ===== -->
<?php if(empty($books)): ?>
<div class="kol-empty">
  <div class="kol-empty-icon">📭</div>
  <div class="kol-empty-h"><?= $currentLang === 'en' ? 'No collections found' : 'Koleksi tidak ditemukan' ?></div>
  <p><?= $currentLang === 'en' ? 'Try different keywords or clear active filters.' : 'Coba kata kunci lain atau hapus filter yang aktif.' ?></p>
  <a href="search.php" class="kol-empty-cta"><?= $currentLang === 'en' ? 'View All Collections' : 'Lihat Semua Koleksi' ?></a>
</div>

<?php else: ?>
<div class="kol-grid">
  <?php foreach($books as $i => $b):
    $covSrc = bookCoverSrc($b);
    $grad   = $grads[$i % count($grads)];
  ?>
  <div class="kol-card" style="--cg:<?= $grad ?>;" onclick="showDetail(<?= $b['id'] ?>)">

    <!-- Cover -->
    <div class="kol-cover">
      <?php if($covSrc): ?>
        <img src="<?= $covSrc ?>" alt="<?= htmlspecialchars($b['judul']) ?>">
      <?php else: ?>
        <div class="kol-cover-gen" style="background:<?= $grad ?>;">
          <span class="kol-cover-gen-text"><?= htmlspecialchars(substr($b['judul'],0,35)) ?></span>
        </div>
      <?php endif; ?>

      <?php if($b['tersedia']>0): ?>
        <div class="kol-status avail">Tersedia</div>
      <?php else: ?>
        <div class="kol-status out">Dipinjam</div>
      <?php endif; ?>

      <!-- Hover overlay -->
      <div class="kol-overlay">
        <div class="kol-overlay-btns" onclick="event.stopPropagation()">
          <?php if(isLoggedIn()): ?>
            <?php if($b['tersedia']>0): ?>
            <a href="profile.php?pinjam=<?= $b['id'] ?>" class="kol-ov-btn">📖 <?= $currentLang === 'en' ? 'Borrow' : 'Pinjam' ?></a>
            <?php else: ?>
            <a href="profile.php?antri=<?= $b['id'] ?>" class="kol-ov-btn">⏳ <?= $currentLang === 'en' ? 'Queue' : 'Antri' ?></a>
            <?php endif; ?>
            <a href="rakku.php?tambah=<?= $b['id'] ?>" class="kol-ov-btn">🔖 Rak</a>
          <?php else: ?>
            <a href="login.php" class="kol-ov-btn pk">✨ Masuk untuk Pinjam</a>
          <?php endif; ?>
        </div>
        <div class="kol-overlay-callnum"><?= htmlspecialchars($b['no_kelas']) ?> · <?= htmlspecialchars($b['bahasa']) ?> · <?= $b['tahun'] ?></div>
      </div>
    </div>

    <!-- Body -->
    <div class="kol-body">
      <div class="kol-cat"><?= htmlspecialchars($b['kategori']) ?></div>
      <div class="kol-name"><?= htmlspecialchars($b['judul']) ?></div>
      <div class="kol-author">by <?= htmlspecialchars($b['pengarang']) ?></div>
      <div class="kol-foot">
        <?php if($b['avg_rating'] > 0): ?>
        <div class="kol-rating">
          <span class="kol-star">★</span>
          <span class="kol-rnum"><?= number_format($b['avg_rating'],1) ?></span>
          <span class="kol-rcnt">(<?= $b['total_reviews'] ?>)</span>
        </div>
        <?php else: ?>
          <span class="kol-rcnt" style="font-size:0.7rem;">Belum ada review</span>
        <?php endif; ?>
        <span class="kol-pinjam">📥 <?= $b['total_pinjam'] ?>×</span>
      </div>
    </div>

  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== BOOK DETAIL MODAL ===== -->
<div class="det-overlay" id="detOverlay" onclick="closeDetail()"></div>
<div class="det-modal" id="detModal">
  <button class="det-close" onclick="closeDetail()">✕</button>
  <div class="det-loading" id="detLoading">
    <div class="det-spinner"></div>
    <div style="margin-top:12px;color:#9CA3AF;font-size:0.85rem;">Memuat detail buku...</div>
  </div>
  <div id="detContent" style="display:none;"></div>
</div>

</main>
<?php include 'includes/footer.php'; ?>

<style>
/* Clickable card */
.kol-card { cursor: pointer; }

/* ---- DETAIL MODAL ---- */
.det-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,0.55);
  backdrop-filter:blur(8px); z-index:999;
  opacity:0; pointer-events:none; transition:opacity 0.3s;
}
.det-overlay.show { opacity:1; pointer-events:all; }

.det-modal {
  position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) scale(0.92);
  width:95%; max-width:560px; max-height:90vh; overflow-y:auto;
  background:white; border-radius:24px; z-index:1000;
  box-shadow:0 25px 80px rgba(0,0,0,0.3);
  opacity:0; pointer-events:none; transition:all 0.35s cubic-bezier(0.34,1.56,0.64,1);
}
.det-modal.show { opacity:1; pointer-events:all; transform:translate(-50%,-50%) scale(1); }
.det-modal::-webkit-scrollbar { width:6px; }
.det-modal::-webkit-scrollbar-thumb { background:#E5E7EB; border-radius:99px; }

.det-close {
  position:sticky; top:12px; float:right; margin:12px 12px 0 0; z-index:10;
  width:34px; height:34px; border-radius:50%;
  border:1.5px solid #E5E7EB; background:white;
  color:#6B7280; font-size:0.9rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:all 0.2s; box-shadow:0 2px 8px rgba(0,0,0,0.1);
}
.det-close:hover { background:#FEE2E2; border-color:#FECACA; color:#DC2626; }

.det-loading { text-align:center; padding:4rem 1rem; }
.det-spinner {
  width:36px; height:36px; margin:0 auto;
  border:3px solid #F3F4F6; border-top-color:#FF4D6D;
  border-radius:50%; animation:spin 0.8s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg); } }

/* Top section */
.det-top { display:flex; gap:1.4rem; padding:1.5rem 1.5rem 1rem; align-items:flex-start; }
.det-cover {
  width:120px; height:170px; border-radius:14px; flex-shrink:0;
  overflow:hidden; box-shadow:0 8px 28px rgba(0,0,0,0.18);
}
.det-cover img { width:100%; height:100%; object-fit:cover; display:block; }
.det-cover-gen {
  width:100%; height:100%; display:flex; align-items:center; justify-content:center;
  padding:0.8rem; text-align:center;
}
.det-cover-gen span {
  color:rgba(255,255,255,0.95); font-weight:800; font-size:0.85rem;
  line-height:1.35; text-shadow:0 2px 8px rgba(0,0,0,0.2);
}
.det-info { flex:1; min-width:0; }
.det-kat {
  font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px;
  color:#FF4D6D; background:rgba(255,77,109,0.08);
  display:inline-block; padding:3px 10px; border-radius:99px; margin-bottom:6px;
}
.det-judul { font-size:1.25rem; font-weight:900; color:#111827; line-height:1.2; margin:0 0 4px; letter-spacing:-0.5px; }
.det-pengarang { font-size:0.82rem; color:#6B7280; font-style:italic; margin-bottom:6px; }
.det-stars { color:#FBBF24; font-size:0.85rem; margin-bottom:6px; }
.det-stars-dim { color:#E5E7EB; }
.det-stars-val { font-size:0.72rem; color:#9CA3AF; font-family:'Space Mono',monospace; margin-left:4px; }
.det-avail-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.det-badge {
  display:inline-flex; align-items:center; gap:4px;
  font-size:0.68rem; font-weight:700; padding:4px 10px; border-radius:99px;
}
.det-badge.green { background:rgba(16,185,129,0.1); color:#059669; border:1px solid rgba(16,185,129,0.2); }
.det-badge.red   { background:rgba(244,63,94,0.08); color:#DC2626; border:1px solid rgba(244,63,94,0.2); }
.det-badge.amber { background:rgba(245,158,11,0.08); color:#D97706; border:1px solid rgba(245,158,11,0.2); }
.det-badge.blue  { background:rgba(79,70,229,0.08); color:#4F46E5; border:1px solid rgba(79,70,229,0.15); }

/* Metadata grid */
.det-meta {
  display:grid; grid-template-columns:1fr 1fr; gap:6px;
  padding:0 1.5rem; margin-bottom:1rem;
}
.det-meta-item { background:#F9FAFB; border-radius:10px; padding:8px 12px; border:1px solid #F3F4F6; }
.det-meta-lbl { font-size:0.58rem; font-weight:700; text-transform:uppercase; color:#9CA3AF; letter-spacing:0.5px; }
.det-meta-val { font-size:0.82rem; font-weight:700; color:#111827; margin-top:1px; }

/* Description */
.det-desc { padding:0 1.5rem; margin-bottom:1rem; border-top:1px solid #F3F4F6; padding-top:1rem; }
.det-desc-lbl { font-size:0.65rem; font-weight:800; text-transform:uppercase; color:#9CA3AF; letter-spacing:0.5px; margin-bottom:4px; }
.det-desc-text { font-size:0.85rem; color:#374151; line-height:1.7; }

/* Actions */
.det-actions { display:flex; gap:8px; padding:0 1.5rem; margin-bottom:1.2rem; flex-wrap:wrap; }
.det-btn {
  flex:1; padding:11px 14px; border-radius:12px;
  font-family:inherit; font-size:0.85rem; font-weight:700;
  cursor:pointer; text-decoration:none; text-align:center;
  transition:all 0.25s; border:none; white-space:nowrap;
}
.det-btn.primary { background:#111827; color:white; min-width:120px; }
.det-btn.primary:hover { background:#FF4D6D; box-shadow:0 4px 16px rgba(255,77,109,0.35); }
.det-btn.rak { background:rgba(255,77,109,0.08); color:#FF4D6D; border:1.5px solid rgba(255,77,109,0.2); }
.det-btn.rak:hover { background:#FF4D6D; color:white; }
.det-btn.sec { background:#F3F4F6; color:#374151; border:1.5px solid #E5E7EB; }
.det-btn.sec:hover { border-color:#FF4D6D; color:#FF4D6D; }
.det-btn.disabled { opacity:0.5; cursor:default; pointer-events:none; }

/* Reviews section */
.det-reviews { padding:0 1.5rem 1.5rem; border-top:1px solid #F3F4F6; padding-top:1rem; }
.det-rv-header {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:0.8rem;
}
.det-rv-title { font-size:0.88rem; font-weight:800; color:#111827; display:flex; align-items:center; gap:6px; }
.det-rv-cnt { font-size:0.65rem; font-weight:700; background:rgba(255,77,109,0.1); color:#FF4D6D; padding:2px 8px; border-radius:99px; }
.det-rv-card {
  background:#F9FAFB; border:1px solid #F3F4F6; border-radius:12px;
  padding:0.8rem 1rem; margin-bottom:8px;
}
.det-rv-top { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.det-rv-avatar {
  width:30px; height:30px; border-radius:50%;
  background:linear-gradient(135deg,rgba(255,77,109,0.15),rgba(255,143,163,0.1));
  color:#FF4D6D; font-weight:800; font-size:0.65rem;
  display:flex; align-items:center; justify-content:center;
  border:1px solid rgba(255,77,109,0.15); flex-shrink:0;
}
.det-rv-name { font-weight:700; font-size:0.8rem; color:#111827; }
.det-rv-meta { font-size:0.65rem; color:#9CA3AF; }
.det-rv-stars { color:#FBBF24; font-size:0.72rem; margin-left:auto; }
.det-rv-text { font-size:0.82rem; color:#374151; line-height:1.6; font-style:italic; }
.det-rv-likes { font-size:0.7rem; color:#9CA3AF; margin-top:4px; }
.det-rv-empty { text-align:center; color:#9CA3AF; font-size:0.82rem; padding:1.5rem 0; }
.det-rv-cta {
  display:inline-block; margin-top:8px; padding:8px 18px;
  background:#111827; color:white; border-radius:99px;
  font-size:0.8rem; font-weight:700; text-decoration:none;
  transition:all 0.2s;
}
.det-rv-cta:hover { background:#FF4D6D; }

@media (max-width:480px) {
  .det-top { flex-direction:column; align-items:center; text-align:center; }
  .det-cover { width:140px; height:195px; }
  .det-meta { grid-template-columns:1fr; }
  .det-actions { flex-direction:column; }
}
</style>

<script>
function showDetail(bookId) {
  const overlay = document.getElementById('detOverlay');
  const modal   = document.getElementById('detModal');
  const loading = document.getElementById('detLoading');
  const content = document.getElementById('detContent');

  loading.style.display = '';
  content.style.display = 'none';
  content.innerHTML = '';
  overlay.classList.add('show');
  modal.classList.add('show');
  document.body.style.overflow = 'hidden';

  fetch('api_book_detail.php?id=' + bookId)
    .then(r => r.json())
    .then(data => {
      if (data.error) { content.innerHTML = '<p style="text-align:center;padding:2rem;color:#DC2626;">'+data.error+'</p>'; loading.style.display='none'; content.style.display=''; return; }
      renderDetail(data);
      loading.style.display = 'none';
      content.style.display = '';
    })
    .catch(err => {
      content.innerHTML = '<p style="text-align:center;padding:2rem;color:#DC2626;">Gagal memuat data</p>';
      loading.style.display = 'none';
      content.style.display = '';
    });
}

function closeDetail() {
  document.getElementById('detOverlay').classList.remove('show');
  document.getElementById('detModal').classList.remove('show');
  document.body.style.overflow = '';
}

function esc(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

function renderDetail(data) {
  const b = data.book;
  const reviews = data.reviews;
  const content = document.getElementById('detContent');
  let html = '';

  // -- TOP --
  const coverHtml = b.cover_src
    ? '<img src="'+esc(b.cover_src)+'" alt="'+esc(b.judul)+'">'
    : '<div class="det-cover-gen" style="background:linear-gradient(135deg,'+esc(b.color1)+','+esc(b.color2)+');"><span style="color:'+esc(b.color3)+'">'+esc(b.judul.substring(0,35))+'</span></div>';

  const starsFull = '★'.repeat(Math.round(b.avg_rating));
  const starsDim  = '★'.repeat(5 - Math.round(b.avg_rating));

  let availHtml = '';
  if (b.tersedia > 0) {
    availHtml = '<span class="det-badge green">✅ <?= $currentLang === 'en' ? 'Available' : 'Tersedia' ?> '+b.tersedia+'/'+b.stok+'</span>';
  } else {
    availHtml = '<span class="det-badge red">❌ <?= $currentLang === 'en' ? 'All borrowed' : 'Semua dipinjam' ?></span>';
  }
  if (data.active_borrowers > 0) {
    availHtml += '<span class="det-badge amber">📖 '+data.active_borrowers+' <?= $currentLang === 'en' ? 'active borrowers' : 'peminjam aktif' ?></span>';
  }
  if (data.antri_count > 0) {
    availHtml += '<span class="det-badge blue">⏳ '+data.antri_count+' <?= $currentLang === 'en' ? 'in queue' : 'dalam antrian' ?></span>';
  }

  html += '<div class="det-top">';
  html += '  <div class="det-cover">'+coverHtml+'</div>';
  html += '  <div class="det-info">';
  html += '    <div class="det-kat">'+esc(b.kategori)+'</div>';
  html += '    <h2 class="det-judul">'+esc(b.judul)+'</h2>';
  html += '    <div class="det-pengarang">by '+esc(b.pengarang)+'</div>';
  html += '    <div class="det-stars">'+starsFull+'<span class="det-stars-dim">'+starsDim+'</span><span class="det-stars-val">'+Number(b.avg_rating).toFixed(1)+' ('+b.total_reviews+' review)</span></div>';
  html += '    <div class="det-avail-row">'+availHtml+'</div>';
  html += '  </div>';
  html += '</div>';

  // -- METADATA --
  html += '<div class="det-meta">';
  const meta = [];
  if (b.penerbit) meta.push(['<?= $currentLang === 'en' ? 'Publisher' : 'Penerbit' ?>', b.penerbit]);
  if (b.tahun) meta.push(['<?= $currentLang === 'en' ? 'Year' : 'Tahun Terbit' ?>', b.tahun]);
  if (b.isbn) meta.push(['ISBN', b.isbn]);
  if (b.no_kelas) meta.push(['<?= $currentLang === 'en' ? 'Class No.' : 'No. Kelas' ?>', b.no_kelas]);
  if (b.bahasa) meta.push(['<?= $currentLang === 'en' ? 'Language' : 'Bahasa' ?>', b.bahasa]);
  meta.push(['<?= $currentLang === 'en' ? 'Stock' : 'Stok' ?>', b.tersedia+'/'+b.stok+' <?= $currentLang === 'en' ? 'available' : 'tersedia' ?>']);
  meta.push(['<?= $currentLang === 'en' ? 'Total Borrowed' : 'Total Dipinjam' ?>', b.total_pinjam+'× <?= $currentLang === 'en' ? 'since added' : 'sejak ditambahkan' ?>']);
  if (data.antri_count > 0) meta.push(['<?= $currentLang === 'en' ? 'Queue' : 'Antrian' ?>', data.antri_count+' <?= $currentLang === 'en' ? 'people waiting' : 'orang menunggu' ?>']);
  meta.forEach(function(m) {
    html += '<div class="det-meta-item"><div class="det-meta-lbl">'+m[0]+'</div><div class="det-meta-val">'+esc(String(m[1]))+'</div></div>';
  });
  html += '</div>';

  // -- DESCRIPTION --
  if (b.deskripsi) {
    html += '<div class="det-desc"><div class="det-desc-lbl"><?= $currentLang === 'en' ? 'Description' : 'Deskripsi' ?></div><div class="det-desc-text">'+esc(b.deskripsi)+'</div></div>';
  }

  // -- ACTIONS --
  html += '<div class="det-actions">';
  if (data.logged_in) {
    if (b.tersedia > 0) {
      html += '<a href="profile.php?pinjam='+b.id+'" class="det-btn rak">📖 <?= $currentLang === 'en' ? 'Borrow Book' : 'Pinjam Buku' ?></a>';
    } else {
      if (data.in_antri) {
        html += '<span class="det-btn sec disabled">⏳ <?= $currentLang === 'en' ? 'Already in Queue' : 'Sudah Dalam Antrian' ?></span>';
      } else {
        html += '<a href="profile.php?antri='+b.id+'" class="det-btn rak">⏳ <?= $currentLang === 'en' ? 'Join Queue' : 'Masuk Antrian' ?></a>';
      }
    }
    if (data.in_shelf) {
      html += '<span class="det-btn sec disabled">🔖 <?= $currentLang === 'en' ? 'Already on My Shelf' : 'Sudah di Rak Ku' ?></span>';
    } else {
      html += '<a href="rakku.php?tambah='+b.id+'" class="det-btn rak">🔖 <?= $currentLang === 'en' ? 'Save to My Shelf' : 'Simpan ke Rak Ku' ?></a>';
    }
    html += '<a href="booktalk.php?review='+b.id+'" class="det-btn sec">💬 <?= $currentLang === 'en' ? 'Write Review' : 'Tulis Review' ?></a>';
  } else {
    html += '<a href="login.php" class="det-btn primary">✨ <?= $currentLang === 'en' ? 'Login to Borrow' : 'Masuk untuk Pinjam' ?></a>';
  }
  html += '</div>';

  // -- REVIEWS --
  html += '<div class="det-reviews">';
  html += '<div class="det-rv-header"><div class="det-rv-title">💬 <?= $currentLang === 'en' ? 'User Reviews' : 'Review Pengguna' ?> <span class="det-rv-cnt">'+reviews.length+' review</span></div></div>';

  if (reviews.length === 0) {
    html += '<div class="det-rv-empty"><?= $currentLang === 'en' ? 'No reviews for this book yet.' : 'Belum ada review untuk buku ini.' ?><br>';
    if (data.logged_in) {
      html += '<a href="booktalk.php?review='+b.id+'" class="det-rv-cta">✍ <?= $currentLang === 'en' ? 'Write the first review' : 'Tulis Review Pertama' ?></a>';
    }
    html += '</div>';
  } else {
    reviews.forEach(function(rv) {
      const rvStars = '★'.repeat(rv.rating) + '<span style="color:#E5E7EB">' + '★'.repeat(5-rv.rating) + '</span>';
      html += '<div class="det-rv-card">';
      html += '  <div class="det-rv-top">';
      html += '    <div class="det-rv-avatar">'+esc(rv.initials)+'</div>';
      html += '    <div><div class="det-rv-name">'+esc(rv.nama)+'</div><div class="det-rv-meta">'+esc(rv.prodi)+' · '+esc(rv.time_ago)+'</div></div>';
      html += '    <div class="det-rv-stars">'+rvStars+'</div>';
      html += '  </div>';
      html += '  <div class="det-rv-text">"'+esc(rv.isi)+'"</div>';
      if (rv.total_likes > 0) html += '  <div class="det-rv-likes">❤️ '+rv.total_likes+' likes</div>';
      html += '</div>';
    });
  }
  html += '</div>';

  content.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', function() {
  <?php if(isset($_GET['detail']) && is_numeric($_GET['detail'])): ?>
    showDetail(<?= (int)$_GET['detail'] ?>);
  <?php endif; ?>
});
</script>
