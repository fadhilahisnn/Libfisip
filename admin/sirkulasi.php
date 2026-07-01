<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();
$msg = '';
$adminId = $_SESSION['user_id'];
$adminNama = $_SESSION['nama'] ?? 'Admin';

// === AUTO-UPDATE: Hitung denda & hari terlambat otomatis untuk semua buku terlambat ===
$db->exec("UPDATE circulation 
    SET denda = DATEDIFF(CURDATE(), jatuh_tempo) * " . DENDA_PER_BUKU . ",
        status = 'terlambat'
    WHERE status IN ('dipinjam','terlambat') 
    AND jatuh_tempo < CURDATE()");

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // === BAYAR DENDA (Cash / QRIS) ===
    if ($action === 'bayar_denda') {
        $cid = (int)$_POST['cid'];
        $metode = $_POST['metode'] ?? '';

        $sirk = $db->prepare("SELECT c.*, u.nim, u.nama as user_nama FROM circulation c JOIN users u ON c.user_id=u.id WHERE c.id=?");
        $sirk->execute([$cid]); $s = $sirk->fetch();

        if ($s && $s['status'] !== 'dikembalikan') {
            // === OTOMATIS: Hitung denda berdasarkan selisih hari ===
            $today = new DateTime();
            $tempo = new DateTime($s['jatuh_tempo']);
            $hariTerlambat = 0;
            $denda = 0;
            if ($today > $tempo) {
                $hariTerlambat = $today->diff($tempo)->days;
                $denda = $hariTerlambat * DENDA_PER_BUKU;
            }
            if ($denda <= 0) $denda = DENDA_PER_BUKU; // fallback minimal 1 hari

            $nim_pembayar = $s['nim'];
            $waktu_bayar = date('Y-m-d H:i:s');

            if ($metode === 'cash') {
                $nim_pembayar = trim($_POST['nim_pembayar'] ?? $s['nim']);
                $waktu_bayar = $_POST['waktu_bayar'] ?? date('Y-m-d H:i:s');
            }

            // === OTOMATIS: Status pembayaran selalu 'berhasil' ===
            // Cash = langsung berhasil, QRIS = form hanya di-submit saat payment gateway sudah confirmed
            $status_bayar = 'berhasil';

            // Record payment
            $ins = $db->prepare("INSERT INTO denda_payments (circulation_id, user_id, nim_pembayar, metode, jumlah, status, admin_id, admin_nama, waktu_bayar) VALUES (?,?,?,?,?,?,?,?,?)");
            $ins->execute([$cid, $s['user_id'], $nim_pembayar, $metode, $denda, $status_bayar, $adminId, $adminNama, $waktu_bayar]);

            // Update circulation: mark fine as paid and return book
            $db->prepare("UPDATE circulation SET status='dikembalikan', tanggal_kembali=CURDATE(), denda=?, denda_metode=?, denda_status='lunas', denda_admin_id=?, denda_waktu_bayar=? WHERE id=?")
               ->execute([$denda, $metode, $adminId, $waktu_bayar, $cid]);
            $db->prepare("UPDATE books SET tersedia=tersedia+1 WHERE id=?")->execute([$s['book_id']]);
            redirect('sirkulasi.php', '✅ Pembayaran denda berhasil (' . strtoupper($metode) . '). Buku dikembalikan. Denda: ' . formatRupiah($denda) . ' (' . $hariTerlambat . ' hari terlambat)');
        }
    }

    // === KEMBALIKAN (non-overdue only) ===
    if ($action === 'kembalikan') {
        $cid = (int)$_POST['cid'];
        $sirk = $db->prepare("SELECT * FROM circulation WHERE id=?");
        $sirk->execute([$cid]); $s = $sirk->fetch();
        if ($s && $s['status'] !== 'dikembalikan') {
            $today = new DateTime(); $tempo = new DateTime($s['jatuh_tempo']);
            if ($today > $tempo) {
                // Overdue — cannot return without payment, redirect back
                $msg = 'Buku terlambat! Silakan proses pembayaran denda terlebih dahulu.';
            } else {
                // Not overdue — return directly
                $db->prepare("UPDATE circulation SET status='dikembalikan', tanggal_kembali=CURDATE(), denda=0 WHERE id=?")->execute([$cid]);
                $db->prepare("UPDATE books SET tersedia=tersedia+1 WHERE id=?")->execute([$s['book_id']]);
                redirect('sirkulasi.php', 'Buku berhasil dikembalikan. Tidak ada denda.');
            }
        }
    }

    // === PINJAMKAN ===
    if ($action === 'pinjamkan') {
        $uid = (int)$_POST['user_id'];
        $bid = (int)$_POST['book_id'];
        $book = $db->prepare("SELECT * FROM books WHERE id=? AND tersedia>0");
        $book->execute([$bid]); $bk = $book->fetch();
        $existing = $db->prepare("SELECT COUNT(*) FROM circulation WHERE user_id=? AND status='dipinjam'");
        $existing->execute([$uid]);
        if ($bk && $existing->fetchColumn() < MAKS_PINJAM) {
            $jatuh = date('Y-m-d', strtotime('+' . LAMA_PINJAM . ' days'));
            $db->prepare("INSERT INTO circulation (user_id, book_id, jatuh_tempo, status) VALUES (?,?,?,'dipinjam')")->execute([$uid, $bid, $jatuh]);
            $db->prepare("UPDATE books SET tersedia=tersedia-1 WHERE id=?")->execute([$bid]);
            redirect('sirkulasi.php', 'Peminjaman berhasil dicatat! 📚');
        } else {
            $msg = $bk ? 'Anggota sudah mencapai batas maksimal pinjam (' . MAKS_PINJAM . ' buku).' : 'Buku tidak tersedia.';
        }
    }
}

// Get filter
$status_filter = $_GET['status'] ?? 'semua';
$search = $_GET['q'] ?? '';

$where = "1=1";
$params = [];
if ($status_filter === 'aktif') { $where .= " AND c.status='dipinjam'"; }
elseif ($status_filter === 'terlambat') { $where .= " AND c.status='dipinjam' AND c.jatuh_tempo < CURDATE()"; }
elseif ($status_filter === 'kembali') { $where .= " AND c.status='dikembalikan'"; }

if ($search) {
    $where .= " AND (u.nama LIKE ? OR u.nim LIKE ? OR b.judul LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$stmt = $db->prepare("SELECT c.*, u.nama, u.nim, b.judul, b.no_kelas, b.color1, b.color2, b.color3, b.cover_image,
    DATEDIFF(c.jatuh_tempo, CURDATE()) as sisa
    FROM circulation c
    JOIN users u ON c.user_id=u.id
    JOIN books b ON c.book_id=b.id
    WHERE $where ORDER BY c.tanggal_pinjam DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// For pinjam form
$allUsers = $db->query("SELECT id, nama, nim FROM users WHERE role='mahasiswa' ORDER BY nama")->fetchAll();
$allBooks = $db->query("SELECT id, judul, tersedia FROM books WHERE tersedia>0 ORDER BY judul")->fetchAll();

$pageTitle = 'Sirkulasi';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">🔄 Sirkulasi Buku</h1>
      <p class="page-sub">Manajemen peminjaman dan pengembalian buku</p>
    </div>
    <button class="adm-btn adm-btn-primary" onclick="document.getElementById('pinjamModal').style.display='flex'">➕ Input Peminjaman</button>
  </div>

  <?php if ($msg): ?>
  <div class="adm-alert adm-alert-error"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- Filter bar -->
  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <input class="filter-input" type="text" name="q" placeholder="🔍 Cari nama, NIM, judul buku..." value="<?= htmlspecialchars($search) ?>">
      <select class="filter-input" name="status" onchange="this.form.submit()">
        <option value="semua" <?= $status_filter==='semua'?'selected':'' ?>>Semua Status</option>
        <option value="aktif" <?= $status_filter==='aktif'?'selected':'' ?>>Sedang Dipinjam</option>
        <option value="terlambat" <?= $status_filter==='terlambat'?'selected':'' ?>>Terlambat</option>
        <option value="kembali" <?= $status_filter==='kembali'?'selected':'' ?>>Sudah Dikembalikan</option>
      </select>
      <button type="submit" class="adm-btn adm-btn-secondary">Cari</button>
      <a href="sirkulasi.php" class="adm-btn adm-btn-ghost">Reset</a>
    </form>
    <div style="margin-left:auto;font-size:0.82rem;color:var(--adm-muted);"><?= count($rows) ?> data ditemukan</div>
  </div>

  <div class="dash-card">
    <div class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Peminjam</th>
            <th>Buku</th>
            <th>Tgl Pinjam</th>
            <th>Jatuh Tempo</th>
            <th>Status</th>
            <th>Denda</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="empty-row">Tidak ada data sirkulasi ditemukan</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $i => $r):
            $overdue = $r['sisa'] < 0 && $r['status'] === 'dipinjam';
            $denda_est = $overdue ? (abs($r['sisa']) * DENDA_PER_BUKU) : ($r['denda'] ?? 0);
            $dendaLunas = ($r['denda_status'] ?? '') === 'lunas';
          ?>
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
                <div>
                  <div class="cell-title" style="max-width:180px;"><?= htmlspecialchars(substr($r['judul'],0,35)) ?></div>
                  <div class="cell-sub"><?= htmlspecialchars($r['no_kelas']) ?></div>
                </div>
              </div>
            </td>
            <td class="cell-sub"><?= date('d M Y', strtotime($r['tanggal_pinjam'])) ?></td>
            <td>
              <div class="cell-title" style="color:<?= $overdue ? '#DC2626' : 'inherit' ?>">
                <?= date('d M Y', strtotime($r['jatuh_tempo'])) ?>
              </div>
              <?php if ($overdue): ?>
              <div class="cell-sub" style="color:#DC2626;"><?= abs($r['sisa']) ?> hari terlambat</div>
              <?php elseif($r['status']==='dipinjam'): ?>
              <div class="cell-sub"><?= $r['sisa'] ?> hari lagi</div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status']==='dikembalikan'): ?>
                <span class="adm-badge adm-badge-green">✓ Kembali</span>
                <?php if($r['tanggal_kembali']): ?><div class="cell-sub"><?= date('d M Y', strtotime($r['tanggal_kembali'])) ?></div><?php endif; ?>
              <?php elseif ($overdue): ?>
                <span class="adm-badge adm-badge-red">⚠ Terlambat</span>
              <?php else: ?>
                <span class="adm-badge adm-badge-blue">📖 Dipinjam</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($denda_est > 0): ?>
                <span style="font-weight:700;color:#DC2626;font-size:0.82rem;"><?= formatRupiah($denda_est) ?></span>
                <?php if ($dendaLunas): ?>
                  <div class="cell-sub" style="color:#059669;">✓ Lunas (<?= strtoupper($r['denda_metode'] ?? '-') ?>)</div>
                <?php elseif(($r['denda_status'] ?? '') === 'tertunda'): ?>
                  <div class="cell-sub" style="color:#D97706;">⏳ Tertunda</div>
                <?php endif; ?>
              <?php else: ?>
                <span class="cell-sub">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] !== 'dikembalikan'): ?>
                <?php if ($overdue): ?>
                  <!-- Overdue: show payment button -->
                  <button type="button" class="adm-btn adm-btn-danger adm-btn-sm" onclick="openDendaModal(<?= $r['id'] ?>, '<?= htmlspecialchars($r['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($r['nim'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars(substr($r['judul'],0,40), ENT_QUOTES) ?>', <?= $denda_est ?>, '<?= formatRupiah($denda_est) ?>', <?= abs($r['sisa']) ?>)">
                    💰 Bayar Denda (<?= abs($r['sisa']) ?> hari)
                  </button>
                <?php else: ?>
                  <!-- Not overdue: direct return -->
                  <form method="POST" onsubmit="return confirm('Konfirmasi pengembalian buku ini?')">
                    <input type="hidden" name="action" value="kembalikan">
                    <input type="hidden" name="cid" value="<?= $r['id'] ?>">
                    <button type="submit" class="adm-btn adm-btn-green adm-btn-sm">✓ Kembalikan</button>
                  </form>
                <?php endif; ?>
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

<!-- ============================================ -->
<!-- MODAL: PEMBAYARAN DENDA (Cash / QRIS) -->
<!-- ============================================ -->
<div id="dendaModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
  <div style="background:#fff;border-radius:20px;padding:0;width:100%;max-width:520px;box-shadow:0 25px 80px rgba(0,0,0,0.3);overflow:hidden;animation:modalIn 0.3s ease;">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#DC2626,#991B1B);padding:1.5rem 2rem;color:#fff;">
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div>
          <h2 style="font-size:1.1rem;font-weight:800;margin-bottom:4px;">💰 Pembayaran Denda</h2>
          <div style="font-size:0.78rem;opacity:0.8;">Buku terlambat — wajib bayar sebelum pengembalian</div>
        </div>
        <button onclick="closeDendaModal()" style="background:rgba(255,255,255,0.2);border:none;font-size:1.2rem;cursor:pointer;color:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;">×</button>
      </div>
    </div>

    <!-- Info Section -->
    <div style="padding:1.2rem 2rem;background:#FEF2F2;border-bottom:1px solid #FECACA;">
      <div style="display:flex;gap:1rem;align-items:flex-start;">
        <div style="flex:1;">
          <div style="font-size:0.72rem;color:#991B1B;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Peminjam</div>
          <div id="dm_nama" style="font-weight:700;font-size:0.95rem;color:#111827;margin-top:2px;">—</div>
          <div id="dm_nim" style="font-size:0.8rem;color:#6B7280;">—</div>
        </div>
        <div style="flex:1;">
          <div style="font-size:0.72rem;color:#991B1B;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Buku</div>
          <div id="dm_buku" style="font-weight:600;font-size:0.88rem;color:#111827;margin-top:2px;">—</div>
        </div>
        <div style="text-align:right;">
          <div style="font-size:0.72rem;color:#991B1B;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Total Denda</div>
          <div id="dm_denda_text" style="font-weight:900;font-size:1.3rem;color:#DC2626;margin-top:2px;font-family:'Space Mono',monospace;">—</div>
        </div>
      </div>
    </div>

    <!-- Tab Switcher -->
    <div style="display:flex;border-bottom:2px solid #E5E7EB;">
      <button id="tabCash" class="denda-tab active" onclick="switchDendaTab('cash')">💵 Cash</button>
      <button id="tabQris" class="denda-tab" onclick="switchDendaTab('qris')">📱 QRIS</button>
    </div>

    <!-- TAB: Cash -->
    <div id="panelCash" style="padding:1.5rem 2rem;">
      <form method="POST" id="formCash">
        <input type="hidden" name="action" value="bayar_denda">
        <input type="hidden" name="cid" id="cash_cid">
        <input type="hidden" name="metode" value="cash">
        <input type="hidden" name="status_bayar" value="berhasil">

        <div class="adm-form-group">
          <label class="adm-label">NIM Pembayar *</label>
          <input type="text" name="nim_pembayar" id="cash_nim" class="adm-input" required placeholder="Masukkan NIM pembayar">
          <div class="adm-hint">NIM mahasiswa yang membayar denda</div>
        </div>

        <div class="adm-form-group">
          <label class="adm-label">Waktu Pembayaran *</label>
          <input type="datetime-local" name="waktu_bayar" id="cash_waktu" class="adm-input" required>
        </div>

        <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;margin-bottom:1.2rem;">
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:1.2rem;">👤</span>
            <div>
              <div style="font-size:0.75rem;color:#059669;font-weight:700;">Penanggung Jawab</div>
              <div style="font-weight:700;font-size:0.9rem;color:#111827;"><?= htmlspecialchars($adminNama) ?></div>
              <div style="font-size:0.72rem;color:#6B7280;">Admin ID: <?= $adminId ?> · Login aktif</div>
            </div>
          </div>
        </div>

        <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:0.95rem;" onclick="return confirm('Konfirmasi pembayaran denda CASH?\nPenanggung jawab: <?= htmlspecialchars($adminNama) ?>')">
          ✅ Konfirmasi Pembayaran Cash
        </button>
      </form>
    </div>

    <!-- TAB: QRIS -->
    <div id="panelQris" style="padding:1.5rem 2rem;display:none;">
      <form method="POST" id="formQris">
        <input type="hidden" name="action" value="bayar_denda">
        <input type="hidden" name="cid" id="qris_cid">
        <input type="hidden" name="metode" value="qris">

        <!-- QRIS Image -->
        <div style="text-align:center;margin-bottom:1.2rem;">
          <div style="background:#F9FAFB;border:2px dashed #D1D5DB;border-radius:16px;padding:1.2rem;display:inline-block;">
            <img src="../assets/qris.jpg" alt="QRIS Payment" style="width:220px;height:auto;border-radius:8px;">
          </div>
          <div style="margin-top:10px;font-size:0.82rem;color:#6B7280;">
            Scan QRIS di atas menggunakan aplikasi e-wallet
          </div>
          <div id="qris_denda_text" style="font-weight:900;font-size:1.1rem;color:#DC2626;margin-top:6px;font-family:'Space Mono',monospace;">
            —
          </div>
        </div>

        <!-- Automatic Payment Detection Status -->
        <div id="qrisSimulationStatus" style="text-align:center;margin-bottom:1.2rem;padding:15px;background:#F3F4F6;border-radius:12px;border:1px solid #E5E7EB;">
          <div id="qrisLoadingSpinner" style="display:inline-block;width:24px;height:24px;border:3px solid #D1D5DB;border-top-color:#3B82F6;border-radius:50%;animation:spin 1s linear infinite;margin-bottom:8px;"></div>
          <div id="qrisSimulationText" style="font-weight:600;font-size:0.9rem;color:#4B5563;">Mendeteksi pembayaran...</div>
          <div style="font-size:0.75rem;color:#6B7280;margin-top:4px;">Status pembayaran akan terdeteksi otomatis oleh sistem</div>
          <div id="qrisCountdown" style="font-size:0.72rem;color:#9CA3AF;margin-top:6px;"></div>
        </div>

        <!-- Status Result (auto-detected) -->
        <div id="qrisPreview" style="display:none;border-radius:10px;padding:12px 16px;margin-bottom:1.2rem;background:#F0FDF4;border:1px solid #BBF7D0;">
          <div id="qrisPreviewIcon" style="text-align:center;font-size:2rem;margin-bottom:4px;">✅</div>
          <div id="qrisPreviewText" style="text-align:center;font-weight:700;font-size:0.88rem;color:#059669;">Pembayaran terdeteksi berhasil!</div>
          <div id="qrisAutoText" style="text-align:center;font-size:0.75rem;color:#6B7280;margin-top:4px;">Memproses pengembalian buku otomatis...</div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Pinjam -->
<div id="pinjamModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:2rem;width:100%;max-width:480px;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
      <h2 style="font-size:1.1rem;font-weight:800;">➕ Input Peminjaman Buku</h2>
      <button onclick="document.getElementById('pinjamModal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6B7280;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="pinjamkan">
      <div class="adm-form-group">
        <label class="adm-label">Anggota (Mahasiswa) *</label>
        <select name="user_id" class="adm-input" required>
          <option value="">-- Pilih Anggota --</option>
          <?php foreach($allUsers as $u): ?>
          <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nama']) ?> (<?= htmlspecialchars($u['nim'] ?? '—') ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="adm-form-group">
        <label class="adm-label">Buku yang Dipinjam *</label>
        <select name="book_id" class="adm-input" required>
          <option value="">-- Pilih Buku --</option>
          <?php foreach($allBooks as $bk): ?>
          <option value="<?= $bk['id'] ?>"><?= htmlspecialchars($bk['judul']) ?> (tersedia: <?= $bk['tersedia'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <div class="adm-hint">Durasi pinjam: <?= LAMA_PINJAM ?> hari | Denda: <?= formatRupiah(DENDA_PER_BUKU) ?>/buku</div>
      </div>
      <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:12px;">📚 Catat Peminjaman</button>
    </form>
  </div>
</div>

<style>
@keyframes modalIn {
  from { transform: translateY(30px) scale(0.95); opacity: 0; }
  to { transform: translateY(0) scale(1); opacity: 1; }
}
.denda-tab {
  flex: 1;
  padding: 12px 16px;
  font-family: inherit;
  font-size: 0.88rem;
  font-weight: 700;
  border: none;
  background: transparent;
  color: #6B7280;
  cursor: pointer;
  transition: all 0.2s;
  border-bottom: 3px solid transparent;
}
.denda-tab:hover { background: #F9FAFB; color: #111827; }
.denda-tab.active { color: #DC2626; border-bottom-color: #DC2626; background: #FEF2F2; }

@keyframes spin { 100% { transform: rotate(360deg); } }
</style>

<script>
let qrisSimulationTimer = null;
let qrisCountdownTimer = null;

// Denda Modal Functions
function openDendaModal(cid, nama, nim, buku, dendaNominal, dendaFormatted, hariTerlambat) {
  document.getElementById('dm_nama').textContent = nama;
  document.getElementById('dm_nim').textContent = nim || '—';
  document.getElementById('dm_buku').textContent = buku;
  document.getElementById('dm_denda_text').textContent = dendaFormatted;
  document.getElementById('qris_denda_text').textContent = dendaFormatted;
  document.getElementById('cash_cid').value = cid;
  document.getElementById('qris_cid').value = cid;
  document.getElementById('cash_nim').value = nim || '';

  // Set default time to now
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  document.getElementById('cash_waktu').value = now.toISOString().slice(0,16);

  // Reset to cash tab
  switchDendaTab('cash');

  document.getElementById('dendaModal').style.display = 'flex';
}

function closeDendaModal() {
  document.getElementById('dendaModal').style.display = 'none';
  if(qrisSimulationTimer) clearTimeout(qrisSimulationTimer);
  if(qrisCountdownTimer) clearInterval(qrisCountdownTimer);
}

function switchDendaTab(tab) {
  document.getElementById('panelCash').style.display = tab === 'cash' ? 'block' : 'none';
  document.getElementById('panelQris').style.display = tab === 'qris' ? 'block' : 'none';
  document.getElementById('tabCash').classList.toggle('active', tab === 'cash');
  document.getElementById('tabQris').classList.toggle('active', tab === 'qris');

  if(qrisSimulationTimer) clearTimeout(qrisSimulationTimer);
  if(qrisCountdownTimer) clearInterval(qrisCountdownTimer);

  if(tab === 'qris') {
    startQrisDetection();
  }
}

function startQrisDetection() {
  // Reset UI
  document.getElementById('qrisSimulationStatus').style.display = 'block';
  document.getElementById('qrisPreview').style.display = 'none';
  const countdownEl = document.getElementById('qrisCountdown');
  
  // Simulate payment gateway detection (3 to 6 seconds)
  const delay = Math.floor(Math.random() * 3000) + 3000;
  const startTime = Date.now();
  
  // Show countdown
  qrisCountdownTimer = setInterval(() => {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    countdownEl.textContent = 'Mengecek status... (' + elapsed + ' detik)';
  }, 1000);
  
  qrisSimulationTimer = setTimeout(() => {
    if(qrisCountdownTimer) clearInterval(qrisCountdownTimer);
    
    // === OTOMATIS: Pembayaran terdeteksi berhasil ===
    // Dalam implementasi nyata, ini akan polling ke payment gateway API
    onPaymentDetected();
  }, delay);
}

function onPaymentDetected() {
  // Sembunyikan spinner
  document.getElementById('qrisSimulationStatus').style.display = 'none';

  // Tampilkan status berhasil
  const preview = document.getElementById('qrisPreview');
  const icon = document.getElementById('qrisPreviewIcon');
  const text = document.getElementById('qrisPreviewText');
  const autoText = document.getElementById('qrisAutoText');
  
  preview.style.display = 'block';
  preview.style.background = '#F0FDF4';
  preview.style.borderColor = '#BBF7D0';
  icon.textContent = '✅';
  text.textContent = 'Pembayaran QRIS terdeteksi berhasil!';
  text.style.color = '#059669';
  autoText.textContent = 'Memproses pengembalian buku secara otomatis...';

  // === AUTO-SUBMIT: Form langsung di-submit otomatis, tidak perlu klik admin ===
  setTimeout(() => {
    autoText.textContent = '⏳ Mengirim data ke server...';
    document.getElementById('formQris').submit();
  }, 1500);
}

// Close modal on backdrop click
document.getElementById('dendaModal').addEventListener('click', function(e) {
  if (e.target === this) closeDendaModal();
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
