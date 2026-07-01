<?php
session_start();
require_once 'config.php';
require_once 'includes/cover_helper.php';
$pageTitle = 'Book Talk';
$db  = getDB();
$uid = $_SESSION['user_id'] ?? null;

// Post review
if ($_SERVER['REQUEST_METHOD']==='POST' && isLoggedIn()) {
    $bid    = (int)$_POST['book_id'];
    $rating = (int)$_POST['rating'];
    $isi    = trim($_POST['isi'] ?? '');
    $tags   = trim($_POST['tags'] ?? '');
    if ($bid && $rating>=1 && $rating<=5 && strlen($isi)>=10) {
        $cek = $db->prepare("SELECT id FROM reviews WHERE user_id=? AND book_id=?");
        $cek->execute([$uid, $bid]);
        if ($cek->fetch()) {
            $db->prepare("UPDATE reviews SET rating=?,isi=?,tags=? WHERE user_id=? AND book_id=?")->execute([$rating,$isi,$tags,$uid,$bid]);
            redirect('booktalk.php','Review berhasil diperbarui! ✦');
        } else {
            $db->prepare("INSERT INTO reviews (user_id,book_id,rating,isi,tags) VALUES (?,?,?,?,?)")->execute([$uid,$bid,$rating,$isi,$tags]);
            redirect('booktalk.php','Review berhasil diposting! ✦');
        }
    } else {
        $_SESSION['flash'] = ['msg'=>'Isi review minimal 10 karakter dan pilih rating.','type'=>'error'];
    }
}

// Like / unlike
if (isset($_GET['like']) && isLoggedIn()) {
    $rid = (int)$_GET['like'];
    $cek = $db->prepare("SELECT id FROM review_likes WHERE user_id=? AND review_id=?");
    $cek->execute([$uid, $rid]);
    if ($cek->fetch()) {
        $db->prepare("DELETE FROM review_likes WHERE user_id=? AND review_id=?")->execute([$uid,$rid]);
    } else {
        $db->prepare("INSERT INTO review_likes (user_id,review_id) VALUES (?,?)")->execute([$uid,$rid]);
    }
    header('Location: booktalk.php'); exit;
}

$reviews = $db->query("SELECT r.*, u.nama, u.prodi, u.nim,
    b.judul, b.pengarang, b.kategori, b.penerbit, b.tahun, b.isbn, b.no_kelas, b.bahasa, b.deskripsi, b.tersedia, b.stok,
    b.color1, b.color2, b.color3, b.cover_image,
    (SELECT COUNT(*) FROM review_likes l WHERE l.review_id=r.id) as total_likes
    FROM reviews r
    JOIN users u ON r.user_id=u.id
    JOIN books b ON r.book_id=b.id
    ORDER BY total_likes DESC, r.created_at DESC")->fetchAll();

$allBooks    = $db->query("SELECT id, judul, pengarang, isbn FROM books ORDER BY judul ASC")->fetchAll();
$reviewBook  = isset($_GET['review']) ? (int)$_GET['review'] : 0;

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

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<style>
/* ============ BOOK TALK PAGE ============ */

/* Hero */
.bt-hero {
  background: linear-gradient(160deg, #fff0f3 0%, #fce7f3 50%, #fff5f7 100%);
  padding: 3rem 5% 2rem;
  border-bottom: 1px solid #fce7f3;
  position: relative;
  overflow: hidden;
}
.bt-hero::before {
  content: 'TALK';
  position: absolute;
  right: -10px; top: 50%;
  transform: translateY(-50%);
  font-size: 14rem;
  font-weight: 900;
  color: rgba(255,77,109,0.04);
  font-family: 'Space Mono', monospace;
  letter-spacing: -5px;
  pointer-events: none;
}
.bt-hero-inner { max-width: 900px; margin: 0 auto; }
.bt-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,77,109,0.1); color: #FF4D6D;
  border: 1px solid rgba(255,77,109,0.2);
  padding: 5px 14px; border-radius: 99px;
  font-size: 0.75rem; font-weight: 700; letter-spacing: 0.8px;
  margin-bottom: 1rem;
}
.bt-title {
  display: inline-block; font-size: 2.8rem; font-weight: 900; letter-spacing: -1.5px;
  line-height: 1.2; background: linear-gradient(135deg, #FF4D6D 0%, #800F2F 100%); color: white; margin-bottom: 0.8rem;
  padding: 8px 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(255, 77, 109, 0.3); border: 2px solid rgba(255,255,255,0.4);
}
.bt-title em { font-family:'Space Mono',monospace; font-style:italic; color:rgba(255,255,255,0.85); }
.bt-sub { color: #6B7280; font-size: 0.95rem; }

/* ---- COMPOSE BOX ---- */
.bt-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 5%; }

.bt-compose {
  background: white;
  border-radius: 20px;
  border: 1.5px solid #F3F4F6;
  box-shadow: 0 4px 24px rgba(0,0,0,0.06);
  overflow: hidden;
  margin-bottom: 2.5rem;
}
.bt-compose-header {
  display: flex; align-items: center; gap: 12px;
  padding: 1.2rem 1.5rem;
  border-bottom: 1px solid #F9FAFB;
  background: #FAFAFA;
}
.bt-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, #FF4D6D, #ff8fa3);
  color: white; font-weight: 800; font-size: 0.9rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.bt-compose-who { font-weight: 700; font-size: 0.9rem; color: #111827; }
.bt-compose-hint { font-size: 0.75rem; color: #9CA3AF; }

.bt-compose-body { padding: 1.2rem 1.5rem; }
.bt-selects {
  display: grid;
  grid-template-columns: 1fr 140px;
  gap: 10px;
  margin-bottom: 12px;
}
.bt-select {
  width: 100%; padding: 10px 14px;
  border: 1.5px solid #E5E7EB; border-radius: 10px;
  font-family: inherit; font-size: 0.88rem; color: #374151;
  background: #FAFAFA; outline: none; cursor: pointer;
  transition: border-color 0.2s;
}
.bt-select:focus { border-color: #FF4D6D; background: white; }

.bt-textarea {
  width: 100%; min-height: 110px;
  padding: 12px 14px;
  border: 1.5px solid #E5E7EB; border-radius: 10px;
  font-family: inherit; font-size: 0.9rem; color: #374151;
  background: #FAFAFA; resize: vertical; outline: none;
  transition: border-color 0.2s;
  margin-bottom: 12px; box-sizing: border-box;
  line-height: 1.6;
}
.bt-textarea:focus { border-color: #FF4D6D; background: white; }
.bt-textarea::placeholder { color: #9CA3AF; }

/* Custom Tom Select override */
.ts-control {
  border: 1.5px solid #E5E7EB !important;
  border-radius: 10px !important;
  padding: 10px 14px !important;
  font-family: inherit !important;
  font-size: 0.88rem !important;
  background: #FAFAFA !important;
  box-shadow: none !important;
}
.ts-control.focus { border-color: #FF4D6D !important; background: white !important; }
.ts-dropdown {
  border-radius: 10px !important;
  border: 1.5px solid #E5E7EB !important;
  box-shadow: 0 4px 20px rgba(0,0,0,0.05) !important;
  font-size: 0.88rem !important;
}

.bt-compose-footer {
  display: flex; align-items: center; justify-content: space-between;
  gap: 10px; flex-wrap: wrap;
}
.bt-tag-input {
  flex: 1; min-width: 160px;
  padding: 8px 14px;
  border: 1.5px solid #E5E7EB; border-radius: 99px;
  font-family: inherit; font-size: 0.82rem; color: #374151;
  background: #F9FAFB; outline: none;
  transition: border-color 0.2s;
}
.bt-tag-input:focus { border-color: #FF4D6D; background: white; }
.bt-tag-input::placeholder { color: #9CA3AF; }
.bt-submit {
  padding: 10px 24px;
  background: #111827; color: white;
  border: none; border-radius: 99px;
  font-family: inherit; font-weight: 700; font-size: 0.88rem;
  cursor: pointer; transition: all 0.25s;
  white-space: nowrap;
}
.bt-submit:hover { background: #FF4D6D; box-shadow: 0 4px 14px rgba(255,77,109,0.35); }

/* Guest CTA */
.bt-guest {
  background: white;
  border: 1.5px dashed #E5E7EB;
  border-radius: 20px;
  padding: 2.5rem;
  text-align: center;
  margin-bottom: 2.5rem;
}
.bt-guest p { color: #6B7280; font-size: 0.9rem; margin-bottom: 1.2rem; }
.bt-guest-btn {
  display: inline-block;
  padding: 11px 28px;
  background: #111827; color: white;
  border-radius: 99px; font-weight: 700; font-size: 0.9rem;
  text-decoration: none; transition: all 0.25s;
}
.bt-guest-btn:hover { background: #FF4D6D; transform: translateY(-2px); }

/* Section header */
.bt-section-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #F3F4F6;
}
.bt-section-title {
  font-size: 1.2rem; font-weight: 800; color: #111827;
  display: flex; align-items: center; gap: 8px;
}
.bt-count {
  background: rgba(255,77,109,0.1); color: #FF4D6D;
  font-size: 0.72rem; font-weight: 700;
  padding: 3px 10px; border-radius: 99px;
}

/* ---- REVIEW CARD ---- */
.bt-card {
  background: white;
  border-radius: 18px;
  border: 1.5px solid #F3F4F6;
  box-shadow: 0 2px 16px rgba(0,0,0,0.05);
  margin-bottom: 1.2rem;
  overflow: hidden;
  transition: box-shadow 0.25s, border-color 0.25s;
}
.bt-card:hover {
  box-shadow: 0 8px 32px rgba(0,0,0,0.09);
  border-color: rgba(255,77,109,0.2);
}

.bt-card-top {
  display: flex; align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: 1.3rem 1.5rem 0;
}

/* Reviewer */
.bt-reviewer { display: flex; align-items: center; gap: 10px; }
.bt-rev-avatar {
  width: 42px; height: 42px; border-radius: 50%;
  background: linear-gradient(135deg, rgba(255,77,109,0.15), rgba(255,143,163,0.1));
  color: #FF4D6D; font-weight: 800; font-size: 0.9rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; border: 1.5px solid rgba(255,77,109,0.15);
}
.bt-rev-name { font-weight: 700; font-size: 0.92rem; color: #111827; }
.bt-rev-meta { font-size: 0.74rem; color: #9CA3AF; margin-top: 1px; }

/* Book mini */
.bt-book-mini {
  display: flex; align-items: center; gap: 10px;
  background: #F9FAFB; border: 1px solid #F3F4F6;
  border-radius: 12px; padding: 8px 12px;
  text-decoration: none; color: inherit;
  transition: all 0.2s; flex-shrink: 0; max-width: 220px;
}
.bt-book-mini:hover { border-color: rgba(255,77,109,0.3); background: #fff5f7; }
.bt-mini-cover {
  width: 34px; height: 46px; border-radius: 5px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.38rem; font-weight: 700; text-align: center;
  line-height: 1.2; padding: 3px; overflow: hidden;
  flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.12);
  color: rgba(255,255,255,0.95);
}
.bt-mini-cover img { width:100%; height:100%; object-fit:cover; border-radius:4px; }
.bt-mini-title { font-size: 0.74rem; font-weight: 700; color: #111827; line-height: 1.3;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.bt-mini-author { font-size: 0.65rem; color: #9CA3AF; margin-top: 1px; }
.bt-mini-stars { color: #FBBF24; font-size: 0.7rem; margin-top: 2px; }

/* Review body */
.bt-body {
  padding: 1rem 1.5rem;
  font-size: 0.92rem; color: #374151;
  line-height: 1.7;
  position: relative;
}
.bt-body::before {
  content: '"';
  font-size: 3rem; color: rgba(255,77,109,0.15);
  font-family: Georgia, serif; line-height: 0;
  position: absolute; top: 1.5rem; left: 1rem;
}
.bt-body-text { padding-left: 1rem; font-style: italic; }

/* Footer */
.bt-card-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0.8rem 1.5rem;
  border-top: 1px solid #F9FAFB;
  background: #FAFAFA;
}
.bt-tags { display: flex; gap: 6px; flex-wrap: wrap; }
.bt-tag-pill {
  font-size: 0.68rem; font-weight: 600;
  color: #FF4D6D; background: rgba(255,77,109,0.08);
  padding: 3px 10px; border-radius: 99px;
  border: 1px solid rgba(255,77,109,0.15);
}
.bt-like-btn {
  display: flex; align-items: center; gap: 6px;
  text-decoration: none; color: #9CA3AF;
  font-size: 0.82rem; font-weight: 600;
  padding: 5px 12px; border-radius: 99px;
  border: 1.5px solid #E5E7EB;
  background: white; transition: all 0.2s;
}
.bt-like-btn:hover { border-color: #FF4D6D; color: #FF4D6D; background: rgba(255,77,109,0.05); }
.bt-like-btn.liked { border-color: #FF4D6D; color: #FF4D6D; background: rgba(255,77,109,0.08); }
.bt-like-static { display: flex; align-items: center; gap: 5px; color: #9CA3AF; font-size: 0.82rem; font-weight: 600; }

/* Empty */
.bt-empty { text-align: center; padding: 4rem 1rem; color: #9CA3AF; }
.bt-empty-icon { font-size: 3.5rem; margin-bottom: 1rem; }
.bt-empty-h { font-size: 1.1rem; font-weight: 800; color: #374151; margin-bottom: 0.4rem; }

/* Flash */
.bt-flash-ok { background: rgba(16,185,129,0.1); color:#059669; border:1px solid rgba(16,185,129,0.2);
  padding:10px 16px; border-radius:10px; font-size:0.85rem; font-weight:600; margin-bottom:1.5rem; }
.bt-flash-err { background:rgba(244,63,94,0.08); color:#DC2626; border:1px solid rgba(244,63,94,0.2);
  padding:10px 16px; border-radius:10px; font-size:0.85rem; font-weight:600; margin-bottom:1.5rem; }

@media (max-width:768px) {
  .bt-title { font-size: 2rem; }
  .bt-hero { padding: 2rem 5% 1.5rem; }
  .bt-card-top { flex-direction: column; }
  .bt-book-mini { max-width: 100%; }
  .bt-selects { grid-template-columns: 1fr; }
  .bt-wrap { padding: 1.5rem 4%; }
}

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
}
</style>

<main style="padding: 0 !important; max-width: 100% !important;">

<!-- HERO -->
<div class="profile-header" style="padding: 8rem 2rem 5rem; background: var(--hero-gradient); position: relative; overflow: hidden; border-bottom: 1px solid var(--border); margin-bottom: 2rem;">
    <!-- Glowing Orbs Background -->
    <div style="position: absolute; top: -50%; left: -10%; width: 350px; height: 350px; background: rgba(255, 77, 109, 0.15); filter: blur(80px); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; bottom: -50%; right: -10%; width: 350px; height: 350px; background: rgba(131, 56, 236, 0.15); filter: blur(80px); border-radius: 50%; pointer-events: none;"></div>
    
    <div class="profile-header-inner" style="display: flex; align-items: center; justify-content: space-between; max-width: 1100px; margin: 0 auto; position: relative; z-index: 1; gap: 2rem; text-align: left;">
        
        <!-- Left Text Content -->
        <div class="hero-text" style="flex: 1;">
            <div style="display: inline-block; padding: 6px 16px; background: var(--card-bg); border: 1px solid var(--border); color: var(--accent); border-radius: 30px; font-size: 0.85rem; font-weight: 700; margin-bottom: 1rem; letter-spacing: 1px; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                💬 <?= $currentLang === 'en' ? 'FISIP Reading Community' : 'Komunitas Baca FISIP' ?>
            </div>
            
            <h1 style="font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.8rem; letter-spacing: -1.5px; line-height: 1.1;">
                Book Talk
            </h1>
            <p style="font-size: 1.15rem; color: var(--muted); max-width: 550px; line-height: 1.6; margin: 0;">
                <?= $currentLang === 'en' ? 'Review, discuss, and recommend books with FISIP Universitas Airlangga community' : 'Review, diskusi, dan rekomendasi buku bareng sesama civitas FISIP Universitas Airlangga' ?>
            </p>
        </div>

        <!-- Right Illustration Content -->
        <div class="hero-illustration" style="flex-shrink: 0; width: 350px; height: 320px; position: relative;">
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
                <div class="ill-book ib-4">DISCUSS</div>
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
                <div class="ill-book ib-1">LIBFISIP</div>
            </div>
        </div>

    </div>
</div>

<div class="bt-wrap">

  <?php
  // Flash message
  $flash = $_SESSION['flash_msg'] ?? '';
  $flashType = $_SESSION['flash_type'] ?? '';
  unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
  if ($flash):
  ?>
    <div class="<?= $flashType==='error' ? 'bt-flash-err' : 'bt-flash-ok' ?>">
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>

  <!-- COMPOSE REVIEW -->
  <?php if(isLoggedIn()): $me = currentUser(); ?>
  <div class="bt-compose">
    <div class="bt-compose-header">
      <div class="bt-avatar"><?= strtoupper(substr($me['nama'],0,2)) ?></div>
      <div>
        <div class="bt-compose-who"><?= htmlspecialchars($me['nama']) ?></div>
        <div class="bt-compose-hint"><?= $currentLang === 'en' ? 'Write a review for a book you\'ve read' : 'Tulis review buku yang sudah kamu baca' ?></div>
      </div>
    </div>
    <div class="bt-compose-body">
      <form method="POST">
        <div class="bt-selects">
          <select name="book_id" id="book-select" required placeholder="<?= $currentLang === 'en' ? '📚 Choose a book to review...' : '📚 Pilih buku yang ingin di-review...' ?>">
            <option value="">📚 <?= $currentLang === 'en' ? 'Choose a book to review...' : 'Pilih buku yang ingin di-review...' ?></option>
            <?php foreach($allBooks as $bk): ?>
              <option value="<?= $bk['id'] ?>" <?= $reviewBook===$bk['id']?'selected':'' ?>>
                <?= htmlspecialchars($bk['judul']) ?> — <?= htmlspecialchars($bk['pengarang']) ?> <?= $bk['isbn'] ? '(ISBN: '.htmlspecialchars($bk['isbn']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
          <select name="rating" class="bt-select" required>
            <option value="">⭐ Rating</option>
            <?php for($i=5;$i>=1;$i--): ?>
              <option value="<?= $i ?>"><?= str_repeat('★',$i) ?> <?= $i ?>/5</option>
            <?php endfor; ?>
          </select>
        </div>
        <textarea class="bt-textarea" name="isi" placeholder="<?= $currentLang === 'en' ? 'Share your thoughts about this book — impressions, critiques, or recommendations for fellow FISIP students... (min. 10 characters)' : 'Bagikan pendapatmu tentang buku ini — kesan, kritik, atau rekomendasimu untuk sesama mahasiswa FISIP... (min. 10 karakter)' ?>" required></textarea>
        <div class="bt-compose-footer">
          <input type="text" name="tags" class="bt-tag-input" placeholder="🏷 Tag: #penelitian, #sosiologi...">
          <button type="submit" class="bt-submit">Post Review ✦</button>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="bt-guest-card">
    <div style="font-size:2.5rem;margin-bottom:0.8rem;">💬</div>
    <p><?= $currentLang === 'en' ? 'Login to write reviews and interact with the FISIP reading community' : 'Masuk untuk menulis review dan berinteraksi dengan komunitas baca FISIP' ?></p>
    <a href="login.php" class="bt-guest-btn">✨ <?= $currentLang === 'en' ? 'Login Now' : 'Masuk Sekarang' ?></a>
  </div>
  <?php endif; ?>

  <div class="bt-feed">
    <div class="bt-section-title">
      🔥 <?= $currentLang === 'en' ? 'Popular Reviews' : 'Review Terpopuler' ?>
      <span class="bt-count"><?= count($reviews) ?> <?= $currentLang === 'en' ? 'reviews' : 'review' ?></span>
    </div>
  </div>

  <!-- EMPTY STATE -->
  <?php if(empty($reviews)): ?>
  <div class="bt-empty">
    <div class="bt-empty-icon">💬</div>
    <div class="bt-empty-h">Belum ada review</div>
    <p>Jadilah yang pertama mereview buku di LibFISIP!</p>
  </div>
  <?php endif; ?>

  <!-- REVIEW CARDS FEED -->
  <?php foreach($reviews as $i => $rv):
    $initials = strtoupper(implode('', array_map(fn($w)=>$w[0], explode(' ', trim($rv['nama'])))));
    $initials = substr($initials, 0, 2);
    $tags = $rv['tags'] ? explode(',', $rv['tags']) : [];
    $isLiked = false;
    if($uid) {
      $lk = $db->prepare("SELECT id FROM review_likes WHERE user_id=? AND review_id=?");
      $lk->execute([$uid, $rv['id']]);
      $isLiked = (bool)$lk->fetch();
    }
    $grad = $grads[$i % count($grads)];
    $covSrc = bookCoverSrc($rv);
  ?>
  <div class="bt-card">

    <div class="bt-card-top">
      <!-- Reviewer info -->
      <div class="bt-reviewer">
        <div class="bt-rev-avatar"><?= $initials ?></div>
        <div>
          <div class="bt-rev-name"><?= htmlspecialchars($rv['nama']) ?></div>
          <div class="bt-rev-meta"><?= htmlspecialchars($rv['prodi']) ?> · <?= timeAgo($rv['created_at']) ?></div>
        </div>
      </div>

      <!-- Book mini card -->
      <a class="bt-book-mini" href="javascript:void(0)" onclick='showBookDetail(<?= json_encode([
        "judul" => $rv["judul"], "pengarang" => $rv["pengarang"], "penerbit" => $rv["penerbit"] ?? "",
        "tahun" => $rv["tahun"] ?? "", "isbn" => $rv["isbn"] ?? "", "no_kelas" => $rv["no_kelas"] ?? "",
        "kategori" => $rv["kategori"], "bahasa" => $rv["bahasa"] ?? "Indonesia",
        "deskripsi" => $rv["deskripsi"] ?? "", "tersedia" => $rv["tersedia"] ?? 0, "stok" => $rv["stok"] ?? 0,
        "rating" => $rv["rating"], "cover" => $covSrc, "grad" => $grad, "book_id" => $rv["book_id"]
      ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
        <div class="bt-mini-cover" style="<?= $covSrc ? '' : 'background:'.$grad.';' ?>">
          <?php if($covSrc): ?>
            <img src="<?= $covSrc ?>" alt="<?= htmlspecialchars($rv['judul']) ?>">
          <?php else: ?>
            <?= htmlspecialchars(substr($rv['judul'],0,12)) ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="bt-mini-title"><?= htmlspecialchars($rv['judul']) ?></div>
          <div class="bt-mini-author"><?= htmlspecialchars($rv['pengarang']) ?></div>
          <div class="bt-mini-stars">
            <?= str_repeat('★', $rv['rating']) ?><span style="color:#D1D5DB"><?= str_repeat('★', 5-$rv['rating']) ?></span>
            <span style="color:#9CA3AF;font-size:0.62rem;"> <?= $rv['rating'] ?>/5</span>
          </div>
        </div>
      </a>
    </div>

    <!-- Review text -->
    <div class="bt-body">
      <div class="bt-body-text"><?= nl2br(htmlspecialchars($rv['isi'])) ?></div>
    </div>

    <!-- Footer: tags + like -->
    <div class="bt-card-footer">
      <div class="bt-tags">
        <?php foreach(array_slice($tags,0,4) as $tg): ?>
          <?php if(trim($tg)): ?>
          <span class="bt-tag-pill"><?= htmlspecialchars(trim($tg)) ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php if(isLoggedIn()): ?>
        <a href="?like=<?= $rv['id'] ?>" class="bt-like-btn <?= $isLiked ? 'liked' : '' ?>">
          <?= $isLiked ? '❤️' : '🤍' ?> <?= $rv['total_likes'] ?>
        </a>
      <?php else: ?>
        <div class="bt-like-static">❤️ <?= $rv['total_likes'] ?></div>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>

</div><!-- /bt-wrap -->

<!-- ===== BOOK DETAIL MODAL ===== -->
<div class="bd-overlay" id="bdOverlay" onclick="closeBookDetail()"></div>
<div class="bd-modal" id="bdModal">
  <button class="bd-close" onclick="closeBookDetail()">✕</button>
  <div class="bd-top">
    <div class="bd-cover" id="bdCover"></div>
    <div class="bd-info">
      <div class="bd-kategori" id="bdKategori"></div>
      <h2 class="bd-judul" id="bdJudul"></h2>
      <div class="bd-pengarang" id="bdPengarang"></div>
      <div class="bd-stars" id="bdStars"></div>
      <div class="bd-avail" id="bdAvail"></div>
    </div>
  </div>
  <div class="bd-meta-grid" id="bdMetaGrid"></div>
  <div class="bd-desc" id="bdDesc"></div>
  <div class="bd-actions" id="bdActions"></div>
</div>

</main>
<?php include 'includes/footer.php'; ?>

<style>
/* ---- BOOK DETAIL MODAL ---- */
.bd-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,0.5);
  backdrop-filter:blur(6px); z-index:999;
  opacity:0; pointer-events:none; transition:opacity 0.3s;
}
.bd-overlay.show { opacity:1; pointer-events:all; }

.bd-modal {
  position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) scale(0.92);
  width:95%; max-width:520px; max-height:90vh; overflow-y:auto;
  background:white; border-radius:24px; z-index:1000;
  box-shadow:0 25px 80px rgba(0,0,0,0.25);
  opacity:0; pointer-events:none; transition:all 0.35s cubic-bezier(0.34,1.56,0.64,1);
  padding:0;
}
.bd-modal.show { opacity:1; pointer-events:all; transform:translate(-50%,-50%) scale(1); }

.bd-close {
  position:absolute; top:14px; right:14px; z-index:10;
  width:34px; height:34px; border-radius:50%;
  border:1.5px solid rgba(255,255,255,0.3); background:rgba(0,0,0,0.3);
  color:white; font-size:0.9rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  transition:all 0.2s; backdrop-filter:blur(4px);
}
.bd-close:hover { background:rgba(255,77,109,0.8); border-color:transparent; }

.bd-top {
  display:flex; gap:1.4rem; padding:1.8rem 1.5rem 1rem;
  align-items:flex-start;
}
.bd-cover {
  width:110px; height:155px; border-radius:14px; flex-shrink:0;
  overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.18);
  display:flex; align-items:center; justify-content:center;
}
.bd-cover img { width:100%; height:100%; object-fit:cover; display:block; }
.bd-cover-gen {
  width:100%; height:100%; display:flex; align-items:center; justify-content:center;
  padding:0.8rem; text-align:center;
}
.bd-cover-gen span {
  color:rgba(255,255,255,0.95); font-weight:800; font-size:0.85rem;
  line-height:1.35; text-shadow:0 2px 8px rgba(0,0,0,0.2);
  display:-webkit-box; -webkit-line-clamp:4; -webkit-box-orient:vertical; overflow:hidden;
}

.bd-info { flex:1; min-width:0; }
.bd-kategori {
  font-size:0.62rem; font-weight:800; text-transform:uppercase; letter-spacing:0.8px;
  color:#FF4D6D; background:rgba(255,77,109,0.08);
  display:inline-block; padding:3px 10px; border-radius:99px;
  margin-bottom:6px;
}
.bd-judul {
  font-size:1.2rem; font-weight:900; color:#111827; line-height:1.25;
  margin:0 0 4px; letter-spacing:-0.5px;
}
.bd-pengarang { font-size:0.82rem; color:#6B7280; font-style:italic; margin-bottom:8px; }
.bd-stars { color:#FBBF24; font-size:0.85rem; margin-bottom:8px; }
.bd-stars .dim { color:#E5E7EB; }
.bd-stars .val { font-size:0.72rem; color:#9CA3AF; font-family:'Space Mono',monospace; margin-left:4px; }
.bd-avail {
  display:inline-flex; align-items:center; gap:5px;
  font-size:0.72rem; font-weight:700;
  padding:4px 12px; border-radius:99px;
}
.bd-avail.yes { background:rgba(16,185,129,0.1); color:#059669; border:1px solid rgba(16,185,129,0.2); }
.bd-avail.no  { background:rgba(244,63,94,0.08); color:#DC2626; border:1px solid rgba(244,63,94,0.2); }

.bd-meta-grid {
  display:grid; grid-template-columns:1fr 1fr; gap:8px;
  padding:0 1.5rem; margin-bottom:1rem;
}
.bd-meta-item {
  background:#F9FAFB; border-radius:10px; padding:8px 12px;
  border:1px solid #F3F4F6;
}
.bd-meta-lbl { font-size:0.6rem; font-weight:700; text-transform:uppercase; color:#9CA3AF; letter-spacing:0.5px; }
.bd-meta-val { font-size:0.82rem; font-weight:700; color:#111827; margin-top:1px; }

.bd-desc {
  padding:0 1.5rem; margin-bottom:1.2rem;
  font-size:0.85rem; color:#374151; line-height:1.7;
  border-top:1px solid #F3F4F6; padding-top:1rem;
}
.bd-desc-label { font-size:0.68rem; font-weight:800; text-transform:uppercase; color:#9CA3AF;
  letter-spacing:0.5px; margin-bottom:4px; }

.bd-actions {
  display:flex; gap:10px; padding:0 1.5rem 1.5rem;
}
.bd-act-btn {
  flex:1; padding:11px 16px; border-radius:12px;
  font-family:inherit; font-size:0.88rem; font-weight:700;
  cursor:pointer; text-decoration:none; text-align:center;
  transition:all 0.25s; border:none;
}
.bd-act-btn.primary { background:#111827; color:white; }
.bd-act-btn.primary:hover { background:#FF4D6D; box-shadow:0 4px 16px rgba(255,77,109,0.35); }
.bd-act-btn.secondary { background:#F3F4F6; color:#374151; border:1.5px solid #E5E7EB; }
.bd-act-btn.secondary:hover { border-color:#FF4D6D; color:#FF4D6D; }

@media (max-width:480px) {
  .bd-top { flex-direction:column; align-items:center; text-align:center; }
  .bd-cover { width:140px; height:195px; }
  .bd-meta-grid { grid-template-columns:1fr; }
}
</style>

<script>
function showBookDetail(book) {
  // Cover
  const coverEl = document.getElementById('bdCover');
  if (book.cover) {
    coverEl.innerHTML = '<img src="' + book.cover + '" alt="' + escHtml(book.judul) + '">';
  } else {
    coverEl.innerHTML = '<div class="bd-cover-gen" style="background:' + book.grad + ';"><span>' + escHtml(book.judul.substring(0,35)) + '</span></div>';
  }

  // Info
  document.getElementById('bdKategori').textContent = book.kategori || '';
  document.getElementById('bdJudul').textContent = book.judul;
  document.getElementById('bdPengarang').textContent = 'by ' + book.pengarang;

  // Stars
  const starsFull = '★'.repeat(book.rating);
  const starsDim  = '★'.repeat(5 - book.rating);
  document.getElementById('bdStars').innerHTML = starsFull + '<span class="dim">' + starsDim + '</span><span class="val">' + book.rating + '/5</span>';

  // Availability
  const availEl = document.getElementById('bdAvail');
  if (book.tersedia > 0) {
    availEl.className = 'bd-avail yes';
    availEl.innerHTML = '✅ Tersedia ' + book.tersedia + '/' + book.stok + ' eksemplar';
  } else {
    availEl.className = 'bd-avail no';
    availEl.innerHTML = '❌ Semua eksemplar sedang dipinjam';
  }

  // Meta grid
  const metaGrid = document.getElementById('bdMetaGrid');
  const items = [];
  if (book.penerbit) items.push(['Penerbit', book.penerbit]);
  if (book.tahun) items.push(['Tahun', book.tahun]);
  if (book.isbn) items.push(['ISBN', book.isbn]);
  if (book.no_kelas) items.push(['No. Kelas', book.no_kelas]);
  if (book.bahasa) items.push(['Bahasa', book.bahasa]);
  items.push(['Stok', book.tersedia + '/' + book.stok + ' tersedia']);
  metaGrid.innerHTML = items.map(function(m) {
    return '<div class="bd-meta-item"><div class="bd-meta-lbl">' + m[0] + '</div><div class="bd-meta-val">' + escHtml(String(m[1])) + '</div></div>';
  }).join('');

  // Description
  const descEl = document.getElementById('bdDesc');
  if (book.deskripsi) {
    descEl.innerHTML = '<div class="bd-desc-label">Deskripsi</div>' + escHtml(book.deskripsi);
    descEl.style.display = '';
  } else {
    descEl.style.display = 'none';
  }

  // Actions
  const actionsEl = document.getElementById('bdActions');
  let actionsHtml = '';
  if (book.tersedia > 0) {
    actionsHtml += '<a href="profile.php?pinjam=' + book.book_id + '" class="bd-act-btn primary">📖 Pinjam Buku</a>';
  } else {
    actionsHtml += '<a href="profile.php?antri=' + book.book_id + '" class="bd-act-btn primary">⏳ Masuk Antrian</a>';
  }
  actionsHtml += '<a href="search.php?detail=' + book.book_id + '" class="bd-act-btn secondary">🔍 Lihat Detail</a>';
  actionsEl.innerHTML = actionsHtml;

  // Show
  document.getElementById('bdOverlay').classList.add('show');
  document.getElementById('bdModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeBookDetail() {
  document.getElementById('bdOverlay').classList.remove('show');
  document.getElementById('bdModal').classList.remove('show');
  document.body.style.overflow = '';
}

function escHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

// Initialize Tom Select for book dropdown if it exists
document.addEventListener("DOMContentLoaded", function() {
  const bookSelect = document.getElementById('book-select');
  if(bookSelect) {
    new TomSelect("#book-select",{
      create: false,
      sortField: {
        field: "text",
        direction: "asc"
      }
    });
  }
});
</script>
