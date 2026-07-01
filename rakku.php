<?php
session_start();
require_once 'config.php';
require_once 'includes/cover_helper.php';
requireLogin();

$pageTitle = 'Rak Ku';
$db  = getDB();
$uid = $_SESSION['user_id'];
$me  = currentUser();

/* ---- ACTIONS ---- */
// Tambah ke wishlist
if (isset($_GET['tambah'])) {
    $bid = (int)$_GET['tambah'];
    $cek = $db->prepare("SELECT id FROM shelves WHERE user_id=? AND book_id=?");
    $cek->execute([$uid,$bid]);
    if (!$cek->fetch()) {
        $db->prepare("INSERT INTO shelves (user_id,book_id,status) VALUES (?,?,'ingin_dibaca')")->execute([$uid,$bid]);
        redirect('rakku.php','Buku ditambahkan ke Wishlist! 🔖');
    } else {
        redirect('rakku.php','Buku sudah ada di Rak Ku.','error');
    }
}
// Hapus dari rak
if (isset($_GET['hapus'])) {
    $sid = (int)$_GET['hapus'];
    $db->prepare("DELETE FROM shelves WHERE id=? AND user_id=?")->execute([$sid,$uid]);
    redirect('rakku.php','Buku dihapus dari Rak Ku.');
}
// Perpanjang pinjaman
if (isset($_GET['perpanjang'])) {
    $cid = (int)$_GET['perpanjang'];
    $sirk = $db->prepare("SELECT * FROM circulation WHERE id=? AND user_id=? AND status IN('dipinjam','terlambat')");
    $sirk->execute([$cid,$uid]);
    $s = $sirk->fetch();
    if ($s && $s['perpanjangan'] < MAKS_PERPANJANG) {
        $db->prepare("UPDATE circulation SET jatuh_tempo=DATE_ADD(jatuh_tempo,INTERVAL ? DAY), perpanjangan=perpanjangan+1 WHERE id=?")
           ->execute([LAMA_PINJAM,$cid]);
        redirect('rakku.php','Berhasil diperpanjang '.LAMA_PINJAM.' hari! 🔄');
    } else {
        redirect('rakku.php','Tidak bisa diperpanjang lagi (maks '.MAKS_PERPANJANG.'x).','error');
    }
}
// Update status shelf
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_status'])) {
    $sid = (int)$_POST['shelf_id'];
    $st  = $_POST['status'];
    $validStatus = ['ingin_dibaca','sedang_dibaca','sudah_dibaca'];
    if (in_array($st,$validStatus)) {
        $db->prepare("UPDATE shelves SET status=? WHERE id=? AND user_id=?")->execute([$st,$sid,$uid]);
        redirect('rakku.php','Status diperbarui!');
    }
}
// Kembalikan buku (user self-return)
if (isset($_GET['kembalikan'])) {
    $cid = (int)$_GET['kembalikan'];
    $sirk = $db->prepare("SELECT * FROM circulation WHERE id=? AND user_id=? AND status IN('dipinjam','terlambat')");
    $sirk->execute([$cid,$uid]);
    $s = $sirk->fetch();
    if ($s) {
        $today = new DateTime();
        $tempo = new DateTime($s['jatuh_tempo']);
        if ($today > $tempo) {
            // Overdue — harus bayar denda dulu
            redirect('rakku.php?tab=dipinjam','Buku terlambat! Silakan bayar denda terlebih dahulu sebelum mengembalikan buku.','error');
        } else {
            // Tepat waktu — kembalikan langsung
            $db->prepare("UPDATE circulation SET status='dikembalikan', tanggal_kembali=CURDATE(), denda=0 WHERE id=?")->execute([$cid]);
            $db->prepare("UPDATE books SET tersedia=tersedia+1 WHERE id=?")->execute([$s['book_id']]);
            // Cek antrian — notifikasi user yang menunggu
            $nextAntri = $db->prepare("SELECT id, user_id FROM antrian WHERE book_id=? AND status='menunggu' ORDER BY tanggal_antri ASC LIMIT 1");
            $nextAntri->execute([$s['book_id']]);
            $next = $nextAntri->fetch();
            if ($next) {
                $db->prepare("UPDATE antrian SET status='tersedia' WHERE id=?")->execute([$next['id']]);
            }
            redirect('rakku.php?tab=dipinjam','Buku berhasil dikembalikan! Terima kasih. 📚✅');
        }
    } else {
        redirect('rakku.php?tab=dipinjam','Data peminjaman tidak ditemukan.','error');
    }
}
// Konfirmasi Bayar QRIS (Menunggu Verifikasi)
if (isset($_GET['qris_bayar'])) {
    $cid = (int)$_GET['qris_bayar'];
    // Update to tertunda
    $db->prepare("UPDATE circulation SET denda_status='tertunda', denda_metode='qris' WHERE id=? AND user_id=? AND status='terlambat'")->execute([$cid, $uid]);
    redirect('rakku.php', 'Menunggu verifikasi admin. Silakan tunggu konfirmasi selanjutnya.');
}

/* ---- AUTO-UPDATE DENDA ---- */
$db->exec("UPDATE circulation
    SET denda = DATEDIFF(CURDATE(), jatuh_tempo) * " . DENDA_PER_BUKU . ",
        status = 'terlambat'
    WHERE status IN ('dipinjam','terlambat')
    AND jatuh_tempo < CURDATE()");

/* ---- DATA QUERIES ---- */
// 1. Wishlist (all shelf statuses)
$wishlist = $db->prepare("SELECT sh.*, b.judul, b.pengarang, b.tahun, b.kategori, b.color1, b.color2, b.color3, b.tersedia, b.cover_image,
    COALESCE(AVG(r.rating),0) as avg_rating
    FROM shelves sh
    JOIN books b ON sh.book_id=b.id
    LEFT JOIN reviews r ON b.id=r.book_id
    WHERE sh.user_id=?
    GROUP BY sh.id
    ORDER BY sh.added_at DESC");
$wishlist->execute([$uid]);
$listWishlist = $wishlist->fetchAll();

$books_read = [];
$books_tbr = [];
$books_reading = [];
foreach($listWishlist as $s) {
    if ($s['status'] === 'sudah_dibaca') $books_read[] = $s;
    elseif ($s['status'] === 'ingin_dibaca') $books_tbr[] = $s;
    else $books_reading[] = $s;
}

// 2. Antrian (ingin pinjam buku yg sedang dipinjam orang lain)
$antrian = $db->prepare("SELECT a.*, b.judul, b.pengarang, b.kategori, b.color1, b.color2, b.color3, b.cover_image,
    b.tersedia,
    (SELECT COUNT(*) FROM antrian WHERE book_id=a.book_id AND status='menunggu') as posisi_antrian
    FROM antrian a
    JOIN books b ON a.book_id=b.id
    WHERE a.user_id=? AND a.status='menunggu'
    ORDER BY a.tanggal_antri DESC");
$antrian->execute([$uid]);
$listAntrian = $antrian->fetchAll();

// 3. Sedang dipinjam (aktif)
$dipinjam = $db->prepare("SELECT c.*, b.judul, b.pengarang, b.no_kelas, b.kategori, b.color1, b.color2, b.color3, b.cover_image,
    DATEDIFF(c.jatuh_tempo, CURDATE()) as sisa_hari
    FROM circulation c
    JOIN books b ON c.book_id=b.id
    WHERE c.user_id=? AND c.status IN('dipinjam','terlambat')
    ORDER BY c.jatuh_tempo ASC");
$dipinjam->execute([$uid]);
$listDipinjam = $dipinjam->fetchAll();

// Stats
$totalPinjam  = count($listDipinjam);
$totalWishlist= count($listWishlist);
$totalDendaQ  = $db->prepare("SELECT COALESCE(SUM(denda),0) FROM circulation WHERE user_id=?");
$totalDendaQ->execute([$uid]);
$totalDenda   = $totalDendaQ->fetchColumn();
$totalReview  = $db->prepare("SELECT COUNT(*) FROM reviews WHERE user_id=?");
$totalReview->execute([$uid]);
$jmlReview    = $totalReview->fetchColumn();

// Flash
$flash     = $_SESSION['flash_msg']  ?? '';
$flashType = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

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

$tab = $_GET['tab'] ?? 'wishlist';
include 'includes/header.php';
?>

<style>
/* ============ RAK KU PAGE ============ */

/* Hero */
.rk-hero {
  padding: 5rem 5% 4rem; /* Adjusted for modern-hero look */
  margin-bottom: 2rem;
}
.rk-hero-inner { max-width: 1200px; margin: 0 auto; position: relative; z-index: 1; }
.rk-hero-top {
  display: flex; align-items: center; justify-content: center; text-align: center;
  gap: 2rem; flex-wrap: wrap; margin-bottom: 2rem;
}
.rk-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,77,109,0.08); color: #FF4D6D;
  border: 1.5px solid rgba(255,77,109,0.25); padding: 6px 16px;
  border-radius: 99px; font-size: 0.75rem; font-weight: 700;
  letter-spacing: 0.5px; margin-bottom: 1rem;
}
.rk-title { font-size: 3rem; font-weight: 900; letter-spacing: -1px; line-height: 1; color: var(--text-main); margin-bottom: 0.8rem; }
.rk-title em { font-family: 'Inter', sans-serif; font-style: italic; color: #FF4D6D; }
.rk-sub { color: var(--muted); font-size: 1rem; font-weight: 500; }

/* Floating Tabs (Stats) */
.rk-stats { display: flex; gap: 12px; margin-top: 1.5rem; overflow-x: auto; scrollbar-width: none; padding-bottom: 10px; justify-content: center; }
.rk-stats::-webkit-scrollbar { display: none; }
.rk-stat {
  background: white; border-radius: 18px; padding: 14px 24px;
  display: flex; flex-direction: column; align-items: center; gap: 6px;
  transition: all 0.3s; border: none; box-shadow: 0 6px 16px rgba(0,0,0,0.04);
  text-decoration: none; min-width: 100px;
}
[data-theme="dark"] .rk-stat { background: var(--bg-tertiary); box-shadow: 0 6px 16px rgba(0,0,0,0.2); }
.rk-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
.rk-stat.active { background: #FF4D6D; color: white; box-shadow: 0 8px 20px rgba(255,77,109,0.3); }
.rk-stat-icon { font-size: 1.25rem; }
.rk-stat-lbl { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-main); }
.rk-stat.active .rk-stat-lbl { color: white; }
[data-theme="dark"] .rk-stat:not(.active) .rk-stat-lbl { color: var(--muted); }

/* Content area */
.rk-content { max-width:1200px; margin:0 auto; padding:2rem 5% 4rem; }

/* Flash */
.rk-flash-ok  { background:rgba(16,185,129,0.08); color:#059669; border:1px solid rgba(16,185,129,0.2);
  padding:10px 16px; border-radius:10px; font-size:0.85rem; font-weight:600; margin-bottom:1.5rem; }
.rk-flash-err { background:rgba(244,63,94,0.08); color:#DC2626; border:1px solid rgba(244,63,94,0.2);
  padding:10px 16px; border-radius:10px; font-size:0.85rem; font-weight:600; margin-bottom:1.5rem; }

/* Section header */
.rk-sec-header {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:2px solid var(--border-light, #F3F4F6);
}
.rk-sec-title { font-size:1.15rem; font-weight:800; color:var(--text-main); display:flex; align-items:center; gap:8px; }
.rk-sec-count { background:rgba(255,77,109,0.1); color:#FF4D6D; font-size:0.7rem; font-weight:700; padding:3px 10px; border-radius:99px; }

/* Empty state */
.rk-empty { text-align:center; padding:4rem 1rem; color:var(--muted); }
.rk-empty-icon { font-size:3rem; margin-bottom:0.8rem; }
.rk-empty-h { font-size:1rem; font-weight:700; color:var(--text-main); margin-bottom:0.4rem; }
.rk-empty p { font-size:0.85rem; margin-bottom:1.2rem; }
.rk-empty-btn {
  display:inline-block; padding:10px 24px; background:var(--text-main); color:var(--bg-color);
  border-radius:99px; font-weight:700; font-size:0.88rem; text-decoration:none;
  transition:all 0.25s;
}
.rk-empty-btn:hover { background:#FF4D6D; transform:translateY(-2px); }

/* ---- WISHLIST GRID ---- */
.rk-grid {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr));
  gap:1.3rem;
}
.rk-book-card {
  background:var(--card-bg); border-radius:18px; overflow:hidden;
  border:1.5px solid var(--border-light, #F3F4F6); box-shadow:0 2px 12px rgba(0,0,0,0.05);
  transition:transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s, border-color 0.3s, background 0.35s;
  display:flex; flex-direction:column; position:relative;
}
.rk-book-card:hover { transform:translateY(-6px) scale(1.015); box-shadow:0 18px 40px rgba(0,0,0,0.1); border-color:rgba(255,77,109,0.2); }
.rk-book-cover {
  height:200px; position:relative; overflow:hidden;
}
.rk-book-cover img { width:100%; height:100%; object-fit:cover; display:block; transition:transform 0.4s; }
.rk-book-card:hover .rk-book-cover img { transform:scale(1.07); }
.rk-cover-gen {
  width:100%; height:100%; display:flex; align-items:center; justify-content:center; padding:1.2rem;
}
.rk-cover-gen span { color:rgba(255,255,255,0.95); font-weight:800; font-size:0.92rem; line-height:1.35;
  text-align:center; text-shadow:0 2px 8px rgba(0,0,0,0.2); display:-webkit-box;
  -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden; }
.rk-avail-dot {
  position:absolute; top:8px; left:8px; width:8px; height:8px;
  border-radius:50%; border:2px solid white;
}
.rk-avail-dot.avail { background:#10b981; }
.rk-avail-dot.out   { background:#f43f5e; }
.rk-card-body { padding:0.9rem 1rem 1rem; flex:1; display:flex; flex-direction:column; }
.rk-card-cat  { font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px;
  color:#FF4D6D; background:rgba(255,77,109,0.08); display:inline-block;
  padding:2px 8px; border-radius:99px; margin-bottom:5px; }
.rk-card-title { font-weight:800; font-size:0.88rem; line-height:1.3; color:var(--text-main); margin-bottom:2px;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.rk-card-author { font-size:0.72rem; color:var(--muted); font-style:italic; margin-bottom:auto; }
.rk-card-actions {
  display:flex; gap:6px; margin-top:10px; padding-top:8px; border-top:1px solid var(--border-light, #F9FAFB); flex-wrap:wrap;
}
.rk-btn {
  flex:1; padding:6px 10px; border-radius:8px; font-family:inherit;
  font-size:0.72rem; font-weight:700; cursor:pointer; text-decoration:none;
  text-align:center; transition:all 0.2s; border:1.5px solid transparent;
  white-space:nowrap;
}
.rk-btn.pinjam { background:var(--text-main); color:var(--bg-color); }
.rk-btn.pinjam:hover { background:#FF4D6D; border-color:#FF4D6D; }
.rk-btn.review { background:rgba(255,77,109,0.08); color:#FF4D6D; border-color:rgba(255,77,109,0.2); }
.rk-btn.review:hover { background:#FF4D6D; color:white; }
.rk-btn.del { background:var(--bg-tertiary, #F9FAFB); color:var(--muted); border-color:var(--border); max-width:36px; flex:none; }
.rk-btn.del:hover { background:#FEE2E2; color:#DC2626; border-color:#FECACA; }

/* ---- ANTRIAN CARDS ---- */
.rk-list { display:flex; flex-direction:column; gap:1rem; }
.rk-antri-card {
  background:var(--card-bg); border-radius:16px; border:1.5px solid var(--border-light, #F3F4F6);
  box-shadow:0 2px 12px rgba(0,0,0,0.04); padding:1.2rem 1.4rem;
  display:flex; gap:1.2rem; align-items:flex-start;
  transition:box-shadow 0.2s, border-color 0.2s, background 0.35s;
}
.rk-antri-card:hover { box-shadow:0 6px 24px rgba(0,0,0,0.08); border-color:rgba(255,184,0,0.3); }
.rk-antri-thumb {
  width:56px; height:76px; border-radius:10px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:0.42rem; font-weight:700; text-align:center; line-height:1.2;
  color:rgba(255,255,255,0.95); overflow:hidden;
  box-shadow:0 3px 10px rgba(0,0,0,0.15);
}
.rk-antri-thumb img { width:100%; height:100%; object-fit:cover; border-radius:8px; }
.rk-antri-body { flex:1; }
.rk-antri-title { font-weight:800; font-size:0.95rem; color:var(--text-main); margin-bottom:2px; }
.rk-antri-author { font-size:0.75rem; color:var(--muted); font-style:italic; margin-bottom:10px; }
.rk-antri-meta { display:flex; gap:0.8rem; flex-wrap:wrap; }
.rk-meta-pill {
  display:inline-flex; align-items:center; gap:5px;
  font-size:0.72rem; font-weight:600;
  padding:4px 12px; border-radius:99px;
}
.rk-meta-pill.waiting { background:rgba(245,158,11,0.1); color:#D97706; border:1px solid rgba(245,158,11,0.2); }
.rk-meta-pill.pos    { background:rgba(79,70,229,0.08); color:#4F46E5; border:1px solid rgba(79,70,229,0.15); }
.rk-meta-pill.date   { background:var(--bg-tertiary, #F9FAFB); color:var(--muted); border:1px solid var(--border); }

/* ---- DIPINJAM CARDS ---- */
.rk-pinjam-card {
  background:var(--card-bg); border-radius:18px; border:1.5px solid var(--border-light, #F3F4F6);
  box-shadow:0 2px 14px rgba(0,0,0,0.05); padding:1.4rem;
  display:flex; gap:1.4rem; align-items:flex-start; position:relative; overflow:hidden;
  transition:box-shadow 0.25s, background 0.35s;
}
.rk-pinjam-card:hover { box-shadow:0 8px 28px rgba(0,0,0,0.09); }
.rk-pinjam-card.danger { border-color:rgba(244,63,94,0.3); }
.rk-pinjam-card.warning { border-color:rgba(245,158,11,0.3); }
.rk-pinjam-card::before {
  content:''; position:absolute; top:0; left:0; right:0; height:3px;
}
.rk-pinjam-card.danger::before { background:linear-gradient(90deg,#f43f5e,#fb7185); }
.rk-pinjam-card.warning::before { background:linear-gradient(90deg,#f59e0b,#fcd34d); }
.rk-pinjam-card.ok::before { background:linear-gradient(90deg,#10b981,#34d399); }
.rk-pinjam-thumb {
  width:70px; height:96px; border-radius:12px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:0.45rem; font-weight:700; text-align:center; line-height:1.2;
  color:rgba(255,255,255,0.95); overflow:hidden;
  box-shadow:0 4px 14px rgba(0,0,0,0.15);
}
.rk-pinjam-thumb img { width:100%; height:100%; object-fit:cover; border-radius:10px; }
.rk-pinjam-body { flex:1; }
.rk-pinjam-cat  { font-size:0.6rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px;
  color:#FF4D6D; background:rgba(255,77,109,0.08); display:inline-block;
  padding:2px 8px; border-radius:99px; margin-bottom:5px; }
.rk-pinjam-title { font-weight:800; font-size:1rem; color:var(--text-main); margin-bottom:2px; }
.rk-pinjam-author { font-size:0.78rem; color:var(--muted); font-style:italic; margin-bottom:12px; }
.rk-pinjam-dates {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(110px,1fr)); gap:8px; margin-bottom:12px;
}
.rk-date-box { background:var(--bg-tertiary, #F9FAFB); border-radius:10px; padding:8px 10px; }
.rk-date-lbl { font-size:0.62rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px; }
.rk-date-val { font-size:0.82rem; font-weight:800; color:var(--text-main); margin-top:2px; }
.rk-date-val.danger  { color:#f43f5e; }
.rk-date-val.warning { color:#D97706; }
.rk-date-val.ok      { color:#10b981; }
/* Progress bar */
.rk-progress { height:5px; background:var(--border-light, #F3F4F6); border-radius:99px; overflow:hidden; margin-bottom:12px; }
.rk-progress-fill { height:100%; border-radius:99px; transition:width 0.3s; }
.rk-progress-fill.ok      { background:linear-gradient(90deg,#10b981,#34d399); }
.rk-progress-fill.warning { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
.rk-progress-fill.danger  { background:linear-gradient(90deg,#f43f5e,#fb7185); }
.rk-pinjam-actions { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.rk-perpanjang {
  padding:8px 18px; background:var(--text-main); color:var(--bg-color);
  border:none; border-radius:99px; font-family:inherit;
  font-size:0.8rem; font-weight:700; cursor:pointer;
  text-decoration:none; transition:all 0.2s;
}
.rk-perpanjang:hover { background:#FF4D6D; }
.rk-denda-badge {
  padding:7px 14px; background:rgba(244,63,94,0.08);
  color:#f43f5e; border:1px solid rgba(244,63,94,0.2);
  border-radius:99px; font-size:0.78rem; font-weight:700;
}
.rk-callnum { font-family:'Space Mono',monospace; font-size:0.68rem; color:var(--muted); background:var(--bg-tertiary, #F3F4F6); padding:5px 10px; border-radius:6px; }

/* ---- HISTORI ---- */
.rk-history-list { display:flex; flex-direction:column; gap:0.8rem; }
.rk-hist-card {
  background:var(--card-bg); border-radius:14px; border:1.5px solid var(--border-light, #F3F4F6);
  padding:1rem 1.2rem; display:flex; gap:1rem; align-items:center;
  transition:all 0.2s;
}
.rk-hist-card:hover { border-color:rgba(255,77,109,0.2); box-shadow:0 4px 16px rgba(0,0,0,0.06); }
.rk-hist-thumb {
  width:44px; height:60px; border-radius:8px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:0.38rem; font-weight:700; text-align:center; line-height:1.2;
  color:rgba(255,255,255,0.95); overflow:hidden;
  box-shadow:0 2px 8px rgba(0,0,0,0.12);
}
.rk-hist-thumb img { width:100%; height:100%; object-fit:cover; border-radius:6px; }
.rk-hist-body { flex:1; min-width:0; }
.rk-hist-title  { font-weight:700; font-size:0.88rem; color:var(--text-main); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rk-hist-author { font-size:0.72rem; color:var(--muted); font-style:italic; }
.rk-hist-dates  { display:flex; gap:1rem; margin-top:4px; flex-wrap:wrap; }
.rk-hist-date   { font-size:0.7rem; color:var(--muted); }
.rk-hist-date span { font-weight:700; color:var(--text-secondary, #374151); }
.rk-hist-right { display:flex; flex-direction:column; align-items:flex-end; gap:4px; flex-shrink:0; }
.rk-hist-badge {
  font-size:0.65rem; font-weight:700; padding:3px 10px;
  border-radius:99px; background:rgba(16,185,129,0.1); color:#059669;
}
.rk-hist-denda { font-size:0.72rem; font-weight:700; color:#f43f5e; }
.rk-hist-nodenda { font-size:0.72rem; color:var(--muted); }
.rk-hist-stars { color:#FBBF24; font-size:0.75rem; }
.rk-hist-review {
  font-size:0.7rem; font-weight:600; color:#FF4D6D;
  text-decoration:none; padding:3px 10px;
  background:rgba(255,77,109,0.08); border-radius:99px;
}
.rk-hist-review:hover { background:rgba(255,77,109,0.15); }

@media (max-width:768px) {
  .rk-title { font-size:2rem; }
  .rk-hero { padding:2rem 5% 0; }
  .rk-grid { grid-template-columns:repeat(2,1fr); gap:1rem; }
  .rk-pinjam-card { flex-direction:column; }
  .rk-pinjam-thumb { width:100%; height:80px; border-radius:10px; }
}
@media (min-width:1200px) {
  .rk-grid { grid-template-columns:repeat(5,1fr); }
}

/* Bayar Denda button */
.rk-bayar-btn {
  padding:7px 16px; background:linear-gradient(135deg,#FF4D6D,#590D22); color:white;
  border:none; border-radius:99px; font-family:inherit; font-size:0.78rem;
  font-weight:700; cursor:pointer; transition:all 0.25s;
  box-shadow:0 2px 10px rgba(255,77,109,0.35); white-space:nowrap;
}
.rk-bayar-btn:hover { transform:translateY(-2px); box-shadow:0 6px 18px rgba(255,77,109,0.5); }

/* ---- DENDA MODAL ---- */
.dm-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,0.5);
  backdrop-filter:blur(6px); z-index:999;
  opacity:0; pointer-events:none; transition:opacity 0.3s;
}
.dm-overlay.show { opacity:1; pointer-events:all; }

.dm-modal {
  position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) scale(0.92);
  width:95%; max-width:440px; max-height:90vh; overflow-y:auto;
  background:var(--card-bg); border-radius:24px; z-index:1000;
  box-shadow:0 25px 80px rgba(0,0,0,0.25);
  opacity:0; pointer-events:none; transition:all 0.35s cubic-bezier(0.34,1.56,0.64,1);
}
.dm-modal.show { opacity:1; pointer-events:all; transform:translate(-50%,-50%) scale(1); }

.dm-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:1.4rem 1.5rem 0;
}
.dm-header-left { display:flex; align-items:center; gap:10px; }
.dm-icon { font-size:1.6rem; }
.dm-title { font-weight:800; font-size:1.1rem; color:var(--text-main); }
.dm-subtitle { font-size:0.75rem; color:var(--muted); max-width:250px; white-space:nowrap;
  overflow:hidden; text-overflow:ellipsis; }
.dm-close {
  width:32px; height:32px; border-radius:50%; border:1.5px solid var(--border);
  background:var(--card-bg); color:var(--muted); font-size:0.9rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center; transition:all 0.2s;
}
.dm-close:hover { background:#FEE2E2; border-color:#FECACA; color:#DC2626; }

.dm-amount {
  text-align:center; padding:1.5rem 1.5rem 1rem;
  border-bottom:1px solid var(--border-light, #F3F4F6);
}
.dm-amount-label { font-size:0.72rem; font-weight:700; color:var(--muted); text-transform:uppercase;
  letter-spacing:0.5px; margin-bottom:4px; }
.dm-amount-value { font-size:2rem; font-weight:900; color:#f43f5e;
  font-family:'Space Mono',monospace; }

/* Methods */
.dm-methods-label { padding:1.2rem 1.5rem 0.8rem; font-size:0.82rem; font-weight:700; color:var(--text-secondary, #374151); }
.dm-methods {
  display:grid; grid-template-columns:1fr 1fr; gap:12px;
  padding:0 1.5rem 1.5rem;
}
.dm-method {
  background:var(--bg-tertiary, #F9FAFB); border:2px solid var(--border); border-radius:16px;
  padding:1.3rem 1rem; cursor:pointer; text-align:center;
  transition:all 0.25s; font-family:inherit;
}
.dm-method:hover { border-color:#FF4D6D; background:#fff5f7; transform:translateY(-3px);
  box-shadow:0 8px 20px rgba(255,77,109,0.15); }
.dm-method-icon { font-size:2rem; margin-bottom:6px; }
.dm-method-name { font-weight:800; font-size:1rem; color:var(--text-main); margin-bottom:2px; }
.dm-method-desc { font-size:0.72rem; color:var(--muted); }

/* Cash notice */
.dm-cash-notice {
  margin:1.2rem 1.5rem; padding:1.5rem; border-radius:16px;
  background:linear-gradient(135deg,#ecfdf5,#d1fae5);
  border:1px solid rgba(16,185,129,0.2); text-align:center;
}
.dm-cash-icon { font-size:2.5rem; margin-bottom:0.6rem; }
.dm-cash-title { font-size:1.05rem; font-weight:800; color:#065f46; margin-bottom:0.5rem; }
.dm-cash-text { font-size:0.85rem; color:#047857; line-height:1.6; margin-bottom:1rem; }
.dm-cash-info { text-align:left; }
.dm-cash-row {
  display:flex; align-items:center; gap:8px;
  font-size:0.8rem; color:#065f46; padding:5px 0;
  border-bottom:1px solid rgba(16,185,129,0.1);
}
.dm-cash-row:last-child { border-bottom:none; }

/* QRIS */
.dm-qris-wrap { padding:1.2rem 1.5rem; text-align:center; }
.dm-qris-label { font-size:0.82rem; font-weight:700; color:var(--text-secondary, #374151); margin-bottom:1rem; }
.dm-qris-img {
  background:var(--card-bg); border:2px solid var(--border); border-radius:16px;
  padding:12px; display:inline-block; margin:0 auto 1rem;
  box-shadow:0 4px 16px rgba(0,0,0,0.06);
}
.dm-qris-img img { max-width:240px; width:100%; height:auto; border-radius:8px; display:block; }
.dm-qris-amount { font-size:0.88rem; font-weight:700; color:var(--text-secondary, #374151); margin-bottom:0.8rem; }
.dm-qris-amount strong { color:#f43f5e; font-family:'Space Mono',monospace; }
.dm-qris-timer {
  display:inline-flex; align-items:center; gap:6px;
  background:rgba(245,158,11,0.1); color:#D97706; border:1px solid rgba(245,158,11,0.2);
  padding:6px 14px; border-radius:99px; font-size:0.78rem; font-weight:600;
  animation: pulse-timer 2s ease-in-out infinite;
}
@keyframes pulse-timer {
  0%,100% { opacity:1; }
  50% { opacity:0.6; }
}

/* Status panel */
.dm-status {
  margin:1rem 1.5rem; padding:1.5rem; border-radius:16px;
  text-align:center; animation: fadeInUp 0.4s ease;
}
@keyframes fadeInUp {
  from { opacity:0; transform:translateY(12px); }
  to { opacity:1; transform:translateY(0); }
}
.dm-status-berhasil { background:linear-gradient(135deg,#ecfdf5,#d1fae5); border:1px solid rgba(16,185,129,0.3); }
.dm-status-tertunda { background:linear-gradient(135deg,#fffbeb,#fef3c7); border:1px solid rgba(245,158,11,0.3); }
.dm-status-gagal    { background:linear-gradient(135deg,#fef2f2,#fecaca); border:1px solid rgba(244,63,94,0.3); }
.dm-status-icon { font-size:2.5rem; margin-bottom:0.5rem; }
.dm-status-title { font-size:1.1rem; font-weight:800; margin-bottom:0.4rem; }
.dm-status-berhasil .dm-status-title { color:#065f46; }
.dm-status-tertunda .dm-status-title { color:#92400e; }
.dm-status-gagal .dm-status-title    { color:#991b1b; }
.dm-status-desc { font-size:0.82rem; line-height:1.6; }
.dm-status-berhasil .dm-status-desc { color:#047857; }
.dm-status-tertunda .dm-status-desc { color:#b45309; }
.dm-status-gagal .dm-status-desc    { color:#dc2626; }

/* Back button */
.dm-back {
  display:block; width:calc(100% - 3rem); margin:0 1.5rem 1.5rem;
  padding:10px; background:var(--bg-tertiary, #F9FAFB); border:1.5px solid var(--border);
  border-radius:12px; font-family:inherit; font-size:0.82rem;
  font-weight:600; color:var(--muted); cursor:pointer; text-align:center;
  transition:all 0.2s;
}
.dm-back:hover { border-color:#FF4D6D; color:#FF4D6D; background:#fff5f7; }

/* ---- KEMBALIKAN BUKU ---- */
.rk-kembali-btn {
  padding:8px 18px; background:linear-gradient(135deg,#059669,#10b981);
  color:white; border:none; border-radius:99px; font-family:inherit;
  font-size:0.8rem; font-weight:700; cursor:pointer;
  transition:all 0.25s; box-shadow:0 2px 10px rgba(16,185,129,0.35);
}
.rk-kembali-btn:hover {
  transform:translateY(-2px); box-shadow:0 6px 18px rgba(16,185,129,0.5);
  background:linear-gradient(135deg,#047857,#059669);
}
.rk-kembali-info {
  font-size:0.7rem; font-weight:600; color:#D97706;
  background:rgba(245,158,11,0.1); border:1px solid rgba(245,158,11,0.2);
  padding:5px 12px; border-radius:99px; white-space:nowrap;
}

/* ---- KEMBALI MODAL ---- */
.km-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,0.5);
  backdrop-filter:blur(6px); z-index:999;
  opacity:0; pointer-events:none; transition:opacity 0.3s;
}
.km-overlay.show { opacity:1; pointer-events:all; }

.km-modal {
  position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) scale(0.92);
  width:95%; max-width:460px; max-height:90vh; overflow-y:auto;
  background:var(--card-bg, #fff); border-radius:24px; z-index:1000;
  box-shadow:0 25px 80px rgba(0,0,0,0.25);
  opacity:0; pointer-events:none; transition:all 0.35s cubic-bezier(0.34,1.56,0.64,1);
}
.km-modal.show { opacity:1; pointer-events:all; transform:translate(-50%,-50%) scale(1); }

.km-header {
  background:linear-gradient(135deg,#059669,#10b981);
  padding:1.5rem 1.8rem; color:white; border-radius:24px 24px 0 0;
  display:flex; align-items:flex-start; justify-content:space-between;
}
.km-header-icon { font-size:2rem; margin-right:12px; }
.km-header-title { font-weight:800; font-size:1.15rem; margin-bottom:3px; }
.km-header-sub { font-size:0.78rem; opacity:0.85; }
.km-close {
  width:32px; height:32px; border-radius:50%; border:none;
  background:rgba(255,255,255,0.2); color:white; font-size:1rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center; transition:all 0.2s;
  flex-shrink:0;
}
.km-close:hover { background:rgba(255,255,255,0.35); }

.km-body { padding:1.5rem 1.8rem; }

.km-book-info {
  background:var(--bg-tertiary, #F0FDF4); border:1px solid rgba(16,185,129,0.2);
  border-radius:14px; padding:1rem 1.2rem; margin-bottom:1.2rem;
}
.km-book-label { font-size:0.68rem; font-weight:700; color:#059669;
  text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; }
.km-book-title { font-weight:800; font-size:1rem; color:var(--text-main, #111827); margin-bottom:2px; }
.km-book-callnum { font-family:'Space Mono',monospace; font-size:0.78rem; color:var(--muted, #6B7280); }
.km-book-tempo { font-size:0.78rem; color:#059669; font-weight:600; margin-top:6px; }

.km-steps { margin-bottom:1.5rem; }
.km-steps-title { font-weight:800; font-size:0.88rem; color:var(--text-main, #111827); margin-bottom:12px; }
.km-step {
  display:flex; gap:12px; align-items:flex-start; padding:10px 0;
  border-bottom:1px solid var(--border-light, #F3F4F6);
}
.km-step:last-child { border-bottom:none; }
.km-step-num {
  width:28px; height:28px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,#059669,#10b981); color:white;
  display:flex; align-items:center; justify-content:center;
  font-weight:800; font-size:0.72rem;
}
.km-step-text { font-size:0.85rem; color:var(--text-main, #374151); line-height:1.5; }
.km-step-text strong { color:var(--text-main, #111827); }

.km-location {
  background:var(--bg-tertiary, #F9FAFB); border-radius:12px;
  padding:12px 14px; margin-bottom:1.5rem;
  display:flex; gap:10px; align-items:center;
}
.km-location-icon { font-size:1.4rem; }
.km-location-text { font-size:0.82rem; color:var(--muted, #6B7280); line-height:1.5; }
.km-location-text strong { color:var(--text-main, #111827); }

.km-actions { display:flex; gap:10px; }
.km-btn-confirm {
  flex:1; padding:12px; background:linear-gradient(135deg,#059669,#10b981);
  color:white; border:none; border-radius:14px; font-family:inherit;
  font-size:0.9rem; font-weight:700; cursor:pointer;
  transition:all 0.25s; box-shadow:0 4px 16px rgba(16,185,129,0.35);
  text-decoration:none; text-align:center; display:block;
}
.km-btn-confirm:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(16,185,129,0.5); }
.km-btn-cancel {
  padding:12px 20px; background:var(--bg-tertiary, #F9FAFB); color:var(--muted, #6B7280);
  border:1.5px solid var(--border, #E5E7EB); border-radius:14px; font-family:inherit;
  font-size:0.9rem; font-weight:600; cursor:pointer;
  transition:all 0.2s;
}
.km-btn-cancel:hover { border-color:var(--accent, #FF4D6D); color:var(--accent, #FF4D6D); }
/* Rakku aesthetic bookshelf styles */


/* Grid Layout for Wishlist */
.rk-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px,1fr)); gap:1.5rem; margin-top:2rem; }
.rk-grid-card {
  background:var(--card-bg); border-radius:12px; border:1px solid var(--border-light, #F3F4F6);
  overflow:hidden; display:flex; flex-direction:column; transition:all 0.2s;
  text-decoration:none;
}
.rk-grid-card:hover { transform:translateY(-4px); box-shadow:0 12px 25px rgba(0,0,0,0.08); border-color:rgba(255,77,109,0.2); }
.rk-grid-thumb {
  width:100%; aspect-ratio:2/3; background-color:var(--bg-tertiary);
  display:flex; align-items:center; justify-content:center;
  color:var(--muted); font-weight:700; font-size:0.8rem; text-align:center; padding:10px;
}
.rk-grid-thumb img { width:100%; height:100%; object-fit:cover; }
.rk-grid-body { padding:12px; display:flex; flex-direction:column; gap:4px; flex:1; }
.rk-grid-title { font-weight:700; font-size:0.9rem; color:var(--text-main); line-height:1.3; }
.rk-grid-author { font-size:0.75rem; color:var(--muted); font-style:italic; }
.rk-grid-actions { display:flex; gap:8px; margin-top:auto; padding-top:10px; }
.rk-btn-sm {
  padding:6px 12px; border-radius:99px; font-size:0.7rem; font-weight:700;
  text-decoration:none; text-align:center; flex:1;
}
.rk-btn-detail { background:rgba(255,77,109,0.1); color:#FF4D6D; }
.rk-btn-detail:hover { background:rgba(255,77,109,0.2); }
.rk-btn-hapus { background:#FEE2E2; color:#f43f5e; }
.rk-btn-hapus:hover { background:#FECACA; }

</style>

<main>

<!-- HERO -->
<div class="hero modern-hero rk-hero">
  <!-- Decorative background elements -->
  <div class="hero-bg-shapes">
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>
    <div class="shape shape-3"></div>
  </div>

  <div class="rk-hero-inner">
    <div class="rk-hero-top">
      <div>
        <div class="rk-tag">📚 <?= $currentLang === 'en' ? 'Personal Bookshelf' : 'Rak Buku Pribadi' ?></div>
        <h1 class="rk-title">Rak <em>Ku</em></h1>
        <p class="rk-sub"><?= $currentLang === 'en' ? 'Manage your wishlist and book borrowing queues' : 'Kelola wishlist dan antrian peminjaman bukumu' ?></p>
      </div>
    </div>
    
    <!-- Tab stats strip (styled like a bottom nav) -->
    <div class="rk-stats">
      <a class="rk-stat active" href="#rak-dipinjam">
        <div class="rk-stat-icon">📖</div>
        <div class="rk-stat-lbl"><?= $currentLang === 'en' ? 'Borrowed' : 'Dipinjam' ?></div>
      </a>
      <a class="rk-stat" href="#rak-antrian">
        <div class="rk-stat-icon">⏳</div>
        <div class="rk-stat-lbl"><?= $currentLang === 'en' ? 'Queue' : 'Antrian' ?></div>
      </a>
      <a class="rk-stat" href="#rak-wishlist">
        <div class="rk-stat-icon">📌</div>
        <div class="rk-stat-lbl">Wishlist</div>
      </a>
      <a class="rk-stat" href="booktalk.php">
        <div class="rk-stat-icon">💬</div>
        <div class="rk-stat-lbl">Journal</div>
      </a>
    </div>
  </div>
</div>

<!-- CONTENT -->
<div class="rk-content">

  <?php if($flash): ?>
    <div class="<?= $flashType==='error' ? 'rk-flash-err' : 'rk-flash-ok' ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <style>
  /* CSS for Bookshelf Rows */
  .shelf-section { margin-bottom: 4rem; position: relative; }
  .shelf-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; padding: 0 1rem; }
  .shelf-title { font-size: 1.25rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
  .shelf-title .badge { background: linear-gradient(135deg, var(--accent1), var(--accent2)); color: white; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 2px 5px rgba(255,77,109,0.3); }
  
  .shelf-container { position: relative; padding-top: 10px; margin: 0 1rem; }
  .shelf-scroll { display: flex; gap: 1.5rem; overflow-x: auto; padding: 0 1.5rem 10px 1.5rem; scroll-snap-type: x mandatory; scrollbar-width: none; position: relative; z-index: 5; align-items: flex-end; }
  .shelf-scroll::-webkit-scrollbar { display: none; }
  
  /* The physical wooden/glass shelf board */
  .shelf-board { position: absolute; bottom: 0; left: 0; right: 0; height: 16px; background: linear-gradient(to bottom, #cbd5e1 0%, #94a3b8 100%); border-radius: 4px; box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.2), inset 0 2px 3px rgba(255,255,255,0.6); z-index: 2; }
  [data-theme="dark"] .shelf-board { background: linear-gradient(to bottom, #334155 0%, #1e293b 100%); box-shadow: 0 8px 15px -3px rgba(0, 0, 0, 0.6), inset 0 2px 3px rgba(255,255,255,0.1); }
  
  .shelf-wall-shadow { position: absolute; bottom: 15px; left: 1rem; right: 1rem; height: 30px; background: transparent; box-shadow: 0 15px 15px rgba(0,0,0,0.06); z-index: 1; border-radius: 50%; }
  [data-theme="dark"] .shelf-wall-shadow { box-shadow: 0 15px 15px rgba(0,0,0,0.2); }
  
  .shelf-scroll .koleksi-card { flex: 0 0 190px; scroll-snap-align: start; margin-bottom: 0; transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); transform-origin: bottom center; cursor: pointer; text-decoration: none; border: 1.5px solid var(--border-light, #F3F4F6); border-radius: 16px; background: var(--card-bg, #fff); box-shadow: 0 6px 15px rgba(0,0,0,0.06); display: flex; flex-direction: column; overflow: hidden; }
  .shelf-scroll .koleksi-card:hover { transform: translateY(-8px) scale(1.03) rotate(-1deg); box-shadow: 0 15px 30px rgba(0,0,0,0.12); border-color: rgba(255,77,109,0.3); }
  .shelf-scroll .koleksi-card-visual { height: 210px; position: relative; overflow: hidden; background: #f9fafb; flex-shrink: 0; }
  .shelf-scroll .koleksi-cover-img { width: 100%; height: 100%; }
  .shelf-scroll .koleksi-cover-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s; }
  .shelf-scroll .koleksi-card:hover .koleksi-cover-img img { transform: scale(1.08); }
  .shelf-scroll .koleksi-cover-gen { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; padding: 1.2rem; }
  .shelf-scroll .koleksi-cover-text { color: rgba(255,255,255,0.95); font-weight: 800; font-size: 0.9rem; line-height: 1.4; text-align: center; text-shadow: 0 2px 8px rgba(0,0,0,0.25); display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
  
  .shelf-scroll .rk-status-badge { position: absolute; top: 10px; left: 10px; font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: white; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 10; display: inline-block; width: max-content; }
  .shelf-scroll .koleksi-card-emoji { position: absolute; bottom: 10px; right: 10px; font-size: 1.5rem; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
  
  .shelf-scroll .koleksi-card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; }
  .shelf-scroll .koleksi-title { font-size: 0.95rem; font-weight: 800; line-height: 1.35; color: var(--text-main); margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .shelf-scroll .koleksi-author { font-size: 0.8rem; color: var(--muted); font-style: italic; margin-bottom: auto; }
  .shelf-scroll .koleksi-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-light, #F3F4F6); }
  .shelf-scroll .koleksi-rating { font-size: 0.8rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 4px; }
  .shelf-scroll .koleksi-rating span { color: #FBBF24; }
  
  .rk-empty-shelf { padding: 2rem 1.5rem; text-align: center; color: var(--muted); font-style: italic; background: transparent; width: 100%; font-size: 0.95rem; }
  
  .rk-wish-del { position: absolute; top: 8px; right: 8px; background: rgba(225,29,72,0.9); color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; z-index: 10; font-size: 12px; text-decoration: none; box-shadow: 0 4px 10px rgba(225,29,72,0.3); opacity: 0; transition: all 0.25s; backdrop-filter: blur(4px); }
  .koleksi-card:hover .rk-wish-del { opacity: 1; transform: scale(1.1); }
  .rk-wish-del:hover { background: #e11d48; transform: scale(1.2) !important; }
  </style>

  <?php
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
    $emojis = ['📖','📚','✨','🎯','💡','🔮','🌟','⚡'];
  ?>

  <!-- ===== SHELF: SEDANG DIPINJAM ===== -->
  <div class="shelf-section" id="rak-dipinjam">
    <div class="shelf-header">
      <div class="shelf-title">📖 <?= $currentLang === 'en' ? 'Currently Borrowed' : 'Sedang Dipinjam' ?> <span class="badge"><?= count($listDipinjam) ?>/<?= MAKS_PINJAM ?></span></div>
    </div>
    <div class="shelf-container">
      <div class="shelf-wall-shadow"></div>
      <div class="shelf-scroll">
        <?php if(empty($listDipinjam)): ?>
          <div class="rk-empty-shelf"><?= $currentLang === 'en' ? 'Empty. No books are currently borrowed.' : 'Kosong. Tidak ada buku yang sedang dipinjam.' ?></div>
        <?php else: ?>
          <?php foreach($listDipinjam as $i => $s):
            $gradient = $gradients[$i % count($gradients)];
            $covSrc = bookCoverSrc($s);
            $overdue = $s['sisa_hari'] < 0;
            $warning = $s['sisa_hari'] == 0;
            $denda = $overdue ? max($s['denda'], abs($s['sisa_hari']) * DENDA_PER_BUKU) : 0;
            $statusBg = $overdue ? '#e11d48' : ($warning ? '#d97706' : '#059669');
            $statusTxt = $overdue ? ($currentLang === 'en' ? 'Overdue ' : 'Telat ').abs($s['sisa_hari']).($currentLang === 'en' ? 'd' : 'h') : ($warning ? ($currentLang === 'en' ? 'Today' : 'Hari ini') : $s['sisa_hari'].($currentLang === 'en' ? 'd left' : 'h lagi'));
          ?>
          <div class="koleksi-card" style="--card-gradient: <?= $gradient ?>; cursor: pointer;" onclick="openInfoPinjam(<?= $s['id'] ?>, '<?= addslashes($s['judul']) ?>', '<?= addslashes($s['no_kelas']) ?>', '<?= date('d M Y',strtotime($s['jatuh_tempo'])) ?>', <?= $overdue?'true':'false' ?>, <?= abs($s['sisa_hari']) ?>, <?= $denda ?>, '<?= $s['denda_status'] ?? '' ?>', <?= $s['perpanjangan'] ?>, <?= MAKS_PERPANJANG ?>)">
            <div class="koleksi-card-visual">
              <?php if ($covSrc): ?>
              <div class="koleksi-cover-img"><img src="<?= $covSrc ?>" alt="<?= htmlspecialchars($s['judul']) ?>"></div>
              <?php else: ?>
              <div class="koleksi-cover-gen" style="background:<?= $gradient ?>;">
                <span class="koleksi-cover-text"><?= htmlspecialchars(substr($s['judul'],0,30)) ?></span>
              </div>
              <?php endif; ?>
              <div class="rk-status-badge" style="background:<?= $statusBg ?>;"><?= $statusTxt ?></div>
            </div>
            <div class="koleksi-card-body">
              <h3 class="koleksi-title"><?= htmlspecialchars($s['judul']) ?></h3>
              <div class="koleksi-author"><?= htmlspecialchars($s['pengarang']) ?></div>
            </div>
            <!-- Action indicators instead of buttons -->
            <div style="padding: 0 10px 10px 10px; text-align:center;">
              <span style="font-size:0.75rem; color:var(--muted); font-weight:600;"><?= $currentLang === 'en' ? 'Click for info & actions' : 'Klik untuk info & aksi' ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="shelf-board"></div>
    </div>
  </div>

  <!-- ===== SHELF: SEDANG ANTRI ===== -->
  <div class="shelf-section" id="rak-antrian">
    <div class="shelf-header">
      <div class="shelf-title">⏳ <?= $currentLang === 'en' ? 'Waiting in Queue' : 'Menunggu Antrian' ?> <span class="badge"><?= count($listAntrian) ?></span></div>
    </div>
    <div class="shelf-container">
      <div class="shelf-wall-shadow"></div>
      <div class="shelf-scroll">
        <?php if(empty($listAntrian)): ?>
          <div class="rk-empty-shelf"><?= $currentLang === 'en' ? 'Empty. You are not queuing for any books.' : 'Kosong. Kamu sedang tidak mengantri buku apapun.' ?></div>
        <?php else: ?>
          <?php foreach($listAntrian as $i => $a):
            $gradient = $gradients[$i % count($gradients)];
            $covSrc = bookCoverSrc($a);
          ?>
          <a href="search.php?detail=<?= $a['book_id'] ?>" class="koleksi-card" style="--card-gradient: <?= $gradient ?>;">
            <div class="koleksi-card-visual">
              <?php if ($covSrc): ?>
              <div class="koleksi-cover-img"><img src="<?= $covSrc ?>" alt="<?= htmlspecialchars($a['judul']) ?>"></div>
              <?php else: ?>
              <div class="koleksi-cover-gen" style="background:<?= $gradient ?>;">
                <span class="koleksi-cover-text"><?= htmlspecialchars(substr($a['judul'],0,30)) ?></span>
              </div>
              <?php endif; ?>
              <div class="rk-status-badge" style="background:#0284c7;"><?= $currentLang === 'en' ? 'Queue pos ' : 'Antrian ke-' ?><?= $a['posisi_antrian'] ?></div>
            </div>
            <div class="koleksi-card-body">
              <h3 class="koleksi-title"><?= htmlspecialchars($a['judul']) ?></h3>
              <div class="koleksi-author"><?= htmlspecialchars($a['pengarang']) ?></div>
            </div>
            <div style="padding: 0 10px 10px 10px;">
              <span class="rk-btn-sm" style="background:var(--bg-tertiary); color:var(--text-main); display:block;"><?= $currentLang === 'en' ? 'View Book' : 'Lihat Buku' ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="shelf-board"></div>
    </div>
  </div>

  <!-- ===== SHELF: WISHLIST ===== -->
  <div class="shelf-section" id="rak-wishlist">
    <div class="shelf-header">
      <div class="shelf-title">📌 <?= $currentLang === 'en' ? 'Saved / Wishlist' : 'Disimpan / Wishlist' ?> <span class="badge"><?= count($listWishlist) ?></span></div>
    </div>
    <div class="shelf-container">
      <div class="shelf-wall-shadow"></div>
      <div class="shelf-scroll">
        <?php if(empty($listWishlist)): ?>
          <div class="rk-empty-shelf"><?= $currentLang === 'en' ? 'Empty. Explore the collection to add to wishlist!' : 'Kosong. Jelajahi koleksi untuk menambahkan wishlist!' ?></div>
        <?php else: ?>
          <?php foreach($listWishlist as $i => $s):
            $gradient = $gradients[$i % count($gradients)];
            $emoji = $emojis[$i % count($emojis)];
            $covSrc = bookCoverSrc($s);
          ?>
          <a href="search.php?detail=<?= $s['book_id'] ?>" class="koleksi-card" style="--card-gradient: <?= $gradient ?>;">
            <div class="koleksi-card-visual">
              <?php if ($covSrc): ?>
              <div class="koleksi-cover-img"><img src="<?= $covSrc ?>" alt="<?= htmlspecialchars($s['judul']) ?>"></div>
              <?php else: ?>
              <div class="koleksi-cover-gen" style="background:<?= $gradient ?>;">
                <span class="koleksi-cover-text"><?= htmlspecialchars(substr($s['judul'],0,30)) ?></span>
              </div>
              <?php endif; ?>
              <div class="koleksi-card-emoji"><?= $emoji ?></div>
            </div>
            <div class="koleksi-card-body">
              <h3 class="koleksi-title"><?= htmlspecialchars($s['judul']) ?></h3>
              <div class="koleksi-author"><?= htmlspecialchars($s['pengarang']) ?></div>
            </div>
            <div class="koleksi-card-footer">
              <div class="koleksi-rating"><span>⭐</span> <?= number_format($s['avg_rating'] ?? 0, 1) ?></div>
            </div>
            <object><a href="?hapus=<?= $s['id'] ?>" class="rk-wish-del" onclick="return confirm('<?= $currentLang === 'en' ? 'Remove from shelf?' : 'Hapus dari rak?' ?>')" title="<?= $currentLang === 'en' ? 'Remove' : 'Hapus' ?>">✖</a></object>
          </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="shelf-board"></div>
    </div>
  </div>

</div><!-- /rk-content -->

<!-- ===== BAYAR DENDA MODAL ===== -->
<div class="dm-overlay" id="dendaOverlay" onclick="closeBayarModal()"></div>
<div class="dm-modal" id="dendaModal">
  <div class="dm-header">
    <div class="dm-header-left">
      <div class="dm-icon">💰</div>
      <div>
        <div class="dm-title"><?= $currentLang === 'en' ? 'Pay Fine' : 'Bayar Denda' ?></div>
        <div class="dm-subtitle" id="dmBookTitle">—</div>
      </div>
    </div>
    <button class="dm-close" onclick="closeBayarModal()">✕</button>
  </div>

  <div class="dm-amount">
    <div class="dm-amount-label"><?= $currentLang === 'en' ? 'Total Fine' : 'Total Denda' ?></div>
    <div class="dm-amount-value" id="dmAmount">Rp 0</div>
  </div>

  <!-- Step 1: Pilih metode -->
  <div id="dmStep1">
    <div class="dm-methods-label"><?= $currentLang === 'en' ? 'Choose payment method:' : 'Pilih metode pembayaran:' ?></div>
    <div class="dm-methods">
      <button class="dm-method" onclick="chooseCash()">
        <div class="dm-method-icon">🏦</div>
        <div class="dm-method-name"><?= $currentLang === 'en' ? 'Cash' : 'Tunai' ?></div>
        <div class="dm-method-desc"><?= $currentLang === 'en' ? 'Pay directly at RBC' : 'Bayar langsung di RBC' ?></div>
      </button>
      <button class="dm-method" onclick="chooseQris()">
        <div class="dm-method-icon">📱</div>
        <div class="dm-method-name">QRIS</div>
        <div class="dm-method-desc"><?= $currentLang === 'en' ? 'Scan & pay online' : 'Scan & bayar online' ?></div>
      </button>
    </div>
  </div>

  <!-- Step 2a: Cash -->
  <div id="dmCash" style="display:none;">
    <div class="dm-cash-notice">
      <div class="dm-cash-icon">🏛️</div>
      <div class="dm-cash-title"><?= $currentLang === 'en' ? 'Please Visit Circulation Desk' : 'Silahkan Datang ke Layanan Sirkulasi' ?></div>
      <div class="dm-cash-text">
        <?= $currentLang === 'en' ? 'Visit <strong>RBC FISIP UNAIR</strong> to pay the fine in cash to the circulation staff.' : 'Kunjungi <strong>RBC FISIP UNAIR</strong> untuk membayar denda secara tunai kepada petugas sirkulasi.' ?>
      </div>
      <div class="dm-cash-info">
        <div class="dm-cash-row"><span>📍</span> <span>Gedung A FISIP Lt. 2 Ruang 201, Kampus B UNAIR</span></div>
        <div class="dm-cash-row"><span>🕐</span> <span>Senin – Jumat, 08:00 – 16:00 WIB</span></div>
        <div class="dm-cash-row"><span>💵</span> <span><?= $currentLang === 'en' ? 'Prepare exact change if possible' : 'Siapkan uang pas jika memungkinkan' ?></span></div>
      </div>
    </div>
    <button class="dm-back" onclick="backToMethods()">← <?= $currentLang === 'en' ? 'Choose Another Method' : 'Pilih Metode Lain' ?></button>
  </div>

  <!-- Step 2b: QRIS -->
  <div id="dmQris" style="display:none;">
    <div class="dm-qris-wrap">
      <div class="dm-qris-label"><?= $currentLang === 'en' ? 'Scan QRIS below' : 'Scan QRIS di bawah ini' ?></div>
      <div class="dm-qris-img">
        <img src="assets/qris.jpg" alt="QRIS Pembayaran Denda" id="dmQrisImg">
      </div>
      <div class="dm-qris-amount">Total: <strong id="dmQrisAmount">Rp 0</strong></div>
      <div style="margin-top: 15px;">
        <a href="#" id="dmQrisBtn" class="dm-back" style="background:#10b981; color:white; border:none; margin:0 auto 10px; width:calc(100% - 3rem);"><?= $currentLang === 'en' ? 'I Have Paid' : 'Saya Sudah Bayar' ?></a>
      </div>
    </div>
    <button class="dm-back" onclick="backToMethods()" id="dmQrisBack">← <?= $currentLang === 'en' ? 'Choose Another Method' : 'Pilih Metode Lain' ?></button>
  </div>
</div>

<!-- ===== INFO PINJAM MODAL ===== -->
<div class="km-overlay" id="infoPinjamOverlay" onclick="closeInfoPinjam()"></div>
<div class="km-modal" id="infoPinjamModal">
  <div class="km-header">
    <div style="display:flex;align-items:flex-start;">
      <span class="km-header-icon" id="ipIcon">📖</span>
      <div>
        <div class="km-header-title"><?= $currentLang === 'en' ? 'Borrowing Details' : 'Detail Peminjaman' ?></div>
        <div class="km-header-sub"><?= $currentLang === 'en' ? 'Return and extension information' : 'Informasi pengembalian dan perpanjangan' ?></div>
      </div>
    </div>
    <button class="km-close" onclick="closeInfoPinjam()">✕</button>
  </div>

  <div class="km-body">
    <!-- Book info -->
    <div class="km-book-info">
      <div class="km-book-title" id="ipBookTitle">—</div>
      <div class="km-book-callnum" id="ipBookCallnum">—</div>
      <div class="km-book-tempo" id="ipBookTempoLabel" style="margin-top:10px; font-size:0.85rem;">—</div>
    </div>

    <!-- Alert for Overdue -->
    <div id="ipOverdueAlert" style="display:none; background:rgba(244,63,94,0.1); border:1px solid rgba(244,63,94,0.3); padding:12px; border-radius:10px; margin-bottom:1.5rem;">
      <div style="color:#DC2626; font-weight:700; font-size:0.85rem; margin-bottom:4px;" id="ipOverdueTitle">⚠️ Terlambat 0 hari</div>
      <div style="color:#991B1B; font-size:0.8rem;"><?= $currentLang === 'en' ? 'You are charged a late fine of ' : 'Anda dikenakan denda keterlambatan sebesar ' ?><strong id="ipDendaAmount">Rp 0</strong><?= $currentLang === 'en' ? '. Please pay the fine before returning the book.' : '. Silakan bayar denda sebelum mengembalikan buku.' ?></div>
    </div>

    <!-- Location -->
    <div class="km-location">
      <span class="km-location-icon">📍</span>
      <div class="km-location-text">
        <strong>Gedung A FISIP Lt. 2 Ruang 201</strong><br>
        Kampus B UNAIR &middot; <?= $currentLang === 'en' ? 'Monday &ndash; Friday' : 'Senin &ndash; Jumat' ?>, 08:00 &ndash; 16:00 WIB
      </div>
    </div>

    <!-- Actions -->
    <div class="km-actions" id="ipActions" style="flex-direction:column; gap:10px;">
      <!-- Buttons will be dynamically injected via JS -->
    </div>
  </div>
</div>

</main>
<?php include 'includes/footer.php'; ?>

<script>
let bayarCid = 0;
let bayarAmount = 0;
let bayarTitle = '';
let qrisTimer = null;
let qrisCountdown = 300; // 5 minutes

function formatRupiah(n) {
  return 'Rp ' + n.toLocaleString('id-ID');
}

function openBayarModal(cid, amount, title) {
  bayarCid = cid;
  bayarAmount = amount;
  bayarTitle = title;
  document.getElementById('dmBookTitle').textContent = title;
  document.getElementById('dmAmount').textContent = formatRupiah(amount);
  document.getElementById('dmStep1').style.display = '';
  document.getElementById('dmCash').style.display = 'none';
  document.getElementById('dmQris').style.display = 'none';
  
  const dmStatus = document.getElementById('dmStatus');
  if (dmStatus) dmStatus.style.display = 'none';
  
  document.getElementById('dendaOverlay').classList.add('show');
  document.getElementById('dendaModal').classList.add('show');
  document.body.style.overflow = 'hidden';
  if (typeof qrisTimer !== 'undefined' && qrisTimer) { clearInterval(qrisTimer); qrisTimer = null; }
}

function closeBayarModal() {
  document.getElementById('dendaOverlay').classList.remove('show');
  document.getElementById('dendaModal').classList.remove('show');
  document.body.style.overflow = '';
}

function chooseCash() {
  document.getElementById('dmStep1').style.display = 'none';
  document.getElementById('dmCash').style.display = '';
}

function chooseQris() {
  document.getElementById('dmStep1').style.display = 'none';
  document.getElementById('dmQris').style.display = '';
  document.getElementById('dmQrisAmount').textContent = formatRupiah(bayarAmount);
  document.getElementById('dmQrisBtn').href = '?qris_bayar=' + bayarCid;
  document.getElementById('dmQrisBack').style.display = '';
}

function backToMethods() {
  document.getElementById('dmStep1').style.display = '';
  document.getElementById('dmCash').style.display = 'none';
  document.getElementById('dmQris').style.display = 'none';
}

// ============================================
// INFO PINJAM MODAL
// ============================================
let activeCid = 0;

function openInfoPinjam(cid, title, callnum, tempo, isOverdue, sisa, denda, dendaStatus, perpanjangan, maxPerpanjang) {
  activeCid = cid;
  document.getElementById('ipBookTitle').textContent = title;
  document.getElementById('ipBookCallnum').textContent = '<?= $currentLang === 'en' ? 'Call No.: ' : 'No. Kelas: ' ?>' + callnum;
  document.getElementById('ipBookTempoLabel').innerHTML = '<?= $currentLang === 'en' ? 'Due date: ' : 'Jatuh tempo: ' ?><strong>' + tempo + '</strong>';

  const alertBox = document.getElementById('ipOverdueAlert');
  const actions = document.getElementById('ipActions');
  
  if (isOverdue) {
    alertBox.style.display = 'block';
    document.getElementById('ipOverdueTitle').textContent = '⚠️ <?= $currentLang === 'en' ? 'Overdue ' : 'Terlambat ' ?>' + sisa + '<?= $currentLang === 'en' ? ' days' : ' hari' ?>';
    document.getElementById('ipDendaAmount').textContent = formatRupiah(denda);

    if (dendaStatus === 'tertunda') {
      actions.innerHTML = `
        <div style="background:#fffbeb; color:#d97706; padding:12px; border:1px solid #fcd34d; border-radius:10px; text-align:center; font-weight:700;">
          ⏳ <?= $currentLang === 'en' ? 'Awaiting Payment Verification' : 'Menunggu Verifikasi Pembayaran' ?>
        </div>
        <button type="button" class="km-btn-cancel" onclick="closeInfoPinjam()"><?= $currentLang === 'en' ? 'Close' : 'Tutup' ?></button>
      `;
    } else {
      actions.innerHTML = `
        <button type="button" class="km-btn-confirm" style="background:#ef4444; box-shadow:0 4px 16px rgba(239,68,68,0.3);" onclick="goToBayar(${cid}, ${denda}, '${title.replace(/'/g,"\\'")}');">
          💰 <?= $currentLang === 'en' ? 'Pay Fine Now' : 'Bayar Denda Sekarang' ?>
        </button>
        <button type="button" class="km-btn-cancel" onclick="closeInfoPinjam()"><?= $currentLang === 'en' ? 'Later' : 'Nanti Saja' ?></button>
      `;
    }
  } else {
    alertBox.style.display = 'none';
    
    let btnPerpanjang = '';
    if (perpanjangan < maxPerpanjang) {
      btnPerpanjang = `<a href="?perpanjang=${cid}" class="km-btn-cancel" style="display:block; text-align:center; text-decoration:none; color:var(--text-main);">🔄 <?= $currentLang === 'en' ? 'Extend Borrowing' : 'Perpanjang Peminjaman' ?></a>`;
    }
    
    actions.innerHTML = `
      <a href="?kembalikan=${cid}&tab=dipinjam" class="km-btn-confirm" onclick="return confirm('<?= $currentLang === 'en' ? 'Are you sure you want to return this book?\\n\\nPlease ensure the book is handed over to the circulation staff.' : 'Apakah Anda yakin ingin mengembalikan buku ini?\\n\\nPastikan buku diserahkan ke petugas sirkulasi.' ?>')">✅ <?= $currentLang === 'en' ? 'Yes, Return Book' : 'Ya, Kembalikan Buku' ?></a>
      ${btnPerpanjang}
      <button type="button" class="km-btn-cancel" onclick="closeInfoPinjam()" style="border:none; background:transparent;"><?= $currentLang === 'en' ? 'Cancel' : 'Batal' ?></button>
    `;
  }

  document.getElementById('infoPinjamOverlay').classList.add('show');
  document.getElementById('infoPinjamModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeInfoPinjam() {
  document.getElementById('infoPinjamOverlay').classList.remove('show');
  document.getElementById('infoPinjamModal').classList.remove('show');
  document.body.style.overflow = '';
}

function goToBayar(cid, denda, title) {
  closeInfoPinjam();
  setTimeout(() => {
    openBayarModal(cid, denda, title);
  }, 300);
}
</script>
