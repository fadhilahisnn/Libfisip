<?php
session_start();
require_once 'config.php';
require_once 'includes/lang.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';
$tab = $_GET['tab'] ?? 'biodata';

// === Handle Sirkulasi Actions (Moved from sirkulasi.php) ===
if (isset($_GET['pinjam'])) {
    $bid = (int)$_GET['pinjam'];
    $book = $db->prepare("SELECT * FROM books WHERE id=? AND tersedia>0");
    $book->execute([$bid]);
    $bk = $book->fetch();
    if ($bk) {
        $jumlahPinjam = $db->prepare("SELECT COUNT(*) FROM circulation WHERE user_id=? AND status IN('dipinjam','terlambat')");
        $jumlahPinjam->execute([$userId]);
        if ($jumlahPinjam->fetchColumn() >= MAKS_PINJAM) {
            redirect('profile.php?tab=buku&sub=dipinjam','Batas maksimal pinjam ('.MAKS_PINJAM.' buku) sudah tercapai.','error');
        }
        $ins = $db->prepare("INSERT INTO circulation (user_id,book_id,tanggal_pinjam,jatuh_tempo) VALUES (?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL ? DAY))");
        $ins->execute([$userId,$bid,LAMA_PINJAM]);
        $db->prepare("UPDATE books SET tersedia=tersedia-1 WHERE id=?")->execute([$bid]);
        redirect('profile.php?tab=buku&sub=dipinjam','Berhasil meminjam buku! Jatuh tempo '.LAMA_PINJAM.' hari dari sekarang. 📚');
    } else {
        redirect('search.php','Buku tidak tersedia.','error');
    }
}
if (isset($_GET['antri'])) {
    $bid = (int)$_GET['antri'];
    $cekAntri = $db->prepare("SELECT id FROM antrian WHERE user_id=? AND book_id=? AND status='menunggu'");
    $cekAntri->execute([$userId,$bid]);
    if (!$cekAntri->fetch()) {
        $db->prepare("INSERT INTO antrian (user_id,book_id) VALUES (?,?)")->execute([$userId,$bid]);
        redirect('profile.php?tab=buku&sub=antrian','Berhasil masuk antrian! Kami akan memberitahu kamu. ⏳');
    } else {
        redirect('profile.php?tab=buku&sub=antrian','Kamu sudah ada di antrian buku ini.','error');
    }
}
if (isset($_GET['perpanjang'])) {
    $cid = (int)$_GET['perpanjang'];
    $sirk = $db->prepare("SELECT * FROM circulation WHERE id=? AND user_id=? AND status IN('dipinjam','terlambat')");
    $sirk->execute([$cid,$userId]);
    $s = $sirk->fetch();
    if ($s && $s['perpanjangan'] < MAKS_PERPANJANG) {
        $db->prepare("UPDATE circulation SET jatuh_tempo=DATE_ADD(jatuh_tempo,INTERVAL ? DAY), perpanjangan=perpanjangan+1 WHERE id=?")
           ->execute([LAMA_PINJAM,$cid]);
        redirect('profile.php?tab=buku&sub=dipinjam','Peminjaman berhasil diperpanjang '.LAMA_PINJAM.' hari! 🔄');
    } else {
        redirect('profile.php?tab=buku&sub=dipinjam','Tidak dapat diperpanjang (batas '.MAKS_PERPANJANG.'x sudah tercapai).','error');
    }
}
if (isset($_GET['kembalikan'])) {
    $cid = (int)$_GET['kembalikan'];
    $sirk = $db->prepare("SELECT * FROM circulation WHERE id=? AND user_id=? AND status IN('dipinjam','terlambat')");
    $sirk->execute([$cid,$userId]);
    $s = $sirk->fetch();
    if ($s) {
        $today = new DateTime();
        $tempo = new DateTime($s['jatuh_tempo']);
        if ($today > $tempo) {
            redirect('profile.php?tab=buku&sub=dipinjam','Buku terlambat! Silakan bayar denda terlebih dahulu sebelum mengembalikan buku.','error');
        } else {
            $db->prepare("UPDATE circulation SET status='dikembalikan', tanggal_kembali=CURDATE(), denda=0 WHERE id=?")->execute([$cid]);
            $db->prepare("UPDATE books SET tersedia=tersedia+1 WHERE id=?")->execute([$s['book_id']]);
            $nextAntri = $db->prepare("SELECT id FROM antrian WHERE book_id=? AND status='menunggu' ORDER BY tanggal_antri ASC LIMIT 1");
            $nextAntri->execute([$s['book_id']]);
            $next = $nextAntri->fetch();
            if ($next) {
                $db->prepare("UPDATE antrian SET status='tersedia' WHERE id=?")->execute([$next['id']]);
            }
            redirect('profile.php?tab=buku&sub=riwayat','Buku berhasil dikembalikan! Terima kasih. 📚✅');
        }
    } else {
        redirect('profile.php?tab=buku','Data peminjaman tidak ditemukan.','error');
    }
}
// === END Sirkulasi Actions ===

// Check if user is admin
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    // Handle photo upload
    $fotoPath = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'assets/profiles/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $filename = uniqid('profile_') . '.' . $ext;
            $targetFile = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetFile)) {
                $fotoPath = $targetFile;
            } else {
                $errorMsg = "Gagal mengunggah foto.";
            }
        } else {
            $errorMsg = "Format foto tidak didukung. Harap unggah JPG, PNG, atau GIF.";
        }
    }

    if (!$errorMsg) {
        try {
            if ($fotoPath) {
                $stmt = $db->prepare("UPDATE users SET no_hp = ?, alamat = ?, bio = ?, foto = ? WHERE id = ?");
                $stmt->execute([$no_hp, $alamat, $bio, $fotoPath, $userId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET no_hp = ?, alamat = ?, bio = ? WHERE id = ?");
                $stmt->execute([$no_hp, $alamat, $bio, $userId]);
            }
            
            $successMsg = "Profil berhasil diperbarui!";
        } catch (Exception $e) {
            $errorMsg = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

// Fetch latest user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// --- FETCH ACTIVITIES ---
// Auto-update denda and status for overdue books
$db->exec("UPDATE circulation 
    SET denda = DATEDIFF(CURDATE(), jatuh_tempo) * " . DENDA_PER_BUKU . ",
        status = 'terlambat'
    WHERE status IN ('dipinjam','terlambat') 
    AND jatuh_tempo < CURDATE()");

$historiBuku = [];
$historiRuang = [];
$historiReview = [];
$historiDilihat = [];
$listDipinjam = [];
$listRiwayat = [];
$listAntrian = [];

if ($tab === 'buku') {
    $sirkTab = $_GET['sub'] ?? 'dipinjam';
    
    $dipinjam = $db->prepare("SELECT c.*, b.judul, b.pengarang, b.no_kelas, b.cover_image, b.color1, b.color2, b.color3,
        DATEDIFF(c.jatuh_tempo, CURDATE()) as sisa_hari
        FROM circulation c JOIN books b ON c.book_id=b.id
        WHERE c.user_id=? AND c.status IN('dipinjam','terlambat')
        ORDER BY c.jatuh_tempo ASC");
    $dipinjam->execute([$userId]);
    $listDipinjam = $dipinjam->fetchAll();

    $riwayat = $db->prepare("SELECT c.*, b.judul, b.pengarang, b.cover_image, b.color1, b.color2, b.color3, r.rating as my_rating
        FROM circulation c JOIN books b ON c.book_id=b.id
        LEFT JOIN reviews r ON r.book_id = c.book_id AND r.user_id = c.user_id
        WHERE c.user_id=? AND c.status='dikembalikan'
        ORDER BY c.tanggal_kembali DESC LIMIT 30");
    $riwayat->execute([$userId]);
    $listRiwayat = $riwayat->fetchAll();

    $antrian = $db->prepare("SELECT a.*, b.judul, b.pengarang, b.cover_image, b.color1, b.color2, b.color3
        FROM antrian a JOIN books b ON a.book_id=b.id
        WHERE a.user_id=? AND a.status='menunggu'");
    $antrian->execute([$userId]);
    $listAntrian = $antrian->fetchAll();
} elseif ($tab === 'ruang') {
    $stmt = $db->prepare("SELECT rb.*, r.name as nama_ruang FROM room_bookings rb JOIN rooms r ON rb.room_id = r.id WHERE rb.user_id = ? ORDER BY rb.created_at DESC LIMIT 30");
    $stmt->execute([$userId]);
    $historiRuang = $stmt->fetchAll();
} elseif ($tab === 'review') {
    $stmt = $db->prepare("SELECT rv.*, b.judul, b.pengarang, b.kategori, b.cover_image, b.color1, b.color2 FROM reviews rv JOIN books b ON rv.book_id = b.id WHERE rv.user_id = ? ORDER BY rv.created_at DESC LIMIT 30");
    $stmt->execute([$userId]);
    $historiReview = $stmt->fetchAll();
} elseif ($tab === 'dilihat') {
    $stmt = $db->prepare("SELECT MAX(v.viewed_at) as last_viewed, b.id, b.judul, b.pengarang, b.kategori, b.cover_image, b.color1, b.color2 FROM book_views v JOIN books b ON v.book_id = b.id WHERE v.user_id = ? AND v.viewed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) GROUP BY v.book_id ORDER BY last_viewed DESC LIMIT 30");
    $stmt->execute([$userId]);
    $historiDilihat = $stmt->fetchAll();
} elseif ($tab === 'absensi') {
    $stmt = $db->prepare("SELECT * FROM admin_attendance WHERE admin_id = ? ORDER BY shift_date DESC, check_in DESC LIMIT 30");
    $stmt->execute([$userId]);
    $historiAbsensi = $stmt->fetchAll();
} elseif ($tab === 'denda') {
    $stmt = $db->prepare("SELECT dp.*, c.book_id, b.judul FROM denda_payments dp JOIN circulation c ON dp.circulation_id = c.id JOIN books b ON c.book_id = b.id WHERE dp.admin_id = ? AND dp.metode = 'cash' ORDER BY dp.waktu_bayar DESC LIMIT 30");
    $stmt->execute([$userId]);
    $historiDenda = $stmt->fetchAll();
}

// Helper for cover images
require_once 'includes/cover_helper.php';

$pageTitle = "My Profile";
include 'includes/header.php';
?>

<style>
.profile-wrapper {
    max-width: 1200px;
    margin: 2rem auto;
    display: flex;
    gap: 2rem;
    align-items: flex-start;
}

/* Sidebar Styling */
.profile-sidebar {
    width: 280px;
    background: var(--bg-primary);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    overflow: hidden;
    flex-shrink: 0;
}

.sidebar-header {
    background: linear-gradient(135deg, #FF4D6D, #D81B60);
    padding: 2.5rem 1.5rem;
    text-align: center;
    color: white;
}

.sidebar-avatar-wrap {
    width: 100px;
    height: 100px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    background: white;
    padding: 4px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.sidebar-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    background: #fff0f3;
    color: #FF4D6D;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
}

.sidebar-name {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
    text-transform: uppercase;
}

.sidebar-role {
    font-size: 0.85rem;
    opacity: 0.9;
    text-transform: capitalize;
}

.sidebar-menu {
    padding: 1rem 0;
    background: var(--bg-primary);
}

.menu-label {
    padding: 0.8rem 1.5rem 0.4rem;
    font-size: 0.75rem;
    color: var(--muted);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    background: var(--bg-tertiary);
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 0.8rem 1.5rem;
    color: var(--text-main);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s;
    border-left: 3px solid transparent;
}

.menu-item:hover, .menu-item.active {
    background: var(--bg-secondary);
    color: #FF4D6D;
    border-left-color: #FF4D6D;
}

.menu-icon {
    margin-right: 12px;
    font-size: 1.1rem;
}

/* Content Area Styling */
.profile-content {
    flex-grow: 1;
    background: var(--bg-primary);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.05);
    border: 1px solid var(--border);
    padding: 2.5rem;
    min-height: 500px;
}

.content-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.profile-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media(max-width: 800px) {
    .profile-wrapper {
        flex-direction: column;
    }
    .profile-sidebar {
        width: 100%;
    }
    .profile-form-grid {
        grid-template-columns: 1fr;
    }
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--text-main);
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 2px solid var(--border);
    border-radius: 12px;
    background: var(--bg-secondary);
    color: var(--text-main);
    font-family: inherit;
    transition: all 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #FF4D6D;
    box-shadow: 0 0 0 4px rgba(255, 77, 109, 0.1);
}

.form-control[readonly] {
    background: var(--bg-primary);
    color: var(--muted);
    cursor: not-allowed;
    border-color: var(--border);
}

.form-control-file {
    padding: 0.5rem;
    border: 2px dashed var(--border);
    border-radius: 12px;
    width: 100%;
    background: var(--bg-secondary);
    color: var(--text-main);
    cursor: pointer;
}

.alert {
    padding: 1rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-danger {
    background: rgba(244, 63, 94, 0.1);
    color: #e11d48;
    border: 1px solid rgba(244, 63, 94, 0.2);
}

.btn-save {
    background: #FF4D6D;
    color: white;
    border: none;
    padding: 0.8rem 2rem;
    border-radius: 9px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save:hover {
    background: #D81B60;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 77, 109, 0.3);
}

textarea.form-control {
    resize: vertical;
    min-height: 100px;
}

/* Activity Items Styling */
.activity-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.activity-item {
    display: flex;
    gap: 12px;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.activity-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.activity-item-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
    overflow: hidden;
}

.activity-item-icon.book-cover {
    width: 44px;
    height: 60px;
    border-radius: 6px;
    background: transparent;
    padding: 0;
}

.activity-item-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.activity-item-details {
    flex-grow: 1;
    overflow: hidden;
}

.activity-item-title {
    font-weight: 700;
    font-size: 1rem;
    color: var(--text-main);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 4px;
}

.activity-item-sub {
    font-size: 0.85rem;
    color: var(--muted);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 0.75rem;
    font-weight: 700;
}
.status-menunggu { background: rgba(245,158,11,0.1); color: #d97706; }
.status-disetujui { background: rgba(16,185,129,0.1); color: #059669; }
.status-ditolak { background: rgba(244,63,94,0.1); color: #e11d48; }

.empty-activity {
    text-align: center;
    padding: 4rem 1rem;
    background: transparent;
    border: none;
    color: var(--muted);
}
.empty-activity .ea-icon {
    font-size: 3rem;
    margin-bottom: 0.8rem;
    opacity: 0.8;
}
.empty-activity .ea-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 0.4rem;
}
.empty-activity p {
    font-size: 0.85rem;
    margin-bottom: 1.2rem;
}

/* History Card Styling */
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

/* Room Booking Modal Styling */
.ruang-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
    z-index: 999; opacity: 0; pointer-events: none; transition: 0.3s;
}
.ruang-overlay.show { opacity: 1; pointer-events: all; }

.ruang-modal {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.95);
    background: #ffffff; width: 90%; max-width: 500px; border-radius: 20px;
    z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s cubic-bezier(0.34,1.56,0.64,1);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    padding: 2rem;
}
.ruang-modal.show { opacity: 1; pointer-events: all; transform: translate(-50%, -50%) scale(1); }

.rmodal-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; }
.rmodal-title { font-size: 1.25rem; font-weight: 800; color: #1e293b; margin-bottom: 0.2rem; }
.rmodal-close { background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer; }
.rmodal-close:hover { color: #0f172a; }

.rmodal-body { display: flex; flex-direction: column; gap: 1rem; }
.rmodal-row { display: flex; flex-direction: column; gap: 4px; }
.rmodal-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.rmodal-val { font-size: 0.95rem; color: #0f172a; font-weight: 500; }

.rmodal-notes-box {
    background: #f8fafc; padding: 1rem; border-radius: 12px;
    border-left: 4px solid #FF4D6D; font-size: 0.9rem; line-height: 1.5; color: #334155;
}
.rmodal-notes-box.approved { border-left-color: #10b981; background: rgba(16,185,129,0.05); }
.rmodal-notes-box.rejected { border-left-color: #f43f5e; background: rgba(244,63,94,0.05); }
.rmodal-notes-box.pending { border-left-color: #f59e0b; background: rgba(245,158,11,0.05); }

</style>

<div class="container" style="max-width: 1200px; padding: 0 20px;">
    <div class="profile-wrapper">
        <!-- SIDEBAR -->
        <div class="profile-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-avatar-wrap">
                    <?php if (!empty($user['foto']) && file_exists($user['foto'])): ?>
                        <img src="<?= htmlspecialchars($user['foto']) ?>" class="sidebar-avatar" alt="Profile Foto">
                    <?php else: ?>
                        <div class="sidebar-avatar"><?= strtoupper(substr($user['nama'], 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="sidebar-name"><?= htmlspecialchars($user['nama']) ?></div>
                <div class="sidebar-role"><?= htmlspecialchars($user['role']) ?></div>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-label">MENU</div>
                <a href="profile.php?tab=biodata" class="menu-item <?= $tab === 'biodata' ? 'active' : '' ?>">
                    <span class="menu-icon">👤</span> <?= $currentLang === 'en' ? 'Personal Details' : 'Biodata Diri' ?>
                </a>
                
                <?php if ($isAdmin): ?>
                    <a href="admin/index.php" class="menu-item" style="color: #2563eb;">
                        <span class="menu-icon">⚙️</span> <?= $currentLang === 'en' ? 'Admin Panel' : 'Panel Admin' ?>
                    </a>
                    <a href="?tab=absensi" class="menu-item <?= $tab==='absensi' ? 'active' : '' ?>">
                        <span class="menu-icon">⏱️</span> <?= $currentLang === 'en' ? 'Attendance History' : 'Histori Absensi Jaga' ?>
                    </a>
                    <a href="?tab=denda" class="menu-item <?= $tab==='denda' ? 'active' : '' ?>">
                        <span class="menu-icon">💵</span> <?= $currentLang === 'en' ? 'Fines Income (Cash)' : 'Penerimaan Denda (Cash)' ?>
                    </a>
                <?php else: ?>
                    <div class="menu-label"><?= $currentLang === 'en' ? 'MY ACTIVITY' : 'AKTIVITAS SAYA' ?></div>
                    <a href="?tab=buku" class="menu-item <?= $tab==='buku' ? 'active' : '' ?>">
                        <span class="menu-icon">📚</span> <?= $currentLang === 'en' ? 'Book Loans' : 'Peminjaman Buku' ?>
                    </a>
                    <a href="?tab=ruang" class="menu-item <?= $tab==='ruang' ? 'active' : '' ?>">
                        <span class="menu-icon">🏢</span> <?= $currentLang === 'en' ? 'Room Booking' : 'Pengajuan Ruangan' ?>
                    </a>
                    <a href="?tab=review" class="menu-item <?= $tab==='review' ? 'active' : '' ?>">
                        <span class="menu-icon">💬</span> <?= $currentLang === 'en' ? 'Review History' : 'Histori Review' ?>
                    </a>
                    <a href="?tab=dilihat" class="menu-item <?= $tab==='dilihat' ? 'active' : '' ?>">
                        <span class="menu-icon">👁️</span> <?= $currentLang === 'en' ? 'Recently Viewed' : 'Terakhir Dilihat' ?>
                    </a>
                <?php endif; ?>

                <div class="menu-label" style="margin-top:auto;"><?= $currentLang === 'en' ? 'ACTION' : 'AKSI' ?></div>
                <a href="logout.php" class="menu-item" style="color: #e11d48;">
                    <span class="menu-icon">🚪</span> <?= $currentLang === 'en' ? 'Logout' : 'Keluar' ?>
                </a>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="profile-content">
            
            <?php if ($tab === 'biodata'): ?>
                <h2 class="content-title"><?= $currentLang === 'en' ? 'Personal Details' : 'Biodata Diri' ?></h2>
                
                <?php if ($successMsg): ?>
                    <div class="alert alert-success">✅ <?= $successMsg ?></div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                    <div class="alert alert-danger">❌ <?= $errorMsg ?></div>
                <?php endif; ?>

                <form method="POST" action="?tab=biodata" enctype="multipart/form-data">
                    <div class="profile-form-grid">
                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Full Name (Cannot be changed)' : 'Nama Lengkap (Tidak dapat diubah)' ?></label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label"><?= $isAdmin ? ($currentLang === 'en' ? 'Staff ID / Username' : 'ID Pegawai / Username') : ($currentLang === 'en' ? 'Student ID' : 'NIM') ?> <?= $currentLang === 'en' ? '(Cannot be changed)' : '(Tidak dapat diubah)' ?></label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['nim'] ?? '') ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Email (Cannot be changed)' : 'Email (Tidak dapat diubah)' ?></label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Study Program / Department (Cannot be changed)' : 'Program Studi / Departemen (Tidak dapat diubah)' ?></label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['prodi'] ?? '') ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'WhatsApp / Phone Number' : 'Nomor WhatsApp / HP' ?></label>
                            <input type="text" class="form-control" name="no_hp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="<?= $currentLang === 'en' ? 'Start with 08...' : 'Mulai dengan 08...' ?>">
                            <small style="color:var(--muted); font-size:0.8rem;"><?= $currentLang === 'en' ? 'Required for book & room booking confirmation.' : 'Dibutuhkan untuk keperluan konfirmasi peminjaman buku & ruang.' ?></small>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Change Profile Picture' : 'Ganti Foto Profil' ?></label>
                            <input type="file" class="form-control-file" name="foto" accept="image/jpeg, image/png, image/gif">
                            <small style="color:var(--muted); font-size:0.8rem;"><?= $currentLang === 'en' ? 'Leave empty if you don\'t want to change it. Max 2MB.' : 'Kosongkan jika tidak ingin mengubah foto. Maksimal 2MB.' ?></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= $currentLang === 'en' ? 'Domicile Address' : 'Alamat Domisili' ?></label>
                        <textarea class="form-control" name="alamat" placeholder="<?= $currentLang === 'en' ? 'Enter current domicile address...' : 'Masukkan alamat domisili saat ini...' ?>"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= $currentLang === 'en' ? 'Short Bio' : 'Bio Singkat' ?></label>
                        <textarea class="form-control" name="bio" placeholder="<?= $currentLang === 'en' ? 'Tell us a little about yourself (Optional)...' : 'Ceritakan sedikit tentang Anda (Opsional)...' ?>"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>

                    <div style="text-align: right; margin-top: 2rem;">
                        <button type="submit" name="update_profile" class="btn-save">
                            💾 <?= $currentLang === 'en' ? 'Save Changes' : 'Simpan Perubahan' ?>
                        </button>
                    </div>
                </form>
            
            <?php elseif ($tab === 'buku'): ?>
                <style>
                .prof-tabs { display: flex; border-bottom: 2px solid var(--border); margin-bottom: 1.5rem; gap: 0.5rem; overflow-x: auto; scrollbar-width: none; }
                .prof-tabs::-webkit-scrollbar { display: none; }
                .prof-tab { padding: 0.8rem 1.2rem; font-weight: 600; color: var(--text-muted); text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s; position: relative; bottom: -2px; white-space: nowrap; }
                .prof-tab:hover { color: var(--text-main); background: var(--bg-tertiary); border-radius: 8px 8px 0 0; }
                .prof-tab.active { color: var(--accent1); border-bottom-color: var(--accent1); }
                .prof-grid { display: flex; flex-direction: column; gap: 1rem; }
                .prof-card { display: flex; gap: 1.5rem; background: var(--card-bg); padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid var(--border); align-items: stretch; }
                @media(max-width:768px){ .prof-card { flex-direction: column; align-items: center; text-align: center; } .prof-meta-grid { justify-content: center; } .prof-actions { justify-content: center; } .prof-cover { width: 120px; height: 160px; } }
                .prof-cover { width: 110px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: bold; text-align: center; padding: 0.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); line-height: 1.3; }
                .prof-body { flex: 1; display: flex; flex-direction: column; min-width: 0; }
                .prof-title { font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .prof-author { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 1.5rem; }
                .prof-meta-grid { display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
                .prof-meta-item { display: flex; flex-direction: column; gap: 0.3rem; }
                .prof-meta-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
                .prof-meta-val { font-size: 0.95rem; font-weight: 600; color: var(--text-main); }
                .prof-meta-val.danger { color: #e11d48; }
                .prof-meta-val.warning { color: #d97706; }
                .prof-progress-wrap { height: 6px; background: var(--bg-tertiary); border-radius: 3px; overflow: hidden; margin-top: auto; margin-bottom: 1rem; }
                .prof-progress { height: 100%; background: #10B981; border-radius: 3px; }
                .prof-progress.warning { background: #F59E0B; }
                .prof-progress.danger { background: #EF4444; }
                .prof-actions { display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap; }
                .prof-btn { display: inline-flex; align-items: center; justify-content: center; padding: 0.6rem 1.2rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: all 0.2s; border: 1px solid var(--border); color: var(--text-main); background: var(--card-bg); cursor: pointer; }
                .prof-btn:hover { background: var(--bg-tertiary); transform: translateY(-1px); }
                .prof-btn.primary { background: linear-gradient(135deg, var(--accent1), var(--accent2)); color: white; border: none; box-shadow: 0 4px 12px rgba(255,77,109,0.3); }
                .prof-btn.primary:hover { box-shadow: 0 6px 16px rgba(255,77,109,0.4); color: white; background: linear-gradient(135deg, var(--accent1), var(--accent2)); }
                .prof-btn.danger-outline { border-color: #fca5a5; color: #ef4444; background: #fef2f2; }
                .prof-empty { text-align: center; padding: 4rem 2rem; background: var(--bg-tertiary); border-radius: 12px; border: 2px dashed var(--border); }
                .prof-empty-icon { font-size: 3.5rem; margin-bottom: 1rem; opacity: 0.5; }
                .prof-empty-title { font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem; }
                .prof-q-item { display: flex; gap: 1.2rem; padding: 1.2rem; background: var(--card-bg); border-radius: 10px; border: 1px solid var(--border); align-items: center; transition: all 0.2s; }
                .prof-q-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); }
                .prof-q-cover { width: 50px; height: 70px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: bold; text-align: center; padding: 0.3rem; flex-shrink: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); line-height:1.2; }
                </style>
                <div style="width:100%;">
                    <div class="page-header" style="text-align:left; padding:0 0 1rem 0;">
                        <h2 class="content-title" style="margin-bottom:0.5rem; border-bottom:none;">Peminjaman Buku</h2>
                        <p style="color:var(--text-muted); font-size:0.95rem;">Kelola peminjaman, antrian, dan riwayat buku kamu.</p>
                    </div>

                    <div class="prof-tabs">
                        <a class="prof-tab <?= $sirkTab==='dipinjam'?'active':'' ?>" href="?tab=buku&sub=dipinjam">📚 Sedang Dipinjam (<?= count($listDipinjam) ?>)</a>
                        <a class="prof-tab <?= $sirkTab==='antrian'?'active':'' ?>"  href="?tab=buku&sub=antrian">⏳ Antrian (<?= count($listAntrian) ?>)</a>
                        <a class="prof-tab <?= $sirkTab==='riwayat'?'active':'' ?>"  href="?tab=buku&sub=riwayat">🕐 Riwayat (<?= count($listRiwayat) ?>)</a>
                    </div>

                    <div class="rk-history-list">
                        <?php if($sirkTab==='dipinjam'): ?>
                            <?php if(empty($listDipinjam)): ?>
                                <div class="prof-empty">
                                    <div class="prof-empty-icon">📭</div>
                                    <div class="prof-empty-title">Belum ada buku yang dipinjam</div>
                                    <p style="margin-bottom:1.5rem; color:var(--text-muted);">Yuk cari buku di koleksi kami!</p>
                                    <a href="search.php" class="prof-btn primary">Cari Buku Sekarang</a>
                                </div>
                            <?php else: ?>
                                <?php foreach($listDipinjam as $s):
                                $overdue  = $s['sisa_hari'] < 0;
                                $warning  = $s['sisa_hari'] == 0;
                                $valClass = $overdue ? 'color:#e11d48;' : ($warning ? 'color:#d97706;' : 'color:#059669;');
                                $denda    = $overdue ? max($s['denda'], abs($s['sisa_hari']) * DENDA_PER_BUKU) : 0;
                                $covSrc   = bookCoverSrc($s);
                                $grad     = 'linear-gradient(135deg,'.$s['color1'].','.$s['color2'].')';
                                ?>
                                <div class="rk-hist-card">
                                    <div class="rk-hist-thumb" style="<?= !$covSrc ? 'background:'.$grad.';color:'.$s['color3'].';' : '' ?>">
                                        <?php if($covSrc): ?><img src="<?= $covSrc ?>" alt="Cover"><?php else: ?><?= htmlspecialchars(substr($s['judul'],0,18)) ?><?php endif; ?>
                                    </div>
                                    <div class="rk-hist-body">
                                        <div class="rk-hist-title"><?= htmlspecialchars($s['judul']) ?></div>
                                        <div class="rk-hist-author"><?= htmlspecialchars($s['pengarang']) ?></div>
                                        
                                        <div class="rk-hist-dates">
                                            <div class="rk-hist-date">Dipinjam: <span><?= date('d M Y',strtotime($s['tanggal_pinjam'])) ?></span></div>
                                            <div class="rk-hist-date">Jatuh Tempo: <span style="<?= $valClass ?>"><?= date('d M Y',strtotime($s['jatuh_tempo'])) ?></span></div>
                                            <div class="rk-hist-date">Perpanjangan: <span><?= $s['perpanjangan'] ?>/<?= MAKS_PERPANJANG ?>x</span></div>
                                        </div>
                                    </div>
                                    <div class="rk-hist-right" style="gap:8px;">
                                        <div class="rk-hist-badge" style="background:var(--bg-tertiary); color:var(--text-main);">
                                            <?= $overdue ? 'Terlambat '.abs($s['sisa_hari']).' hari' : ($warning ? 'Hari ini!' : $s['sisa_hari'].' hari lagi') ?>
                                        </div>
                                        <?php if(!$overdue): ?>
                                            <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
                                                <a href="?kembalikan=<?= $s['id'] ?>" class="rk-hist-badge" style="background:linear-gradient(135deg,var(--accent1),var(--accent2)); color:#fff; text-decoration:none; padding:6px 12px; font-size:0.75rem;" onclick="return confirm('Konfirmasi pengembalian buku ini?\n\nPastikan buku sudah diserahkan ke petugas RBC FISIP UNAIR.')">📦 Kembalikan</a>
                                                <?php if($s['perpanjangan'] < MAKS_PERPANJANG): ?>
                                                    <a href="?perpanjang=<?= $s['id'] ?>" class="rk-hist-badge" style="background:var(--bg-tertiary); color:var(--text-main); text-decoration:none; padding:6px 12px; font-size:0.75rem; border:1px solid var(--border);">🔄 Perpanjang</a>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="rk-hist-denda" style="margin-top:0.5rem;">⚠️ Denda <?= formatRupiah($denda) ?></div>
                                            <div style="font-size:0.65rem; color:var(--muted);">Harap bayar denda ke petugas</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        <?php elseif($sirkTab==='riwayat'): ?>
                            <?php if(empty($listRiwayat)): ?>
                                <div class="prof-empty">
                                    <div class="prof-empty-icon">📜</div>
                                    <div class="prof-empty-title">Belum ada riwayat peminjaman</div>
                                </div>
                            <?php else: ?>
                                <?php foreach($listRiwayat as $r):
                                $covSrc = bookCoverSrc($r);
                                $grad   = 'linear-gradient(135deg,'.$r['color1'].','.$r['color2'].')';
                                ?>
                                <div class="rk-hist-card">
                                    <div class="rk-hist-thumb" style="<?= !$covSrc ? 'background:'.$grad.';color:'.$r['color3'].';' : '' ?>">
                                        <?php if($covSrc): ?><img src="<?= $covSrc ?>" alt="Cover"><?php else: ?><?= htmlspecialchars(substr($r['judul'],0,18)) ?><?php endif; ?>
                                    </div>
                                    <div class="rk-hist-body">
                                        <div class="rk-hist-title"><?= htmlspecialchars($r['judul']) ?></div>
                                        <div class="rk-hist-author"><?= htmlspecialchars($r['pengarang']) ?></div>
                                        <div class="rk-hist-dates">
                                            <div class="rk-hist-date">Pinjam: <span><?= date('d M Y',strtotime($r['tanggal_pinjam'])) ?></span></div>
                                            <div class="rk-hist-date">Kembali: <span><?= $r['tanggal_kembali'] ? date('d M Y',strtotime($r['tanggal_kembali'])) : '-' ?></span></div>
                                        </div>
                                    </div>
                                    <div class="rk-hist-right">
                                        <?php if($r['denda']>0): ?><div class="rk-hist-denda">Denda: <?= formatRupiah($r['denda']) ?></div>
                                        <?php else: ?><div class="rk-hist-nodenda">Tidak ada denda</div><?php endif; ?>
                                        
                                        <?php if($r['my_rating']): ?>
                                            <div class="rk-hist-stars"><?= str_repeat('⭐', $r['my_rating']) ?></div>
                                        <?php else: ?>
                                            <a href="booktalk.php?review=<?= $r['book_id'] ?>" class="rk-hist-review">+ Beri Rating</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        <?php elseif($sirkTab==='antrian'): ?>
                            <?php if(empty($listAntrian)): ?>
                                <div class="prof-empty">
                                    <div class="prof-empty-icon">⏳</div>
                                    <div class="prof-empty-title">Tidak ada antrian aktif</div>
                                </div>
                            <?php else: ?>
                                <?php foreach($listAntrian as $a):
                                $covSrc = bookCoverSrc($a);
                                $grad   = 'linear-gradient(135deg,'.$a['color1'].','.$a['color2'].')';
                                ?>
                                <div class="rk-hist-card">
                                    <div class="rk-hist-thumb" style="<?= !$covSrc ? 'background:'.$grad.';color:'.$a['color3'].';' : '' ?>">
                                        <?php if($covSrc): ?><img src="<?= $covSrc ?>" alt="Cover"><?php else: ?><?= htmlspecialchars(substr($a['judul'],0,18)) ?><?php endif; ?>
                                    </div>
                                    <div class="rk-hist-body">
                                        <div class="rk-hist-title"><?= htmlspecialchars($a['judul']) ?></div>
                                        <div class="rk-hist-author"><?= htmlspecialchars($a['pengarang']) ?></div>
                                        <div class="rk-hist-dates">
                                            <div class="rk-hist-date">Masuk antrian: <span><i class="fas fa-clock"></i> <?= date('d M Y H:i',strtotime($a['tanggal_antri'])) ?></span></div>
                                        </div>
                                    </div>
                                    <div class="rk-hist-right">
                                        <a href="search.php?detail=<?= $a['book_id'] ?>" class="rk-hist-badge" style="background:var(--bg-tertiary); color:var(--text-main); text-decoration:none; padding:6px 12px; font-size:0.75rem; border:1px solid var(--border);">Lihat Buku</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($tab === 'ruang'): ?>
                <h2 class="content-title">Pengajuan Ruangan</h2>
                <?php if(empty($historiRuang)): ?>
                    <div class="empty-activity">
                        <div class="ea-icon">🏢</div>
                        <div class="ea-title">Belum ada pengajuan ruangan</div>
                        <p>Riwayat peminjaman ruangan baca Anda akan muncul di sini.</p>
                        <a href="ruangan.php" class="btn-save" style="display:inline-block; text-decoration:none;">Pinjam Ruang</a>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach($historiRuang as $r): 
                            $stClass = strtolower($r['status']) == 'approved' ? 'status-disetujui' : (strtolower($r['status']) == 'rejected' ? 'status-ditolak' : 'status-menunggu');
                            
                            // Prepare data for modal
                            $modalData = htmlspecialchars(json_encode([
                                'nama_ruang' => $r['nama_ruang'],
                                'tanggal' => date('d M Y', strtotime($r['booking_date'])),
                                'waktu' => date('H:i', strtotime($r['start_time'])) . ' - ' . date('H:i', strtotime($r['end_time'])),
                                'kegiatan' => $r['activity_category'],
                                'status' => ucfirst($r['status']),
                                'statusClass' => $stClass,
                                'alasan' => $r['admin_notes'] ? $r['admin_notes'] : ($r['status'] === 'pending' ? 'Pengajuan Anda sedang ditinjau oleh Admin.' : ($r['status'] === 'approved' ? 'Silakan mengambil kunci di resepsionis pada hari H.' : 'Pengajuan ditolak. Silakan hubungi admin.'))
                            ]));
                        ?>
                        <div class="activity-item" style="cursor:pointer; padding: 1rem; border-radius:12px; transition:0.2s;" onmouseover="this.style.background='var(--bg-secondary)'" onmouseout="this.style.background='transparent'" onclick="openRuangModal(this.dataset.info)" data-info="<?= $modalData ?>">
                            <div class="activity-item-icon" style="background:#e0f2fe; color:#0284c7;">🏢</div>
                            <div class="activity-item-details">
                                <div class="activity-item-title"><?= htmlspecialchars($r['nama_ruang']) ?></div>
                                <div class="activity-item-sub">Tanggal Pengajuan: <?= date('d M Y', strtotime($r['booking_date'])) ?> | Status: <span class="status-badge <?= $stClass ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></div>
                            </div>
                            <div style="color:var(--muted); font-size:1.2rem;">&rsaquo;</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'review'): ?>
                <h2 class="content-title">Histori Review Buku</h2>
                <?php if(empty($historiReview)): ?>
                    <div class="empty-activity">
                        <div class="ea-icon">💬</div>
                        <div class="ea-title">Belum ada review yang ditulis</div>
                        <p>Bagikan pendapat Anda tentang buku yang sudah Anda baca.</p>
                        <a href="booktalk.php" class="btn-save" style="display:inline-block; text-decoration:none;">Tulis Review</a>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach($historiReview as $rv): ?>
                        <div class="activity-item">
                            <div class="activity-item-icon book-cover">
                                <?php if($src = bookCoverSrc($rv)): ?>
                                    <img src="<?= $src ?>">
                                <?php else: ?>
                                    <div style="width:100%; height:100%; background:linear-gradient(135deg,<?= $rv['color1']??'#667eea' ?>,<?= $rv['color2']??'#764ba2' ?>); display:flex; align-items:center; justify-content:center; color:white; font-size:10px; font-weight:bold; text-align:center; padding:2px;">
                                        <?= htmlspecialchars(substr($rv['judul'],0,10)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="activity-item-details">
                                <div class="activity-item-title"><?= htmlspecialchars($rv['judul']) ?> (Rating: <?= $rv['rating'] ?>/5)</div>
                                <div class="activity-item-sub">"<?= htmlspecialchars($rv['isi']) ?>"</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'dilihat'): ?>
                <h2 class="content-title">Terakhir Dilihat</h2>
                <?php if(empty($historiDilihat)): ?>
                    <div class="empty-activity">
                        <div class="ea-icon">👁️</div>
                        <div class="ea-title">Belum ada buku yang dilihat bulan ini</div>
                        <p>Eksplorasi koleksi perpustakaan untuk menemukan buku menarik.</p>
                        <a href="search.php" class="btn-save" style="display:inline-block; text-decoration:none;">Cari Buku</a>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach($historiDilihat as $v): ?>
                        <div class="activity-item">
                            <div class="activity-item-icon book-cover">
                                <?php if($src = bookCoverSrc($v)): ?>
                                    <img src="<?= $src ?>">
                                <?php else: ?>
                                    <div style="width:100%; height:100%; background:linear-gradient(135deg,<?= $v['color1']??'#667eea' ?>,<?= $v['color2']??'#764ba2' ?>); display:flex; align-items:center; justify-content:center; color:white; font-size:10px; font-weight:bold; text-align:center; padding:2px;">
                                        <?= htmlspecialchars(substr($v['judul'],0,10)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="activity-item-details">
                                <div class="activity-item-title"><?= htmlspecialchars($v['judul']) ?></div>
                                <div class="activity-item-sub">Terakhir dilihat: <?= date('d M Y, H:i', strtotime($v['last_viewed'])) ?></div>
                            </div>
                            <a href="search.php?detail=<?= $v['id'] ?>" class="btn-save" style="padding:0.4rem 1rem; font-size:0.8rem; background:var(--bg-tertiary); color:var(--text-main); border:1px solid var(--border); box-shadow:none;">Lihat Lagi</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($tab === 'absensi' && $user['role'] === 'admin'): ?>
                <h2 class="content-title">Histori Absensi Jaga</h2>
                <?php if(empty($historiAbsensi)): ?>
                    <div class="empty-activity">
                        <div class="ea-icon">⏱️</div>
                        <div class="ea-title">Belum ada histori absensi jaga</div>
                        <p>Riwayat absensi shift jaga Anda akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach($historiAbsensi as $abs): ?>
                        <div class="activity-item">
                            <div class="activity-item-icon" style="background:#f0fdf4; color:#16a34a; width:50px; height:50px; border-radius:10px;">📅</div>
                            <div class="activity-item-details">
                                <div class="activity-item-title"><?= date('d F Y', strtotime($abs['shift_date'])) ?></div>
                                <div class="activity-item-sub">Check In: <?= $abs['check_in'] ?> | Check Out: <?= $abs['check_out'] ?? '-' ?> <br>Status: <?= htmlspecialchars($abs['status']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($tab === 'denda' && $user['role'] === 'admin'): ?>
                <h2 class="content-title">Penerimaan Denda (Cash)</h2>
                <?php if(empty($historiDenda)): ?>
                    <div class="empty-activity">
                        <div class="ea-icon">💵</div>
                        <div class="ea-title">Belum ada penerimaan denda cash</div>
                        <p>Riwayat denda tunai yang Anda proses akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach($historiDenda as $d): ?>
                        <div class="activity-item">
                            <div class="activity-item-icon" style="background:#fef3c7; color:#d97706; width:50px; height:50px; border-radius:10px;">💵</div>
                            <div class="activity-item-details">
                                <div class="activity-item-title">Rp <?= number_format($d['jumlah'],0,',','.') ?> - NIM: <?= htmlspecialchars($d['nim_pembayar']) ?></div>
                                <div class="activity-item-sub">Buku: <?= htmlspecialchars($d['judul']) ?><br>Diterima pada: <?= date('d M Y H:i', strtotime($d['waktu_bayar'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail Ruangan -->
<div class="ruang-overlay" id="ruangOverlay" onclick="closeRuangModal()"></div>
<div class="ruang-modal" id="ruangModal">
    <div class="rmodal-header">
        <div>
            <div class="rmodal-title" id="rmTitle">Nama Ruangan</div>
            <div style="margin-top:0.3rem;"><span id="rmBadge" class="status-badge">Status</span></div>
        </div>
        <button class="rmodal-close" onclick="closeRuangModal()">&times;</button>
    </div>
    <div class="rmodal-body">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div class="rmodal-row">
                <span class="rmodal-label">Tanggal Pelaksanaan</span>
                <span class="rmodal-val" id="rmDate">-</span>
            </div>
            <div class="rmodal-row">
                <span class="rmodal-label">Waktu</span>
                <span class="rmodal-val" id="rmTime">-</span>
            </div>
        </div>
        <div class="rmodal-row">
            <span class="rmodal-label">Kategori Kegiatan</span>
            <span class="rmodal-val" id="rmCat">-</span>
        </div>
        <div class="rmodal-row" style="margin-top:0.5rem;">
            <span class="rmodal-label" id="rmNotesLabel">Catatan / Alasan Admin</span>
            <div id="rmNotesBox" class="rmodal-notes-box">
                -
            </div>
        </div>
    </div>
</div>

<script>
function openRuangModal(dataStr) {
    const data = JSON.parse(dataStr);
    
    document.getElementById('rmTitle').textContent = data.nama_ruang;
    document.getElementById('rmDate').textContent = data.tanggal;
    document.getElementById('rmTime').textContent = data.waktu;
    document.getElementById('rmCat').textContent = data.kegiatan;
    
    const badge = document.getElementById('rmBadge');
    badge.textContent = data.status;
    badge.className = 'status-badge ' + data.statusClass;
    
    const notesBox = document.getElementById('rmNotesBox');
    notesBox.innerHTML = data.alasan.replace(/\n/g, '<br>');
    
    // Set notes box color based on status
    notesBox.className = 'rmodal-notes-box';
    if(data.status.toLowerCase() === 'approved' || data.status.toLowerCase() === 'disetujui') notesBox.classList.add('approved');
    else if(data.status.toLowerCase() === 'rejected' || data.status.toLowerCase() === 'ditolak') notesBox.classList.add('rejected');
    else notesBox.classList.add('pending');

    document.getElementById('rmNotesLabel').textContent = data.status.toLowerCase() === 'rejected' ? 'Alasan Penolakan' : (data.status.toLowerCase() === 'approved' ? 'Instruksi Selanjutnya' : 'Status Saat Ini');

    document.getElementById('ruangOverlay').classList.add('show');
    document.getElementById('ruangModal').classList.add('show');
}

function closeRuangModal() {
    document.getElementById('ruangOverlay').classList.remove('show');
    document.getElementById('ruangModal').classList.remove('show');
}
</script>

<?php include 'includes/footer.php'; ?>
