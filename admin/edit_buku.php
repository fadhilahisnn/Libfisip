<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cover_helper.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$book = $db->prepare("SELECT * FROM books WHERE id=?");
$book->execute([$id]); $b = $book->fetch();
if (!$b) { redirect('koleksi.php', 'Buku tidak ditemukan.', 'error'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $f = $_POST;
        $stok = (int)$f['stok'];
        $tersedia = (int)$f['tersedia'];

        // Handle cover image upload (keep old if no new file)
        $coverFile = handleCoverUpload('cover_image', $b['cover_image'] ?? null);
        if (!$coverFile) {
            // Check if user wants to remove existing cover
            if (isset($_POST['remove_cover']) && $_POST['remove_cover'] === '1') {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/libfisip/uploads/covers/';
                if ($b['cover_image'] && file_exists($uploadDir . $b['cover_image'])) {
                    @unlink($uploadDir . $b['cover_image']);
                }
                $coverFile = null;
            } else {
                $coverFile = $b['cover_image'] ?? null; // Keep existing
            }
        }

        $db->prepare("UPDATE books SET judul=?,pengarang=?,penerbit=?,tahun=?,isbn=?,no_kelas=?,kategori=?,bahasa=?,stok=?,tersedia=?,deskripsi=?,color1=?,color2=?,color3=?,cover_image=? WHERE id=?")->execute([
            trim($f['judul']), trim($f['pengarang']), trim($f['penerbit']),
            (int)$f['tahun'], trim($f['isbn']), trim($f['no_kelas']),
            trim($f['kategori']), trim($f['bahasa']),
            $stok, $tersedia, trim($f['deskripsi']),
            $f['color1'], $f['color2'], $f['color3'],
            $coverFile, $id
        ]);
        redirect('koleksi.php', 'Data buku berhasil diupdate! ✅');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Edit Buku';
include __DIR__ . '/includes/admin_header.php';
$currentCoverSrc = bookCoverSrc($b);
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">✏️ Edit Buku</h1>
      <p class="page-sub">ID: <?= $b['id'] ?> — <?= htmlspecialchars($b['judul']) ?></p>
    </div>
    <a href="koleksi.php" class="adm-btn adm-btn-secondary">← Kembali</a>
  </div>

  <?php if ($error): ?>
  <div class="adm-alert adm-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">
    <div class="adm-form-card">
      <div class="adm-form-title">📋 Edit Data Bibliografi</div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="remove_cover" id="removeCoverFlag" value="0">
        <div class="adm-form-grid">
          <div class="adm-form-group full">
            <label class="adm-label">Judul Buku *</label>
            <input class="adm-input" type="text" name="judul" required value="<?= htmlspecialchars($b['judul']) ?>" id="judulInput" oninput="updatePreview()">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Pengarang *</label>
            <input class="adm-input" type="text" name="pengarang" required value="<?= htmlspecialchars($b['pengarang']) ?>">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Penerbit</label>
            <input class="adm-input" type="text" name="penerbit" value="<?= htmlspecialchars($b['penerbit'] ?? '') ?>">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Tahun Terbit</label>
            <input class="adm-input" type="number" name="tahun" value="<?= $b['tahun'] ?? date('Y') ?>" min="1900" max="2030">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">ISBN</label>
            <input class="adm-input" type="text" name="isbn" value="<?= htmlspecialchars($b['isbn'] ?? '') ?>">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">No. Kelas (DDC) *</label>
            <input class="adm-input" type="text" name="no_kelas" required value="<?= htmlspecialchars($b['no_kelas'] ?? '') ?>" style="font-family:'Space Mono',monospace;">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Kategori *</label>
            <select class="adm-input" name="kategori" required>
              <?php foreach(['Sosiologi','Komunikasi','Politik','Administrasi','Antropologi','Ilmu Informasi','Hukum','Ekonomi','Umum'] as $k): ?>
              <option <?= $b['kategori']===$k?'selected':'' ?>><?= $k ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Bahasa</label>
            <select class="adm-input" name="bahasa">
              <?php foreach(['Indonesia','Inggris','Lainnya'] as $lang): ?>
              <option <?= ($b['bahasa']??'Indonesia')===$lang?'selected':'' ?>><?= $lang ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Total Stok</label>
            <input class="adm-input" type="number" name="stok" value="<?= $b['stok'] ?? $b['tersedia'] ?>" min="0">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Stok Tersedia</label>
            <input class="adm-input" type="number" name="tersedia" value="<?= $b['tersedia'] ?>" min="0">
          </div>
          <div class="adm-form-group full">
            <label class="adm-label">Deskripsi / Abstrak</label>
            <textarea class="adm-input" name="deskripsi" rows="4" style="resize:vertical;"><?= htmlspecialchars($b['deskripsi'] ?? '') ?></textarea>
          </div>

          <!-- ===== COVER IMAGE UPLOAD ===== -->
          <div class="adm-form-group full">
            <label class="adm-label">🖼️ Foto Cover Buku</label>

            <?php if ($b['cover_image'] && $currentCoverSrc): ?>
            <!-- Current cover display -->
            <div id="currentCoverSection" style="display:flex;align-items:center;gap:14px;padding:12px;background:#f9fafb;border:1px solid var(--adm-border);border-radius:10px;margin-bottom:12px;">
              <img src="<?= $currentCoverSrc ?>" alt="Cover saat ini" style="width:50px;height:70px;object-fit:cover;border-radius:6px;flex-shrink:0;">
              <div style="flex:1;">
                <div style="font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:4px;">✓ Cover gambar aktif</div>
                <div class="adm-hint"><?= htmlspecialchars($b['cover_image']) ?></div>
              </div>
              <button type="button" onclick="markRemoveCover()" class="adm-btn adm-btn-danger adm-btn-sm">✕ Hapus</button>
            </div>
            <div id="removedNotice" style="display:none;padding:10px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;font-size:0.82rem;color:#DC2626;margin-bottom:12px;">
              🗑️ Cover lama akan dihapus saat disimpan. Upload gambar baru atau biarkan kosong untuk pakai gradien.
            </div>
            <?php endif; ?>

            <div class="cover-upload-zone" id="uploadZone" onclick="document.getElementById('coverInput').click()">
              <div class="cover-upload-icon">📤</div>
              <div class="cover-upload-text"><?= $b['cover_image'] ? 'Upload cover baru (opsional)' : 'Klik atau seret gambar ke sini' ?></div>
              <div class="cover-upload-hint">JPG, JPEG, PNG — Maks. 5MB</div>
              <div id="uploadFileName" style="font-size:0.8rem;color:var(--adm-accent);font-weight:600;margin-top:8px;display:none;"></div>
            </div>
            <input type="file" id="coverInput" name="cover_image" accept=".jpg,.jpeg,.png" style="display:none;" onchange="previewUpload(this)">
            <div class="adm-hint" style="margin-top:6px;">Kosongkan untuk mempertahankan cover saat ini. Gradien di bawah dipakai jika tidak ada gambar.</div>
          </div>

          <!-- Gradient fallback -->
          <div class="adm-form-group">
            <label class="adm-label">Warna Cover (Atas)</label>
            <input class="adm-input" type="color" name="color1" value="<?= htmlspecialchars($b['color1']) ?>" oninput="updatePreview()">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Warna Cover (Bawah)</label>
            <input class="adm-input" type="color" name="color2" value="<?= htmlspecialchars($b['color2']) ?>" oninput="updatePreview()">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Warna Teks Cover</label>
            <input class="adm-input" type="color" name="color3" value="<?= htmlspecialchars($b['color3']) ?>" oninput="updatePreview()">
          </div>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:1rem;margin-top:0.5rem;">✅ Simpan Perubahan</button>
      </form>
    </div>

    <!-- Preview -->
    <div style="position:sticky;top:80px;">
      <div class="adm-form-card">
        <div class="adm-form-title">👁 Preview Cover</div>
        <?php if ($currentCoverSrc): ?>
        <div id="coverPreview" style="aspect-ratio:2/3;border-radius:12px;overflow:hidden;margin-bottom:1rem;box-shadow:0 10px 30px rgba(0,0,0,0.15);position:relative;">
          <img id="previewImg" src="<?= $currentCoverSrc ?>" style="width:100%;height:100%;object-fit:cover;display:block;" alt="Preview">
          <div id="previewTextOverlay" style="display:none;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-align:center;font-weight:800;font-size:1rem;line-height:1.4;padding:1.5rem;" id="previewTextOverlay">
            <span id="previewText"><?= htmlspecialchars(substr($b['judul'],0,40)) ?></span>
          </div>
        </div>
        <?php else: ?>
        <div id="coverPreview" style="aspect-ratio:2/3;border-radius:12px;background:linear-gradient(135deg,<?=$b['color1']?>,<?=$b['color2']?>);color:<?=$b['color3']?>;display:flex;align-items:center;justify-content:center;text-align:center;font-weight:800;font-size:1rem;line-height:1.4;padding:1.5rem;margin-bottom:1rem;box-shadow:0 10px 30px rgba(0,0,0,0.15);overflow:hidden;position:relative;">
          <span id="previewText"><?= htmlspecialchars(substr($b['judul'],0,40)) ?></span>
          <img id="previewImg" src="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" alt="Preview">
        </div>
        <?php endif; ?>
        <div id="previewBadge" style="display:none;text-align:center;margin-bottom:8px;">
          <span class="adm-badge adm-badge-green">✓ Gambar baru siap diupload</span>
        </div>
        <div style="font-size:0.72rem;color:var(--adm-muted);text-align:center;">Preview tampilan cover di katalog</div>
      </div>
    </div>
  </div>
</div>

<style>
.cover-upload-zone {
  border: 2px dashed var(--adm-border);
  border-radius: 12px;
  padding: 1.5rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #FAFAFA;
}
.cover-upload-zone:hover, .cover-upload-zone.dragover {
  border-color: var(--adm-accent);
  background: rgba(255,77,109,0.04);
}
.cover-upload-icon { font-size: 1.8rem; margin-bottom: 6px; }
.cover-upload-text { font-weight: 600; font-size: 0.88rem; color: #374151; }
.cover-upload-hint { font-size: 0.75rem; color: var(--adm-muted); margin-top: 4px; }
</style>

<script>
const hasCurrent = <?= $currentCoverSrc ? 'true' : 'false' ?>;

function updatePreview() {
  const judul = document.getElementById('judulInput').value || 'Judul Buku';
  const c1 = document.querySelector('[name=color1]').value;
  const c2 = document.querySelector('[name=color2]').value;
  const c3 = document.querySelector('[name=color3]').value;
  const prev = document.getElementById('coverPreview');
  const img = document.getElementById('previewImg');
  const txt = document.getElementById('previewText');
  if (!img || img.style.display === 'none' || !img.src) {
    prev.style.background = `linear-gradient(135deg,${c1},${c2})`;
    prev.style.color = c3;
    if (txt) txt.textContent = judul.substring(0, 40);
  }
}

function previewUpload(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const reader = new FileReader();
  reader.onload = function(e) {
    const prev = document.getElementById('coverPreview');
    const img = document.getElementById('previewImg');
    const txt = document.getElementById('previewText');
    img.src = e.target.result;
    img.style.display = 'block';
    prev.style.background = 'none';
    if (txt) txt.style.display = 'none';
    document.getElementById('previewBadge').style.display = 'block';
    const fn = document.getElementById('uploadFileName');
    fn.textContent = '✓ ' + file.name;
    fn.style.display = 'block';
    // Cancel remove flag if uploading new
    document.getElementById('removeCoverFlag').value = '0';
  };
  reader.readAsDataURL(file);
}

function markRemoveCover() {
  document.getElementById('removeCoverFlag').value = '1';
  const sec = document.getElementById('currentCoverSection');
  if (sec) sec.style.display = 'none';
  document.getElementById('removedNotice').style.display = 'block';
  // Reset preview to gradient
  const img = document.getElementById('previewImg');
  if (img) { img.src = ''; img.style.display = 'none'; }
  const txt = document.getElementById('previewText');
  if (txt) txt.style.display = 'block';
  updatePreview();
}

// Drag and drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('dragover');
  if (e.dataTransfer.files.length) {
    document.getElementById('coverInput').files = e.dataTransfer.files;
    previewUpload(document.getElementById('coverInput'));
  }
});
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
