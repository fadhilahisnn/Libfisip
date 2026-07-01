<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'start_session':
        startSession($db);
        break;
    case 'send_message':
        sendMessage($db);
        break;
    case 'get_messages':
        getMessages($db);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function startSession($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? session_id();
    
    // Check if there's an active session
    $stmt = $db->prepare("SELECT id FROM livechat_sessions WHERE session_id = ? AND status = 'active'");
    $stmt->execute([$sessionId]);
    $liveSession = $stmt->fetch();
    
    if (!$liveSession) {
        $stmt = $db->prepare("INSERT INTO livechat_sessions (session_id) VALUES (?)");
        $stmt->execute([$sessionId]);
        $liveSessionId = $db->lastInsertId();
    } else {
        $liveSessionId = $liveSession['id'];
    }
    
    echo json_encode(['success' => true, 'livechat_session_id' => $liveSessionId]);
}

function sendMessage($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? session_id();
    $message = $input['message'] ?? '';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message required']);
        return;
    }
    
    $stmt = $db->prepare("SELECT id FROM livechat_sessions WHERE session_id = ? AND status = 'active'");
    $stmt->execute([$sessionId]);
    $liveSession = $stmt->fetch();
    
    if (!$liveSession) {
        echo json_encode(['success' => false, 'error' => 'No active livechat session']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO livechat_messages (livechat_session_id, sender, message) VALUES (?, 'user', ?)");
    $stmt->execute([$liveSession['id'], $message]);
    
    echo json_encode(['success' => true]);
}

function getMessages($db) {
    $sessionId = $_GET['session_id'] ?? session_id();
    $lastId = $_GET['last_id'] ?? 0;
    
    $stmt = $db->prepare("SELECT id FROM livechat_sessions WHERE session_id = ? AND status = 'active'");
    $stmt->execute([$sessionId]);
    $liveSession = $stmt->fetch();
    
    if (!$liveSession) {
        echo json_encode(['success' => true, 'messages' => [], 'active' => false]);
        return;
    }
    
    $stmt = $db->prepare("SELECT * FROM livechat_messages WHERE livechat_session_id = ? AND id > ? ORDER BY id ASC");
    $stmt->execute([$liveSession['id'], $lastId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark admin messages as read
    if (count($messages) > 0) {
        $stmt = $db->prepare("UPDATE livechat_messages SET is_read = 1 WHERE livechat_session_id = ? AND sender = 'admin' AND id > ?");
        $stmt->execute([$liveSession['id'], $lastId]);
    }
    
    echo json_encode(['success' => true, 'messages' => $messages, 'active' => true]);
}
?>
