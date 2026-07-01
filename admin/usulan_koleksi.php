<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS usulan_koleksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    judul VARCHAR(255) NOT NULL,
    pengarang VARCHAR(255),
    penerbit VARCHAR(255),
    tahun INT,
    isbn VARCHAR(50),
    kategori VARCHAR(100),
    alasan TEXT,
    status ENUM('menunggu','disetujui','ditolak') DEFAULT 'menunggu',
    catatan_admin TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $id   = (int)$_POST['id'];
        $stat = $_POST['status'];
        $cat  = trim($_POST['catatan_admin']);
        $db->prepare("UPDATE usulan_koleksi SET status=?, catatan_admin=? WHERE id=?")->execute([$stat, $cat, $id]);
        // If approved, optionally push to acquisitions
        if ($stat === 'disetujui' && isset($_POST['push_akuisisi'])) {
            $u = $db->prepare("SELECT * FROM usulan_koleksi WHERE id=?");
            $u->execute([$id]); $uc = $u->fetch();
            if ($uc) {
                $db->prepare("INSERT INTO acquisitions (judul,pengarang,penerbit,tahun,isbn,kategori,status,tanggal_usulan)
                    VALUES (?,?,?,?,?,?,'usulan',CURDATE())")->execute([
                    $uc['judul'],$uc['pengarang'],$uc['penerbit'],$uc['tahun'],$uc['isbn'],$uc['kategori']
                ]);
            }
        }
        redirect('usulan_koleksi.php', 'Usulan berhasil diperbarui.');
    }
    if ($action === 'tambah_usulan') {
        $f = $_POST;
        $db->prepare("INSERT INTO usulan_koleksi (judul,pengarang,penerbit,tahun,isbn,kategori,alasan,status)
            VALUES (?,?,?,?,?,?,?,'menunggu')")->execute([
            trim($f['judul']),trim($f['pengarang']),trim($f['penerbit']),(int)$f['tahun'],trim($f['isbn']),trim($f['kategori']),trim($f['alasan'])
        ]);
        redirect('usulan_koleksi.php', 'Usulan berhasil ditambahkan!');
    }
    if ($action === 'hapus') {
        $db->prepare("DELETE FROM usulan_koleksi WHERE id=?")->execute([(int)$_POST['id']]);
        redirect('usulan_koleksi.php', 'Usulan dihapus.');
    }
}

$status_f = $_GET['status'] ?? '';
$search   = $_GET['q'] ?? '';
$where = '1=1'; $params = [];
if ($status_f) { $where .= ' AND u.status=?'; $params[] = $status_f; }
if ($search)   { $where .= ' AND (u.judul LIKE ? OR u.pengarang LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%"]); }

$stmt = $db->prepare("SELECT u.*, m.nama as pengusul, m.nim FROM usulan_koleksi u LEFT JOIN users m ON u.user_id=m.id WHERE $where ORDER BY u.created_at DESC");
$stmt->execute($params); $rows = $stmt->fetchAll();
$counts = $db->query("SELECT status, COUNT(*) FROM usulan_koleksi GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Usulan Koleksi';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">📋 Usulan Koleksi</h1>
      <p class="page-sub">Kelola usulan pengadaan koleksi dari anggota perpustakaan</p>
    </div>
    <button class="adm-btn adm-btn-primary" onclick="document.getElementById('addModal').style.display='flex'">➕ Tambah Usulan</button>
  </div>

  <!-- Status chips -->
  <div style="display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <a href="usulan_koleksi.php" class="adm-badge <?= !$status_f?'adm-badge-blue':'' ?>" style="padding:6px 14px;font-size:0.82rem;text-decoration:none;background:<?= !$status_f?'rgba(59,130,246,0.15)':'#f3f4f6' ?>;">Semua (<?= array_sum($counts) ?>)</a>
    <?php foreach(['menunggu'=>['⏳','adm-badge-yellow'],'disetujui'=>['✅','adm-badge-green'],'ditolak'=>['❌','adm-badge-red']] as $s=>[$ic,$bc]): ?>
    <a href="?status=<?= $s ?>" class="adm-badge <?= $status_f===$s?$bc:'' ?>" style="padding:6px 14px;font-size:0.82rem;text-decoration:none;background:<?= $status_f===$s?'':'#f3f4f6' ?>;"><?= $ic ?> <?= ucfirst($s) ?> (<?= $counts[$s]??0 ?>)</a>
    <?php endforeach; ?>
  </div>

  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;width:100%;">
      <input type="hidden" name="status" value="<?= htmlspecialchars($status_f) ?>">
      <input class="filter-input" type="text" name="q" placeholder="🔍 Cari judul atau pengarang..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
      <button type="submit" class="adm-btn adm-btn-primary">Cari</button>
      <a href="usulan_koleksi.php" class="adm-btn adm-btn-ghost">Reset</a>
    </form>
  </div>

  <div class="dash-card">
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>#</th><th>Buku yang Diusulkan</th><th>Pengusul</th><th>Kategori</th><th>Alasan</th><th>Status</th><th>Waktu</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if(empty($rows)): ?>
          <tr><td colspan="8" class="empty-row">Belum ada usulan koleksi</td></tr>
          <?php endif; ?>
          <?php foreach($rows as $i => $r): ?>
          <tr>
            <td class="cell-sub"><?= $i+1 ?></td>
            <td>
              <div class="cell-title"><?= htmlspecialchars($r['judul']) ?></div>
              <div class="cell-sub"><?= htmlspecialchars($r['pengarang']??'—') ?> <?= $r['tahun']?'· '.$r['tahun']:'' ?></div>
              <?php if($r['isbn']): ?><div class="cell-sub">ISBN: <?= htmlspecialchars($r['isbn']) ?></div><?php endif; ?>
            </td>
            <td>
              <?php if($r['pengusul']): ?>
              <div class="cell-title"><?= htmlspecialchars($r['pengusul']) ?></div>
              <div class="cell-sub"><?= htmlspecialchars($r['nim']??'') ?></div>
              <?php else: ?><span class="cell-sub">Admin</span><?php endif; ?>
            </td>
            <td><span class="adm-badge adm-badge-purple"><?= htmlspecialchars($r['kategori']??'—') ?></span></td>
            <td style="max-width:180px;font-size:0.8rem;color:#374151;"><?= htmlspecialchars(substr($r['alasan']??'',0,80)) ?><?= strlen($r['alasan']??'')>80?'…':'' ?></td>
            <td>
              <?php $bc=['menunggu'=>'adm-badge-yellow','disetujui'=>'adm-badge-green','ditolak'=>'adm-badge-red'][$r['status']]??'adm-badge-yellow'; ?>
              <span class="adm-badge <?= $bc ?>"><?= ucfirst($r['status']) ?></span>
              <?php if($r['catatan_admin']): ?><div class="cell-sub" style="margin-top:4px;"><?= htmlspecialchars(substr($r['catatan_admin'],0,40)) ?></div><?php endif; ?>
            </td>
            <td class="cell-sub"><?= timeAgo($r['created_at']) ?></td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <button onclick="openReview(<?= $r['id'] ?>,'<?= $r['status'] ?>','<?= addslashes($r['catatan_admin']??'') ?>')" class="adm-btn adm-btn-secondary adm-btn-sm">📝 Review</button>
                <form method="POST" onsubmit="return confirm('Hapus usulan?')">
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id" value="<?= $r['id'] ?>">
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

<!-- Modal Review -->
<div id="reviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:2rem;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="font-size:1.1rem;font-weight:800;">📝 Review Usulan</h2>
      <button onclick="document.getElementById('reviewModal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6B7280;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="reviewId">
      <div class="adm-form-group">
        <label class="adm-label">Status Keputusan</label>
        <select class="adm-input" name="status" id="reviewStatus">
          <option value="menunggu">⏳ Menunggu</option>
          <option value="disetujui">✅ Disetujui</option>
          <option value="ditolak">❌ Ditolak</option>
        </select>
      </div>
      <div class="adm-form-group" id="pushAkuisisiGroup" style="display:none;">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.85rem;font-weight:600;">
          <input type="checkbox" name="push_akuisisi" value="1" style="accent-color:var(--adm-accent);"> Tambahkan ke daftar Akuisisi otomatis
        </label>
      </div>
      <div class="adm-form-group">
        <label class="adm-label">Catatan Admin (opsional)</label>
        <textarea class="adm-input" name="catatan_admin" id="reviewCatatan" rows="3" style="resize:vertical;" placeholder="Alasan persetujuan/penolakan..."></textarea>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:12px;">✅ Simpan Keputusan</button>
    </form>
  </div>
</div>

<!-- Modal Tambah Usulan (admin) -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;">
  <div style="background:#fff;border-radius:16px;padding:2rem;width:100%;max-width:580px;box-shadow:0 20px 60px rgba(0,0,0,0.2);margin:2rem auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="font-size:1.1rem;font-weight:800;">➕ Tambah Usulan Koleksi</h2>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6B7280;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah_usulan">
      <div class="adm-form-grid">
        <div class="adm-form-group full"><label class="adm-label">Judul Buku *</label><input class="adm-input" type="text" name="judul" required></div>
        <div class="adm-form-group"><label class="adm-label">Pengarang</label><input class="adm-input" type="text" name="pengarang"></div>
        <div class="adm-form-group"><label class="adm-label">Penerbit</label><input class="adm-input" type="text" name="penerbit"></div>
        <div class="adm-form-group"><label class="adm-label">Tahun</label><input class="adm-input" type="number" name="tahun" value="<?= date('Y') ?>"></div>
        <div class="adm-form-group"><label class="adm-label">ISBN</label><input class="adm-input" type="text" name="isbn"></div>
        <div class="adm-form-group"><label class="adm-label">Kategori</label>
          <select class="adm-input" name="kategori">
            <?php foreach(['Sosiologi','Komunikasi','Politik','Administrasi','Antropologi','Ilmu Informasi','Hukum','Ekonomi','Umum'] as $k): ?><option><?= $k ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="adm-form-group full"><label class="adm-label">Alasan / Justifikasi</label><textarea class="adm-input" name="alasan" rows="3" style="resize:vertical;" placeholder="Mengapa buku ini perlu diadakan?"></textarea></div>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:12px;margin-top:0.5rem;">📋 Tambah Usulan</button>
    </form>
  </div>
</div>

<script>
function openReview(id, status, catatan) {
  document.getElementById('reviewId').value = id;
  document.getElementById('reviewStatus').value = status;
  document.getElementById('reviewCatatan').value = catatan;
  document.getElementById('reviewModal').style.display = 'flex';
}
document.getElementById('reviewStatus').addEventListener('change', function() {
  document.getElementById('pushAkuisisiGroup').style.display = this.value === 'disetujui' ? 'block' : 'none';
});
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
