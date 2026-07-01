<?php
/**
 * AJAX endpoint: returns JSON with full book detail, reviews, and antrian
 */
session_start();
require_once 'config.php';
require_once 'includes/cover_helper.php';

require_once 'includes/lang.php';
header('Content-Type: application/json; charset=utf-8');

$bid = (int)($_GET['id'] ?? 0);
if (!$bid) { echo json_encode(['error' => 'No book ID']); exit; }

$db  = getDB();
$uid = $_SESSION['user_id'] ?? null;

// Book
$stmt = $db->prepare("SELECT b.*,
    COALESCE(AVG(r.rating),0) as avg_rating,
    COUNT(DISTINCT r.id) as total_reviews,
    (SELECT COUNT(*) FROM circulation c2 WHERE c2.book_id=b.id AND c2.status='kembali') as total_pinjam
    FROM books b
    LEFT JOIN reviews r ON b.id = r.book_id
    LEFT JOIN circulation c ON b.id = c.book_id
    WHERE b.id = ?
    GROUP BY b.id");
$stmt->execute([$bid]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$book) { echo json_encode(['error' => 'Book not found']); exit; }

$book['kategori'] = t_cat($book['kategori']);

// Cover src
$book['cover_src'] = bookCoverSrc($book);

// Reviews (latest 10)
$rvStmt = $db->prepare("SELECT r.*, u.nama, u.prodi,
    (SELECT COUNT(*) FROM review_likes l WHERE l.review_id=r.id) as total_likes
    FROM reviews r JOIN users u ON r.user_id=u.id
    WHERE r.book_id=? ORDER BY total_likes DESC, r.created_at DESC LIMIT 10");
$rvStmt->execute([$bid]);
$reviews = $rvStmt->fetchAll(PDO::FETCH_ASSOC);

// Antrian count
$antriStmt = $db->prepare("SELECT COUNT(*) FROM antrian WHERE book_id=? AND status='menunggu'");
$antriStmt->execute([$bid]);
$antriCount = (int)$antriStmt->fetchColumn();

// Active borrowers count
$borrowStmt = $db->prepare("SELECT COUNT(*) FROM circulation WHERE book_id=? AND status IN('dipinjam','terlambat')");
$borrowStmt->execute([$bid]);
$activeBorrowers = (int)$borrowStmt->fetchColumn();

// Is user in shelf?
$inShelf = false;
$inAntri = false;
if ($uid) {
    $shelfCheck = $db->prepare("SELECT id FROM shelves WHERE user_id=? AND book_id=?");
    $shelfCheck->execute([$uid, $bid]);
    $inShelf = (bool)$shelfCheck->fetch();

    $antriCheck = $db->prepare("SELECT id FROM antrian WHERE user_id=? AND book_id=? AND status='menunggu'");
    $antriCheck->execute([$uid, $bid]);
    $inAntri = (bool)$antriCheck->fetch();

    // Log the view
    $db->prepare("INSERT INTO book_views (user_id, book_id) VALUES (?, ?)")->execute([$uid, $bid]);
}

// Format reviews
foreach ($reviews as &$rv) {
    $rv['time_ago'] = timeAgo($rv['created_at']);
    $initials = strtoupper(implode('', array_map(fn($w) => $w[0] ?? '', explode(' ', trim($rv['nama'])))));
    $rv['initials'] = substr($initials, 0, 2);
}
unset($rv);

echo json_encode([
    'book'            => $book,
    'reviews'         => $reviews,
    'antri_count'     => $antriCount,
    'active_borrowers'=> $activeBorrowers,
    'in_shelf'        => $inShelf,
    'in_antri'        => $inAntri,
    'logged_in'       => isLoggedIn(),
]);
