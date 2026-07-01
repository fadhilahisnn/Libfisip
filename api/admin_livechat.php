<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_sessions':
        getSessions($db);
        break;
    case 'get_messages':
        getMessages($db);
        break;
    case 'send_message':
        sendMessage($db);
        break;
    case 'close_session':
        closeSession($db);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getSessions($db) {
    $stmt = $db->query("SELECT s.id, s.session_id, s.status, s.created_at, s.updated_at, 
        (SELECT COUNT(*) FROM livechat_messages m WHERE m.livechat_session_id = s.id AND m.sender = 'user' AND m.is_read = 0) as unread_count,
        (SELECT message FROM livechat_messages m2 WHERE m2.livechat_session_id = s.id ORDER BY m2.id DESC LIMIT 1) as last_message
        FROM livechat_sessions s 
        WHERE s.status = 'active'
        ORDER BY s.updated_at DESC");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

function getMessages($db) {
    $sessionId = $_GET['session_id'] ?? 0;
    $lastId = $_GET['last_id'] ?? 0;
    
    $stmt = $db->prepare("SELECT * FROM livechat_messages WHERE livechat_session_id = ? AND id > ? ORDER BY id ASC");
    $stmt->execute([$sessionId, $lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark user messages as read
    if (count($messages) > 0) {
        $stmt = $db->prepare("UPDATE livechat_messages SET is_read = 1 WHERE livechat_session_id = ? AND sender = 'user' AND id > ?");
        $stmt->execute([$sessionId, $lastId]);
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function sendMessage($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? 0;
    $message = $input['message'] ?? '';
    
    if (empty($message) || empty($sessionId)) {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO livechat_messages (livechat_session_id, sender, message) VALUES (?, 'admin', ?)");
    $stmt->execute([$sessionId, $message]);
    
    $stmt = $db->prepare("UPDATE livechat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$sessionId]);
    
    echo json_encode(['success' => true]);
}

function closeSession($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE livechat_sessions SET status = 'closed' WHERE id = ?");
    $stmt->execute([$sessionId]);
    
    echo json_encode(['success' => true]);
}
?>
