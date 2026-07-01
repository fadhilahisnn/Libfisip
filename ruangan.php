<?php
session_start();
require_once 'config.php';

$pageTitle = 'Peminjaman Ruang';
require_once 'includes/header.php';

// Get current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get all active rooms
$db = getDB();
$rooms = $db->query("SELECT * FROM rooms WHERE is_active = TRUE ORDER BY id")->fetchAll();

// Get bookings for current month
$stmt = $db->prepare("SELECT booking_date, room_id, status FROM room_bookings WHERE YEAR(booking_date) = ? AND MONTH(booking_date) = ?");
$stmt->execute([$year, $month]);
$bookings = $stmt->fetchAll();

// Create availability map
$availability = [];
foreach ($bookings as $b) {
    if ($b['status'] !== 'cancelled' && $b['status'] !== 'rejected') {
        $availability[$b['booking_date']][$b['room_id']] = true;
    }
}

// Calendar calculation
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$lastDay = date('t', $firstDay);
$startingDay = date('w', $firstDay);
$monthName = date('F Y', $firstDay);

// Indonesian month names
$monthNames = [
    'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
    'April' => 'April', 'May' => 'Mei', 'June' => 'Juni',
    'July' => 'Juli', 'August' => 'Agustus', 'September' => 'September',
    'October' => 'Oktober', 'November' => 'November', 'December' => 'Desember'
];
$displayMonth = $currentLang === 'en' ? $monthName : str_replace(array_keys($monthNames), array_values($monthNames), $monthName);

// Navigation
$prevMonth = $month == 1 ? 12 : $month - 1;
$prevYear = $month == 1 ? $year - 1 : $year;
$nextMonth = $month == 12 ? 1 : $month + 1;
$nextYear = $month == 12 ? $year + 1 : $year;
?>

<style>
/* Room Booking Page Styles */
.room-booking-hero {
    background: linear-gradient(135deg, #1a0533 0%, #2d0a4e 40%, #590D22 100%);
    padding: 3rem 5%;
    border-radius: 0 0 24px 24px;
    margin-top: -2rem;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.room-booking-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 30% 50%, rgba(255,77,109,0.2) 0%, transparent 50%);
    pointer-events: none;
}

.room-booking-hero-content {
    position: relative;
    z-index: 1;
    text-align: center;
    color: #fff;
}

.room-booking-hero h1 {
    font-size: 2.5rem;
    font-weight: 800;
    letter-spacing: -1px;
    margin-bottom: 0.5rem;
}

.room-booking-hero p {
    font-size: 1rem;
    opacity: 0.8;
    max-width: 600px;
    margin: 0 auto;
}

/* Calendar Navigation */
.calendar-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    margin-bottom: 2rem;
}

.calendar-nav-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 1.5px solid var(--border);
    background: var(--card-bg);
    color: var(--text-main);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.25s;
    text-decoration: none;
    font-size: 1.2rem;
}

.calendar-nav-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    transform: scale(1.1);
}

.calendar-month-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-main);
    min-width: 200px;
    text-align: center;
}

/* Calendar Grid */
.calendar-container {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-bottom: 2rem;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-header {
    text-align: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--muted);
    padding: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    font-size: 0.95rem;
    font-weight: 600;
    border: 1.5px solid transparent;
    background: var(--bg-secondary);
    color: var(--text-main);
    min-height: 60px;
}

.calendar-day:hover:not(.disabled):not(.empty) {
    border-color: var(--accent);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(255,77,109,0.2);
}

.calendar-day.empty {
    background: transparent;
    cursor: default;
}

.calendar-day.disabled {
    opacity: 0.3;
    cursor: not-allowed;
    background: var(--bg-tertiary);
}

.calendar-day.available {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.3);
    color: #059669;
}

.calendar-day.available:hover {
    background: rgba(16, 185, 129, 0.15);
    border-color: #059669;
}

.calendar-day.partial {
    background: rgba(251, 191, 36, 0.08);
    border-color: rgba(251, 191, 36, 0.3);
    color: #D97706;
}

.calendar-day.partial:hover {
    background: rgba(251, 191, 36, 0.15);
    border-color: #D97706;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(251,191,36,0.2);
}

.calendar-day.today {
    box-shadow: 0 0 0 2px var(--accent);
}

.calendar-day .day-number {
    font-size: 1rem;
    font-weight: 700;
}

.calendar-day .day-label {
    font-size: 0.6rem;
    margin-top: 2px;
    font-weight: 500;
}

/* Legend */
.calendar-legend {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--muted);
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 4px;
}

.legend-dot.available { background: rgba(16, 185, 129, 0.3); border: 1px solid #059669; }
.legend-dot.partial { background: rgba(251, 191, 36, 0.3); border: 1px solid #D97706; }
.legend-dot.full { background: rgba(239, 68, 68, 0.3); border: 1px solid #DC2626; }
.legend-dot.today { background: transparent; border: 2px solid var(--accent); }

/* Rooms Quick View */
.rooms-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.room-card {
    background: var(--card-bg);
    border-radius: var(--radius-md);
    padding: 1.5rem;
    border: 1px solid var(--border);
    transition: all 0.3s;
}

.room-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow);
}

.room-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 1rem;
}

.room-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-sm);
    background: linear-gradient(135deg, rgba(255,77,109,0.1), rgba(255,143,163,0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.room-info h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 2px;
}

.room-capacity {
    font-size: 0.8rem;
    color: var(--muted);
}

.room-facilities {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 0.8rem;
}

.facility-tag {
    font-size: 0.7rem;
    padding: 3px 8px;
    background: var(--bg-secondary);
    border-radius: 99px;
    color: var(--muted);
    font-weight: 600;
}

/* Booking Modal */
.booking-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.booking-modal-overlay.active {
    display: flex;
}

.booking-modal {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--card-bg);
    z-index: 10;
}

.modal-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
}

.modal-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: var(--bg-secondary);
    color: var(--text-main);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: all 0.2s;
}

.modal-close:hover {
    background: var(--accent);
    color: #fff;
}

.modal-body {
    padding: 1.5rem;
}

.booking-date-display {
    background: linear-gradient(135deg, rgba(255,77,109,0.1), rgba(118,75,162,0.1));
    border-radius: var(--radius-md);
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    text-align: center;
}

.booking-date-display .date-label {
    font-size: 0.8rem;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.booking-date-display .date-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
    margin-top: 4px;
}

.form-section {
    margin-bottom: 1.5rem;
}

.form-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text-main);
    margin-bottom: 0.8rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.room-options {
    display: grid;
    gap: 0.8rem;
}

.time-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1rem;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: inherit;
    font-size: 0.9rem;
    background: var(--input-bg);
    color: var(--text-main);
    transition: all 0.3s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(255,77,109,0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

.participant-input {
    display: flex;
    align-items: center;
    gap: 10px;
}

.participant-input input {
    width: 80px;
    text-align: center;
}

.participant-max {
    font-size: 0.8rem;
    color: var(--muted);
}

/* SOP Section */
.sop-section {
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    padding: 1.2rem;
    margin-bottom: 1.5rem;
}

.sop-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.sop-title {
    font-weight: 700;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 6px;
}

.sop-download {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: var(--accent);
    color: #fff;
    border-radius: 99px;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}

.sop-download:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,77,109,0.3);
}

.sop-content {
    font-size: 0.82rem;
    line-height: 1.6;
    color: var(--text-secondary);
    max-height: 200px;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.sop-content h4 {
    font-size: 0.85rem;
    font-weight: 700;
    margin: 1rem 0 0.5rem;
    color: var(--text-main);
}

.sop-content ul {
    padding-left: 1.2rem;
    margin: 0.5rem 0;
}

.sop-content li {
    margin-bottom: 0.3rem;
}

.sop-agreement {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 1rem;
    background: rgba(16, 185, 129, 0.05);
    border: 1.5px solid rgba(16, 185, 129, 0.2);
    border-radius: var(--radius-md);
    margin-top: 1rem;
}

.sop-agreement input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #059669;
    flex-shrink: 0;
    margin-top: 2px;
}

.sop-agreement label {
    font-size: 0.85rem;
    color: var(--text-secondary);
    line-height: 1.5;
    cursor: pointer;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    position: sticky;
    bottom: 0;
    background: var(--card-bg);
}

.btn-cancel {
    padding: 10px 24px;
    border: 1.5px solid var(--border);
    background: transparent;
    color: var(--text-main);
    border-radius: var(--radius-sm);
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    border-color: var(--text-main);
}

.btn-submit {
    padding: 10px 24px;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: var(--radius-sm);
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(255,77,109,0.3);
}

.btn-submit:hover {
    background: var(--accent3);
    transform: translateY(-2px);
}

.btn-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Responsive */
@media (max-width: 600px) {
    .calendar-day {
        min-height: 45px;
        font-size: 0.85rem;
    }
    
    .calendar-nav {
        gap: 1rem;
    }
    
    .calendar-month-title {
        font-size: 1.1rem;
        min-width: 150px;
    }
    
    .time-inputs {
        grid-template-columns: 1fr;
    }
    
    .booking-modal {
        max-height: 95vh;
        margin: 1rem;
    }
}
</style>

<main class="room-booking-main">
    <!-- Hero Section -->
    <div class="room-booking-hero">
        <div class="room-booking-hero-content">
            <h1>🏛️ <?= $currentLang === 'en' ? 'Room Booking' : 'Peminjaman Ruang' ?></h1>
            <p><?= $currentLang === 'en' ? 'Reserve reading, discussion, or practical rooms for academic and student activities' : 'Reservasi ruang baca, ruang diskusi, atau ruang praktikum untuk kegiatan akademik dan kemahasiswaan' ?></p>
        </div>
    </div>

    <!-- Calendar Navigation -->
    <div class="calendar-nav">
        <a href="?month=<?=$prevMonth?>&year=<?=$prevYear?>" class="calendar-nav-btn">←</a>
        <div class="calendar-month-title"><?=$displayMonth?></div>
        <a href="?month=<?=$nextMonth?>&year=<?=$nextYear?>" class="calendar-nav-btn">→</a>
    </div>

    <!-- Calendar Grid -->
    <div class="calendar-container">
        <div class="calendar-grid">
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Sun' : 'Min' ?></div>
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Mon' : 'Sen' ?></div>
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Tue' : 'Sel' ?></div>
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Wed' : 'Rab' ?></div>
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Thu' : 'Kam' ?></div>
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Fri' : 'Jum' ?></div>
            <div class="calendar-header"><?= $currentLang === 'en' ? 'Sat' : 'Sab' ?></div>
            
            <?php
            $today = date('Y-m-d');
            $minDate = date('Y-m-d'); // Today or later
            
            // Empty cells before first day
            for ($i = 0; $i < $startingDay; $i++) {
                echo '<div class="calendar-day empty"></div>';
            }
            
            // Calendar days
            for ($day = 1; $day <= $lastDay; $day++) {
                $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $dayOfWeek = date('w', mktime(0, 0, 0, $month, $day, $year));
                $isToday = ($today === $dateStr);
                $isPast = mktime(0, 0, 0, $month, $day, $year) < strtotime('today');
                $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
                
                // Check availability for each room
                $bookedCount = 0;
                $totalRooms = count($rooms);
                foreach ($rooms as $room) {
                    if (isset($availability[$dateStr][$room['id']])) {
                        $bookedCount++;
                    }
                }
                $allBooked = ($bookedCount >= $totalRooms && $totalRooms > 0);
                $someBooked = ($bookedCount > 0 && $bookedCount < $totalRooms);
                $allFree = ($bookedCount === 0);
                
                $class = '';
                $label = '';
                
                if ($isPast) {
                    $class = 'disabled';
                    $label = $currentLang === 'en' ? 'Passed' : 'Lewat';
                } elseif ($isWeekend) {
                    $class = 'disabled';
                    $label = $currentLang === 'en' ? 'Closed' : 'Tutup';
                } elseif ($allBooked) {
                    $class = 'disabled';
                    $label = $currentLang === 'en' ? 'Full' : 'Penuh';
                } elseif ($someBooked) {
                    $class = 'partial';
                    $label = $currentLang === 'en' ? 'Limited' : 'Terbatas';
                } else {
                    $class = 'available';
                    $label = $currentLang === 'en' ? 'Available' : 'Tersedia';
                }
                
                if ($isToday) {
                    $class .= ($class ? ' ' : '') . 'today';
                }
                
                $dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                $indonesianMonths = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                
                $clickable = !$isPast && !$allBooked && !$isWeekend;
                $style = $clickable ? "style='cursor:pointer'" : "style='cursor:not-allowed'";
                $onclickAttr = $clickable ? "onclick=\"openBookingModal('$dateStr')\"" : '';
                
                echo "<div class='calendar-day $class' $onclickAttr data-date='$dateStr'>";
                echo "<span class='day-number'>$day</span>";
                if ($label) {
                    echo "<span class='day-label'>$label</span>";
                }
                echo "</div>";
            }
            ?>
        </div>
        
        <div class="calendar-legend">
            <div class="legend-item">
                <div class="legend-dot available"></div>
                <span><?= $currentLang === 'en' ? 'Available' : 'Tersedia' ?></span>
            </div>
            <div class="legend-item">
                <div class="legend-dot partial"></div>
                <span><?= $currentLang === 'en' ? 'Limited' : 'Terbatas' ?></span>
            </div>
            <div class="legend-item">
                <div class="legend-dot full"></div>
                <span><?= $currentLang === 'en' ? 'Full' : 'Penuh' ?></span>
            </div>
            <div class="legend-item">
                <div class="legend-dot disabled" style="background-color: #e2e8f0;"></div>
                <span><?= $currentLang === 'en' ? 'Closed' : 'Tutup' ?></span>
            </div>
            <div class="legend-item">
                <div class="legend-dot today"></div>
                <span><?= $currentLang === 'en' ? 'Today' : 'Hari Ini' ?></span>
            </div>
        </div>
    </div>

    <!-- Rooms Overview -->
    <div class="sec-header">
        <div class="sec-title">
            <span>🏢</span> <?= $currentLang === 'en' ? 'Rooms List' : 'Daftar Ruangan' ?>
        </div>
    </div>
    
    <div class="rooms-overview">
        <?php foreach ($rooms as $room): ?>
        <div class="room-card">
            <div class="room-card-header">
                <div class="room-icon">🏛️</div>
                <div class="room-info">
                    <h3><?= htmlspecialchars($room['name']) ?></h3>
                    <div class="room-capacity">👥 <?= $currentLang === 'en' ? 'Max' : 'Maksimal' ?> <?= $room['capacity'] ?> <?= $currentLang === 'en' ? 'people' : 'orang' ?></div>
                </div>
            </div>
            <?php
            $descText = $room['description'];
            if ($currentLang === 'en') {
                if (stripos($descText, 'Ruang baca utama') !== false) {
                    $descText = "Main reading room with large capacity, suitable for course practicums, independent or small group study, as well as meetings. Not allowed food and drink (only tumblr) ❌ 🥤";
                } elseif (stripos($descText, 'Ruang diskusi kecil') !== false) {
                    $descText = "Small discussion room for study groups or organizational meetings. Food and Drink area 🧃 🍕";
                }
            }
            
            $facEn = [
                'Meja Panjang' => 'Long Table',
                'Kursi Empuk' => 'Cushioned Chairs',
                'Stopkontak' => 'Power Outlets',
                'Stop kontak' => 'Power Outlets',
                'meja diskusi' => 'Discussion Tables',
                'koleksi buku.' => 'Book Collection.',
                'dan Meja Panjang.' => 'and Long Table.'
            ];
            
            $roomImage = '';
            if (stripos($room['name'], 'Utama') !== false) {
                // Gunakan gambar dari folder assets/ruang jika ada, jika belum gunakan placeholder sementara
                $roomImage = file_exists('assets/ruang/img_ruang_utama.jpeg') ? 'assets/ruang/img_ruang_utama.jpeg' : 'https://images.unsplash.com/photo-1568667256549-094345857637?auto=format&fit=crop&q=80&w=800';
            } elseif (stripos($room['name'], 'Hidden') !== false) {
                $roomImage = file_exists('assets/ruang/img_hidden_room.png') ? 'assets/ruang/img_hidden_room.png' : 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=800';
            }
            ?>
            <?php if($roomImage): ?>
                <div style="width: 100%; aspect-ratio: 16/9; margin-bottom: 1.5rem; border-radius: var(--radius-md); overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); background: var(--bg-secondary);">
                    <img src="<?= $roomImage ?>" alt="<?= htmlspecialchars($room['name']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.4s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </div>
            <?php endif; ?>
            <p style="font-size: 0.85rem; color: var(--muted); line-height: 1.5;"><?= htmlspecialchars($descText) ?></p>
            <div class="room-facilities">
                <?php 
                $facilities = explode(',', $room['facilities']);
                foreach ($facilities as $f): 
                    $fName = trim($f);
                    if ($currentLang === 'en') {
                        foreach ($facEn as $id => $en) {
                            if (strcasecmp($fName, $id) === 0) {
                                $fName = $en;
                                break;
                            }
                        }
                    }
                ?>
                    <span class="facility-tag"><?= htmlspecialchars($fName) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Booking Modal -->
<div class="booking-modal-overlay" id="bookingModal">
    <div class="booking-modal">
        <div class="modal-header">
            <h2>📅 <?= $currentLang === 'en' ? 'Room Booking Form' : 'Form Peminjaman Ruang' ?></h2>
            <button class="modal-close" onclick="closeBookingModal()">✕</button>
        </div>
        
        <form id="bookingForm" action="api/submit_booking.php" method="POST">
            <div class="modal-body">
                <div class="booking-date-display" style="margin-bottom: 1rem;">
                    <div class="date-label"><?= $currentLang === 'en' ? 'Selected Date' : 'Tanggal Dipilih' ?></div>
                    <div class="date-value" id="selectedDateDisplay">-</div>
                </div>
                
                <input type="hidden" name="booking_date" id="bookingDate">
                
                <!-- PAGE 1: SOP -->
                <div id="modalPage1" class="modal-page active-page">
                    <div class="form-section">
                        <div class="sop-section" style="margin-top: 0;">
                            <div class="sop-header">
                                <div class="sop-title">📋 <?= $currentLang === 'en' ? 'Room Booking SOP' : 'SOP Peminjaman Tempat' ?></div>
                                <a href="assets/SOP_Peminjaman_Tempat.pdf" class="sop-download" download target="_blank">
                                    ⬇️ <?= $currentLang === 'en' ? 'Download SOP' : 'Unduh SOP' ?>
                                </a>
                            </div>
                            <div class="sop-content">
                                <h4><?= $currentLang === 'en' ? 'A. Academic & Practicum Activities' : 'A. Kegiatan Akademik & Praktikum' ?></h4>
                                <ul>
                                    <li><?= $currentLang === 'en' ? 'Activities include practicums, make-up classes, or oral exams.' : 'Kegiatan yang dimaksud meliputi kegiatan praktikum, kuliah pengganti, atau ujian lisan.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Submission is done by Lab Assistants by contacting the reading room manager at least <strong>D-3</strong> before the event day.' : 'Pengajuan dilakukan oleh Aslab dengan menghubungi pengelola ruang baca maksimal <strong>H-3</strong> sebelum hari pelaksanaan.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Room booking requests are made by stating the purpose, date, and duration of the booking.' : 'Pengajuan peminjaman ruangan dilakukan dengan menyampaikan tujuan kegiatan, tanggal, dan durasi peminjaman.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'The maximum booking duration is <strong>4 hours</strong> or extended as needed and agreed.' : 'Batas waktu durasi peminjaman ialah maksimal <strong>4 jam</strong> atau diperpanjang sesuai kebutuhan dan kesepakatan.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Reading room booking for academic & practicum activities is a <strong>top priority</strong>.' : 'Peminjaman ruang baca untuk kegiatan akademik & praktikum bersifat <strong>prioritas utama</strong>.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Damage to facilities caused by borrower\'s negligence is entirely the borrower\'s responsibility.' : 'Kerusakan fasilitas yang disebabkan oleh kelalaian peminjam menjadi tanggung jawab sepenuhnya pihak peminjam.' ?></li>
                                </ul>
                                
                                <h4><?= $currentLang === 'en' ? 'B. Student Activities' : 'B. Kegiatan Kemahasiswaan' ?></h4>
                                <ul>
                                    <li><?= $currentLang === 'en' ? 'Activities include organizational meetings, organizational work programs, or other discussion activities.' : 'Kegiatan yang dimaksud meliputi rapat organisasi, program kerja organisasi, atau kegiatan diskusi lainnya.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Submission is done by Users by contacting the reading room manager at least <strong>D-3</strong> before the event day.' : 'Pengajuan dilakukan oleh Pengguna dengan menghubungi pengelola ruang baca maksimal <strong>H-3</strong> sebelum hari pelaksanaan.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Room booking requests are made by stating the user\'s identity, purpose, date, and duration of the booking.' : 'Pengajuan peminjaman ruangan dilakukan dengan menyampaikan identitas pengguna, tujuan kegiatan, tanggal, dan durasi peminjaman.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'The maximum booking duration is <strong>4 hours</strong>.' : 'Batas waktu durasi peminjaman maksimal <strong>4 jam</strong>.' ?></li>
                                    <li><?= $currentLang === 'en' ? 'Damage to facilities caused by borrower\'s negligence is entirely the user\'s responsibility.' : 'Kerusakan fasilitas yang disebabkan oleh kelalaian peminjam menjadi tanggung jawab sepenuhnya pihak pengguna.' ?></li>
                                </ul>
                            </div>
                            
                            <div class="sop-agreement">
                                <input type="checkbox" id="sopAgree" name="sop_agree" required>
                                <label for="sopAgree">
                                    <?= $currentLang === 'en' ? 'I have read, understood, and agreed to comply with all provisions in the Room Booking SOP. I am willing to take full responsibility for any damage to facilities caused by negligence during the booking.' : 'Saya telah membaca, memahami, dan menyetujui untuk mematuhi seluruh ketentuan dalam SOP Peminjaman Tempat. Saya bersedia bertanggung jawab penuh atas segala kerusakan fasilitas yang disebabkan oleh kelalaian selama peminjaman.' ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="closeBookingModal()"><?= $currentLang === 'en' ? 'Cancel' : 'Batal' ?></button>
                        <button type="button" class="btn-submit" id="btnNext1" disabled onclick="goToPage(2)"><?= $currentLang === 'en' ? 'Next' : 'Selanjutnya' ?></button>
                    </div>
                </div>

                <!-- PAGE 2: Category Selection -->
                <div id="modalPage2" class="modal-page">
                    <div class="form-section">
                        <div class="form-section-title">
                            <span>🏷️</span> <?= $currentLang === 'en' ? 'Select Activity Category' : 'Pilih Kategori Kegiatan' ?>
                        </div>
                        <div class="category-options">
                            <label class="category-option">
                                <input type="radio" name="activity_category" value="Akademik & Praktikum" required onchange="validatePage2()">
                                <div class="category-icon">🎓</div>
                                <div class="category-details">
                                    <div class="category-name"><?= $currentLang === 'en' ? 'Academic & Practicum' : 'Kegiatan Akademik & Praktikum' ?></div>
                                    <div class="category-desc"><?= $currentLang === 'en' ? 'Practicum, make-up classes, oral exams (Top Priority)' : 'Praktikum, kuliah pengganti, ujian lisan (Prioritas Utama)' ?></div>
                                </div>
                            </label>
                            <label class="category-option">
                                <input type="radio" name="activity_category" value="Kemahasiswaan" required onchange="validatePage2()">
                                <div class="category-icon">👥</div>
                                <div class="category-details">
                                    <div class="category-name"><?= $currentLang === 'en' ? 'Student Activities' : 'Kegiatan Kemahasiswaan' ?></div>
                                    <div class="category-desc"><?= $currentLang === 'en' ? 'Organizational meetings, programs, other discussions' : 'Rapat organisasi, proker, diskusi lainnya' ?></div>
                                </div>
                            </label>
                        </div>
                        <!-- Category Workflow Display -->
                    <div id="workflowDisplay" class="workflow-display" style="display:none; margin-top: 1.5rem; padding: 1.5rem; background: var(--bg-secondary); border-radius: var(--radius-md); border-left: 4px solid var(--accent);">
                        <h4 style="margin-top: 0; color: var(--accent);"><?= $currentLang === 'en' ? 'Booking Workflow by Category' : 'Alur Peminjaman Sesuai Kategori' ?></h4>
                        
                        <div id="workflowAkademik" style="display:none; text-align: center;">
                            <div style="text-align: right; margin-bottom: 10px;">
                                <button type="button" class="btn-zoom" onclick="zoomImage('imgAkademik', 20)">🔍 +</button>
                                <button type="button" class="btn-zoom" onclick="zoomImage('imgAkademik', -20)">🔍 -</button>
                                <button type="button" class="btn-zoom" onclick="resetZoom('imgAkademik')">Reset</button>
                            </div>
                            <div style="overflow: auto; max-height: 400px; border: 1px solid var(--border); border-radius: var(--radius-md);">
                                <img id="imgAkademik" src="assets/workflow_akademik.png" alt="Workflow Peminjaman Ruang Baca untuk Kegiatan Akademik & Praktikum" style="width: 100%; max-width: 100%; height: auto; transition: width 0.3s ease;">
                            </div>
                        </div>
                        
                        <div id="workflowKemahasiswaan" style="display:none; text-align: center;">
                            <div style="text-align: right; margin-bottom: 10px;">
                                <button type="button" class="btn-zoom" onclick="zoomImage('imgKemahasiswaan', 20)">🔍 +</button>
                                <button type="button" class="btn-zoom" onclick="zoomImage('imgKemahasiswaan', -20)">🔍 -</button>
                                <button type="button" class="btn-zoom" onclick="resetZoom('imgKemahasiswaan')">Reset</button>
                            </div>
                            <div style="overflow: auto; max-height: 400px; border: 1px solid var(--border); border-radius: var(--radius-md);">
                                <img id="imgKemahasiswaan" src="assets/workflow_kemahasiswaan.png" alt="Workflow Peminjaman Ruang Baca untuk Kegiatan Kemahasiswaan" style="width: 100%; max-width: 100%; height: auto; transition: width 0.3s ease;">
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="goToPage(1)"><?= $currentLang === 'en' ? 'Back' : 'Kembali' ?></button>
                        <button type="button" class="btn-submit" id="btnNext2" disabled onclick="goToPage(3)"><?= $currentLang === 'en' ? 'Next' : 'Selanjutnya' ?></button>
                    </div>
                </div>

                <!-- PAGE 3: Detail Form -->
                <div id="modalPage3" class="modal-page">
                    <!-- Room Selection -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span>🏛️</span> <?= $currentLang === 'en' ? 'Select Room' : 'Pilih Ruangan' ?>
                        </div>
                        <div class="room-options" id="roomOptions">
                            <?php foreach ($rooms as $idx => $room): 
                                $roomIcons = ['📚', '💬'];
                                $icon = $roomIcons[$idx] ?? '🏛️';
                            ?>
                            <label class="room-option" data-room-id="<?= $room['id'] ?>" data-capacity="<?= $room['capacity'] ?>">
                                <input type="radio" name="room_id" value="<?= $room['id'] ?>" required>
                                <div class="room-option-icon"><?= $icon ?></div>
                                <div class="room-option-details">
                                    <div class="room-option-name"><?= htmlspecialchars($room['name']) ?></div>
                                    <div class="room-option-capacity">👥 <?= $currentLang === 'en' ? 'Capacity:' : 'Kapasitas:' ?> <?= $room['capacity'] ?> <?= $currentLang === 'en' ? 'people' : 'orang' ?></div>
                                    <div class="room-option-facilities">
                                        <?php 
                                        $facs = array_filter(array_map('trim', explode(',', $room['facilities'])));
                                        foreach ($facs as $f): ?>
                                            <span class="room-fac-tag"><?= htmlspecialchars($f) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="room-option-right">
                                    <span class="room-option-status status-available">✓ <?= $currentLang === 'en' ? 'Available' : 'Tersedia' ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Time Selection -->
                    <div class="form-section" id="timeSection">
                        <div class="form-section-title">
                            <span>🕐</span> <?= $currentLang === 'en' ? 'Booking Time' : 'Waktu Peminjaman' ?>
                        </div>
                        <div class="time-inputs">
                            <div>
                                <label class="form-label"><?= $currentLang === 'en' ? 'Start Time' : 'Jam Mulai' ?></label>
                                <select name="start_time" class="form-select" id="startTimeSelect" required>
                                    <option value="08:00">08:00</option>
                                    <option value="09:00">09:00</option>
                                    <option value="10:00">10:00</option>
                                    <option value="11:00">11:00</option>
                                    <option value="12:00">12:00</option>
                                    <option value="13:00">13:00</option>
                                    <option value="14:00">14:00</option>
                                    <option value="15:00">15:00</option>
                                    <option value="16:00">16:00</option>
                                    <option value="17:00">17:00</option>
                                    <option value="18:00">18:00</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label"><?= $currentLang === 'en' ? 'Duration' : 'Durasi' ?></label>
                                <select name="duration" class="form-select" id="durationSelect" required>
                                    <option value="1">1 <?= $currentLang === 'en' ? 'Hour' : 'Jam' ?></option>
                                    <option value="2" selected>2 <?= $currentLang === 'en' ? 'Hours' : 'Jam' ?></option>
                                    <option value="3">3 <?= $currentLang === 'en' ? 'Hours' : 'Jam' ?></option>
                                    <option value="4">4 <?= $currentLang === 'en' ? 'Hours (Max)' : 'Jam (Maks)' ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label"><?= $currentLang === 'en' ? 'End Time' : 'Jam Selesai' ?></label>
                                <input type="time" name="end_time" class="form-input" id="endTime" readonly>
                            </div>
                        </div>
                        <div class="time-summary" id="timeSummary"></div>
                    </div>
                    
                    <!-- Detail Kegiatan -->
                    <div class="form-section" id="detailSection">
                        <div class="form-section-title">
                            <span>📝</span> <?= $currentLang === 'en' ? 'Activity Details' : 'Detail Kegiatan' ?>
                        </div>
                        <div class="form-group-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label"><?= $currentLang === 'en' ? 'Person in Charge *' : 'Nama Penanggung Jawab *' ?></label>
                                <input type="text" name="pic_name" class="form-input" placeholder="<?= $currentLang === 'en' ? 'Full Name' : 'Nama Lengkap' ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><?= $currentLang === 'en' ? 'Student/Staff ID *' : 'NIM atau NIP *' ?></label>
                                <input type="text" name="pic_id" class="form-input" placeholder="NIM / NIP" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Purpose of Activity *' : 'Perihal/Tujuan Kegiatan *' ?></label>
                            <input type="text" name="purpose" class="form-input" placeholder="<?= $currentLang === 'en' ? 'E.g.: Organizational Meeting, Group Discussion' : 'Contoh: Rapat Organisasi, Diskusi Kelompok' ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Activity Briefing' : 'Briefing Kegiatan' ?></label>
                            <textarea name="briefing" class="form-textarea" placeholder="<?= $currentLang === 'en' ? 'Explain the details of the activity...' : 'Jelaskan detail kegiatan yang akan dilakukan...' ?>"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= $currentLang === 'en' ? 'Number of Participants *' : 'Jumlah Peserta *' ?></label>
                            <div class="participant-input">
                                <input type="number" name="participants" class="form-input" id="participants" min="1" max="1000" value="1" required oninput="validateCapacity()">
                                <span class="participant-max" id="maxParticipants"><?= $currentLang === 'en' ? 'Max: select room' : 'Maksimal: pilih ruangan' ?></span>
                            </div>
                            <div id="capacityWarning" class="capacity-warning" style="display:none; color: red; font-style: italic; margin-top: 5px; font-size: 0.85rem;"></div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="goToPage(2)"><?= $currentLang === 'en' ? 'Back' : 'Kembali' ?></button>
                        <button type="submit" class="btn-submit" id="submitBtn" disabled><?= $currentLang === 'en' ? 'Submit Booking' : 'Ajukan Peminjaman' ?></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
/* Modal Pagination */
.modal-page {
    display: none;
    animation: fadeIn 0.3s ease;
}

.modal-page.active-page {
    display: block;
}

/* Category Selection */
.category-options {
    display: grid;
    gap: 1rem;
    grid-template-columns: 1fr;
}

@media (min-width: 600px) {
    .category-options {
        grid-template-columns: 1fr 1fr;
    }
}

.category-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 10px;
    padding: 1.5rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--bg-secondary);
}

.category-option input[type="radio"] {
    display: none;
}

.category-option:hover {
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,77,109,0.1);
}

.category-option input[type="radio"]:checked + .category-icon + .category-details {
    color: var(--accent);
}

.category-option.selected {
    border-color: var(--accent);
    background: rgba(255,77,109,0.05);
}

.category-icon {
    font-size: 2.5rem;
}

.category-name {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-main);
}

.category-desc {
    font-size: 0.85rem;
    color: var(--muted);
    margin-top: 5px;
}

/* Zoom Controls */
.btn-zoom {
    background: var(--bg-primary);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 5px 10px;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-main);
    transition: all 0.2s;
    margin-left: 5px;
}

.btn-zoom:hover {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}
/* Enhanced room option cards */
.room-option {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 1.2rem;
    border: 2px solid var(--border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.3s ease;
    background: var(--bg-secondary);
    position: relative;
    overflow: hidden;
}

.room-option::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,77,109,0.05), rgba(118,75,162,0.05));
    opacity: 0;
    transition: opacity 0.3s;
}

.room-option:hover:not(.room-booked)::before {
    opacity: 1;
}

.room-option:hover:not(.room-booked) {
    border-color: var(--accent);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255,77,109,0.15);
}

.room-option.selected {
    border-color: var(--accent);
    background: rgba(255,77,109,0.06);
    box-shadow: 0 0 0 3px rgba(255,77,109,0.12);
}

.room-option.selected::before { opacity: 1; }

/* Booked/disabled state */
.room-option.room-booked {
    opacity: 1;
    cursor: not-allowed;
    pointer-events: none;
    background: var(--bg-tertiary, #f0f0f0);
    border-color: transparent;
}

.room-option.room-booked .room-option-icon,
.room-option.room-booked .room-option-name,
.room-option.room-booked .room-option-capacity {
    opacity: 0.4;
    filter: grayscale(100%);
}

.room-option.room-booked .room-option-facilities { opacity: 0.3; }

.room-option input[type="radio"] { display: none; }

.room-option-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(255,77,109,0.1), rgba(118,75,162,0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
    transition: all 0.3s;
}

.room-option.selected .room-option-icon {
    background: linear-gradient(135deg, var(--accent), #764BA2);
    box-shadow: 0 4px 12px rgba(255,77,109,0.3);
}

.room-option-details { flex: 1; min-width: 0; }

.room-option-name {
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 2px;
    color: var(--text-main);
}

.room-option-capacity {
    font-size: 0.8rem;
    color: var(--muted);
    margin-bottom: 8px;
}

.room-option-facilities {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.room-fac-tag {
    font-size: 0.65rem;
    padding: 2px 7px;
    background: rgba(255,77,109,0.08);
    border-radius: 99px;
    color: var(--accent);
    font-weight: 600;
    letter-spacing: 0.2px;
}

.room-option.selected .room-fac-tag {
    background: rgba(255,77,109,0.15);
}

.room-option-right {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}

.room-option-status {
    font-size: 0.75rem;
    font-weight: 700;
    padding: 5px 12px;
    border-radius: 99px;
    white-space: nowrap;
}

.status-available {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}

.status-booked {
    background: rgba(156, 163, 175, 0.2);
    color: #9CA3AF;
}

/* Selection checkmark */
.room-option .room-check {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: transparent;
    transition: all 0.25s;
    flex-shrink: 0;
}

.room-option.selected .room-check {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
    box-shadow: 0 2px 8px rgba(255,77,109,0.3);
}

/* Step hidden/shown animation */
.booking-step-hidden {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    margin: 0;
    padding: 0;
    transition: max-height 0.4s ease, opacity 0.3s ease, margin 0.3s ease;
}

.booking-step-visible {
    max-height: 800px;
    opacity: 1;
    margin-bottom: 1.5rem;
    overflow: visible;
}

/* Time summary */
.time-summary {
    margin-top: 10px;
    padding: 10px 14px;
    background: rgba(16,185,129,0.06);
    border: 1px solid rgba(16,185,129,0.15);
    border-radius: var(--radius-sm);
    font-size: 0.82rem;
    color: #059669;
    font-weight: 600;
    display: none;
}

.time-summary.active { display: block; }

/* Dark mode overrides */
[data-theme="dark"] .room-option.room-booked {
    background: rgba(255,255,255,0.03);
}
[data-theme="dark"] .status-booked {
    background: rgba(107,114,128,0.2);
    color: #6B7280;
}
[data-theme="dark"] .room-fac-tag {
    background: rgba(255,77,109,0.12);
}
</style>

<script>
const ROOMS_DATA = <?= json_encode($rooms) ?>;
const AVAILABILITY_DATA = <?= json_encode($availability) ?>;

const IS_LOGGED_IN = <?= isLoggedIn() ? 'true' : 'false' ?>;

// Open booking modal
function openBookingModal(dateStr) {
    if (!IS_LOGGED_IN) {
        if (confirm('Anda harus login terlebih dahulu.\nPergi ke halaman login?')) {
            window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
        }
        return;
    }
    
    const modal = document.getElementById('bookingModal');
    const dateDisplay = document.getElementById('selectedDateDisplay');
    const bookingDate = document.getElementById('bookingDate');
    
    // Format date to Indonesian
    const date = new Date(dateStr + 'T00:00:00');
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const formattedDate = date.toLocaleDateString('<?= $currentLang === 'en' ? 'en-GB' : 'id-ID' ?>', options);
    
    dateDisplay.textContent = formattedDate;
    bookingDate.value = dateStr;
    
    // Reset form & set to page 1
    document.getElementById('bookingForm').reset();
    document.querySelectorAll('.room-option').forEach(o => o.classList.remove('selected'));
    document.querySelectorAll('.category-option').forEach(o => o.classList.remove('selected'));
    document.getElementById('capacityWarning').style.display = 'none';
    
    goToPage(1);
    
    // Refresh room availability for selected date
    refreshRoomAvailability(dateStr);
    
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close booking modal
function closeBookingModal() {
    const modal = document.getElementById('bookingModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('bookingForm').reset();
}

// Modal Pagination
function goToPage(pageNum) {
    document.querySelectorAll('.modal-page').forEach(page => {
        page.classList.remove('active-page');
    });
    const targetPage = document.getElementById('modalPage' + pageNum);
    if (targetPage) {
        targetPage.classList.add('active-page');
    }
    
    if (pageNum === 1) {
        validatePage1();
    } else if (pageNum === 2) {
        validatePage2();
    } else if (pageNum === 3) {
        validatePage3();
        calculateEndTime();
    }
}

// Image Zoom Controls
const zoomLevels = {};

function zoomImage(imgId, change) {
    const img = document.getElementById(imgId);
    if (!zoomLevels[imgId]) zoomLevels[imgId] = 100;
    
    zoomLevels[imgId] += change;
    
    // Limits: min 50%, max 300%
    if (zoomLevels[imgId] < 50) zoomLevels[imgId] = 50;
    if (zoomLevels[imgId] > 300) zoomLevels[imgId] = 300;
    
    img.style.maxWidth = 'none';
    img.style.width = zoomLevels[imgId] + '%';
}

function resetZoom(imgId) {
    const img = document.getElementById(imgId);
    zoomLevels[imgId] = 100;
    img.style.maxWidth = '100%';
    img.style.width = '100%';
}

// Validations
function validatePage1() {
    const agreed = document.getElementById('sopAgree').checked;
    document.getElementById('btnNext1').disabled = !agreed;
}

document.getElementById('sopAgree').addEventListener('change', validatePage1);

function validatePage2() {
    const selected = document.querySelector('input[name="activity_category"]:checked');
    document.getElementById('btnNext2').disabled = !selected;
    
    // UI feedback
    document.querySelectorAll('.category-option').forEach(opt => {
        opt.classList.remove('selected');
    });
    
    const workflowDisplay = document.getElementById('workflowDisplay');
    const workflowAkademik = document.getElementById('workflowAkademik');
    const workflowKemahasiswaan = document.getElementById('workflowKemahasiswaan');
    
    if (selected) {
        selected.closest('.category-option').classList.add('selected');
        
        workflowDisplay.style.display = 'block';
        if (selected.value === 'Akademik & Praktikum') {
            workflowAkademik.style.display = 'block';
            workflowKemahasiswaan.style.display = 'none';
        } else {
            workflowAkademik.style.display = 'none';
            workflowKemahasiswaan.style.display = 'block';
        }
    } else {
        workflowDisplay.style.display = 'none';
    }
}

function validateCapacity() {
    const selectedRoom = document.querySelector('input[name="room_id"]:checked');
    const participantsInput = document.getElementById('participants');
    const warningEl = document.getElementById('capacityWarning');
    const submitBtn = document.getElementById('submitBtn');
    
    if (!selectedRoom) return false;
    
    const capacity = parseInt(selectedRoom.closest('.room-option').dataset.capacity);
    const participants = parseInt(participantsInput.value);
    
    if (participants > capacity) {
        warningEl.textContent = `Maksimal ruangan untuk ${capacity} orang. Sarankan untuk memilih ruangan lainnya yang sesuai dengan kapasitas peserta kegiatan.`;
        warningEl.style.display = 'block';
        submitBtn.disabled = true;
        return false;
    } else {
        warningEl.style.display = 'none';
        return true;
    }
}

function validatePage3() {
    const roomSelected = document.querySelector('input[name="room_id"]:checked');
    const isCapacityValid = validateCapacity();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = !(roomSelected && isCapacityValid);
}

// Room option selection
document.querySelectorAll('.room-option').forEach(option => {
    option.addEventListener('click', function() {
        if (this.classList.contains('room-booked')) return;
        
        // Deselect all
        document.querySelectorAll('.room-option').forEach(o => o.classList.remove('selected'));
        // Select this
        this.classList.add('selected');
        this.querySelector('input[type="radio"]').checked = true;
        
        // Update max participants
        const capacity = parseInt(this.dataset.capacity);
        // We do NOT set max attribute, so user can type > capacity to trigger the warning
        // document.getElementById('participants').max = capacity; 
        
        // Don't auto-reset value to 1 if they already typed something
        if (!document.getElementById('participants').value) {
            document.getElementById('participants').value = 1;
        }
        
        document.getElementById('maxParticipants').textContent = 'Maksimal: ' + capacity + ' orang';
        
        validatePage3();
    });
});
function calculateEndTime() {
    const startSel = document.getElementById('startTimeSelect');
    const durSel = document.getElementById('durationSelect');
    if (!startSel || !durSel) return;
    
    const startTime = startSel.value;
    const duration = parseInt(durSel.value);
    
    if (startTime) {
        const [hours, minutes] = startTime.split(':').map(Number);
        let endHour = hours + duration;
        if (endHour > 21) endHour = 21; // Cap at operating hours
        const endHours = String(endHour).padStart(2, '0');
        const endMinutes = String(minutes).padStart(2, '0');
        document.getElementById('endTime').value = `${endHours}:${endMinutes}`;
        
        // Show time summary
        const summary = document.getElementById('timeSummary');
        summary.textContent = `⏰ ${startTime} — ${endHours}:${endMinutes} WIB (${duration} jam)`;
        summary.classList.add('active');
    }
}

document.getElementById('startTimeSelect').addEventListener('change', calculateEndTime);
document.getElementById('durationSelect').addEventListener('change', calculateEndTime);

// Participants input validation
document.getElementById('participants').addEventListener('input', validatePage3);

// Refresh room availability for a specific date
function refreshRoomAvailability(dateStr) {
    document.querySelectorAll('.room-option').forEach(option => {
        const roomId = option.dataset.roomId;
        const isBooked = AVAILABILITY_DATA[dateStr] && AVAILABILITY_DATA[dateStr][roomId];
        
        const statusEl = option.querySelector('.room-option-status');
        const radioEl = option.querySelector('input[type="radio"]');
        
        if (isBooked) {
            option.classList.add('room-booked');
            option.classList.remove('selected');
            radioEl.disabled = true;
            radioEl.checked = false;
            statusEl.className = 'room-option-status status-booked';
            statusEl.textContent = '✕ <?= $currentLang === "en" ? "Already Booked" : "Sudah Dibooking" ?>';
        } else {
            option.classList.remove('room-booked');
            radioEl.disabled = false;
            statusEl.className = 'room-option-status status-available';
            statusEl.textContent = '✓ <?= $currentLang === "en" ? "Available" : "Tersedia" ?>';
        }
    });
}

// Form submission
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = document.getElementById('submitBtn');
    
    submitBtn.textContent = 'Mengirim...';
    submitBtn.disabled = true;
    
    fetch('api/submit_booking.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success & reload to refresh calendar
            alert('✅ Peminjaman berhasil diajukan!\nStatus: Menunggu persetujuan admin.');
            window.location.reload();
        } else {
            alert('❌ ' + data.message);
            submitBtn.textContent = 'Ajukan Peminjaman';
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        alert('❌ Terjadi kesalahan. Silakan coba lagi.');
        console.error(error);
        submitBtn.textContent = 'Ajukan Peminjaman';
        submitBtn.disabled = false;
    });
});

// Escape key to close modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeBookingModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>