<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// === AUTO-UPDATE: Hitung denda & hari terlambat otomatis untuk buku terlambat aktif ===
$db->exec("UPDATE circulation 
    SET denda = DATEDIFF(CURDATE(), jatuh_tempo) * " . DENDA_PER_BUKU . ",
        status = 'terlambat'
    WHERE status IN ('dipinjam','terlambat') 
    AND jatuh_tempo < CURDATE()");

// Handle actions
if (isset($_GET['terima'])) {
    $cid = (int)$_GET['terima'];
    $db->prepare("UPDATE circulation SET status='dikembalikan', denda_status='lunas', tanggal_kembali=CURDATE(), denda_waktu_bayar=NOW(), denda_admin_id=? WHERE id=?")
       ->execute([$_SESSION['user_id'], $cid]);
    // Kembalikan stok buku
    $circ = $db->prepare("SELECT book_id FROM circulation WHERE id=?");
    $circ->execute([$cid]);
    if ($b = $circ->fetch()) {
        $db->prepare("UPDATE books SET tersedia=tersedia+1 WHERE id=?")->execute([$b['book_id']]);
    }
    redirect('denda.php', 'Pembayaran QRIS berhasil diverifikasi dan buku otomatis dikembalikan.');
}
if (isset($_GET['tolak'])) {
    $cid = (int)$_GET['tolak'];
    $db->prepare("UPDATE circulation SET denda_status='belum_bayar' WHERE id=?")
       ->execute([$cid]);
    redirect('denda.php', 'Pembayaran QRIS ditolak.');
}

// Denda summary
$summary = $db->query("SELECT
    COUNT(*) as total_records,
    SUM(CASE WHEN denda > 0 THEN 1 ELSE 0 END) as total_bermasalah,
    COALESCE(SUM(denda),0) as total_denda,
    COALESCE(SUM(CASE WHEN status='dikembalikan' THEN denda ELSE 0 END),0) as denda_lunas,
    COALESCE(SUM(CASE WHEN status='dipinjam' AND jatuh_tempo < CURDATE() THEN DATEDIFF(CURDATE(), jatuh_tempo) * 3000 ELSE 0 END),0) as denda_berjalan
FROM circulation")->fetch();

$rows = $db->query("SELECT c.*, u.nama, u.nim, u.email, b.judul,
    CASE 
        WHEN c.status = 'dikembalikan' AND c.tanggal_kembali IS NOT NULL 
            THEN DATEDIFF(c.tanggal_kembali, c.jatuh_tempo)
        ELSE DATEDIFF(CURDATE(), c.jatuh_tempo)
    END as hari_terlambat
    FROM circulation c
    JOIN users u ON c.user_id=u.id
    JOIN books b ON c.book_id=b.id
    WHERE c.denda > 0 OR (c.status IN ('dipinjam','terlambat') AND c.jatuh_tempo < CURDATE())
    ORDER BY c.jatuh_tempo ASC")->fetchAll();

// Get payment history
$payments = $db->query("SELECT dp.*, u.nama as admin_nama_ref FROM denda_payments dp LEFT JOIN users u ON dp.admin_id=u.id ORDER BY dp.created_at DESC")->fetchAll();

$pageTitle = 'Laporan Denda';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">💰 Laporan Denda</h1>
      <p class="page-sub">Rekap denda keterlambatan pengembalian buku</p>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));margin-bottom:2rem;">
    <div class="stat-card stat-red">
      <div class="stat-icon">⚠️</div>
      <div class="stat-body">
        <div class="stat-val"><?= $summary['total_bermasalah'] ?></div>
        <div class="stat-lbl">Kasus Keterlambatan</div>
      </div>
    </div>
    <div class="stat-card stat-rose">
      <div class="stat-icon">💰</div>
      <div class="stat-body">
        <div class="stat-val" style="font-size:1rem;"><?= formatRupiah($summary['total_denda']) ?></div>
        <div class="stat-lbl">Total Denda Tercatat</div>
      </div>
    </div>
    <div class="stat-card stat-green">
      <div class="stat-icon">✅</div>
      <div class="stat-body">
        <div class="stat-val" style="font-size:1rem;"><?= formatRupiah($summary['denda_lunas']) ?></div>
        <div class="stat-lbl">Denda Sudah Lunas</div>
      </div>
    </div>
  </div>

  <div class="dash-card">
    <div class="dash-card-header">
      <h2 class="dash-card-title">📋 Rincian Denda per Transaksi</h2>
    </div>
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr><th>#</th><th>Anggota</th><th>Buku</th><th>Jatuh Tempo</th><th>Hari Terlambat</th><th>Denda</th><th>Metode</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
          <?php if(empty($rows)): ?>
          <tr><td colspan="9" class="empty-row">🎉 Tidak ada catatan denda!</td></tr>
          <?php endif; ?>
          <?php foreach($rows as $i => $r):
            $dendaAktif = in_array($r['status'], ['dipinjam','terlambat']) && $r['hari_terlambat'] > 0;
            // Gunakan denda dari database (sudah dihitung otomatis)
            $denda = $r['denda'] > 0 ? $r['denda'] : ($r['hari_terlambat'] > 0 ? $r['hari_terlambat'] * DENDA_PER_BUKU : 0);
            $metode = $r['denda_metode'] ?? null;
            $dendaStatus = $r['denda_status'] ?? 'belum_bayar';
            // Otomatis: jika sudah dikembalikan dan denda > 0, status = lunas
            if ($r['status'] === 'dikembalikan' && $denda > 0 && $dendaStatus !== 'lunas') {
                $dendaStatus = 'lunas';
            }
          ?>
          <tr>
            <td class="cell-sub"><?= $i+1 ?></td>
            <td>
              <div class="cell-title"><?= htmlspecialchars($r['nama']) ?></div>
              <div class="cell-sub"><?= htmlspecialchars($r['nim'] ?? '—') ?></div>
              <div class="cell-sub"><?= htmlspecialchars($r['email']) ?></div>
            </td>
            <td>
              <div class="cell-title"><?= htmlspecialchars(substr($r['judul'],0,40)) ?></div>
            </td>
            <td style="color:#EF4444;font-weight:600;font-size:0.85rem;"><?= date('d M Y', strtotime($r['jatuh_tempo'])) ?></td>
            <td>
              <?php if($r['hari_terlambat'] > 0): ?>
                <span class="adm-badge adm-badge-red"><?= $r['hari_terlambat'] ?> hari</span>
              <?php else: ?>
                <span class="cell-sub">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-weight:800;color:<?= $denda>0?'#F87171':'#34D399' ?>;font-size:0.9rem;"><?= formatRupiah($denda) ?></span>
            </td>
            <td>
              <?php if($metode): ?>
                <span class="adm-badge <?= $metode==='cash' ? 'adm-badge-green' : 'adm-badge-purple' ?>"><?= $metode==='cash' ? '💵 Cash' : '📱 QRIS' ?></span>
              <?php else: ?>
                <span class="cell-sub">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($dendaStatus==='lunas'): ?>
                <span class="adm-badge adm-badge-green">✓ Lunas</span>
              <?php elseif($dendaStatus==='tertunda'): ?>
                <span class="adm-badge adm-badge-yellow">⏳ Menunggu Verifikasi</span>
              <?php elseif($dendaStatus==='gagal'): ?>
                <span class="adm-badge adm-badge-red">❌ Gagal</span>
              <?php else: ?>
                <span class="adm-badge adm-badge-red">⏳ Belum Bayar</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($dendaStatus==='tertunda'): ?>
                <div style="display:flex; gap:5px;">
                  <a href="?terima=<?= $r['id'] ?>" class="adm-btn adm-btn-green adm-btn-sm" onclick="return confirm('Terima pembayaran QRIS ini?')">✓ Terima</a>
                  <a href="?tolak=<?= $r['id'] ?>" class="adm-btn adm-btn-danger adm-btn-sm" onclick="return confirm('Tolak pembayaran QRIS ini?')">✕ Tolak</a>
                </div>
              <?php else: ?>
                <span class="cell-sub">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
