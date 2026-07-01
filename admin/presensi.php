<?php
session_start();
require_once '../config.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$db = getDB();
$adminId = $_SESSION['user_id'];

// Create table if not exists (old schema fallback)
$db->exec("CREATE TABLE IF NOT EXISTS admin_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    shift_date DATE,
    check_in TIME,
    check_out TIME,
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Safely add missing columns if they don't exist
try { $db->exec("ALTER TABLE admin_attendance ADD COLUMN kegiatan TEXT"); } catch(Exception $e) {}
try { $db->exec("ALTER TABLE admin_attendance ADD COLUMN foto_bukti VARCHAR(255)"); } catch(Exception $e) {}

$successMsg = '';
$errorMsg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_presensi'])) {
    $shiftDate = $_POST['shift_date'] ?? date('Y-m-d');
    $checkIn = $_POST['check_in'] ?? date('H:i');
    $checkOut = $_POST['check_out'] ?? date('H:i');
    $kegiatan = trim($_POST['kegiatan'] ?? '');
    
    // Check if shift_date already exists for today to update, or just insert new row.
    // For simplicity, we just insert a new log for each submission.
    
    // File upload
    $fotoPath = '';
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/presensi/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $filename = uniqid('presensi_') . '.' . $ext;
            $targetFile = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $targetFile)) {
                $fotoPath = 'assets/presensi/' . $filename;
            } else {
                $errorMsg = "Gagal mengunggah foto bukti.";
            }
        } else {
            $errorMsg = "Format foto tidak didukung. Harap unggah JPG, PNG, atau GIF.";
        }
    } else {
        $errorMsg = "Bukti foto kegiatan wajib diunggah.";
    }

    if (!$errorMsg && empty($kegiatan)) {
        $errorMsg = "Kegiatan wajib diisi.";
    }

    if (!$errorMsg) {
        try {
            $stmt = $db->prepare("INSERT INTO admin_attendance (admin_id, shift_date, check_in, check_out, kegiatan, foto_bukti, status) VALUES (?, ?, ?, ?, ?, ?, 'Selesai')");
            $stmt->execute([$adminId, $shiftDate, $checkIn, $checkOut, $kegiatan, $fotoPath]);
            $successMsg = "Presensi berhasil dicatat!";
        } catch (Exception $e) {
            $errorMsg = "Gagal mencatat presensi: " . $e->getMessage();
        }
    }
}

// Fetch history
$stmt = $db->prepare("SELECT * FROM admin_attendance WHERE admin_id = ? ORDER BY shift_date DESC, check_in DESC");
$stmt->execute([$adminId]);
$history = $stmt->fetchAll();

$pageTitle = 'Presensi Jaga Admin';
include 'includes/admin_header.php';
?>

<div class="admin-content">
    <div class="page-title-bar">
        <div>
            <h1 class="page-title">📸 Presensi Jaga</h1>
            <p class="page-sub">Catat dan kelola histori kehadiran shift jaga Anda di sini.</p>
        </div>
    </div>

    <?php if ($successMsg): ?>
        <div style="padding:1rem; background:#dcfce7; color:#166534; border-radius:8px; margin-bottom:1.5rem; font-weight:600; border: 1px solid #bbf7d0;">✅ <?= $successMsg ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div style="padding:1rem; background:#fee2e2; color:#991b1b; border-radius:8px; margin-bottom:1.5rem; font-weight:600; border: 1px solid #fecaca;">❌ <?= $errorMsg ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
        <!-- Form Presensi -->
        <div class="dash-card" style="align-self: start; padding: 1.5rem;">
            <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-light); padding-bottom: 0.5rem; color: var(--text-main);">Catat Presensi Baru</h2>
            
            <form method="POST" action="presensi.php" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Tanggal Shift</label>
                    <input type="date" name="shift_date" class="form-control" value="<?= date('Y-m-d') ?>" required style="width: 100%; padding: 0.8rem; border: 1.5px solid var(--border-light); border-radius: 8px;">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Check In</label>
                        <input type="time" name="check_in" class="form-control" value="<?= date('H:i') ?>" required style="width: 100%; padding: 0.8rem; border: 1.5px solid var(--border-light); border-radius: 8px;">
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Check Out</label>
                        <input type="time" name="check_out" class="form-control" value="<?= date('H:i') ?>" required style="width: 100%; padding: 0.8rem; border: 1.5px solid var(--border-light); border-radius: 8px;">
                    </div>
                </div>

                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Kegiatan / Laporan Jaga</label>
                    <textarea name="kegiatan" required placeholder="Ceritakan apa saja yang dilakukan selama jaga..." style="width: 100%; padding: 0.8rem; border: 1.5px solid var(--border-light); border-radius: 8px; min-height: 100px; resize: vertical; font-family: inherit;"></textarea>
                </div>

                <div>
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem;">Bukti Foto Kegiatan</label>
                    <input type="file" name="foto_bukti" accept="image/*" required style="width: 100%; padding: 0.8rem; border: 1.5px dashed var(--border-light); border-radius: 8px; background: #f8fafc; cursor: pointer;">
                    <small style="color: var(--muted); font-size: 0.75rem;">Harap unggah swafoto atau foto situasi di ruang baca (JPG/PNG).</small>
                </div>

                <button type="submit" name="submit_presensi" style="margin-top: 1.5rem; padding: 1rem; background: #FF4D6D; color: #ffffff; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(255,77,109,0.3); text-align: center; width: 100%;">
                    Kirim Presensi
                </button>
            </form>
        </div>

        <!-- Histori Presensi -->
        <div class="dash-card" style="align-self: start; padding: 1.5rem;">
            <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-light); padding-bottom: 0.5rem; color: var(--text-main);">Rekap Presensi Anda</h2>
            
            <?php if (empty($history)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--muted);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📭</div>
                    <div style="font-weight: 600;">Belum ada histori presensi.</div>
                    <div style="font-size: 0.85rem;">Presensi yang Anda catat akan muncul di sini.</div>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php foreach ($history as $h): ?>
                    <div style="display: flex; gap: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-light);">
                        <div style="width: 100px; height: 100px; border-radius: 12px; overflow: hidden; background: #f1f5f9; flex-shrink: 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                            <?php if ($h['foto_bukti'] && file_exists('../' . $h['foto_bukti'])): ?>
                                <img src="../<?= htmlspecialchars($h['foto_bukti']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:2rem;">📷</div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
                                <div style="font-weight: 700; color: var(--text-main); font-size: 1.1rem;">
                                    <?= date('d M Y', strtotime($h['shift_date'])) ?>
                                </div>
                                <span style="font-size: 0.75rem; font-weight: 700; background: rgba(16,185,129,0.1); color: #059669; padding: 4px 10px; border-radius: 20px;">
                                    <?= htmlspecialchars($h['status']) ?>
                                </span>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.6rem;">
                                ⏱️ <?= substr($h['check_in'], 0, 5) ?> - <?= substr($h['check_out'], 0, 5) ?> WIB
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-main); line-height: 1.5; background: #f8fafc; padding: 0.8rem; border-radius: 8px; border-left: 3px solid var(--primary);">
                                "<?= nl2br(htmlspecialchars($h['kegiatan'])) ?>"
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>
