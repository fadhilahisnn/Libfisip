<?php
session_start();
require_once '../config.php';

// Check if admin
if (!isAdmin()) {
    redirect('../login.php', 'Anda harus login sebagai admin.', 'error');
}

$pageTitle = 'Kelola Peminjaman Ruang';
require_once 'includes/admin_header.php';

$db = getDB();

// Handle status update
if (isset($_POST['action']) && isset($_POST['booking_id'])) {
    $bookingId = (int)$_POST['booking_id'];
    $action = $_POST['action'];
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE room_bookings SET status = 'approved', admin_notes = ? WHERE id = ?");
        $stmt->execute([$adminNotes, $bookingId]);
        $_SESSION['flash_msg'] = 'Peminjaman berhasil disetujui.';
        $_SESSION['flash_type'] = 'success';
    } elseif ($action === 'reject') {
        $stmt = $db->prepare("UPDATE room_bookings SET status = 'rejected', admin_notes = ? WHERE id = ?");
        $stmt->execute([$adminNotes, $bookingId]);
        $_SESSION['flash_msg'] = 'Peminjaman ditolak.';
        $_SESSION['flash_type'] = 'success';
    }
    
    echo "<script>window.location='ruangan.php';</script>";
    exit;
}

// Get filter
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = "rb.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $where[] = "(u.nama LIKE ? OR rb.purpose LIKE ? OR r.name LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Get bookings with pagination
$limit = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("
    SELECT rb.*, u.nama as user_name, u.nim, r.name as room_name, r.capacity
    FROM room_bookings rb
    JOIN users u ON rb.user_id = u.id
    JOIN rooms r ON rb.room_id = r.id
    WHERE $whereClause
    ORDER BY rb.booking_date DESC, rb.start_time DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get total count
$countStmt = $db->prepare("
    SELECT COUNT(*) as total
    FROM room_bookings rb
    JOIN users u ON rb.user_id = u.id
    JOIN rooms r ON rb.room_id = r.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalBookings = $countStmt->fetch()['total'];
$totalPages = ceil($totalBookings / $limit);

// Get statistics
$stats = [
    'pending' => $db->query("SELECT COUNT(*) FROM room_bookings WHERE status = 'pending'")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM room_bookings WHERE status = 'approved'")->fetchColumn(),
    'rejected' => $db->query("SELECT COUNT(*) FROM room_bookings WHERE status = 'rejected'")->fetchColumn(),
    'total' => $db->query("SELECT COUNT(*) FROM room_bookings")->fetchColumn()
];

// Get all rooms
$rooms = $db->query("SELECT * FROM rooms ORDER BY id")->fetchAll();
?>

<div class="admin-content">
    <div class="page-title-bar">
        <div>
            <h1 class="page-title">🏛️ Kelola Peminjaman Ruang</h1>
            <p class="page-sub">Kelola peminjaman ruang baca, diskusi, dan praktikum</p>
        </div>
        <div class="page-actions">
            <a href="../ruangan.php" class="adm-btn adm-btn-primary" target="_blank">
                🔗 Lihat Halaman Peminjaman
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stat-grid">
        <div class="stat-card stat-rose">
            <div class="stat-icon">⏳</div>
            <div>
                <div class="stat-val"><?= $stats['pending'] ?></div>
                <div class="stat-lbl">Menunggu Persetujuan</div>
            </div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-icon">✅</div>
            <div>
                <div class="stat-val"><?= $stats['approved'] ?></div>
                <div class="stat-lbl">Disetujui</div>
            </div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-icon">❌</div>
            <div>
                <div class="stat-val"><?= $stats['rejected'] ?></div>
                <div class="stat-lbl">Ditolak</div>
            </div>
        </div>
        <div class="stat-card stat-blue">
            <div class="stat-icon">📊</div>
            <div>
                <div class="stat-val"><?= $stats['total'] ?></div>
                <div class="stat-lbl">Total Peminjaman</div>
            </div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="dash-card" style="margin-bottom: 1.5rem;">
        <form method="GET" class="filter-bar" style="padding: 1rem 1.5rem; margin-bottom: 0;">
            <select name="status" class="filter-input" onchange="this.form.submit()">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
            </select>
            <input type="text" name="search" class="filter-input" placeholder="Cari nama, tujuan..." value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="adm-btn adm-btn-secondary adm-btn-sm">🔍 Cari</button>
            <?php if (!empty($searchQuery) || $statusFilter !== 'all'): ?>
                <a href="ruangan.php" class="adm-btn adm-btn-ghost">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Bookings Table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <div class="dash-card-title">Daftar Peminjaman</div>
            <div style="font-size: 0.8rem; color: var(--adm-muted);">
                Halaman <?= $page ?> dari <?= $totalPages ?: 1 ?>
            </div>
        </div>
        
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Tanggal & Waktu</th>
                        <th>Peminjam</th>
                        <th>Ruangan</th>
                        <th>Tujuan</th>
                        <th>Peserta</th>
                        <th>Durasi</th>
                        <th>Status</th>
                        <th>Catatan Admin</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="8" class="empty-row">
                            <?php if (empty($searchQuery) && $statusFilter === 'all'): ?>
                                Belum ada peminjaman ruangan.
                            <?php else: ?>
                                Tidak ditemukan peminjaman yang sesuai.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($bookings as $b): 
                        $bookingDate = date('d M Y', strtotime($b['booking_date']));
                        $dayName = date('l', strtotime($b['booking_date']));
                        $timeRange = date('H:i', strtotime($b['start_time'])) . ' - ' . date('H:i', strtotime($b['end_time']));
                    ?>
                    <tr>
                        <td>
                            <div class="cell-title"><?= $bookingDate ?></div>
                            <div class="cell-sub"><?= $dayName ?> | <?= $timeRange ?> WIB</div>
                        </td>
                        <td>
                            <div class="cell-title"><?= htmlspecialchars($b['user_name']) ?></div>
                            <div class="cell-sub"><?= htmlspecialchars($b['nim'] ?? '-') ?></div>
                        </td>
                        <td>
                            <div class="cell-title">🏛️ <?= htmlspecialchars($b['room_name']) ?></div>
                            <div class="cell-sub">Kapasitas: <?= $b['capacity'] ?> orang</div>
                        </td>
                        <td>
                            <div class="cell-title" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <?= htmlspecialchars($b['purpose']) ?>
                            </div>
                        </td>
                        <td>
                            <div class="cell-title"><?= $b['participant_count'] ?> orang</div>
                        </td>
                        <td>
                            <div class="cell-title"><?= $b['duration_hours'] ?> jam</div>
                        </td>
                        <td>
                            <?php
                            $badgeClass = 'adm-badge-yellow';
                            $badgeText = 'Menunggu';
                            if ($b['status'] === 'approved') {
                                $badgeClass = 'adm-badge-green';
                                $badgeText = 'Disetujui';
                            } elseif ($b['status'] === 'rejected') {
                                $badgeClass = 'adm-badge-red';
                                $badgeText = 'Ditolak';
                            }
                            ?>
                            <span class="adm-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                        </td>
                        <td>
                            <?php if ($b['status'] === 'pending'): ?>
                                <input type="text" name="admin_notes" form="form-action-<?= $b['id'] ?>" class="filter-input" placeholder="Alasan/Instruksi..." style="width: 150px; padding: 0.3rem 0.5rem; font-size: 0.8rem;">
                            <?php else: ?>
                                <div style="font-size: 0.8rem; color: var(--adm-muted); max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($b['admin_notes'] ?? '') ?>">
                                    <?= htmlspecialchars($b['admin_notes'] ?? '-') ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['status'] === 'pending'): ?>
                            <form method="POST" id="form-action-<?= $b['id'] ?>" style="display: flex; gap: 4px;">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <button type="submit" name="action" value="approve" class="adm-btn adm-btn-green adm-btn-sm" 
                                        onclick="return confirm('Setujui peminjaman ini?')">
                                    ✓ Setujui
                                </button>
                                <button type="submit" name="action" value="reject" class="adm-btn adm-btn-danger adm-btn-sm"
                                        onclick="return confirm('Tolak peminjaman ini?')">
                                    ✕ Tolak
                                </button>
                            </form>
                            <?php else: ?>
                                <button class="adm-btn adm-btn-secondary adm-btn-sm" onclick="alert('Catatan: <?= htmlspecialchars($b['admin_notes'] ?? '-') ?>')">📄 Detail</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--adm-border); display: flex; justify-content: center; gap: 8px;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?><?= $statusFilter !== 'all' ? '&status=' . $statusFilter : '' ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                   class="adm-btn adm-btn-secondary adm-btn-sm">← Sebelumnya</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?><?= $statusFilter !== 'all' ? '&status=' . $statusFilter : '' ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                   class="adm-btn adm-btn-sm <?= $i === $page ? 'adm-btn-primary' : 'adm-btn-secondary' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?><?= $statusFilter !== 'all' ? '&status=' . $statusFilter : '' ?><?= !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : '' ?>" 
                   class="adm-btn adm-btn-secondary adm-btn-sm">Berikutnya →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Room Management Section -->
    <div class="dash-card" style="margin-top: 2rem;">
        <div class="dash-card-header">
            <div class="dash-card-title">🏢 Kelola Ruangan</div>
            <a href="tambah_ruangan.php" class="adm-btn adm-btn-primary adm-btn-sm">+ Tambah Ruangan</a>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Nama Ruangan</th>
                        <th>Kapasitas</th>
                        <th>Fasilitas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td>
                            <div class="cell-title">🏛️ <?= htmlspecialchars($room['name']) ?></div>
                            <div class="cell-sub"><?= htmlspecialchars($room['description']) ?></div>
                        </td>
                        <td>
                            <div class="cell-title"><?= $room['capacity'] ?> orang</div>
                        </td>
                        <td>
                            <div class="cell-sub"><?= htmlspecialchars($room['facilities']) ?></div>
                        </td>
                        <td>
                            <span class="adm-badge <?= $room['is_active'] ? 'adm-badge-green' : 'adm-badge-red' ?>">
                                <?= $room['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_ruangan.php?id=<?= $room['id'] ?>" class="adm-btn adm-btn-secondary adm-btn-sm">
                                ✏️ Edit
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showDetail(bookingId) {
    // Could open a modal with full booking details
    alert('Fitur detail booking - ID: ' + bookingId);
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>