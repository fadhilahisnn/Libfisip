<?php
session_start();
require_once __DIR__ . '/../config.php';
requireLogin();

if (!isAdmin()) {
    redirect('../index.php', 'Akses ditolak. Anda bukan admin.', 'error');
}

$pageTitle = "Live Chat";
include __DIR__ . '/includes/admin_header.php';
?>
<div class="admin-content">
<div class="page-title-bar">
    <div>
        <h1 class="page-title">💬 Live Chat dengan Pengguna</h1>
        <p class="page-sub">Balas pesan dari pengguna secara real-time</p>
    </div>
</div>

<style>
.livechat-container { display: flex; height: calc(100vh - 160px); background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; }
.chat-sidebar { width: 300px; border-right: 1px solid #eee; background: #f9fafb; display: flex; flex-direction: column; }
.session-list { flex: 1; overflow-y: auto; }
.session-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; position: relative; }
.session-item:hover, .session-item.active { background: #eff6ff; }
.session-id { font-weight: 600; color: #1f2937; margin-bottom: 4px; font-size: 0.9rem; }
.session-preview { font-size: 0.8rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.session-badge { position: absolute; right: 15px; top: 15px; background: #ef4444; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; }
.chat-main { flex: 1; display: flex; flex-direction: column; background: white; }
.chat-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #fff; }
.chat-header h3 { margin: 0; font-size: 1.1rem; color: #1f2937; }
.close-chat-btn { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; }
.chat-messages { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 10px; background: #fcfcfc; }
.msg { max-width: 70%; padding: 10px 14px; border-radius: 16px; font-size: 0.9rem; line-height: 1.4; position: relative; }
.msg-user { background: #f1f5f9; color: #334155; align-self: flex-start; border-bottom-left-radius: 4px; }
.msg-admin { background: #2563eb; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.chat-input { padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; background: white; }
.chat-input input { flex: 1; padding: 10px 15px; border: 1px solid #cbd5e1; border-radius: 20px; outline: none; }
.chat-input button { background: #2563eb; color: white; border: none; padding: 10px 20px; border-radius: 20px; cursor: pointer; font-weight: 600; }
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: #9ca3af; }
</style>

<div class="livechat-container">
    <div class="chat-sidebar">
        <div style="padding: 15px; border-bottom: 1px solid #eee; background: white; font-weight: bold; text-align: center;">Daftar Antrean Chat</div>
        <div class="session-list" id="session-list">
            <!-- Sessions will be loaded here -->
        </div>
    </div>
    <div class="chat-main" id="chat-main">
        <div class="empty-state">
            <h2 style="font-size: 3rem; margin-bottom: 1rem;">💬</h2>
            <p>Pilih sesi chat dari panel kiri untuk mulai membalas.</p>
        </div>
    </div>
</div>

<script>
let currentSessionId = null;
let lastMessageId = 0;
let fetchInterval = null;
let sessionInterval = null;

function loadSessions() {
    fetch('../api/admin_livechat.php?action=get_sessions')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const list = document.getElementById('session-list');
                list.innerHTML = data.sessions.length ? '' : '<div style="padding:20px;text-align:center;color:#9ca3af;font-size:0.9rem;">Belum ada antrean chat.</div>';
                
                data.sessions.forEach(s => {
                    const el = document.createElement('div');
                    el.className = `session-item ${currentSessionId == s.id ? 'active' : ''}`;
                    el.onclick = () => openChat(s.id, s.session_id);
                    el.innerHTML = `
                        <div class="session-id">User: ${s.session_id.substr(0, 8)}...</div>
                        <div class="session-preview">${s.last_message || '...'}</div>
                        ${s.unread_count > 0 ? `<div class="session-badge">${s.unread_count}</div>` : ''}
                    `;
                    list.appendChild(el);
                });
            }
        });
}

function openChat(id, sessionIdRaw) {
    currentSessionId = id;
    lastMessageId = 0;
    
    document.querySelectorAll('.session-item').forEach(el => el.classList.remove('active'));
    
    const main = document.getElementById('chat-main');
    main.innerHTML = `
        <div class="chat-header">
            <h3>Chatting dengan User: ${sessionIdRaw.substr(0, 8)}...</h3>
            <button class="close-chat-btn" onclick="closeSession(${id})">Akhiri Chat</button>
        </div>
        <div class="chat-messages" id="chat-messages"></div>
        <div class="chat-input">
            <input type="text" id="msg-input" placeholder="Ketik balasan Anda..." onkeypress="if(event.key === 'Enter') sendMsg()">
            <button onclick="sendMsg()">Kirim</button>
        </div>
    `;
    
    if (fetchInterval) clearInterval(fetchInterval);
    loadMessages();
    fetchInterval = setInterval(loadMessages, 3000);
    
    setTimeout(() => {
        const input = document.getElementById('msg-input');
        if(input) input.focus();
    }, 100);
}

function loadMessages() {
    if (!currentSessionId) return;
    
    fetch(`../api/admin_livechat.php?action=get_messages&session_id=${currentSessionId}&last_id=${lastMessageId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                const container = document.getElementById('chat-messages');
                data.messages.forEach(m => {
                    lastMessageId = Math.max(lastMessageId, m.id);
                    const el = document.createElement('div');
                    el.className = `msg msg-${m.sender}`;
                    el.textContent = m.message;
                    container.appendChild(el);
                });
                container.scrollTop = container.scrollHeight;
            }
        });
}

function sendMsg() {
    const input = document.getElementById('msg-input');
    const msg = input.value.trim();
    if (!msg || !currentSessionId) return;
    
    input.value = '';
    
    fetch('../api/admin_livechat.php?action=send_message', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: currentSessionId, message: msg })
    }).then(res => res.json()).then(data => {
        if (data.success) {
            loadMessages();
            loadSessions();
        }
    });
}

function closeSession(id) {
    if(!confirm('Yakin ingin mengakhiri sesi chat ini?')) return;
    
    fetch('../api/admin_livechat.php?action=close_session', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: id })
    }).then(res => res.json()).then(data => {
        if (data.success) {
            currentSessionId = null;
            if (fetchInterval) clearInterval(fetchInterval);
            document.getElementById('chat-main').innerHTML = `
                <div class="empty-state">
                    <h2 style="font-size: 3rem; margin-bottom: 1rem;">💬</h2>
                    <p>Sesi chat telah diakhiri.</p>
                </div>
            `;
            loadSessions();
        }
    });
}

// Initial load
loadSessions();
sessionInterval = setInterval(loadSessions, 5000);
</script>

</div> <!-- End admin-content -->
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
