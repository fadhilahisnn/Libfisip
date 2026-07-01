<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$pageTitle = 'Admin Panel';
$db = getDB();

// === AUTO-UPDATE: Hitung denda & hari terlambat otomatis ===
$db->exec("UPDATE circulation 
    SET denda = DATEDIFF(CURDATE(), jatuh_tempo) * " . DENDA_PER_BUKU . ",
        status = 'terlambat'
    WHERE status IN ('dipinjam','terlambat') 
    AND jatuh_tempo < CURDATE()");

// Handle tambah buku
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    if ($_POST['action']==='tambah_buku') {
        $f = $_POST;
        $ins = $db->prepare("INSERT INTO books (judul,pengarang,penerbit,tahun,isbn,no_kelas,kategori,bahasa,stok,tersedia,deskripsi,color1,color2,color3) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stok = (int)$f['stok'];
        $ins->execute([
            trim($f['judul']),trim($f['pengarang']),trim($f['penerbit']),(int)$f['tahun'],
            trim($f['isbn']),trim($f['no_kelas']),trim($f['kategori']),trim($f['bahasa']),
            $stok,$stok,trim($f['deskripsi']),$f['color1'],$f['color2'],$f['color3']
        ]);
        redirect('index.php','Buku berhasil ditambahkan! 📚');
    }
    if ($_POST['action']==='kembalikan') {
        $cid = (int)$_POST['cid'];
        $sirk = $db->prepare("SELECT * FROM circulation WHERE id=?"); $sirk->execute([$cid]); $s=$sirk->fetch();
        if ($s) {
            $denda=0;$today=new DateTime();$tempo=new DateTime($s['jatuh_tempo']);
            if($today>$tempo) {
                // Overdue — redirect to admin sirkulasi for payment
                redirect('admin/sirkulasi.php','Buku terlambat! Silakan proses pembayaran denda dari halaman Sirkulasi Admin.','error');
            } else {
                $db->prepare("UPDATE circulation SET status='dikembalikan',tanggal_kembali=CURDATE(),denda=0 WHERE id=?")->execute([$cid]);
                $db->prepare("UPDATE books SET tersedia=tersedia+1 WHERE id=?")->execute([$s['book_id']]);
                redirect('index.php','Buku berhasil dikembalikan. Tidak ada denda.');
            }
        }
    }
}

// Stats
$stats = [
    'buku'      => $db->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'users'     => $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn(),
    'dipinjam'  => $db->query("SELECT COUNT(*) FROM circulation WHERE status IN('dipinjam','terlambat')")->fetchColumn(),
    'terlambat' => $db->query("SELECT COUNT(*) FROM circulation WHERE status='terlambat'")->fetchColumn(),
    'denda'     => $db->query("SELECT COALESCE(SUM(denda),0) FROM circulation")->fetchColumn(),
    'review'    => $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
];

// Semua peminjaman aktif
$aktif = $db->query("SELECT c.*, u.nama, u.nim, b.judul, b.no_kelas, DATEDIFF(c.jatuh_tempo,CURDATE()) as sisa
    FROM circulation c JOIN users u ON c.user_id=u.id JOIN books b ON c.book_id=b.id
    WHERE c.status IN('dipinjam','terlambat') ORDER BY c.jatuh_tempo ASC")->fetchAll();

$tab = $_GET['tab']??'dashboard';

include_once __DIR__ . '/../includes/header.php';
?>
<main>
<div style="max-width:1280px;margin:0 auto;padding:2rem;">
  <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div><h1>⚙️ Admin Panel</h1><p>Manajemen koleksi dan sirkulasi LibFISIP</p></div>
    <div style="display:flex;gap:8px;">
      <a href="?tab=dashboard" class="sirk-tab <?= $tab==='dashboard'?'active':'' ?>">📊 Dashboard</a>
      <a href="?tab=buku"      class="sirk-tab <?= $tab==='buku'?'active':'' ?>">📚 Koleksi</a>
      <a href="?tab=sirkulasi" class="sirk-tab <?= $tab==='sirkulasi'?'active':'' ?>">🔄 Sirkulasi</a>
      <a href="?tab=tambah"    class="sirk-tab <?= $tab==='tambah'?'active':'' ?>">➕ Tambah Buku</a>
    </div>
  </div>

  <?php if($tab==='dashboard'): ?>
  <div class="admin-grid">
    <div class="admin-stat"><div class="admin-stat-num"><?= $stats['buku'] ?></div><div class="admin-stat-label">Total Koleksi</div></div>
    <div class="admin-stat"><div class="admin-stat-num" style="color:var(--accent2);"><?= $stats['users'] ?></div><div class="admin-stat-label">Anggota Aktif</div></div>
    <div class="admin-stat"><div class="admin-stat-num" style="color:var(--accent4);"><?= $stats['dipinjam'] ?></div><div class="admin-stat-label">Sedang Dipinjam</div></div>
    <div class="admin-stat"><div class="admin-stat-num" style="color:#ff6b6b;"><?= $stats['terlambat'] ?></div><div class="admin-stat-label">Terlambat</div></div>
    <div class="admin-stat"><div class="admin-stat-num" style="color:var(--accent3);"><?= $stats['review'] ?></div><div class="admin-stat-label">Total Review</div></div>
    <div class="admin-stat"><div class="admin-stat-num" style="color:#ff6b6b;font-size:1.3rem;"><?= formatRupiah($stats['denda']) ?></div><div class="admin-stat-label">Total Denda</div></div>
  </div>

  <?php elseif($tab==='buku'): ?>
  <?php $allBooks = $db->query("SELECT b.*,COALESCE(AVG(r.rating),0) as ar,COUNT(DISTINCT r.id) as tr FROM books b LEFT JOIN reviews r ON b.id=r.book_id GROUP BY b.id ORDER BY b.judul")->fetchAll(); ?>
  <table class="opac-table">
    <thead><tr><th>Judul</th><th>No. Kelas</th><th>Kategori</th><th>Stok</th><th>Tersedia</th><th>Rating</th></tr></thead>
    <tbody>
    <?php foreach($allBooks as $b): ?>
    <tr>
      <td><div class="book-cell"><div class="opac-mini-cover" style="background:linear-gradient(135deg,<?=$b['color1']?>,<?=$b['color2']?>);color:<?=$b['color3']?>;"><?=htmlspecialchars(substr($b['judul'],0,10))?></div><div><div class="opac-title"><?=htmlspecialchars($b['judul'])?></div><div class="opac-author"><?=htmlspecialchars($b['pengarang'])?> · <?=$b['tahun']?></div></div></div></td>
      <td><span class="call-num"><?=htmlspecialchars($b['no_kelas'])?></span></td>
      <td><span style="font-size:0.8rem;color:var(--muted);"><?=htmlspecialchars($b['kategori'])?></span></td>
      <td><?=$b['stok']?></td>
      <td><?=$b['tersedia']>0 ? '<span style="color:var(--accent3);">'.$b['tersedia'].'</span>' : '<span style="color:#ff6b6b;">0</span>'?></td>
      <td><span style="color:var(--accent2);"><?=$b['ar']>0?stars((int)round($b['ar'])):'—'?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php elseif($tab==='sirkulasi'): ?>
  <?php if(empty($aktif)): ?>
    <div class="empty-state"><div class="empty-state-icon">🎉</div><div class="empty-state-title">Semua buku sudah dikembalikan!</div></div>
  <?php else: ?>
  <table class="opac-table">
    <thead><tr><th>Peminjam</th><th>Buku</th><th>Pinjam</th><th>Jatuh Tempo</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach($aktif as $a): $overdue=$a['sisa']<0; ?>
    <tr>
      <td><div class="opac-title"><?=htmlspecialchars($a['nama'])?></div><div class="opac-author"><?=htmlspecialchars($a['nim'])?></div></td>
      <td><div class="opac-title"><?=htmlspecialchars(substr($a['judul'],0,35))?></div><div class="opac-author"><span class="call-num"><?=htmlspecialchars($a['no_kelas'])?></span></div></td>
      <td style="font-size:0.8rem;"><?=date('d M Y',strtotime($a['tanggal_pinjam']))?></td>
      <td style="font-size:0.8rem;color:<?=$overdue?'#ff6b6b':'var(--text)'?>;"><?=date('d M Y',strtotime($a['jatuh_tempo']))?><?php if($overdue): ?><br><small style="color:#ff6b6b;"><?=abs($a['sisa'])?> hari terlambat · <?=formatRupiah(abs($a['sisa']) * DENDA_PER_BUKU)?></small><?php endif; ?></td>
      <td><?=$overdue?'<span class="status-badge status-danger">● Terlambat</span>':'<span class="status-badge status-available">● Dipinjam</span>'?></td>
      <td>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Konfirmasi pengembalian buku ini?')">
          <input type="hidden" name="action" value="kembalikan">
          <input type="hidden" name="cid" value="<?=$a['id']?>">
          <button type="submit" class="action-btn primary">✓ Kembali</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php elseif($tab==='tambah'): ?>
  <div class="admin-form" style="max-width:700px;">
    <h3 style="font-family:'Playfair Display',serif;font-size:1.2rem;margin-bottom:1.5rem;">📚 Tambah Koleksi Baru</h3>
    <form method="POST" class="modal-form">
      <input type="hidden" name="action" value="tambah_buku">
      <div class="form-row">
        <div class="form-group"><label class="form-label">Judul Buku *</label><input class="form-input" type="text" name="judul" required></div>
        <div class="form-group"><label class="form-label">Pengarang *</label><input class="form-input" type="text" name="pengarang" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Penerbit</label><input class="form-input" type="text" name="penerbit"></div>
        <div class="form-group"><label class="form-label">Tahun Terbit</label><input class="form-input" type="number" name="tahun" min="1900" max="2030" value="<?=date('Y')?>"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">No. Kelas (DDC) *</label><input class="form-input" style="font-family:'Space Mono',monospace;" type="text" name="no_kelas" placeholder="301 SOE s" required></div>
        <div class="form-group"><label class="form-label">ISBN</label><input class="form-input" type="text" name="isbn"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Kategori *</label>
          <select class="form-input" name="kategori" required>
            <?php foreach(['Sosiologi','Komunikasi','Politik','Administrasi','Antropologi','Ilmu Informasi','Hukum','Ekonomi','Umum'] as $k): ?>
              <option><?=$k?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Bahasa</label>
          <select class="form-input" name="bahasa">
            <option>Indonesia</option><option>Inggris</option><option>Lainnya</option>
          </select>
        </div>
      </div>
      <div class="form-group"><label class="form-label">Jumlah Stok</label><input class="form-input" type="number" name="stok" value="1" min="1"></div>
      <div class="form-group"><label class="form-label">Deskripsi</label><textarea class="form-input" name="deskripsi" rows="3" style="resize:vertical;"></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Warna Cover 1</label><input class="form-input" type="color" name="color1" value="#0d1a33"></div>
        <div class="form-group"><label class="form-label">Warna Cover 2</label><input class="form-input" type="color" name="color2" value="#1a3a7a"></div>
        <div class="form-group"><label class="form-label">Warna Teks Cover</label><input class="form-input" type="color" name="color3" value="#60a5fa"></div>
      </div>
      <button type="submit" class="modal-submit">➕ Tambah Koleksi</button>
    </form>
  </div>
  <?php endif; ?>
</div>
</main>
<?php include_once __DIR__ . '/../includes/footer.php'; ?>
