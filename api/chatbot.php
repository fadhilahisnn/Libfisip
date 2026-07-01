<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_categories':
        getCategories($db);
        break;
    case 'get_responses':
        getResponses($db);
        break;
    case 'send_message':
        sendMessage($db);
        break;
    case 'log_conversation':
        logConversation($db);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getCategories($db) {
    $stmt = $db->query("SELECT * FROM chatbot_categories WHERE is_active = 1 ORDER BY sort_order");
    $categories = $stmt->fetchAll();
    echo json_encode(['success' => true, 'categories' => $categories]);
}

function getResponses($db) {
    $categoryId = $_GET['category_id'] ?? 0;
    
    if (!$categoryId) {
        echo json_encode(['success' => false, 'error' => 'Category ID required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT id, question, answer FROM chatbot_responses WHERE category_id = ? AND is_active = 1 ORDER BY sort_order");
    $stmt->execute([$categoryId]);
    $responses = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'responses' => $responses]);
}

function sendMessage($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $message = $input['message'] ?? '';
    $categoryId = $input['category_id'] ?? 0;
    $sessionId = $input['session_id'] ?? session_id();
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message required']);
        return;
    }
    // Langsung gunakan Gemini API (tanpa cek lokal)
    $geminiResponse = askGemini($message);
    
    if ($geminiResponse) {
        saveConversation($db, $sessionId, $message, $geminiResponse, null);
        echo json_encode([
            'success' => true,
            'response' => $geminiResponse,
            'matched' => true
        ]);
    } else {
        // Cek jam kerja: Senin - Jumat, 08:00 - 16:00 WIB
        date_default_timezone_set('Asia/Jakarta');
        $day = date('N'); // 1 (Mon) - 7 (Sun)
        $hour = (int)date('H'); // 00 - 23
        $isWorkingHours = ($day >= 1 && $day <= 5 && $hour >= 8 && $hour < 16);

        $defaultResponse = "Maaf, saya belum menemukan jawaban yang tepat dan koneksi ke AI sedang sibuk. Hubungi pustakawan langsung untuk bantuan lebih lanjut. 📚\n\n";

        if ($isWorkingHours) {
            $defaultResponse .= '<a href="https://wa.me/6281234567890" target="_blank" style="display:inline-block; margin-top:8px; padding:8px 12px; background:#10b981; color:white; border-radius:15px; text-decoration:none; font-size:0.85rem; font-weight:600; text-align:center;">👩‍🏫 Bertanya ke Pustakawan</a>';
        } else {
            $defaultResponse .= '<div style="margin-top:8px; padding:8px 12px; background:#f3f4f6; color:#9ca3af; border-radius:15px; font-size:0.8rem; font-weight:600; text-align:center; border:1px solid #e5e7eb;">🌙 Pustakawan Sedang Offline<br><span style="font-size:0.7rem; font-weight:400;">Layanan aktif: Sen-Jum, 08:00-16:00 WIB</span></div>';
        }
        
        saveConversation($db, $sessionId, $message, $defaultResponse, null);
        
        echo json_encode([
            'success' => true,
            'response' => $defaultResponse,
            'matched' => false
        ]);
    }
}

function askGemini($message) {
    // Load API key from .env file
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') !== false) {
                putenv(trim($line));
            }
        }
    }
    $apiKey = getenv('GEMINI_API_KEY');
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $systemPrompt = "Kamu adalah LibBot, asisten virtual resmi Ruang Baca FISIP UNAIR. 
Tugasmu adalah menjawab pertanyaan pengguna secara ramah, singkat, dan jelas.

PENTING! Berikut adalah fitur-fitur yang ada di website ini. Berikan jawaban berdasarkan informasi ini:
1. PENCARIAN BUKU: Pengguna bisa mencari buku melalui kotak pencarian di Beranda atau menu Layanan Pustaka.
2. RAK KU (Halaman Profil): Mahasiswa bisa melihat buku yang 'Ditahan' (masuk keranjang), 'Sedang Dipinjam', dan 'Riwayat Peminjaman' di menu 'Rak Ku'.
3. PEMINJAMAN & PERPANJANGAN: Buku bisa dipinjam oleh mahasiswa UNAIR. Buku yang sedang dipinjam bisa diperpanjang 1 kali (tambahan 7 hari) langsung dari menu 'Rak Ku' (selama belum lewat jatuh tempo).
4. DENDA KETERLAMBATAN: Denda keterlambatan adalah Rp 1.000 / hari / buku.
5. PEMBAYARAN DENDA: Jika ada denda, mahasiswa bisa bayar secara Tunai ke petugas atau via QRIS. Di menu 'Rak Ku', pilih bayar via QRIS, lalu tekan tombol 'Saya Sudah Bayar' jika sudah transfer. Tunggu Admin memverifikasi (Terima/Tolak).
6. AKSESIBILITAS: Website ini sangat ramah disabilitas. Ada tombol biru di pojok kiri bawah untuk mengaktifkan Text-to-Speech (suara), mengatur kontras warna, dan memperbesar ukuran teks.
7. PEMINJAMAN RUANG: Selain buku, mahasiswa juga bisa meminjam ruang diskusi melalui menu 'Pinjam Ruang'.
8. JAM LAYANAN: Senin - Jumat, 08:00 - 16:00 WIB.

Jika pengguna tampak bingung, frustasi, atau secara spesifik meminta untuk berbicara dengan manusia/admin/pustakawan, ATAU pertanyaannya benar-benar tidak bisa kamu jawab, sertakan teks persis ini di akhir jawabanmu: [LIVE_CHAT_BTN]

Jika ditanya hal di luar perpustakaan atau fitur yang tidak ada, jawab dengan sopan bahwa fitur itu belum tersedia atau kamu tidak tahu.

Pertanyaan pengguna: ";

    $prompt = $systemPrompt . $message;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Untuk local environment XAMPP
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            // Filter markdown bold if needed, or just return raw text
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    return false;
}

function findBestResponse($db, $message, $categoryId) {
    $message = strtolower($message);
    
    // Cari berdasarkan kesamaan teks pertanyaan
    $stmt = $db->prepare("SELECT id, category_id, question, answer, keywords FROM chatbot_responses WHERE category_id = ? AND is_active = 1 ORDER BY sort_order");
    $stmt->execute([$categoryId]);
    $responses = $stmt->fetchAll();
    
    $bestMatch = null;
    $bestSimilarity = 0;
    
    foreach ($responses as $response) {
        $question = strtolower($response['question']);
        $keywords = strtolower($response['keywords'] ?? '');
        
        // Hitung similarity dengan similar_text
        similar_text($message, $question, $percent);
        
        // Cek apakah ada kata kunci yang cocok
        $keywordMatch = 0;
        if (!empty($keywords)) {
            $keywordArray = explode(',', $keywords);
            foreach ($keywordArray as $keyword) {
                $keyword = trim($keyword);
                if (strpos($message, $keyword) !== false) {
                    $keywordMatch += 10;
                }
            }
        }
        
        $totalScore = $percent + $keywordMatch;
        
        if ($totalScore > $bestSimilarity && $totalScore >= 30) {
            $bestSimilarity = $totalScore;
            $bestMatch = $response;
        }
    }
    
    return $bestMatch;
}

function saveConversation($db, $sessionId, $userMessage, $botResponse, $categoryId) {
    $stmt = $db->prepare("INSERT INTO chatbot_conversations (session_id, user_message, bot_response, category_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $sessionId,
        $userMessage,
        $botResponse,
        $categoryId,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

function logConversation($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sessionId = $input['session_id'] ?? session_id();
    $userMessage = $input['user_message'] ?? '';
    $botResponse = $input['bot_response'] ?? '';
    $categoryId = $input['category_id'] ?? null;
    
    if (empty($userMessage) || empty($botResponse)) {
        echo json_encode(['success' => false, 'error' => 'Message and response required']);
        return;
    }
    
    saveConversation($db, $sessionId, $userMessage, $botResponse, $categoryId);
    
    echo json_encode(['success' => true]);
}
?>