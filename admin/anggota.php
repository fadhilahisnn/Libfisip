<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'hapus') {
        $id = (int)$_POST['id'];
        // Remove user (cascade should remove circulation & reviews)
        $db->prepare("DELETE FROM users WHERE id=? AND role='mahasiswa'")->execute([$id]);
        redirect('anggota.php', 'Anggota berhasil dihapus.');
    }
    if ($action === 'tambah') {
        $nama = trim($_POST['nama']);
        $nim  = trim($_POST['nim']);
        $prodi = trim($_POST['prodi']);
        $email = trim($_POST['email']);
        $pass  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        // Validasi format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('anggota.php', 'Format email tidak valid (harus mengandung @)!', 'error');
        }
        // Validasi password min 6
        if (strlen($_POST['password']) < 6) {
            redirect('anggota.php', 'Password minimal 6 karakter!', 'error');
        }
        
        // Check duplicate
        $check = $db->prepare("SELECT id FROM users WHERE nim=? OR email=?");
        $check->execute([$nim, $email]);
        if ($check->fetch()) {
            redirect('anggota.php', 'NIM atau email sudah terdaftar!', 'error');
        }
        $db->prepare("INSERT INTO users (nama,nim,prodi,email,password,role) VALUES (?,?,?,?,?,'mahasiswa')")->execute([$nama,$nim,$prodi,$email,$pass]);
        redirect('anggota.php', 'Anggota berhasil ditambahkan! 👤');
    }
    if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $nama = trim($_POST['nama']);
        $nim = trim($_POST['nim']);
        $prodi = trim($_POST['prodi']);
        $email = trim($_POST['email']);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect('anggota.php', 'Format email tidak valid (harus mengandung @)!', 'error');
        }
        if (!empty($_POST['password']) && strlen($_POST['password']) < 6) {
            redirect('anggota.php', 'Password minimal 6 karakter!', 'error');
        }

        // Check duplicate (excluding current user)
        $check = $db->prepare("SELECT id FROM users WHERE (nim=? OR email=?) AND id!=?");
        $check->execute([$nim, $email, $id]);
        if ($check->fetch()) {
            redirect('anggota.php', 'NIM atau email sudah digunakan anggota lain!', 'error');
        }
        $db->prepare("UPDATE users SET nama=?, nim=?, prodi=?, email=? WHERE id=? AND role='mahasiswa'")->execute([$nama, $nim, $prodi, $email, $id]);
        // Update password if provided
        if (!empty($_POST['password'])) {
            $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$pass, $id]);
        }
        redirect('anggota.php', 'Data anggota berhasil diperbarui! ✏️');
    }
}

$search = $_GET['q'] ?? '';
$prodi_filter = $_GET['prodi'] ?? '';

$where = "role='mahasiswa'";
$params = [];
if ($search) { $where .= " AND (nama LIKE ? OR nim LIKE ? OR email LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($prodi_filter) { $where .= " AND prodi=?"; $params[] = $prodi_filter; }

$stmt = $db->prepare("SELECT u.*, 
    COUNT(c.id) as total_pinjam,
    SUM(CASE WHEN c.status IN ('dipinjam','terlambat') THEN 1 ELSE 0 END) as aktif_pinjam,
    COALESCE(SUM(CASE WHEN c.status IN ('dipinjam','terlambat') AND c.jatuh_tempo < CURDATE() THEN 1 ELSE 0 END),0) as terlambat,
    COALESCE(SUM(c.denda),0) as total_denda
    FROM users u LEFT JOIN circulation c ON u.id=c.user_id
    WHERE $where GROUP BY u.id ORDER BY u.nama");
$stmt->execute($params);
$users = $stmt->fetchAll();

$prodiList = $db->query("SELECT DISTINCT prodi FROM users WHERE role='mahasiswa' AND prodi IS NOT NULL AND prodi!='' ORDER BY prodi")->fetchAll(PDO::FETCH_COLUMN);

// Summary stats
$totalAnggota = count($users);
$totalAktifPinjam = 0;
$totalTerlambat = 0;
$totalDenda = 0;
foreach ($users as $u) {
    $totalAktifPinjam += (int)$u['aktif_pinjam'];
    $totalTerlambat += (int)$u['terlambat'];
    $totalDenda += (int)$u['total_denda'];
}

$pageTitle = 'Data Anggota';
include __DIR__ . '/includes/admin_header.php';
?>

<style>
/* === Anggota Page Styles === */
.anggota-stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 1.8rem;
}
.anggota-stat-card {
  background: #fff;
  border-radius: 14px;
  padding: 1.2rem 1.4rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  box-shadow: 0 2px 12px rgba(0,0,0,0.05);
  border: 1px solid #E5E7EB;
  transition: transform 0.2s, box-shadow 0.2s;
}
.anggota-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.anggota-stat-icon {
  width: 48px; height: 48px; border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem; flex-shrink: 0;
}
.anggota-stat-val {
  font-size: 1.5rem; font-weight: 800; line-height: 1;
  font-family: 'Space Mono', monospace;
}
.anggota-stat-lbl {
  font-size: 0.72rem; color: #6B7280; font-weight: 600; margin-top: 3px;
}

/* Member avatar */
.member-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--adm-accent), #8B5CF6);
  color: #fff; display: flex; align-items: center; justify-content: center;
  font-weight: 800; font-size: 0.9rem; flex-shrink: 0;
  letter-spacing: -0.5px;
  box-shadow: 0 2px 8px rgba(255,77,109,0.2);
}

/* Table enhancements */
.anggota-table .member-info {
  display: flex; align-items: center; gap: 12px; min-width: 200px;
}
.anggota-table .member-name {
  font-weight: 700; font-size: 0.88rem; color: #111827;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 200px;
}
.anggota-table .member-email {
  font-size: 0.72rem; color: #6B7280; margin-top: 2px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 200px;
}
.anggota-table .nim-cell {
  font-family: 'Space Mono', monospace; font-size: 0.82rem;
  background: #F3F4F6; padding: 4px 10px; border-radius: 6px;
  display: inline-block; font-weight: 600; color: #374151;
}
.anggota-table .prodi-cell {
  font-size: 0.8rem; color: #4B5563; font-weight: 500;
}

/* Action buttons */
.action-group {
  display: flex; gap: 6px; align-items: center;
}

/* Filter bar improved */
.anggota-filter {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 1.5rem; flex-wrap: wrap;
  background: #fff; padding: 1rem 1.2rem; border-radius: 12px;
  border: 1px solid #E5E7EB; box-shadow: 0 1px 4px rgba(0,0,0,0.03);
}
.anggota-filter input[type="text"],
.anggota-filter select {
  padding: 9px 14px; border: 1px solid #E5E7EB; border-radius: 8px;
  font-family: inherit; font-size: 0.85rem; background: #F9FAFB;
  color: #111827; outline: none; transition: all 0.2s;
}
.anggota-filter input[type="text"] { flex: 1; min-width: 220px; }
.anggota-filter select { min-width: 160px; }
.anggota-filter input:focus,
.anggota-filter select:focus { border-color: var(--adm-accent); box-shadow: 0 0 0 3px rgba(255,77,109,0.08); background: #fff; }
.anggota-filter .filter-count {
  margin-left: auto; font-size: 0.78rem; color: #9CA3AF; font-weight: 500;
  white-space: nowrap;
}

/* Modal overlay */
.anggota-modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 1000; align-items: center; justify-content: center;
  backdrop-filter: blur(4px);
  padding: 1rem;
}
.anggota-modal {
  background: #fff; border-radius: 20px; width: 100%; max-width: 520px;
  box-shadow: 0 25px 80px rgba(0,0,0,0.25);
  overflow: hidden; animation: angModalIn 0.3s ease;
  max-height: 92vh; display: flex; flex-direction: column;
}
@keyframes angModalIn {
  from { transform: translateY(30px) scale(0.95); opacity: 0; }
  to { transform: translateY(0) scale(1); opacity: 1; }
}
.anggota-modal-header {
  background: linear-gradient(135deg, #0F172A, #1E293B);
  padding: 1.5rem 2rem; color: #fff;
  display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0;
}
.anggota-modal-header h2 {
  font-size: 1.05rem; font-weight: 800; display: flex; align-items: center; gap: 8px;
}
.anggota-modal-header .modal-close-btn {
  background: rgba(255,255,255,0.15); border: none; font-size: 1.1rem;
  cursor: pointer; color: #fff; width: 32px; height: 32px;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  transition: background 0.2s;
}
.anggota-modal-header .modal-close-btn:hover { background: rgba(255,255,255,0.3); }
.anggota-modal-body {
  padding: 1.5rem 2rem 2rem; overflow-y: auto; flex: 1;
}
.anggota-modal-body .form-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0;
}
.anggota-modal-body .form-group { margin-bottom: 1rem; }
.anggota-modal-body .form-group.full { grid-column: 1 / -1; }
.anggota-modal-body label {
  display: block; font-size: 0.78rem; font-weight: 700; color: #374151;
  margin-bottom: 5px; letter-spacing: 0.2px;
}
.anggota-modal-body input,
.anggota-modal-body select {
  width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB;
  border-radius: 10px; font-family: inherit; font-size: 0.88rem;
  background: #F9FAFB; color: #111827; transition: all 0.2s; outline: none;
}
.anggota-modal-body input:focus,
.anggota-modal-body select:focus {
  border-color: var(--adm-accent); box-shadow: 0 0 0 3px rgba(255,77,109,0.1); background: #fff;
}
.anggota-modal-body .form-hint {
  font-size: 0.7rem; color: #9CA3AF; margin-top: 3px;
}
.anggota-modal-body .submit-btn {
  width: 100%; padding: 13px; border: none; border-radius: 12px;
  background: var(--adm-accent); color: #fff; font-family: inherit;
  font-size: 0.92rem; font-weight: 700; cursor: pointer;
  box-shadow: 0 4px 14px rgba(255,77,109,0.3);
  transition: all 0.2s; margin-top: 0.5rem;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.anggota-modal-body .submit-btn:hover {
  background: #E63956; transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(255,77,109,0.4);
}

/* Responsive */
@media (max-width: 768px) {
  .anggota-stats { grid-template-columns: repeat(2, 1fr); }
  .anggota-filter input[type="text"] { min-width: 100%; flex-basis: 100%; }
  .anggota-modal-body .form-row { grid-template-columns: 1fr; }
  .anggota-table .member-info { min-width: 160px; }
  .anggota-table .member-name,
  .anggota-table .member-email { max-width: 140px; }
}
@media (max-width: 480px) {
  .anggota-stats { grid-template-columns: 1fr; }
}
</style>

<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">👥 Data Anggota</h1>
      <p class="page-sub">Manajemen data anggota perpustakaan</p>
    </div>
    <button class="adm-btn adm-btn-primary" onclick="openModal('tambahModal')">➕ Tambah Anggota</button>
  </div>

  <!-- Summary Stats -->
  <div class="anggota-stats">
    <div class="anggota-stat-card">
      <div class="anggota-stat-icon" style="background:rgba(59,130,246,0.1);">👥</div>
      <div>
        <div class="anggota-stat-val" style="color:#2563EB;"><?= $totalAnggota ?></div>
        <div class="anggota-stat-lbl">Total Anggota</div>
      </div>
    </div>
    <div class="anggota-stat-card">
      <div class="anggota-stat-icon" style="background:rgba(245,158,11,0.1);">📚</div>
      <div>
        <div class="anggota-stat-val" style="color:#D97706;"><?= $totalAktifPinjam ?></div>
        <div class="anggota-stat-lbl">Sedang Meminjam</div>
      </div>
    </div>
    <div class="anggota-stat-card">
      <div class="anggota-stat-icon" style="background:rgba(239,68,68,0.1);">⚠️</div>
      <div>
        <div class="anggota-stat-val" style="color:#DC2626;"><?= $totalTerlambat ?></div>
        <div class="anggota-stat-lbl">Terlambat</div>
      </div>
    </div>
    <div class="anggota-stat-card">
      <div class="anggota-stat-icon" style="background:rgba(139,92,246,0.1);">💰</div>
      <div>
        <div class="anggota-stat-val" style="color:#7C3AED;font-size:1rem;"><?= formatRupiah($totalDenda) ?></div>
        <div class="anggota-stat-lbl">Total Denda</div>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="anggota-filter">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
      <input type="text" name="q" placeholder="🔍 Cari nama, NIM, atau email anggota..." value="<?= htmlspecialchars($search) ?>">
      <select name="prodi" onchange="this.form.submit()">
        <option value="">📋 Semua Prodi</option>
        <?php foreach($prodiList as $p): ?>
        <option value="<?= htmlspecialchars($p) ?>" <?= $prodi_filter===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="adm-btn adm-btn-primary" style="padding:9px 18px;">Cari</button>
      <?php if($search || $prodi_filter): ?>
      <a href="anggota.php" class="adm-btn adm-btn-ghost">✕ Reset</a>
      <?php endif; ?>
      <span class="filter-count"><?= count($users) ?> anggota ditemukan</span>
    </form>
  </div>

  <!-- Members Table -->
  <div class="dash-card">
    <div class="adm-table-wrap">
      <table class="adm-table anggota-table">
        <thead>
          <tr>
            <th style="width:40px;">#</th>
            <th style="min-width:220px;">Anggota</th>
            <th style="min-width:130px;">NIM</th>
            <th style="min-width:140px;">Program Studi</th>
            <th style="min-width:90px;text-align:center;">Total Pinjam</th>
            <th style="min-width:90px;text-align:center;">Aktif</th>
            <th style="min-width:100px;">Terdaftar</th>
            <th style="min-width:140px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($users)): ?>
          <tr><td colspan="8" class="empty-row">
            <div style="padding:2rem 0;">
              <div style="font-size:2.5rem;margin-bottom:8px;">😕</div>
              <div style="font-weight:600;margin-bottom:4px;">Tidak ada anggota ditemukan</div>
              <div style="font-size:0.78rem;color:#9CA3AF;">Coba ubah filter pencarian atau tambah anggota baru</div>
            </div>
          </td></tr>
          <?php endif; ?>
          <?php foreach($users as $i => $u):
            $initials = '';
            $nameParts = explode(' ', trim($u['nama']));
            $initials = strtoupper(substr($nameParts[0], 0, 1));
            if (count($nameParts) > 1) $initials .= strtoupper(substr(end($nameParts), 0, 1));
            $colors = ['#FF4D6D','#8B5CF6','#3B82F6','#059669','#D97706','#EC4899','#0891B2','#7C3AED'];
            $avatarColor = $colors[$u['id'] % count($colors)];
            $hasOverdue = $u['terlambat'] > 0;
          ?>
          <tr>
            <td class="cell-sub"><?= $i+1 ?></td>
            <td>
              <div class="member-info">
                <div class="member-avatar" style="background:linear-gradient(135deg,<?= $avatarColor ?>,<?= $colors[($u['id']+3) % count($colors)] ?>);">
                  <?= $initials ?>
                </div>
                <div>
                  <div class="member-name"><?= htmlspecialchars($u['nama']) ?></div>
                  <div class="member-email"><?= htmlspecialchars($u['email'] ?? '—') ?></div>
                </div>
              </div>
            </td>
            <td><span class="nim-cell"><?= htmlspecialchars($u['nim'] ?? '—') ?></span></td>
            <td><span class="prodi-cell"><?= htmlspecialchars($u['prodi'] ?? '—') ?></span></td>
            <td style="text-align:center;">
              <span class="adm-badge adm-badge-blue"><?= $u['total_pinjam'] ?>x</span>
            </td>
            <td style="text-align:center;">
              <?php if($hasOverdue): ?>
                <span class="adm-badge adm-badge-red">⚠ <?= $u['terlambat'] ?> terlambat</span>
              <?php elseif($u['aktif_pinjam'] > 0): ?>
                <span class="adm-badge adm-badge-yellow"><?= $u['aktif_pinjam'] ?> buku</span>
              <?php else: ?>
                <span class="cell-sub">—</span>
              <?php endif; ?>
            </td>
            <td class="cell-sub"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div class="action-group" style="justify-content:center;">
                <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm" onclick='openEditModal(<?= json_encode([
                  "id" => $u["id"],
                  "nama" => $u["nama"],
                  "nim" => $u["nim"] ?? "",
                  "prodi" => $u["prodi"] ?? "",
                  "email" => $u["email"] ?? ""
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  ✏️ Edit
                </button>
                <form method="POST" onsubmit="return confirm('Yakin hapus anggota «<?= htmlspecialchars($u['nama'], ENT_QUOTES) ?>»?\nSemua data peminjaman & review akan terhapus.')" style="margin:0;">
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ========== Modal: Tambah Anggota ========== -->
<div id="tambahModal" class="anggota-modal-overlay" onclick="if(event.target===this)closeModal('tambahModal')">
  <div class="anggota-modal">
    <div class="anggota-modal-header">
      <h2>➕ Tambah Anggota Baru</h2>
      <button class="modal-close-btn" onclick="closeModal('tambahModal')">×</button>
    </div>
    <div class="anggota-modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="tambah">
        <div class="form-row">
          <div class="form-group">
            <label>Nama Lengkap *</label>
            <input type="text" name="nama" required placeholder="Masukkan nama lengkap">
          </div>
          <div class="form-group">
            <label>NIM *</label>
            <input type="text" name="nim" required placeholder="Contoh: 071911133090">
            <div class="form-hint">Nomor Induk Mahasiswa</div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Program Studi *</label>
            <select name="prodi" required>
              <option value="">— Pilih Prodi —</option>
              <?php foreach(['Sosiologi','Ilmu Komunikasi','Ilmu Politik','Administrasi Negara','Antropologi','Hubungan Internasional','Ilmu Informasi dan Perpustakaan'] as $p): ?>
              <option><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input type="text" name="email" required placeholder="nama@fisip.unair.ac.id">
          </div>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" required placeholder="Minimal 6 karakter">
          <div class="form-hint">Password untuk login anggota</div>
        </div>
        <button type="submit" class="submit-btn">👤 Tambah Anggota</button>
      </form>
    </div>
  </div>
</div>

<!-- ========== Modal: Edit Anggota ========== -->
<div id="editModal" class="anggota-modal-overlay" onclick="if(event.target===this)closeModal('editModal')">
  <div class="anggota-modal">
    <div class="anggota-modal-header" style="background:linear-gradient(135deg,#1E40AF,#3B82F6);">
      <h2>✏️ Edit Data Anggota</h2>
      <button class="modal-close-btn" onclick="closeModal('editModal')">×</button>
    </div>
    <div class="anggota-modal-body">
      <form method="POST">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_id">
        <div class="form-row">
          <div class="form-group">
            <label>Nama Lengkap *</label>
            <input type="text" name="nama" id="edit_nama" required>
          </div>
          <div class="form-group">
            <label>NIM *</label>
            <input type="text" name="nim" id="edit_nim" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Program Studi *</label>
            <select name="prodi" id="edit_prodi" required>
              <option value="">— Pilih Prodi —</option>
              <?php foreach(['Sosiologi','Ilmu Komunikasi','Ilmu Politik','Administrasi Negara','Antropologi','Hubungan Internasional','Ilmu Informasi dan Perpustakaan'] as $p): ?>
              <option><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Email *</label>
            <input type="text" name="email" id="edit_email" required>
          </div>
        </div>
        <div class="form-group">
          <label>Password Baru</label>
          <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah">
          <div class="form-hint">Biarkan kosong jika tidak mengubah password</div>
        </div>
        <button type="submit" class="submit-btn" style="background:#2563EB;box-shadow:0 4px 14px rgba(37,99,235,0.3);">✏️ Simpan Perubahan</button>
      </form>
    </div>
  </div>
</div>

<script>
function openModal(id) {
  document.getElementById(id).style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
  document.body.style.overflow = '';
}
function openEditModal(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_nama').value = data.nama;
  document.getElementById('edit_nim').value = data.nim;
  document.getElementById('edit_email').value = data.email;
  // Set prodi select
  const prodiSelect = document.getElementById('edit_prodi');
  for (let i = 0; i < prodiSelect.options.length; i++) {
    if (prodiSelect.options[i].value === data.prodi) {
      prodiSelect.selectedIndex = i;
      break;
    }
  }
  openModal('editModal');
}

// Keyboard shortcut: Escape to close modals
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('tambahModal');
    closeModal('editModal');
  }
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
