<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// Create acquisitions table if needed
$db->exec("CREATE TABLE IF NOT EXISTS acquisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    pengarang VARCHAR(255),
    penerbit VARCHAR(255),
    tahun INT,
    isbn VARCHAR(50),
    kategori VARCHAR(100),
    bahasa VARCHAR(50) DEFAULT 'Indonesia',
    jumlah INT DEFAULT 1,
    harga INT DEFAULT 0,
    sumber ENUM('pembelian','hibah','tukar','lainnya') DEFAULT 'pembelian',
    status ENUM('usulan','disetujui','dipesan','diterima','dikatalog') DEFAULT 'usulan',
    catatan TEXT,
    tanggal_usulan DATE,
    tanggal_terima DATE NULL,
    book_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE SET NULL
)");

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah_akuisisi') {
        $f = $_POST;
        $db->prepare("INSERT INTO acquisitions (judul,pengarang,penerbit,tahun,isbn,kategori,bahasa,jumlah,harga,sumber,status,catatan,tanggal_usulan)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            trim($f['judul']), trim($f['pengarang']), trim($f['penerbit']),
            (int)$f['tahun'], trim($f['isbn']),
            trim($f['kategori']), trim($f['bahasa']),
            (int)$f['jumlah'], (int)str_replace('.','',trim($f['harga'])),
            $f['sumber'], $f['status'], trim($f['catatan']),
            $f['tanggal_usulan'] ?: date('Y-m-d')
        ]);
        redirect('akuisisi.php', 'Data akuisisi berhasil ditambahkan! 📥');
    }

    if ($action === 'update_status') {
        $id  = (int)$_POST['id'];
        $st  = $_POST['status'];
        $tgl = $_POST['tanggal_terima'] ?: null;
        $db->prepare("UPDATE acquisitions SET status=?, tanggal_terima=? WHERE id=?")->execute([$st, $tgl, $id]);
        redirect('akuisisi.php', 'Status akuisisi diperbarui.');
    }

    if ($action === 'hapus') {
        $db->prepare("DELETE FROM acquisitions WHERE id=?")->execute([(int)$_POST['id']]);
        redirect('akuisisi.php', 'Data akuisisi dihapus.');
    }
}

// Filters
$status_f = $_GET['status'] ?? '';
$search   = $_GET['q'] ?? '';
$where = '1=1'; $params = [];
if ($status_f) { $where .= ' AND status=?'; $params[] = $status_f; }
if ($search)   { $where .= ' AND (judul LIKE ? OR pengarang LIKE ? OR isbn LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }

$stmt = $db->prepare("SELECT * FROM acquisitions WHERE $where ORDER BY created_at DESC");
$stmt->execute($params); $rows = $stmt->fetchAll();

// Summary counts
$counts = $db->query("SELECT status, COUNT(*) as n FROM acquisitions GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Akuisisi — Daftar Koleksi';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">📥 Akuisisi — Daftar Koleksi</h1>
      <p class="page-sub">Manajemen pengadaan dan penerimaan koleksi baru</p>
    </div>
    <button class="adm-btn adm-btn-primary" onclick="openModal('addModal')">➕ Tambah Data Akuisisi</button>
  </div>

  <!-- Status Summary -->
  <div class="stat-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.5rem;">
    <?php
    $statuses = ['usulan'=>['🗒️','Usulan','adm-badge-yellow'],'disetujui'=>['✅','Disetujui','adm-badge-blue'],'dipesan'=>['🛒','Dipesan','adm-badge-purple'],'diterima'=>['📦','Diterima','adm-badge-green'],'dikatalog'=>['📚','Dikatalog','adm-badge-blue']];
    foreach ($statuses as $s => [$icon,$lbl,$cls]):
    ?>
    <a href="?status=<?= $s ?>" class="stat-card" style="text-decoration:none;<?= $status_f===$s?'border-color:var(--adm-accent);':'' ?>">
      <div class="stat-icon" style="font-size:1.5rem;width:42px;height:42px;"><?= $icon ?></div>
      <div class="stat-body">
        <div class="stat-val" style="font-size:1.4rem;"><?= $counts[$s] ?? 0 ?></div>
        <div class="stat-lbl"><?= $lbl ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Filter bar -->
  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
      <input class="filter-input" type="text" name="q" placeholder="🔍 Cari judul, pengarang, ISBN..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
      <select class="filter-input" name="status">
        <option value="">Semua Status</option>
        <?php foreach(array_keys($statuses) as $s): ?>
        <option value="<?= $s ?>" <?= $status_f===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="adm-btn adm-btn-primary">Cari</button>
      <a href="akuisisi.php" class="adm-btn adm-btn-ghost">Reset</a>
    </form>
  </div>

  <div class="dash-card">
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>#</th><th>Judul / Pengarang</th><th>ISBN</th><th>Kategori</th><th>Jml</th><th>Sumber</th><th>Status</th><th>Tgl Usulan</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if(empty($rows)): ?>
          <tr><td colspan="9" class="empty-row">Belum ada data akuisisi</td></tr>
          <?php endif; ?>
          <?php foreach($rows as $i => $r): ?>
          <tr>
            <td class="cell-sub"><?= $i+1 ?></td>
            <td>
              <div class="cell-title"><?= htmlspecialchars($r['judul']) ?></div>
              <div class="cell-sub"><?= htmlspecialchars($r['pengarang'] ?? '—') ?> <?= $r['tahun'] ? '· '.$r['tahun'] : '' ?></div>
              <?php if($r['penerbit']): ?><div class="cell-sub"><?= htmlspecialchars($r['penerbit']) ?></div><?php endif; ?>
            </td>
            <td class="cell-sub" style="font-family:'Space Mono',monospace;"><?= htmlspecialchars($r['isbn'] ?? '—') ?></td>
            <td><span class="adm-badge adm-badge-purple"><?= htmlspecialchars($r['kategori'] ?? '—') ?></span></td>
            <td class="cell-title" style="text-align:center;"><?= $r['jumlah'] ?></td>
            <td>
              <?php $srcMap = ['pembelian'=>'🛒','hibah'=>'🎁','tukar'=>'🔁','lainnya'=>'📌']; ?>
              <span class="cell-sub"><?= $srcMap[$r['sumber']] ?? '' ?> <?= ucfirst($r['sumber']) ?></span>
            </td>
            <td>
              <?php
              $badgeMap = ['usulan'=>'adm-badge-yellow','disetujui'=>'adm-badge-blue','dipesan'=>'adm-badge-purple','diterima'=>'adm-badge-green','dikatalog'=>'adm-badge-blue'];
              $bc = $badgeMap[$r['status']] ?? 'adm-badge-yellow';
              ?>
              <span class="adm-badge <?= $bc ?>"><?= ucfirst($r['status']) ?></span>
            </td>
            <td class="cell-sub"><?= $r['tanggal_usulan'] ? date('d M Y', strtotime($r['tanggal_usulan'])) : '—' ?></td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <button onclick="openUpdateModal(<?= $r['id'] ?>,'<?= $r['status'] ?>')" class="adm-btn adm-btn-secondary adm-btn-sm">🔄 Status</button>
                <?php if($r['status']==='diterima' && !$r['book_id']): ?>
                <a href="tambah_buku.php?from_akuisisi=<?= $r['id'] ?>&judul=<?= urlencode($r['judul']) ?>&pengarang=<?= urlencode($r['pengarang']??'') ?>&isbn=<?= urlencode($r['isbn']??'') ?>&kategori=<?= urlencode($r['kategori']??'') ?>&tahun=<?= $r['tahun'] ?>" class="adm-btn adm-btn-green adm-btn-sm">📝 Katalog</a>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Hapus data ini?')">
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

<!-- Modal Tambah Akuisisi -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;overflow-y:auto;">
  <div style="background:#fff;border-radius:16px;padding:2rem;width:100%;max-width:680px;box-shadow:0 20px 60px rgba(0,0,0,0.2);margin:2rem auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="font-size:1.1rem;font-weight:800;">📥 Tambah Data Akuisisi</h2>
      <button onclick="closeModal('addModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6B7280;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="tambah_akuisisi">
      <div class="adm-form-grid">
        <div class="adm-form-group full">
          <label class="adm-label">Judul Buku *</label>
          <input class="adm-input" type="text" name="judul" required>
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Pengarang</label>
          <input class="adm-input" type="text" name="pengarang">
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Penerbit</label>
          <input class="adm-input" type="text" name="penerbit">
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Tahun</label>
          <input class="adm-input" type="number" name="tahun" value="<?= date('Y') ?>" min="1900" max="2030">
        </div>
        <div class="adm-form-group">
          <label class="adm-label">ISBN</label>
          <input class="adm-input" type="text" name="isbn">
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Kategori</label>
          <select class="adm-input" name="kategori">
            <?php foreach(['Sosiologi','Komunikasi','Politik','Administrasi','Antropologi','Ilmu Informasi','Hukum','Ekonomi','Umum'] as $k): ?>
            <option><?= $k ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Bahasa</label>
          <select class="adm-input" name="bahasa">
            <option>Indonesia</option><option>Inggris</option><option>Lainnya</option>
          </select>
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Jumlah Eksemplar</label>
          <input class="adm-input" type="number" name="jumlah" value="1" min="1">
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Harga Satuan (Rp)</label>
          <input class="adm-input" type="number" name="harga" value="0" min="0">
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Sumber Pengadaan</label>
          <select class="adm-input" name="sumber">
            <option value="pembelian">🛒 Pembelian</option>
            <option value="hibah">🎁 Hibah/Donasi</option>
            <option value="tukar">🔁 Tukar Menukar</option>
            <option value="lainnya">📌 Lainnya</option>
          </select>
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Status Awal</label>
          <select class="adm-input" name="status">
            <option value="usulan">🗒️ Usulan</option>
            <option value="disetujui">✅ Disetujui</option>
            <option value="dipesan">🛒 Dipesan</option>
            <option value="diterima">📦 Diterima</option>
          </select>
        </div>
        <div class="adm-form-group">
          <label class="adm-label">Tanggal Usulan</label>
          <input class="adm-input" type="date" name="tanggal_usulan" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="adm-form-group full">
          <label class="adm-label">Catatan</label>
          <textarea class="adm-input" name="catatan" rows="2" style="resize:vertical;"></textarea>
        </div>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:12px;margin-top:0.5rem;">📥 Simpan Data Akuisisi</button>
    </form>
  </div>
</div>

<!-- Modal Update Status -->
<div id="updateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="font-size:1.1rem;font-weight:800;">🔄 Update Status</h2>
      <button onclick="closeModal('updateModal')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6B7280;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="id" id="updateId">
      <div class="adm-form-group">
        <label class="adm-label">Status Baru</label>
        <select class="adm-input" name="status" id="updateStatus">
          <option value="usulan">🗒️ Usulan</option>
          <option value="disetujui">✅ Disetujui</option>
          <option value="dipesan">🛒 Dipesan</option>
          <option value="diterima">📦 Diterima</option>
          <option value="dikatalog">📚 Dikatalog</option>
        </select>
      </div>
      <div class="adm-form-group">
        <label class="adm-label">Tanggal Terima (jika diterima)</label>
        <input class="adm-input" type="date" name="tanggal_terima" value="<?= date('Y-m-d') ?>">
      </div>
      <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:12px;">✅ Simpan</button>
    </form>
  </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display='flex'; }
function closeModal(id) { document.getElementById(id).style.display='none'; }
function openUpdateModal(id, status) {
  document.getElementById('updateId').value = id;
  document.getElementById('updateStatus').value = status;
  openModal('updateModal');
}
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
