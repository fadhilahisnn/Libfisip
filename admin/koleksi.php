<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'hapus') {
        $id = (int)$_POST['id'];
        // Check no active loans
        $check = $db->prepare("SELECT COUNT(*) FROM circulation WHERE book_id=? AND status='dipinjam'");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            redirect('koleksi.php', 'Tidak bisa menghapus buku yang sedang dipinjam!', 'error');
        }
        $db->prepare("DELETE FROM books WHERE id=?")->execute([$id]);
        redirect('koleksi.php', 'Buku berhasil dihapus.');
    }
    if ($_POST['action'] === 'update_stok') {
        $id = (int)$_POST['id'];
        $stok = (int)$_POST['stok'];
        $tersedia = (int)$_POST['tersedia'];
        $db->prepare("UPDATE books SET stok=?, tersedia=? WHERE id=?")->execute([$stok, $tersedia, $id]);
        redirect('koleksi.php', 'Stok buku berhasil diupdate.');
    }
}

// Filters
$search = $_GET['q'] ?? '';
$kategori = $_GET['kategori'] ?? '';
$sort = $_GET['sort'] ?? 'judul';

$where = "1=1";
$params = [];
if ($search) { $where .= " AND (b.judul LIKE ? OR b.pengarang LIKE ? OR b.isbn LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($kategori) { $where .= " AND b.kategori=?"; $params[] = $kategori; }

$sortMap = ['judul'=>'b.judul', 'terbaru'=>'b.created_at DESC', 'populer'=>'total_pinjam DESC', 'tersedia'=>'b.tersedia DESC'];
$orderBy = $sortMap[$sort] ?? 'b.judul';

$stmt = $db->prepare("SELECT b.*, COALESCE(AVG(r.rating),0) as ar, COUNT(DISTINCT r.id) as tr,
    COUNT(DISTINCT c.id) as total_pinjam
    FROM books b
    LEFT JOIN reviews r ON b.id=r.book_id
    LEFT JOIN circulation c ON b.id=c.book_id
    WHERE $where
    GROUP BY b.id ORDER BY $orderBy");
$stmt->execute($params);
$books = $stmt->fetchAll();

$kategoriList = $db->query("SELECT DISTINCT kategori FROM books ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Kelola Koleksi';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">📖 Kelola Koleksi</h1>
      <p class="page-sub">Total <?= count($books) ?> judul buku ditemukan</p>
    </div>
    <a href="tambah_buku.php" class="adm-btn adm-btn-primary">➕ Tambah Buku</a>
  </div>

  <!-- Filter bar -->
  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
      <input class="filter-input" type="text" name="q" placeholder="🔍 Cari judul, pengarang, ISBN..." value="<?= htmlspecialchars($search) ?>" style="flex:1;min-width:200px;">
      <select class="filter-input" name="kategori">
        <option value="">Semua Kategori</option>
        <?php foreach($kategoriList as $k): ?>
        <option value="<?= htmlspecialchars($k) ?>" <?= $kategori===$k?'selected':'' ?>><?= htmlspecialchars($k) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="filter-input" name="sort">
        <option value="judul" <?= $sort==='judul'?'selected':'' ?>>Urutkan: A-Z</option>
        <option value="terbaru" <?= $sort==='terbaru'?'selected':'' ?>>Urutkan: Terbaru</option>
        <option value="populer" <?= $sort==='populer'?'selected':'' ?>>Urutkan: Terpopuler</option>
        <option value="tersedia" <?= $sort==='tersedia'?'selected':'' ?>>Urutkan: Tersedia</option>
      </select>
      <button type="submit" class="adm-btn adm-btn-primary">Cari</button>
      <a href="koleksi.php" class="adm-btn adm-btn-ghost">Reset</a>
    </form>
  </div>

  <div class="dash-card">
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>#</th><th>Buku</th><th>No. Kelas</th><th>Kategori</th><th>Rating</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if(empty($books)): ?>
          <tr><td colspan="6" class="empty-row">Tidak ada buku ditemukan</td></tr>
          <?php endif; ?>
          <?php foreach($books as $i => $b): ?>
          <tr>
            <td class="cell-sub"><?= $i+1 ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <?php if(!empty($b['cover_image'])): ?>
                  <img src="../uploads/covers/<?= htmlspecialchars($b['cover_image']) ?>" alt="Cover" style="width:32px;height:44px;object-fit:cover;border-radius:4px;flex-shrink:0;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                <?php else: ?>
                  <div class="mini-cov" style="background:linear-gradient(135deg,<?=$b['color1']?>,<?=$b['color2']?>);color:<?=$b['color3']?>;font-size:0.42rem;width:32px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:4px;flex-shrink:0;text-align:center;font-weight:bold;overflow:hidden;line-height:1.1;"><?= htmlspecialchars(substr($b['judul'],0,8)) ?></div>
                <?php endif; ?>
                <div>
                  <div class="cell-title"><?= htmlspecialchars($b['judul']) ?></div>
                  <div class="cell-sub"><?= htmlspecialchars($b['pengarang']) ?> · <?= $b['tahun'] ?? '—' ?></div>
                  <?php if($b['isbn']): ?><div class="cell-sub">ISBN: <?= htmlspecialchars($b['isbn']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td><span style="font-family:'Space Mono',monospace;font-size:0.78rem;background:#F3F4F6;padding:3px 8px;border-radius:4px;"><?= htmlspecialchars($b['no_kelas'] ?? '—') ?></span></td>
            <td><span class="adm-badge adm-badge-purple"><?= htmlspecialchars($b['kategori']) ?></span></td>
            <td>
              <?php if($b['ar'] > 0): ?>
              <span style="color:#F59E0B;font-weight:700;font-size:0.82rem;">★ <?= number_format($b['ar'],1) ?></span>
              <div class="cell-sub"><?= $b['tr'] ?> review</div>
              <?php else: ?><span class="cell-sub">—</span><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <a href="entri_katalog.php?edit=<?= $b['id'] ?>" class="adm-btn adm-btn-secondary adm-btn-sm">✏️ Edit</a>
                <form method="POST" onsubmit="return confirm('Yakin hapus buku ini? Data tidak bisa dikembalikan!')">
                  <input type="hidden" name="action" value="hapus">
                  <input type="hidden" name="id" value="<?= $b['id'] ?>">
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
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
