<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();
if (!isAdmin()) { redirect('../index.php', 'Akses ditolak.', 'error'); }

$db = getDB();
$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM rooms WHERE id = ?");
$stmt->execute([$id]);
$room = $stmt->fetch();

if (!$room) {
    redirect('ruangan.php', 'Ruangan tidak ditemukan.', 'error');
}

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

        $stmt = $db->prepare("UPDATE rooms SET name = ?, description = ?, capacity = ?, facilities = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $description, $capacity, $facilities, $isActive, $id]);

        redirect('ruangan.php', 'Data ruangan berhasil diupdate! ✅');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Edit Ruangan';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-content">
    <div class="page-title-bar">
        <div>
            <h1 class="page-title">✏️ Edit Ruangan</h1>
            <p class="page-sub">ID: <?= $room['id'] ?> — <?= htmlspecialchars($room['name']) ?></p>
        </div>
        <a href="ruangan.php" class="adm-btn adm-btn-secondary">← Kembali</a>
    </div>

    <?php if ($error): ?>
    <div class="adm-alert adm-alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start;">
        <div class="adm-form-card">
            <div class="adm-form-title">📋 Edit Data Ruangan</div>
            <form method="POST">
                <div class="adm-form-grid">
                    <div class="adm-form-group full">
                        <label class="adm-label">Nama Ruangan *</label>
                        <input class="adm-input" type="text" name="name" required 
                               value="<?= htmlspecialchars($room['name']) ?>"
                               placeholder="Contoh: Ruang Diskusi 1">
                    </div>

                    <div class="adm-form-group full">
                        <label class="adm-label">Deskripsi</label>
                        <textarea class="adm-input" name="description" rows="3" 
                                  placeholder="Deskripsi singkat tentang ruangan..."
                                  style="resize: vertical;"><?= htmlspecialchars($room['description'] ?? '') ?></textarea>
                    </div>

                    <div class="adm-form-group">
                        <label class="adm-label">Kapasitas (Orang) *</label>
                        <input class="adm-input" type="number" name="capacity" required 
                               value="<?= $room['capacity'] ?>" min="1" max="500">
                        <div class="adm-hint">Jumlah maksimal orang yang bisa menggunakan ruangan</div>
                    </div>

                    <div class="adm-form-group">
                        <label class="adm-label">Status</label>
                        <div style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.88rem;">
                                <input type="checkbox" name="is_active" value="1" 
                                       <?= $room['is_active'] ? 'checked' : '' ?>
                                       style="width: 20px; height: 20px; accent-color: #059669;"
                                       onchange="updateStatusBadge(this)">
                                <span id="statusLabel"><?= $room['is_active'] ? '✅ Aktif — bisa dipinjam' : '❌ Nonaktif — tidak bisa dipinjam' ?></span>
                            </label>
                        </div>
                    </div>

                    <div class="adm-form-group full">
                        <label class="adm-label">Fasilitas</label>
                        <textarea class="adm-input" name="facilities" rows="2"
                                  placeholder="Pisahkan dengan koma, contoh: AC, WiFi, Proyektor, Whiteboard"
                                  style="resize: vertical;"><?= htmlspecialchars($room['facilities'] ?? '') ?></textarea>
                        <div class="adm-hint">Pisahkan setiap fasilitas dengan tanda koma (,)</div>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary" 
                        style="width: 100%; justify-content: center; padding: 14px; font-size: 1rem; margin-top: 0.5rem;">
                    ✅ Simpan Perubahan
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
                            <div id="previewName" style="font-weight: 700; font-size: 1.05rem;"><?= htmlspecialchars($room['name']) ?></div>
                            <div id="previewCapacity" style="font-size: 0.8rem; color: var(--adm-muted);">👥 Kapasitas: <?= $room['capacity'] ?> orang</div>
                        </div>
                    </div>
                    <p id="previewDesc" style="font-size: 0.82rem; color: var(--adm-muted); line-height: 1.5; margin-bottom: 1rem;">
                        <?= htmlspecialchars($room['description'] ?? 'Belum ada deskripsi') ?>
                    </p>
                    <div id="previewFacilities" style="display: flex; flex-wrap: wrap; gap: 6px;">
                        <?php 
                        $facilities = array_filter(array_map('trim', explode(',', $room['facilities'] ?? '')));
                        foreach ($facilities as $f): ?>
                            <span style="font-size: 0.7rem; padding: 3px 8px; background: var(--adm-border); border-radius: 99px; color: var(--adm-muted); font-weight: 600;">
                                <?= htmlspecialchars($f) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 1rem; text-align: center;">
                        <span id="previewStatus" class="adm-badge <?= $room['is_active'] ? 'adm-badge-green' : 'adm-badge-red' ?>">
                            <?= $room['is_active'] ? '✅ Aktif' : '❌ Nonaktif' ?>
                        </span>
                    </div>
                </div>
                <div style="font-size: 0.72rem; color: var(--adm-muted); text-align: center; margin-top: 10px;">Preview tampilan ruangan</div>
            </div>

            <!-- Booking Count -->
            <?php
            $bookingCount = $db->prepare("SELECT COUNT(*) FROM room_bookings WHERE room_id = ?");
            $bookingCount->execute([$id]);
            $totalBookings = $bookingCount->fetchColumn();
            
            $activeBookings = $db->prepare("SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND status IN ('pending','approved') AND booking_date >= CURDATE()");
            $activeBookings->execute([$id]);
            $activeCount = $activeBookings->fetchColumn();
            ?>
            <div class="adm-form-card" style="margin-top: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <span style="font-size: 0.82rem; font-weight: 600;">📊 Statistik</span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="text-align: center; padding: 12px; background: rgba(59,130,246,0.06); border-radius: 8px;">
                        <div style="font-size: 1.4rem; font-weight: 800; color: #2563EB; font-family: 'Space Mono', monospace;"><?= $totalBookings ?></div>
                        <div style="font-size: 0.7rem; color: var(--adm-muted); font-weight: 600;">Total Booking</div>
                    </div>
                    <div style="text-align: center; padding: 12px; background: rgba(16,185,129,0.06); border-radius: 8px;">
                        <div style="font-size: 1.4rem; font-weight: 800; color: #059669; font-family: 'Space Mono', monospace;"><?= $activeCount ?></div>
                        <div style="font-size: 0.7rem; color: var(--adm-muted); font-weight: 600;">Booking Aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview updates
document.querySelector('[name="name"]').addEventListener('input', function() {
    document.getElementById('previewName').textContent = this.value || 'Nama Ruangan';
});

document.querySelector('[name="description"]').addEventListener('input', function() {
    document.getElementById('previewDesc').textContent = this.value || 'Belum ada deskripsi';
});

document.querySelector('[name="capacity"]').addEventListener('input', function() {
    document.getElementById('previewCapacity').textContent = '👥 Kapasitas: ' + (this.value || '0') + ' orang';
});

document.querySelector('[name="facilities"]').addEventListener('input', function() {
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
