<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php', 'Akses ditolak.', 'error'); }

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $facilities = trim($_POST['facilities'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        // Validation
        if (empty($name)) {
            throw new RuntimeException('Nama ruangan harus diisi.');
        }
        if ($capacity < 1) {
            throw new RuntimeException('Kapasitas minimal 1 orang.');
        }

        // Check duplicate name
        $check = $db->prepare("SELECT id FROM rooms WHERE name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            throw new RuntimeException('Ruangan dengan nama tersebut sudah ada.');
        }

        $stmt = $db->prepare("INSERT INTO rooms (name, description, capacity, facilities, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $capacity, $facilities, $isActive]);

        redirect('ruangan.php', 'Ruangan baru berhasil ditambahkan! ✅');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Tambah Ruangan';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-content">
    <div class="page-title-bar">
        <div>
            <h1 class="page-title">➕ Tambah Ruangan Baru</h1>
            <p class="page-sub">Tambahkan ruangan baru untuk peminjaman</p>
        </div>
        <a href="ruangan.php" class="adm-btn adm-btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
    <div class="adm-alert adm-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
        <div class="adm-form-card">
            <div class="adm-form-title">📋 Data Ruangan Baru</div>
            <form method="POST">
                <div class="adm-form-grid">
                    <div class="adm-form-group full">
                        <label class="adm-label">Nama Ruangan *</label>
                        <input class="adm-input" type="text" name="name" required 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               placeholder="Contoh: Ruang Diskusi 3"
                               id="nameInput">
                    </div>

                    <div class="adm-form-group full">
                        <label class="adm-label">Deskripsi</label>
                        <textarea class="adm-input" name="description" rows="3" 
                                  placeholder="Deskripsi singkat tentang ruangan..."
                                  style="resize: vertical;" id="descInput"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="adm-form-group">
                        <label class="adm-label">Kapasitas (Orang) *</label>
                        <input class="adm-input" type="number" name="capacity" required 
                               value="<?= $_POST['capacity'] ?? 10 ?>" min="1" max="500"
                               id="capInput">
                        <div class="adm-hint">Jumlah maksimal orang yang bisa menggunakan ruangan</div>
                    </div>

                    <div class="adm-form-group">
                        <label class="adm-label">Status</label>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.88rem;">
                                <input type="checkbox" name="is_active" value="1" checked
                                       style="width: 20px; height: 20px; accent-color: #059669;"
                                       onchange="updateStatusBadge(this)">
                                <span id="statusLabel">✅ Aktif — bisa dipinjam</span>
                            </label>
                        </div>
                    </div>

                    <div class="adm-form-group full">
                        <label class="adm-label">Fasilitas</label>
                        <textarea class="adm-input" name="facilities" rows="2"
                                  placeholder="Pisahkan dengan koma, contoh: AC, WiFi, Proyektor, Whiteboard"
                                  style="resize: vertical;"
                                  id="facInput"><?= htmlspecialchars($_POST['facilities'] ?? 'AC, WiFi') ?></textarea>
                        <div class="adm-hint">Pisahkan setiap fasilitas dengan tanda koma (,)</div>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary" 
                        style="width: 100%; justify-content: center; padding: 14px; font-size: 1rem; margin-top: 0.5rem;">
                    ➕ Tambah Ruangan
                </button>
            </form>
        </div>

        <!-- Preview Card -->
        <div style="position: sticky; top: 80px;">
            <div class="adm-form-card">
                <div class="adm-form-title">👁 Preview Ruangan</div>
                <div style="padding: 1.2rem; background: linear-gradient(135deg, rgba(255,77,109,0.06), rgba(118,75,162,0.06)); border-radius: 12px; border: 1px solid var(--adm-border);">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1rem;">
                        <div style="width: 48px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, rgba(255,77,109,0.15), rgba(118,75,162,0.15)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">🏛️</div>
                        <div>
                            <div id="previewName" style="font-weight: 700; font-size: 1.05rem;">Nama Ruangan</div>
                            <div id="previewCapacity" style="font-size: 0.8rem; color: var(--adm-muted);">👥 Kapasitas: 10 orang</div>
                        </div>
                    </div>
                    <p id="previewDesc" style="font-size: 0.82rem; color: var(--adm-muted); line-height: 1.5; margin-bottom: 1rem;">
                        Belum ada deskripsi
                    </p>
                    <div id="previewFacilities" style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <span style="font-size:0.7rem;padding:3px 8px;background:var(--adm-border);border-radius:99px;color:var(--adm-muted);font-weight:600;">AC</span>
                        <span style="font-size:0.7rem;padding:3px 8px;background:var(--adm-border);border-radius:99px;color:var(--adm-muted);font-weight:600;">WiFi</span>
                    </div>
                    <div style="margin-top: 1rem; text-align: center;">
                        <span id="previewStatus" class="adm-badge adm-badge-green">✅ Aktif</span>
                    </div>
                </div>
                <div style="font-size: 0.72rem; color: var(--adm-muted); text-align: center; margin-top: 10px;">Preview tampilan ruangan</div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview updates
document.getElementById('nameInput').addEventListener('input', function() {
    document.getElementById('previewName').textContent = this.value || 'Nama Ruangan';
});

document.getElementById('descInput').addEventListener('input', function() {
    document.getElementById('previewDesc').textContent = this.value || 'Belum ada deskripsi';
});

document.getElementById('capInput').addEventListener('input', function() {
    document.getElementById('previewCapacity').textContent = '👥 Kapasitas: ' + (this.value || '0') + ' orang';
});

document.getElementById('facInput').addEventListener('input', function() {
    const container = document.getElementById('previewFacilities');
    const items = this.value.split(',').map(s => s.trim()).filter(Boolean);
    container.innerHTML = items.map(f => 
        `<span style="font-size:0.7rem;padding:3px 8px;background:var(--adm-border);border-radius:99px;color:var(--adm-muted);font-weight:600;">${f}</span>`
    ).join('');
});

function updateStatusBadge(checkbox) {
    const label = document.getElementById('statusLabel');
    const badge = document.getElementById('previewStatus');
    if (checkbox.checked) {
        label.textContent = '✅ Aktif — bisa dipinjam';
        badge.className = 'adm-badge adm-badge-green';
        badge.textContent = '✅ Aktif';
    } else {
        label.textContent = '❌ Nonaktif — tidak bisa dipinjam';
        badge.className = 'adm-badge adm-badge-red';
        badge.textContent = '❌ Nonaktif';
    }
}
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
