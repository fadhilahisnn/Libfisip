<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/cover_helper.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php','Akses ditolak.','error'); }

$db = getDB();

// Pre-fill from akuisisi
$pre = [];
if (isset($_GET['from_akuisisi'])) {
    $pre = [
        'judul'     => $_GET['judul'] ?? '',
        'pengarang' => $_GET['pengarang'] ?? '',
        'isbn'      => $_GET['isbn'] ?? '',
        'kategori'  => $_GET['kategori'] ?? '',
        'tahun'     => $_GET['tahun'] ?? date('Y'),
    ];
}

// Edit mode
$editId = (int)($_GET['edit'] ?? 0);
$editBook = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM books WHERE id=?");
    $s->execute([$editId]); $editBook = $s->fetch();
    if ($editBook) $pre = $editBook;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $f = $_POST;
        $stok = (int)($f['stok'] ?? 1);

        // Handle cover
        $coverFile = handleCoverUpload('cover_image', $editBook['cover_image'] ?? null);
        if (!$coverFile && $editBook) {
            if (isset($_POST['remove_cover']) && $_POST['remove_cover'] === '1') {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/libfisip/uploads/covers/';
                if ($editBook['cover_image'] && file_exists($uploadDir . $editBook['cover_image'])) @unlink($uploadDir . $editBook['cover_image']);
                $coverFile = null;
            } else {
                $coverFile = $editBook['cover_image'] ?? null;
            }
        }

        // Build no_kelas from DDC components
        $noKelas = trim($f['ddc_nomor'] . ' ' . strtoupper(substr($f['pengarang_utama'],0,3)) . ' ' . strtolower(substr($f['judul_utama'],0,1)));
        if ($f['no_kelas_manual']) $noKelas = $f['no_kelas_manual'];

        $fields = [
            trim($f['judul_utama']),
            trim($f['pengarang_utama']),
            trim($f['penerbit']),
            (int)$f['tahun_terbit'],
            trim($f['isbn']),
            $noKelas,
            trim($f['kategori']),
            trim($f['bahasa']),
            $stok, $stok,
            trim($f['deskripsi']),
            $f['color1'], $f['color2'], $f['color3'],
            $coverFile ?: null,
            // Extended RDA fields stored in deskripsi as JSON prefix
        ];

        if ($editBook) {
            $fields[] = $editId;
            $db->prepare("UPDATE books SET judul=?,pengarang=?,penerbit=?,tahun=?,isbn=?,no_kelas=?,kategori=?,bahasa=?,stok=?,tersedia=?,deskripsi=?,color1=?,color2=?,color3=?,cover_image=? WHERE id=?")->execute($fields);
            // Mark acquisition as cataloged
            if (isset($_POST['akuisisi_id']) && $_POST['akuisisi_id']) {
                $db->prepare("UPDATE acquisitions SET status='dikatalog', book_id=? WHERE id=?")->execute([$editId, (int)$_POST['akuisisi_id']]);
            }
            redirect('koleksi.php', 'Katalog berhasil diperbarui! ✅');
        } else {
            $db->prepare("INSERT INTO books (judul,pengarang,penerbit,tahun,isbn,no_kelas,kategori,bahasa,stok,tersedia,deskripsi,color1,color2,color3,cover_image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($fields);
            $newId = $db->lastInsertId();
            if (isset($_POST['akuisisi_id']) && $_POST['akuisisi_id']) {
                $db->prepare("UPDATE acquisitions SET status='dikatalog', book_id=? WHERE id=?")->execute([$newId, (int)$_POST['akuisisi_id']]);
            }
            redirect('koleksi.php', 'Katalog baru berhasil dibuat dan masuk ke koleksi! 📚');
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$currentCoverSrc = $editBook ? bookCoverSrc($editBook) : '';
$pageTitle = $editBook ? 'Edit Katalog' : 'Entri Katalog (RDA)';
include __DIR__ . '/includes/admin_header.php';

$kv = fn($k,$def='') => htmlspecialchars($pre[$k] ?? $def);
$sv = fn($k,$opt) => ($pre[$k]??'') === $opt ? 'selected' : '';
?>
<div class="admin-content">
  <div class="page-title-bar">
    <div>
      <h1 class="page-title">📝 <?= $editBook ? 'Edit Katalog' : 'Entri Katalog (RDA)' ?></h1>
      <p class="page-sub">Pengisian data bibliografis <?= $editBook ? 'buku ID #'.$editId : 'koleksi baru' ?> menggunakan standar RDA</p>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="koleksi.php" class="adm-btn adm-btn-secondary">← Daftar Katalog</a>
      <?php if (!$editBook): ?>
      <a href="tambah_buku.php" class="adm-btn adm-btn-ghost">Form Sederhana</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($error): ?><div class="adm-alert adm-alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="rdaForm">
    <?php if (isset($_GET['from_akuisisi'])): ?>
    <input type="hidden" name="akuisisi_id" value="<?= (int)$_GET['from_akuisisi'] ?>">
    <?php endif; ?>
    <?php if ($editBook): ?>
    <input type="hidden" name="remove_cover" id="removeCoverFlag" value="0">
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;">
      <div>

        <!-- Jenis Bahan -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title" style="display:flex;align-items:center;gap:8px;">
            <span>📦 Jenis Bahan</span>
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Jenis Bahan *</label>
            <select class="adm-input" name="jenis_bahan" style="max-width:400px;">
              <option value="monograf">Monograf (Buku, Laporan, dll)</option>
              <option value="jurnal">Jurnal / Terbitan Berkala</option>
              <option value="tesis">Tesis / Disertasi</option>
              <option value="prosiding">Prosiding</option>
              <option value="digital">Konten Digital</option>
            </select>
          </div>
        </div>

        <!-- Data Bibliografi: Judul -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">📖 Judul</div>
          <div class="adm-form-grid">
            <div class="adm-form-group full">
              <label class="adm-label">Judul Utama *</label>
              <input class="adm-input" type="text" name="judul_utama" required value="<?= $kv('judul') ?>" id="judulUtama" oninput="updatePreview()">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Anak Judul / Sub-judul</label>
              <input class="adm-input" type="text" name="anak_judul" value="<?= $kv('anak_judul') ?>" placeholder="Sub-judul jika ada">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Variasi Judul</label>
              <input class="adm-input" type="text" name="variasi_judul" value="<?= $kv('variasi_judul') ?>" placeholder="Judul lain / alternatif">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Judul Asli (jika terjemahan)</label>
              <input class="adm-input" type="text" name="judul_asli" value="<?= $kv('judul_asli') ?>" placeholder="Original title">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Penanggung Jawab</label>
              <input class="adm-input" type="text" name="penanggung_jawab" value="<?= $kv('pengarang') ?>" placeholder="Nama penulis / editor / lembaga">
            </div>
          </div>
        </div>

        <!-- Kreator -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">👤 Kreator</div>
          <div class="adm-form-grid">
            <div class="adm-form-group">
              <label class="adm-label">Peran Utama</label>
              <select class="adm-input" name="peran_utama">
                <option>Pengarang</option><option>Editor</option><option>Penyusun</option><option>Penerjemah</option><option>Ilustrator</option>
              </select>
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Nama Pengarang Utama *</label>
              <input class="adm-input" type="text" name="pengarang_utama" required value="<?= $kv('pengarang') ?>" placeholder="Nama Belakang, Nama Depan" oninput="updateNoKelas()">
              <div class="adm-hint">Format RDA: Nama Belakang, Nama Depan (cth: Bungin, Burhan)</div>
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Pengarang Tambahan</label>
              <input class="adm-input" type="text" name="pengarang_tambahan" placeholder="Nama lain (jika ada)">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Penerjemah (jika ada)</label>
              <input class="adm-input" type="text" name="penerjemah" placeholder="Nama penerjemah">
            </div>
          </div>
        </div>

        <!-- Penerbitan -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">🏢 Penerbitan</div>
          <div class="adm-form-grid">
            <div class="adm-form-group">
              <label class="adm-label">Penerbit *</label>
              <input class="adm-input" type="text" name="penerbit" value="<?= $kv('penerbit') ?>" placeholder="Nama penerbit">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Tempat Terbit</label>
              <input class="adm-input" type="text" name="tempat_terbit" value="<?= $kv('tempat_terbit','Jakarta') ?>" placeholder="Kota tempat terbit">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Tahun Terbit *</label>
              <input class="adm-input" type="number" name="tahun_terbit" required min="1900" max="2030" value="<?= $kv('tahun', date('Y')) ?>">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Edisi / Cetakan</label>
              <input class="adm-input" type="text" name="edisi" value="<?= $kv('edisi') ?>" placeholder="cth: Edisi 3 / Cetakan ke-5">
            </div>
          </div>
        </div>

        <!-- Deskripsi Fisik -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">📐 Deskripsi Fisik</div>
          <div class="adm-form-grid">
            <div class="adm-form-group">
              <label class="adm-label">Jumlah Halaman</label>
              <input class="adm-input" type="text" name="halaman" value="<?= $kv('halaman') ?>" placeholder="cth: xii, 256 hlm">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Ukuran (cm)</label>
              <input class="adm-input" type="text" name="ukuran" value="<?= $kv('ukuran') ?>" placeholder="cth: 24 cm">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Ilustrasi</label>
              <input class="adm-input" type="text" name="ilustrasi" value="<?= $kv('ilustrasi') ?>" placeholder="cth: il., peta, tab.">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Bahasa *</label>
              <select class="adm-input" name="bahasa">
                <option value="Indonesia" <?= $sv('bahasa','Indonesia') ?>>Indonesia</option>
                <option value="Inggris" <?= $sv('bahasa','Inggris') ?>>Inggris</option>
                <option value="Lainnya" <?= $sv('bahasa','Lainnya') ?>>Lainnya</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Identifikasi & Klasifikasi -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">🔖 Identifikasi & Klasifikasi</div>
          <div class="adm-form-grid">
            <div class="adm-form-group">
              <label class="adm-label">ISBN / ISSN</label>
              <input class="adm-input" type="text" name="isbn" value="<?= $kv('isbn') ?>" placeholder="978-xxx-xxx-xxx-x">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Kategori Subjek *</label>
              <select class="adm-input" name="kategori">
                <?php foreach(['Sosiologi','Komunikasi','Politik','Administrasi','Antropologi','Ilmu Informasi','Hukum','Ekonomi','Umum'] as $k): ?>
                <option <?= $sv('kategori',$k) ?>><?= $k ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Nomor DDC</label>
              <input class="adm-input" type="text" name="ddc_nomor" id="ddcNomor" value="<?= $kv('ddc_nomor') ?>" placeholder="cth: 302.2" style="font-family:'Space Mono',monospace;" oninput="updateNoKelas()">
              <div class="adm-hint">Nomor Dewey Decimal Classification</div>
            </div>
            <div class="adm-form-group">
              <label class="adm-label">No. Kelas Lengkap</label>
              <input class="adm-input" type="text" name="no_kelas_manual" id="noKelasDisplay" value="<?= $kv('no_kelas') ?>" placeholder="Auto-generate atau isi manual" style="font-family:'Space Mono',monospace;">
              <div class="adm-hint">DDC + Kode Pengarang + Huruf Judul (akan digenerate otomatis)</div>
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Subjek / Kata Kunci</label>
              <input class="adm-input" type="text" name="subjek" value="<?= $kv('subjek') ?>" placeholder="cth: sosiologi, komunikasi massa, media">
            </div>
            <div class="adm-form-group">
              <label class="adm-label">Jumlah Stok / Eksemplar</label>
              <input class="adm-input" type="number" name="stok" value="<?= $kv('stok','1') ?>" min="1">
            </div>
          </div>
        </div>

        <!-- Catatan -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">📌 Catatan & Deskripsi</div>
          <div class="adm-form-group">
            <label class="adm-label">Abstrak / Deskripsi Konten</label>
            <textarea class="adm-input" name="deskripsi" rows="4" style="resize:vertical;" placeholder="Ringkasan isi buku / abstrak..."><?= $kv('deskripsi') ?></textarea>
          </div>
          <div class="adm-form-group">
            <label class="adm-label">Catatan Tambahan</label>
            <input class="adm-input" type="text" name="catatan_tambahan" value="<?= $kv('catatan_tambahan') ?>" placeholder="Bibliografi, indeks, lampiran, dll.">
          </div>
        </div>

        <!-- Cover Image Upload -->
        <div class="adm-form-card" style="margin-bottom:1.2rem;">
          <div class="adm-form-title">🖼️ Sampul / Cover Buku</div>

          <?php if ($editBook && $currentCoverSrc): ?>
          <div id="currentCoverSection" style="display:flex;align-items:center;gap:14px;padding:12px;background:#f9fafb;border:1px solid var(--adm-border);border-radius:10px;margin-bottom:12px;">
            <img src="<?= $currentCoverSrc ?>" style="width:50px;height:70px;object-fit:cover;border-radius:6px;flex-shrink:0;">
            <div style="flex:1;">
              <div style="font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:4px;">✓ Cover gambar aktif</div>
            </div>
            <button type="button" onclick="markRemoveCover()" class="adm-btn adm-btn-danger adm-btn-sm">✕ Hapus</button>
          </div>
          <div id="removedNotice" style="display:none;padding:10px 14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:8px;font-size:0.82rem;color:#DC2626;margin-bottom:12px;">
            🗑️ Cover lama akan dihapus.
          </div>
          <?php endif; ?>

          <div class="cover-upload-zone" id="uploadZone" onclick="document.getElementById('coverInput').click()">
            <div style="font-size:2rem;margin-bottom:6px;">📤</div>
            <div style="font-weight:600;font-size:0.9rem;color:#374151;">Klik atau seret gambar ke sini</div>
            <div style="font-size:0.75rem;color:var(--adm-muted);margin-top:4px;">JPG, JPEG, PNG — Maks. 5MB</div>
            <div id="uploadFileName" style="font-size:0.8rem;color:var(--adm-accent);font-weight:600;margin-top:8px;display:none;"></div>
          </div>
          <input type="file" id="coverInput" name="cover_image" accept=".jpg,.jpeg,.png" style="display:none;" onchange="previewUpload(this)">

          <div style="margin-top:1.5rem;">
            <label class="adm-label">Atau pilih warna gradien cover</label>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:8px;">
              <div><label class="adm-label" style="font-size:0.72rem;">Warna Atas</label><input class="adm-input" type="color" name="color1" value="<?= $kv('color1','#FF4D6D') ?>" oninput="updatePreview()" style="height:40px;"></div>
              <div><label class="adm-label" style="font-size:0.72rem;">Warna Bawah</label><input class="adm-input" type="color" name="color2" value="<?= $kv('color2','#590D22') ?>" oninput="updatePreview()" style="height:40px;"></div>
              <div><label class="adm-label" style="font-size:0.72rem;">Warna Teks</label><input class="adm-input" type="color" name="color3" value="<?= $kv('color3','#FFFFFF') ?>" oninput="updatePreview()" style="height:40px;"></div>
            </div>
          </div>
        </div>

        <!-- Submit -->
        <div style="display:flex;gap:10px;">
          <button type="submit" name="submit_action" value="simpan" class="adm-btn adm-btn-primary" style="flex:1;justify-content:center;padding:14px;font-size:1rem;">
            📚 <?= $editBook ? 'Simpan Perubahan Katalog' : 'Simpan & Tambah ke Koleksi' ?>
          </button>
          <button type="reset" class="adm-btn adm-btn-secondary" style="padding:14px 20px;">↺ Reset</button>
        </div>
      </div>

      <!-- Right column: Preview + Nav Breadcrumb -->
      <div style="position:sticky;top:80px;display:flex;flex-direction:column;gap:1rem;">
        <div class="adm-form-card">
          <div class="adm-form-title" style="margin-bottom:1rem;">👁 Preview Cover</div>
          <?php if ($currentCoverSrc): ?>
          <div id="coverPreview" style="aspect-ratio:2/3;border-radius:12px;overflow:hidden;margin-bottom:1rem;box-shadow:0 10px 30px rgba(0,0,0,0.15);position:relative;">
            <img id="previewImg" src="<?= $currentCoverSrc ?>" style="width:100%;height:100%;object-fit:cover;display:block;" alt="Preview">
            <span id="previewText" style="display:none;"></span>
          </div>
          <?php else: ?>
          <div id="coverPreview" style="aspect-ratio:2/3;border-radius:12px;background:linear-gradient(135deg,<?= $kv('color1','#FF4D6D') ?>,<?= $kv('color2','#590D22') ?>);color:<?= $kv('color3','#FFFFFF') ?>;display:flex;align-items:center;justify-content:center;text-align:center;font-weight:800;font-size:0.95rem;line-height:1.4;padding:1.2rem;margin-bottom:1rem;box-shadow:0 10px 30px rgba(0,0,0,0.15);overflow:hidden;position:relative;">
            <span id="previewText"><?= $kv('judul','Judul Buku') ?></span>
            <img id="previewImg" src="" style="display:none;position:absolute;inset:0;width:100%;height:100%;object-fit:cover;" alt="Preview">
          </div>
          <?php endif; ?>
          <div id="previewBadge" style="display:none;text-align:center;margin-bottom:8px;"><span class="adm-badge adm-badge-green">✓ Gambar siap</span></div>
          <button type="button" id="removeCoverBtn" onclick="removeCoverNew()" class="adm-btn adm-btn-danger" style="width:100%;justify-content:center;display:none;">✕ Batal Upload</button>
        </div>

        <!-- RDA Info Card -->
        <div class="adm-form-card">
          <div style="font-size:0.82rem;font-weight:700;color:var(--adm-accent);margin-bottom:8px;">ℹ️ Tentang RDA</div>
          <p style="font-size:0.75rem;color:var(--adm-muted);line-height:1.6;">
            <strong>Resource Description and Access (RDA)</strong> adalah standar katalogisasi internasional yang menggantikan AACR2. RDA menekankan pada deskripsi lengkap identitas entitas bibliografis.
          </p>
          <div style="margin-top:10px;font-size:0.72rem;color:var(--adm-muted);">
            <div>✦ Judul → MARC 245</div>
            <div>✦ Pengarang → MARC 100/700</div>
            <div>✦ Penerbit → MARC 260/264</div>
            <div>✦ No. Kelas → MARC 082/092</div>
          </div>
        </div>
      </div>
    </div>
  </form>
</div>

<style>
.cover-upload-zone { border:2px dashed var(--adm-border);border-radius:12px;padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:all 0.2s;background:#FAFAFA; }
.cover-upload-zone:hover,.cover-upload-zone.dragover { border-color:var(--adm-accent);background:rgba(255,77,109,0.04); }
</style>

<script>
function updatePreview() {
  const judul = document.getElementById('judulUtama').value || 'Judul Buku';
  const c1 = document.querySelector('[name=color1]').value;
  const c2 = document.querySelector('[name=color2]').value;
  const c3 = document.querySelector('[name=color3]').value;
  const prev = document.getElementById('coverPreview');
  const img  = document.getElementById('previewImg');
  const txt  = document.getElementById('previewText');
  if (img && img.style.display === 'none') {
    prev.style.background = `linear-gradient(135deg,${c1},${c2})`;
    prev.style.color = c3;
    if (txt) txt.textContent = judul.substring(0,40);
  }
}

function updateNoKelas() {
  const ddc      = document.getElementById('ddcNomor').value.trim();
  const pengarang = document.querySelector('[name=pengarang_utama]').value.trim();
  const judul    = document.getElementById('judulUtama').value.trim();
  if (ddc && pengarang && judul) {
    const kodePen = pengarang.substring(0,3).toUpperCase();
    const hurufJudul = judul.substring(0,1).toLowerCase();
    document.getElementById('noKelasDisplay').value = `${ddc} ${kodePen} ${hurufJudul}`;
  }
}

function previewUpload(input) {
  if (!input.files || !input.files[0]) return;
  const reader = new FileReader();
  reader.onload = function(e) {
    const img = document.getElementById('previewImg');
    const txt = document.getElementById('previewText');
    const prev = document.getElementById('coverPreview');
    img.src = e.target.result;
    img.style.display = 'block';
    prev.style.background = 'none';
    if (txt) txt.style.display = 'none';
    document.getElementById('previewBadge').style.display = 'block';
    document.getElementById('removeCoverBtn').style.display = 'flex';
    const fn = document.getElementById('uploadFileName');
    fn.textContent = '✓ ' + input.files[0].name;
    fn.style.display = 'block';
    if (document.getElementById('removeCoverFlag')) document.getElementById('removeCoverFlag').value = '0';
  };
  reader.readAsDataURL(input.files[0]);
}

function removeCoverNew() {
  document.getElementById('coverInput').value = '';
  const img = document.getElementById('previewImg');
  if (img) { img.src=''; img.style.display='none'; }
  const txt = document.getElementById('previewText');
  if (txt) txt.style.display = 'block';
  document.getElementById('previewBadge').style.display = 'none';
  document.getElementById('removeCoverBtn').style.display = 'none';
  document.getElementById('uploadFileName').style.display = 'none';
  updatePreview();
}

function markRemoveCover() {
  if (document.getElementById('removeCoverFlag')) document.getElementById('removeCoverFlag').value = '1';
  const sec = document.getElementById('currentCoverSection');
  if (sec) sec.style.display = 'none';
  document.getElementById('removedNotice').style.display = 'block';
  const img = document.getElementById('previewImg');
  if (img) { img.src=''; img.style.display='none'; }
  const txt = document.getElementById('previewText');
  if (txt) txt.style.display = 'block';
  updatePreview();
}

// Drag & drop
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('dragover');
  if (e.dataTransfer.files.length) {
    document.getElementById('coverInput').files = e.dataTransfer.files;
    previewUpload(document.getElementById('coverInput'));
  }
});
</script>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
