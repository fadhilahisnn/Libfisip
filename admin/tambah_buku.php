<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cover_helper.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $f = $_POST;
        $stok = (int)$f['stok'];

        // Handle cover image upload
        $coverFile = handleCoverUpload('cover_image');

        $db->prepare("INSERT INTO books (judul,pengarang,penerbit,tahun,isbn,no_kelas,kategori,bahasa,stok,tersedia,deskripsi,color1,color2,color3,cover_image)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
            trim($f['judul']), trim($f['pengarang']), trim($f['penerbit']),
            (int)$f['tahun'], trim($f['isbn']), trim($f['no_kelas']),
            trim($f['kategori']), trim($f['bahasa']),
            $stok, $stok, trim($f['deskripsi']),
            $f['color1'], $f['color2'], $f['color3'],
            $coverFile ?: null
        ]);
        redirect('koleksi.php', 'Buku berhasil ditambahkan! 📚');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Tambah Buku';
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">➕ Tambah Koleksi Baru</h1>
      <p class="page-sub">Tambahkan buku baru ke dalam katalog perpustakaan</p>
    </div>
    <a href="koleksi.php" class="adm-btn adm-btn-secondary">← Kembali ke Koleksi</a>
  </div>

  <?php if ($error): ?>
  <div class="adm-alert adm-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">
    <div class="adm-form-card">
      <div class="adm-form-title">📋 Data Bibliografi</div>
      <form method="POST" enctype="multipart/form-data" id="tambahForm">
        <div class="adm-form-grid">
          <div class="adm-form-group full">
            <label class="adm-label">Judul Buku *</label>
            <input class="adm-input" type="text" name="judul" required placeholder="Contoh: Sosiologi Komunikasi" id="judulInput" oninput="updatePreview()">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Pengarang *</label>
            <input class="adm-input" type="text" name="pengarang" required placeholder="Nama pengarang">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Penerbit</label>
            <input class="adm-input" type="text" name="penerbit" placeholder="Nama penerbit">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Tahun Terbit</label>
            <input class="adm-input" type="number" name="tahun" min="1900" max="2030" value="<?= date('Y') ?>">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">ISBN</label>
            <input class="adm-input" type="text" name="isbn" placeholder="978-xxx-xxx-xxx-x">
          </div>
          <div class="adm-form-group">
            <label class="adm-label">No. Kelas (DDC) *</label>
            <input class="adm-input" type="text" name="no_kelas" required placeholder="301 BUN s" style="font-family:'Space Mono',monospace;">
            <div class="adm-hint">Format: Nomor Kelas + Kode Pengarang + Huruf Judul</div>
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Kategori *</label>
            <select class="adm-input" name="kategori" required>
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
            <label class="adm-label">Jumlah Stok</label>
            <input class="adm-input" type="number" name="stok" value="1" min="1">
          </div>
          <div class="adm-form-group full">
            <label class="adm-label">Deskripsi / Abstrak</label>
            <textarea class="adm-input" name="deskripsi" rows="4" style="resize:vertical;" placeholder="Ringkasan isi buku..."></textarea>
          </div>

          <!-- ===== COVER IMAGE UPLOAD ===== -->
          <div class="adm-form-group full">
            <label class="adm-label">🖼️ Foto Cover Buku</label>
            <div class="cover-upload-zone" id="uploadZone" onclick="document.getElementById('coverInput').click()">
              <div class="cover-upload-icon">📤</div>
              <div class="cover-upload-text">Klik atau seret gambar ke sini</div>
              <div class="cover-upload-hint">JPG, JPEG, PNG — Maks. 5MB</div>
              <div id="uploadFileName" style="font-size:0.8rem;color:var(--adm-accent);font-weight:600;margin-top:8px;display:none;"></div>
            </div>
            <input type="file" id="coverInput" name="cover_image" accept=".jpg,.jpeg,.png" style="display:none;" onchange="previewUpload(this)">
            <div class="adm-hint" style="margin-top:6px;">Jika tidak diupload, cover akan menggunakan warna gradien di bawah.</div>
          </div>

          <!-- Gradient fallback -->
          <div class="adm-form-group" id="gradientGroup1">
            <label class="adm-label">Warna Cover (Atas)</label>
            <input class="adm-input" type="color" name="color1" value="#FF4D6D" oninput="updatePreview()">
          </div>
          <div class="adm-form-group" id="gradientGroup2">
            <label class="adm-label">Warna Cover (Bawah)</label>
            <input class="adm-input" type="color" name="color2" value="#590D22" oninput="updatePreview()">
          </div>
          <div class="adm-form-group" id="gradientGroup3">
            <label class="adm-label">Warna Teks Cover</label>
            <input class="adm-input" type="color" name="color3" value="#FFFFFF" oninput="updatePreview()">
          </div>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:14px;font-size:1rem;margin-top:0.5rem;">➕ Simpan Buku Baru</button>
      </form>
    </div>

    <!-- Preview -->
    <div style="position:sticky;top:80px;">
      <div class="adm-form-card">
        <div class="adm-form-title">👁 Preview Cover</div>
        <div id="coverPreview" style="aspect-ratio:2/3;border-radius:12px;background:linear-gradient(135deg,#FF4D6D,#590D22);color:#fff;display:flex;align-items:center;justify-content:center;text-align:center;font-weight:800;font-size:1rem;line-height:1.4;padding:1.5rem;margin-bottom:1rem;box-shadow:0 10px 30px rgba(0,0,0,0.15);overflow:hidden;position:relative;">
          <span id="previewText">Judul Buku</span>
          <img id="previewImg" src="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" alt="Preview">
        </div>
        <div id="previewBadge" style="display:none;text-align:center;margin-bottom:8px;">
          <span class="adm-badge adm-badge-green">✓ Gambar siap diupload</span>
        </div>
        <button type="button" id="removeCoverBtn" onclick="removeCover()" class="adm-btn adm-btn-danger" style="width:100%;justify-content:center;display:none;">✕ Hapus Gambar</button>
        <div style="font-size:0.72rem;color:var(--adm-muted);text-align:center;margin-top:8px;">Preview tampilan cover di katalog</div>
      </div>
    </div>
  </div>
</div>

<style>
.cover-upload-zone {
  border: 2px dashed var(--adm-border);
  border-radius: 12px;
  padding: 2rem 1rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #FAFAFA;
}
.cover-upload-zone:hover, .cover-upload-zone.dragover {
  border-color: var(--adm-accent);
  background: rgba(255,77,109,0.04);
}
.cover-upload-icon { font-size: 2rem; margin-bottom: 8px; }
.cover-upload-text { font-weight: 600; font-size: 0.9rem; color: #374151; }
.cover-upload-hint { font-size: 0.75rem; color: var(--adm-muted); margin-top: 4px; }
</style>

<script>
function updatePreview() {
  const judul = document.getElementById('judulInput').value || 'Judul Buku';
  const c1 = document.querySelector('[name=color1]').value;
  const c2 = document.querySelector('[name=color2]').value;
  const c3 = document.querySelector('[name=color3]').value;
  const prev = document.getElementById('coverPreview');
  const imgShowing = document.getElementById('previewImg').style.display !== 'none';
  if (!imgShowing) {
    prev.style.background = `linear-gradient(135deg,${c1},${c2})`;
    prev.style.color = c3;
    document.getElementById('previewText').textContent = judul.substring(0, 40);
  }
}

function previewUpload(input) {
  if (!input.files || !input.files[0]) return;
  const file = input.files[0];
  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('previewImg');
    const txt = document.getElementById('previewText');
    img.src = e.target.result;
    img.style.display = 'block';
    txt.style.display = 'none';
    document.getElementById('previewBadge').style.display = 'block';
    document.getElementById('removeCoverBtn').style.display = 'flex';
    // Show filename in upload zone
    const fn = document.getElementById('uploadFileName');
    fn.textContent = '✓ ' + file.name;
    fn.style.display = 'block';
  };
  reader.readAsDataURL(file);
}

function removeCover() {
  document.getElementById('coverInput').value = '';
  document.getElementById('previewImg').style.display = 'none';
  document.getElementById('previewText').style.display = 'block';
  document.getElementById('previewBadge').style.display = 'none';
  document.getElementById('removeCoverBtn').style.display = 'none';
  document.getElementById('uploadFileName').style.display = 'none';
  updatePreview();
}

// Drag and drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault();
  zone.classList.remove('dragover');
  const dt = e.dataTransfer;
  if (dt.files.length) {
    document.getElementById('coverInput').files = dt.files;
    previewUpload(document.getElementById('coverInput'));
  }
});
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
