<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak. Anda bukan admin.','error'); }

$db = getDB();

// Stats
$stats = [
    'buku'      => $db->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'users'     => $db->query("SELECT COUNT(*) FROM users WHERE role='mahasiswa'")->fetchColumn(),
    'dipinjam'  => $db->query("SELECT COUNT(*) FROM circulation WHERE status IN('dipinjam','terlambat')")->fetchColumn(),
    'terlambat' => $db->query("SELECT COUNT(*) FROM circulation WHERE status='terlambat' OR (status='dipinjam' AND jatuh_tempo < CURDATE())")->fetchColumn(),
    'denda'     => $db->query("SELECT COALESCE(SUM(denda),0) FROM circulation")->fetchColumn(),
    'review'    => $db->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'kembali_hari'  => $db->query("SELECT COUNT(*) FROM circulation WHERE DATE(tanggal_kembali)=CURDATE()")->fetchColumn(),
];

// Recent circulation
$recent = $db->query("SELECT c.*, u.nama, u.nim, b.judul, b.color1, b.color2, b.color3, b.cover_image,
    DATEDIFF(c.jatuh_tempo, CURDATE()) as sisa
    FROM circulation c
    JOIN users u ON c.user_id=u.id
    JOIN books b ON c.book_id=b.id
    ORDER BY c.tanggal_pinjam DESC LIMIT 8")->fetchAll();

// Top books
$topBooks = $db->query("SELECT b.judul, b.pengarang, b.color1, b.color2, b.color3, b.cover_image,
    COUNT(c.id) as total_pinjam
    FROM books b LEFT JOIN circulation c ON b.id=c.book_id
    GROUP BY b.id ORDER BY total_pinjam DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard Admin';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <!-- Page Title -->
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">📊 Dashboard</h1>
      <p class="page-sub">Selamat datang, <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong> — Ringkasan sistem LibFISIP</p>
    </div>
    <div class="page-actions">
      <span class="admin-badge">🟢 Online</span>
      <a href="tambah_buku.php" class="adm-btn adm-btn-primary">➕ Tambah Buku</a>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stat-grid">
    <div class="stat-card stat-blue">
      <div class="stat-icon">📚</div>
      <div class="stat-body">
        <div class="stat-val"><?= number_format($stats['buku']) ?></div>
        <div class="stat-lbl">Total Koleksi</div>
      </div>
    </div>
    <div class="stat-card stat-green">
      <div class="stat-icon">👥</div>
      <div class="stat-body">
        <div class="stat-val"><?= number_format($stats['users']) ?></div>
        <div class="stat-lbl">Anggota Aktif</div>
      </div>
    </div>
    <div class="stat-card stat-yellow">
      <div class="stat-icon">🔄</div>
      <div class="stat-body">
        <div class="stat-val"><?= number_format($stats['dipinjam']) ?></div>
        <div class="stat-lbl">Sedang Dipinjam</div>
      </div>
    </div>
    <div class="stat-card stat-red">
      <div class="stat-icon">⚠️</div>
      <div class="stat-body">
        <div class="stat-val"><?= number_format($stats['terlambat']) ?></div>
        <div class="stat-lbl">Terlambat</div>
      </div>
    </div>
    <div class="stat-card stat-purple">
      <div class="stat-icon">💬</div>
      <div class="stat-body">
        <div class="stat-val"><?= number_format($stats['review']) ?></div>
        <div class="stat-lbl">Total Review</div>
      </div>
    </div>
    <div class="stat-card stat-rose">
      <div class="stat-icon">💰</div>
      <div class="stat-body">
        <div class="stat-val" style="font-size:1.1rem;"><?= formatRupiah($stats['denda']) ?></div>
        <div class="stat-lbl">Total Denda</div>
      </div>
    </div>
  </div>

  <!-- Two column -->
  <div class="dash-cols">
    <!-- Recent Circulation -->
    <div class="dash-card">
      <div class="dash-card-header">
        <h2 class="dash-card-title">🔄 Aktivitas Sirkulasi Terbaru</h2>
        <a href="sirkulasi.php" class="adm-btn adm-btn-ghost">Lihat Semua</a>
      </div>
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead>
            <tr><th>Peminjam</th><th>Buku</th><th>Tgl Pinjam</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r):
              $overdue = $r['sisa'] < 0 && $r['status'] !== 'dikembalikan';
            ?>
            <tr>
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
                  <div class="cell-title" style="max-width:150px;"><?= htmlspecialchars(substr($r['judul'],0,30)) ?></div>
                </div>
              </td>
              <td class="cell-sub"><?= date('d M Y', strtotime($r['tanggal_pinjam'])) ?></td>
              <td>
                <?php if($r['status']==='dikembalikan'): ?>
                  <span class="adm-badge adm-badge-green">✓ Kembali</span>
                <?php elseif($overdue): ?>
                  <span class="adm-badge adm-badge-red">⚠ Terlambat</span>
                <?php else: ?>
                  <span class="adm-badge adm-badge-blue">📖 Dipinjam</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($recent)): ?>
              <tr><td colspan="4" class="empty-row">Belum ada data sirkulasi</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Top books -->
    <div class="dash-card" style="max-width:380px;">
      <div class="dash-card-header">
        <h2 class="dash-card-title">🏆 Buku Terpopuler</h2>
        <a href="koleksi.php" class="adm-btn adm-btn-ghost">Lihat Semua</a>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;padding:1rem;">
        <?php foreach($topBooks as $i => $tb): ?>
        <div class="top-book-row">
          <div class="top-rank"><?= $i+1 ?></div>
          <?php if(!empty($tb['cover_image'])): ?>
            <img src="../uploads/covers/<?= htmlspecialchars($tb['cover_image']) ?>" alt="Cover" style="width:32px;height:44px;object-fit:cover;border-radius:4px;flex-shrink:0;box-shadow:0 2px 4px rgba(0,0,0,0.1);">
          <?php else: ?>
            <div class="mini-cov" style="background:linear-gradient(135deg,<?=$tb['color1']?>,<?=$tb['color2']?>);color:<?=$tb['color3']?>;font-size:0.4rem;width:32px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:4px;flex-shrink:0;text-align:center;font-weight:bold;overflow:hidden;line-height:1.1;"><?= htmlspecialchars(substr($tb['judul'],0,6)) ?></div>
          <?php endif; ?>
          <div style="flex:1;min-width:0;">
            <div class="cell-title" style="font-size:0.85rem;"><?= htmlspecialchars(substr($tb['judul'],0,28)) ?></div>
            <div class="cell-sub"><?= htmlspecialchars($tb['pengarang']) ?></div>
          </div>
          <div style="font-weight:700;color:var(--adm-accent);font-size:0.85rem;"><?= $tb['total_pinjam'] ?>x</div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($topBooks)): ?><p class="cell-sub" style="padding:1rem;">Belum ada data peminjaman.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
