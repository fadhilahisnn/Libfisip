<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'hapus') {
    $id = (int)$_POST['id'];
    $db->prepare("DELETE FROM reviews WHERE id=?")->execute([$id]);
    redirect('reviews.php', 'Review berhasil dihapus.');
}

$search = $_GET['q'] ?? '';
$rating_filter = $_GET['rating'] ?? '';

$where = "1=1";
$params = [];
if ($search) { $where .= " AND (r.isi LIKE ? OR u.nama LIKE ? OR b.judul LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($rating_filter) { $where .= " AND r.rating=?"; $params[] = (int)$rating_filter; }

$stmt = $db->prepare("SELECT r.*, u.nama, u.nim, b.judul, b.color1, b.color2, b.color3, b.cover_image FROM reviews r
    JOIN users u ON r.user_id=u.id JOIN books b ON r.book_id=b.id
    WHERE $where ORDER BY r.created_at DESC");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$pageTitle = 'Moderasi Review';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">💬 Moderasi Review</h1>
      <p class="page-sub"><?= count($reviews) ?> review ditemukan</p>
    </div>
  </div>

  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input class="filter-input" type="text" name="q" placeholder="🔍 Cari isi review, anggota, judul buku..." value="<?= htmlspecialchars($search) ?>">
      <select class="filter-input" name="rating">
        <option value="">Semua Rating</option>
        <?php for($i=5;$i>=1;$i--): ?>
        <option value="<?= $i ?>" <?= $rating_filter==$i?'selected':'' ?>><?= str_repeat('★',$i) ?> <?= $i ?> Bintang</option>
        <?php endfor; ?>
      </select>
      <button type="submit" class="adm-btn adm-btn-primary">Cari</button>
      <a href="reviews.php" class="adm-btn adm-btn-ghost">Reset</a>
    </form>
  </div>

  <div class="dash-card">
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>#</th><th>Reviewer</th><th>Buku</th><th>Rating</th><th>Review</th><th>Tags</th><th>Waktu</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if(empty($reviews)): ?>
          <tr><td colspan="8" class="empty-row">Belum ada review</td></tr>
          <?php endif; ?>
          <?php foreach($reviews as $i => $r): ?>
          <tr>
            <td class="cell-sub"><?= $i+1 ?></td>
            <td>
              <div class="cell-title"><?= htmlspecialchars($r['nama']) ?></div>
              <div class="cell-sub"><?= htmlspecialchars($r['nim'] ?? '—') ?></div>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <?php if(!empty($r['cover_image'])): ?>
                  <img src="../uploads/covers/<?= htmlspecialchars($r['cover_image']) ?>" alt="Cover" style="width:32px;height:44px;object-fit:cover;border-radius:4px;flex-shrink:0;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
                <?php else: ?>
                  <div class="mini-cov" style="background:linear-gradient(135deg,<?=$r['color1']?>,<?=$r['color2']?>);color:<?=$r['color3']?>;font-size:0.4rem;width:32px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:4px;flex-shrink:0;text-align:center;font-weight:bold;overflow:hidden;line-height:1.1;"><?= htmlspecialchars(substr($r['judul'],0,6)) ?></div>
                <?php endif; ?>
                <div class="cell-title" style="max-width:140px;"><?= htmlspecialchars(substr($r['judul'],0,28)) ?></div>
              </div>
            </td>
            <td>
              <span style="color:#F59E0B;font-weight:700;"><?= str_repeat('★', $r['rating']) ?></span>
              <div class="cell-sub"><?= $r['rating'] ?>/5</div>
            </td>
            <td style="max-width:200px;">
              <div style="font-size:0.82rem;color:#374151;font-style:italic;line-height:1.5;">
                "<?= htmlspecialchars(substr($r['isi'],0,100)) ?><?= strlen($r['isi'])>100?'…':'' ?>"
              </div>
            </td>
            <td>
              <?php if($r['tags']): ?>
              <?php foreach(explode(',', $r['tags']) as $tag): ?>
              <span style="display:inline-block;background:rgba(255,77,109,0.08);color:#FF4D6D;padding:2px 8px;border-radius:10px;font-size:0.7rem;font-weight:600;margin:2px;"><?= htmlspecialchars(trim($tag)) ?></span>
              <?php endforeach; ?>
              <?php else: ?><span class="cell-sub">—</span><?php endif; ?>
            </td>
            <td class="cell-sub"><?= timeAgo($r['created_at']) ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Hapus review ini?')">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">🗑️ Hapus</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
