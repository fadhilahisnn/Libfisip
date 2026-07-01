<?php
session_start();
require_once 'config.php';
require_once 'includes/cover_helper.php';
$pageTitle = 'Usulan Koleksi';
$db  = getDB();
$uid = $_SESSION['user_id'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $judul    = trim($_POST['judul'] ?? '');
    $pengarang = trim($_POST['pengarang'] ?? '');
    $penerbit  = trim($_POST['penerbit'] ?? '');
    $tahun     = (int)($_POST['tahun'] ?? 0);
    $isbn      = trim($_POST['isbn'] ?? '');
    $kategori  = trim($_POST['kategori'] ?? '');
    $alasan    = trim($_POST['alasan'] ?? '');
    
    if ($judul && strlen($alasan) >= 10) {
        $db->prepare("INSERT INTO usulan_koleksi (user_id, judul, pengarang, penerbit, tahun, isbn, kategori, alasan, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'menunggu')")->execute([
            $uid, $judul, $pengarang, $penerbit, $tahun, $isbn, $kategori, $alasan
        ]);
        redirect('usulan.php', 'Usulan koleksi berhasil dikirim! Tim perpustakaan akan meninjaunya. ✦', 'success');
    } else {
        $_SESSION['flash_msg'] = 'Judul dan alasan (min. 10 karakter) wajib diisi.';
        $_SESSION['flash_type'] = 'error';
    }
}

// Get user's own suggestions
$mySuggestions = [];
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT * FROM usulan_koleksi WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $mySuggestions = $stmt->fetchAll();
}

$me = isLoggedIn() ? currentUser() : null;

include 'includes/header.php';
?>

<style>
/* ============ USULAN KOLEKSI PAGE ============ */

/* Hero */
.uk-hero {
  background: linear-gradient(160deg, #fff0f3 0%, #fce7f3 50%, #fff5f7 100%);
  padding: 3rem 5% 2rem;
  border-bottom: 1px solid #fce7f3;
  position: relative;
  overflow: hidden;
}
.uk-hero::before {
  content: 'USUL';
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
.uk-hero-inner { max-width: 900px; margin: 0 auto; }
.uk-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(255,77,109,0.1); color: #FF4D6D;
  border: 1px solid rgba(255,77,109,0.2);
  padding: 5px 14px; border-radius: 99px;
  font-size: 0.75rem; font-weight: 700; letter-spacing: 0.8px;
  margin-bottom: 1rem;
}
.uk-title {
  display: inline-block; font-size: 2.8rem; font-weight: 900; letter-spacing: -1.5px;
  line-height: 1.2; background: linear-gradient(135deg, #FF4D6D 0%, #800F2F 100%); color: white; margin-bottom: 0.8rem;
  padding: 8px 30px; border-radius: 20px; box-shadow: 0 10px 25px rgba(255, 77, 109, 0.3); border: 2px solid rgba(255,255,255,0.4);
}
.uk-title em { font-family:'Space Mono',monospace; font-style:italic; color:rgba(255,255,255,0.85); }
.uk-sub { color: #6B7280; font-size: 0.95rem; }

/* Wrap */
.uk-wrap { max-width: 900px; margin: 0 auto; padding: 2rem 5%; }

/* Form Card */
.uk-form-card {
  background: white;
  border-radius: 20px;
  border: 1.5px solid #F3F4F6;
  box-shadow: 0 4px 24px rgba(0,0,0,0.06);
  overflow: hidden;
  margin-bottom: 2.5rem;
}
.uk-form-header {
  display: flex; align-items: center; gap: 12px;
  padding: 1.2rem 1.5rem;
  border-bottom: 1px solid #F9FAFB;
  background: #FAFAFA;
}
.uk-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, #FF4D6D, #ff8fa3);
  color: white; font-weight: 800; font-size: 0.9rem;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.uk-form-who { font-weight: 700; font-size: 0.9rem; color: #111827; }
.uk-form-hint { font-size: 0.75rem; color: #9CA3AF; }

.uk-form-body { padding: 1.5rem; }

.uk-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.uk-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 700;
  color: #374151;
  margin-bottom: 6px;
}

.uk-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.88rem;
  color: #374151;
  background: #FAFAFA;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
}
.uk-input:focus { border-color: #FF4D6D; background: white; }
.uk-input::placeholder { color: #9CA3AF; }

.uk-textarea {
  width: 100%;
  min-height: 120px;
  padding: 12px 14px;
  border: 1.5px solid #E5E7EB;
  border-radius: 10px;
  font-family: inherit;
  font-size: 0.9rem;
  color: #374151;
  background: #FAFAFA;
  resize: vertical;
  outline: none;
  transition: border-color 0.2s;
  box-sizing: border-box;
  line-height: 1.6;
}
.uk-textarea:focus { border-color: #FF4D6D; background: white; }
.uk-textarea::placeholder { color: #9CA3AF; }

.uk-form-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 1rem;
}

.uk-submit {
  padding: 12px 32px;
  background: #111827;
  color: white;
  border: none;
  border-radius: 99px;
  font-family: inherit;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.25s;
}
.uk-submit:hover { background: #FF4D6D; box-shadow: 0 4px 14px rgba(255,77,109,0.35); transform: translateY(-2px); }

/* Guest CTA */
.uk-guest {
  background: white;
  border: 1.5px dashed #E5E7EB;
  border-radius: 20px;
  padding: 2.5rem;
  text-align: center;
  margin-bottom: 2.5rem;
}
.uk-guest p { color: #6B7280; font-size: 0.9rem; margin-bottom: 1.2rem; }
.uk-guest-btn {
  display: inline-block;
  padding: 11px 28px;
  background: #111827;
  color: white;
  border-radius: 99px;
  font-weight: 700;
  font-size: 0.9rem;
  text-decoration: none;
  transition: all 0.25s;
}
.uk-guest-btn:hover { background: #FF4D6D; transform: translateY(-2px); }

/* Section header */
.uk-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #F3F4F6;
}
.uk-section-title {
  font-size: 1.2rem;
  font-weight: 800;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 8px;
}
.uk-count {
  background: rgba(255,77,109,0.1);
  color: #FF4D6D;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 99px;
}

/* Suggestion cards */
.uk-card {
  background: white;
  border-radius: 18px;
  border: 1.5px solid #F3F4F6;
  box-shadow: 0 2px 16px rgba(0,0,0,0.05);
  margin-bottom: 1rem;
  overflow: hidden;
  transition: box-shadow 0.25s, border-color 0.25s;
}
.uk-card:hover {
  box-shadow: 0 8px 32px rgba(0,0,0,0.09);
  border-color: rgba(255,77,109,0.2);
}

.uk-card-body {
  padding: 1.3rem 1.5rem;
  display: flex;
  gap: 1.2rem;
  align-items: flex-start;
}

.uk-card-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.uk-card-content { flex: 1; min-width: 0; }

.uk-card-title {
  font-weight: 800;
  font-size: 1rem;
  color: #111827;
  margin-bottom: 4px;
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.uk-card-author {
  font-size: 0.82rem;
  color: #6B7280;
  margin-bottom: 6px;
}

.uk-card-reason {
  font-size: 0.88rem;
  color: #374151;
  line-height: 1.6;
  margin-bottom: 8px;
}

.uk-card-meta {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  font-size: 0.75rem;
  color: #9CA3AF;
}

.uk-card-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.8rem 1.5rem;
  border-top: 1px solid #F9FAFB;
  background: #FAFAFA;
}

.uk-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 4px 12px;
  border-radius: 99px;
}
.uk-status.menunggu {
  background: rgba(251,191,36,0.1);
  color: #D97706;
  border: 1px solid rgba(251,191,36,0.2);
}
.uk-status.disetujui {
  background: rgba(16,185,129,0.1);
  color: #059669;
  border: 1px solid rgba(16,185,129,0.2);
}
.uk-status.ditolak {
  background: rgba(244,63,94,0.1);
  color: #DC2626;
  border: 1px solid rgba(244,63,94,0.2);
}

.uk-date {
  font-size: 0.75rem;
  color: #9CA3AF;
}

.uk-catatan {
  background: #F9FAFB;
  border-radius: 10px;
  padding: 10px 14px;
  margin-top: 8px;
  font-size: 0.82rem;
  color: #6B7280;
  border-left: 3px solid #FF4D6D;
}

/* Flash messages */
.uk-flash-ok {
  background: rgba(16,185,129,0.1);
  color: #059669;
  border: 1px solid rgba(16,185,129,0.2);
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
}
.uk-flash-err {
  background: rgba(244,63,94,0.08);
  color: #DC2626;
  border: 1px solid rgba(244,63,94,0.2);
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
}

/* Empty */
.uk-empty {
  text-align: center;
  padding: 3rem 1rem;
  color: #9CA3AF;
}
.uk-empty-icon { font-size: 3rem; margin-bottom: 1rem; }
.uk-empty-h { font-size: 1.1rem; font-weight: 800; color: #374151; margin-bottom: 0.4rem; }

@media (max-width: 768px) {
  .uk-title { font-size: 2rem; }
  .uk-hero { padding: 2rem 5% 1.5rem; }
  .uk-form-grid { grid-template-columns: 1fr; }
  .uk-wrap { padding: 1.5rem 4%; }
  .uk-card-body { flex-direction: column; }
}
</style>

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
                💡 <?= $currentLang === 'en' ? 'Book Suggestion' : 'Usulan Buku' ?>
            </div>
            
            <h1 style="font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.8rem; letter-spacing: -1.5px; line-height: 1.1;">
                <?= $currentLang === 'en' ? 'Suggest a Book' : 'Usulan Koleksi' ?>
            </h1>
            <p style="font-size: 1.15rem; color: var(--muted); max-width: 550px; line-height: 1.6; margin: 0;">
                <?= $currentLang === 'en' ? 'Is there a book you want to read but isn\'t available? Suggest it here!' : 'Ada buku yang ingin kamu baca tapi belum tersedia? Usulkan di sini!' ?>
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
                <div class="ill-book ib-4">IDEAS</div>
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
                <div class="ill-book ib-1">REQUEST</div>
            </div>
        </div>

    </div>
</div>

<div class="uk-wrap">

  <?php
  // Flash message
  $flash = $_SESSION['flash_msg'] ?? '';
  $flashType = $_SESSION['flash_type'] ?? '';
  unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
  if ($flash):
  ?>
    <div class="<?= $flashType === 'error' ? 'uk-flash-err' : 'uk-flash-ok' ?>">
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>

  <!-- COMPOSE FORM -->
  <?php if (isLoggedIn()): ?>
  <div class="uk-form-card">
    <div class="uk-form-header">
      <div class="uk-avatar"><?= strtoupper(substr($me['nama'], 0, 2)) ?></div>
      <div>
        <div class="uk-form-who"><?= htmlspecialchars($me['nama']) ?></div>
        <div class="uk-form-hint"><?= $currentLang === 'en' ? 'Suggest a book you want to read in FISIP Reading Room' : 'Usulkan buku yang ingin kamu baca di Ruang Baca FISIP' ?></div>
      </div>
    </div>
    <div class="uk-form-body">
      <form method="POST">
        <div class="uk-form-grid">
          <div style="grid-column: 1 / -1;">
            <label class="uk-label"><?= $currentLang === 'en' ? 'Book Title' : 'Judul Buku' ?> <span style="color:#e11d48">*</span></label>
            <input type="text" name="judul" class="uk-input" placeholder="<?= $currentLang === 'en' ? 'E.g., The Origin of Species' : 'Contoh: Sosiologi Suatu Pengantar' ?>" required>
          </div>
          
          <div>
            <label class="uk-label"><?= $currentLang === 'en' ? 'Author' : 'Pengarang' ?> <span style="color:#e11d48">*</span></label>
            <input type="text" name="pengarang" class="uk-input" placeholder="<?= $currentLang === 'en' ? 'E.g., Soerjono Soekanto' : 'Contoh: Soerjono Soekanto' ?>" required>
          </div>
          
          <div>
            <label class="uk-label"><?= $currentLang === 'en' ? 'Publisher / Year (Optional)' : 'Penerbit / Tahun (Opsional)' ?></label>
            <input type="text" name="penerbit" class="uk-input" placeholder="<?= $currentLang === 'en' ? 'E.g., Rajawali Pers (2013)' : 'Contoh: Rajawali Pers (2013)' ?>">
          </div>
          
          <div>
            <label class="uk-label"><?= $currentLang === 'en' ? 'Publication Year' : 'Tahun Terbit' ?></label>
            <input type="number" name="tahun" class="uk-input" placeholder="<?= date('Y') ?>" value="<?= date('Y') ?>">
          </div>
          
          <div>
            <label class="uk-label">ISBN</label>
            <input type="text" name="isbn" class="uk-input" placeholder="<?= $currentLang === 'en' ? 'Book ISBN (optional)' : 'ISBN buku (opsional)' ?>">
          </div>
          
          <div style="grid-column: 1 / -1;">
            <label class="uk-label"><?= $currentLang === 'en' ? 'Category' : 'Kategori' ?></label>
            <select name="kategori" class="uk-input">
              <option value=""><?= $currentLang === 'en' ? 'Select category...' : 'Pilih kategori...' ?></option>
              <?php 
              $categories = ['Sosiologi','Komunikasi','Politik','Administrasi','Antropologi','Ilmu Informasi','Hukum','Ekonomi','Umum'];
              foreach($categories as $k): 
                  $catName = $k;
                  if ($currentLang === 'en') {
                      $dict = ['Sosiologi' => 'Sociology', 'Komunikasi' => 'Communication', 'Politik' => 'Politics', 'Administrasi' => 'Administration', 'Antropologi' => 'Anthropology', 'Ilmu Informasi' => 'Information Science', 'Hukum' => 'Law', 'Ekonomi' => 'Economics', 'Umum' => 'General'];
                      $catName = isset($dict[$k]) ? $dict[$k] : $k;
                  }
              ?>
                <option value="<?= $k ?>"><?= $catName ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div style="grid-column: 1 / -1;">
            <label class="uk-label"><?= $currentLang === 'en' ? 'Reason for Suggesting (Optional)' : 'Alasan Usulan (Opsional)' ?></label>
            <textarea name="alasan" class="uk-textarea" placeholder="<?= $currentLang === 'en' ? 'Why do you want this book?' : 'Mengapa buku ini penting untuk diadakan?' ?>"></textarea>
          </div>
        </div>
        
        <div class="uk-form-footer">
          <button type="submit" class="uk-submit">🚀 <?= $currentLang === 'en' ? 'Submit Suggestion' : 'Kirim Usulan' ?></button>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="uk-guest">
    <div style="font-size:2.5rem;margin-bottom:0.8rem;">📋</div>
    <p><?= $currentLang === 'en' ? 'Log in to suggest a collection and track your suggestion status' : 'Masuk untuk mengajukan usulan koleksi dan melacak status usulanmu' ?></p>
    <a href="login.php" class="uk-guest-btn">✨ <?= $currentLang === 'en' ? 'Log In Now' : 'Masuk Sekarang' ?></a>
  </div>
  <?php endif; ?>

  <!-- MY SUGGESTIONS -->
  <?php if (isLoggedIn() && !empty($mySuggestions)): ?>
  <div class="uk-section-header">
    <div class="uk-section-title">
      📌 Riwayat Usulan Saya
      <span class="uk-count"><?= count($mySuggestions) ?> usulan</span>
    </div>
  </div>

  <?php foreach ($mySuggestions as $s): 
    $statusIcon = [
      'menunggu' => '⏳',
      'disetujui' => '✅',
      'ditolak' => '❌'
    ][$s['status']] ?? '⏳';
  ?>
  <div class="uk-card">
    <div class="uk-card-body">
      <div class="uk-card-icon" style="background: <?= $s['status'] === 'disetujui' ? 'rgba(16,185,129,0.1)' : ($s['status'] === 'ditolak' ? 'rgba(244,63,94,0.1)' : 'rgba(251,191,36,0.1)') ?>;">
        <?= $statusIcon ?>
      </div>
      <div class="uk-card-content">
        <div class="uk-card-title">
          <?= htmlspecialchars($s['judul']) ?>
          <span class="uk-status <?= $s['status'] ?>">
            <?= $statusIcon ?> <?= ucfirst($s['status']) ?>
          </span>
        </div>
        <?php if ($s['pengarang']): ?>
        <div class="uk-card-author">by <?= htmlspecialchars($s['pengarang']) ?></div>
        <?php endif; ?>
        <div class="uk-card-reason"><?= nl2br(htmlspecialchars($s['alasan'])) ?></div>
        <div class="uk-card-meta">
          <?php if ($s['penerbit']): ?><span>📖 <?= htmlspecialchars($s['penerbit']) ?></span><?php endif; ?>
          <?php if ($s['tahun']): ?><span>📅 <?= $s['tahun'] ?></span><?php endif; ?>
          <?php if ($s['kategori']): ?><span>🏷 <?= htmlspecialchars($s['kategori']) ?></span><?php endif; ?>
        </div>
        <?php if ($s['catatan_admin']): ?>
        <div class="uk-catatan">
          <strong>Catatan Admin:</strong> <?= nl2br(htmlspecialchars($s['catatan_admin'])) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="uk-card-footer">
      <span class="uk-date">📅 Diusulkan <?= timeAgo($s['created_at']) ?></span>
    </div>
  </div>
  <?php endforeach; ?>
  
  <?php elseif (isLoggedIn()): ?>
  <div class="uk-empty">
    <div class="uk-empty-icon">📋</div>
    <div class="uk-list-title">📌 <?= $currentLang === 'en' ? 'Suggestion Status' : 'Status Usulan Kamu' ?></div>
    <p><?= $currentLang === 'en' ? 'You have never suggested a collection. Fill out the form above to get started!' : 'Kamu belum pernah mengusulkan koleksi. Isi form di atas untuk memulai!' ?></p>
  </div>
  <?php endif; ?>

</div>

</main>
<?php include 'includes/footer.php'; ?>