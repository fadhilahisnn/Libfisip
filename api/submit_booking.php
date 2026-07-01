<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Silakan login terlebih dahulu untuk melakukan peminjaman.'
    ]);
    exit;
}

// Check if method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Metode request tidak valid.'
    ]);
    exit;
}

// Get and sanitize inputs
$userId = $_SESSION['user_id'];
$roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
$bookingDate = isset($_POST['booking_date']) ? $_POST['booking_date'] : '';
$startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
$duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
$endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';
$activityCategory = isset($_POST['activity_category']) ? trim($_POST['activity_category']) : '';
$picName = isset($_POST['pic_name']) ? trim($_POST['pic_name']) : '';
$picId = isset($_POST['pic_id']) ? trim($_POST['pic_id']) : '';
$purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
$briefing = isset($_POST['briefing']) ? trim($_POST['briefing']) : '';
$participants = isset($_POST['participants']) ? (int)$_POST['participants'] : 0;
$sopAgree = isset($_POST['sop_agree']) ? true : false;

// Validation
$errors = [];

if (empty($roomId)) {
    $errors[] = 'Silakan pilih ruangan.';
}

if (empty($bookingDate)) {
    $errors[] = 'Tanggal peminjaman harus diisi.';
}

if (empty($startTime)) {
    $errors[] = 'Jam mulai harus diisi.';
}

if (empty($endTime)) {
    $errors[] = 'Jam selesai harus diisi.';
}

if ($duration < 1 || $duration > 4) {
    $errors[] = 'Durasi harus antara 1-4 jam.';
}

if (empty($activityCategory)) {
    $errors[] = 'Kategori kegiatan harus dipilih.';
}

if (empty($picName)) {
    $errors[] = 'Nama Penanggung Jawab harus diisi.';
}

if (empty($picId)) {
    $errors[] = 'NIM atau NIP harus diisi.';
}

if (empty($purpose)) {
    $errors[] = 'Perihal/tujuan kegiatan harus diisi.';
}

if ($participants < 1) {
    $errors[] = 'Jumlah peserta minimal 1 orang.';
}

if (!$sopAgree) {
    $errors[] = 'Anda harus menyetujui SOP untuk melanjutkan.';
}

// Normalize booking date to Y-m-d format for MySQL
$bookingTimestamp = strtotime($bookingDate);
if ($bookingTimestamp === false) {
    $errors[] = 'Format tanggal peminjaman tidak valid.';
} else {
    $bookingDate = date('Y-m-d', $bookingTimestamp);
    
    if ($bookingTimestamp < strtotime('today')) {
        $errors[] = 'Tanggal peminjaman tidak boleh di masa lalu.';
    }
    
    // Check H-3 rule (booking must be made at least 3 days before)
    $todayMidnight = strtotime('today');
    $daysUntilBooking = ($bookingTimestamp - $todayMidnight) / 86400;
    if ($daysUntilBooking < 3) {
        $errors[] = 'Peminjaman harus diajukan minimal H-3 (3 hari) sebelum tanggal pelaksanaan sesuai SOP.';
    }
}

// Validate room exists and get capacity
$db = getDB();
$stmt = $db->prepare("SELECT * FROM rooms WHERE id = ? AND is_active = TRUE");
$stmt->execute([$roomId]);
$room = $stmt->fetch();

if (!$room) {
    $errors[] = 'Ruangan tidak tersedia.';
} elseif ($participants > $room['capacity']) {
    $errors[] = "Jumlah peserta melebihi kapasitas ruangan (maksimal {$room['capacity']} orang).";
}

// Check for conflicting bookings
if (!empty($roomId) && !empty($bookingDate)) {
    $stmt = $db->prepare("
        SELECT * FROM room_bookings 
        WHERE room_id = ? 
        AND booking_date = ? 
        AND status IN ('pending', 'approved')
        AND (
            (start_time <= ? AND end_time > ?) OR
            (start_time < ? AND end_time >= ?) OR
            (start_time >= ? AND end_time <= ?)
        )
    ");
    $stmt->execute([
        $roomId, 
        $bookingDate,
        $startTime, $startTime,
        $endTime, $endTime,
        $startTime, $endTime
    ]);
    $conflict = $stmt->fetch();
    
    if ($conflict) {
        $errors[] = 'Ruangan sudah dibooking pada waktu tersebut. Silakan pilih waktu atau ruangan lain.';
    }
}

// Check operating hours (08:00 - 21:00)
$startHour = (int)substr($startTime, 0, 2);
$endHour = (int)substr($endTime, 0, 2);

if ($startHour < 8 || $startHour > 21) {
    $errors[] = 'Jam operasional ruangan adalah 08:00 - 21:00.';
}

if ($endHour < 8 || $endHour > 21) {
    $errors[] = 'Jam operasional ruangan adalah 08:00 - 21:00.';
}

// Return errors if any
if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode("\n", $errors)
    ]);
    exit;
}

// Insert booking
try {
    $stmt = $db->prepare("
        INSERT INTO room_bookings 
        (user_id, room_id, activity_category, pic_name, pic_id, booking_date, start_time, end_time, duration_hours, purpose, briefing, participant_count, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    
    $result = $stmt->execute([
        $userId,
        $roomId,
        $activityCategory,
        $picName,
        $picId,
        $bookingDate,
        $startTime,
        $endTime,
        $duration,
        $purpose,
        $briefing,
        $participants
    ]);
    
    if ($result) {
        $bookingId = $db->lastInsertId();
        
        // Get user info for response
        $user = currentUser();
        
        echo json_encode([
            'success' => true,
            'message' => 'Peminjaman berhasil diajukan dan menunggu persetujuan admin.',
            'booking_id' => $bookingId,
            'status' => 'Pending',
            'booking' => [
                'id' => $bookingId,
                'room_name' => $room['name'],
                'date' => date('d F Y', strtotime($bookingDate)),
                'time' => $startTime . ' - ' . $endTime . ' WIB',
                'duration' => $duration . ' jam',
                'purpose' => $purpose,
                'participants' => $participants
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Terjadi kesalahan saat menyimpan peminjaman. Silakan coba lagi.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem. Silakan hubungi administrator.'
    ]);
}