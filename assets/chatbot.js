/**
 * Chatbot Widget for Ruang Baca FISIP UNAIR
 * Accessible to all users (before and after login)
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        apiBase: 'api/chatbot.php',
        storageKey: 'chatbot_session_id',
        debounceTime: 300
    };

    // State
    let state = {
        isOpen: false,
        currentCategory: null,
        sessionId: null,
        categories: [],
        responses: [],
        messages: [],
        isLoading: false,
        isLiveChat: false,
        livechatSessionId: null,
        livechatInterval: null,
        lastMessageId: 0
    };

    // Initialize
    function init() {
        state.sessionId = getSessionId();
        createWidget();
        bindEvents();
        
        // Push initial welcome message
        if (state.messages.length === 0) {
            addMessage('bot', 'Halo! 👋 Saya LibBot, asisten AI dari Ruang Baca FISIP UNAIR. Ada yang bisa saya bantu hari ini?');
        }
    }

    // Get or create session ID
    function getSessionId() {
        let sessionId = localStorage.getItem(CONFIG.storageKey);
        if (!sessionId) {
            sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem(CONFIG.storageKey, sessionId);
        }
        return sessionId;
    }

    // Send message to chatbot
    async function sendMessage(message) {
        if (!message.trim() || state.isLoading) return;

        state.isLoading = true;
        
        // Add user message to chat
        addMessage('user', message);

        if (state.isLiveChat) {
            // Live Chat Mode
            try {
                await fetch('api/livechat.php?action=send_message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: state.sessionId,
                        message: message
                    })
                });
                // Note: Admin messages will be fetched via pollLiveChat
            } catch (error) {
                console.error('Failed to send live chat:', error);
                addMessage('bot', 'Gagal mengirim pesan. Coba lagi.');
            } finally {
                state.isLoading = false;
            }
            return;
        }

        // AI Bot Mode
        try {
            const response = await fetch(`${CONFIG.apiBase}?action=send_message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    category_id: state.currentCategory,
                    session_id: state.sessionId
                })
            });

            const data = await response.json();
            if (data.success) {
                let text = data.response;
                if (text.includes('[LIVE_CHAT_BTN]')) {
                    text = text.replace('[LIVE_CHAT_BTN]', '');
                    addMessage('bot', text);
                    
                    // Show live chat prompt
                    addMessage('bot', `<div style="text-align:center; padding:10px 0;">
                        <p style="margin-bottom:8px; font-size:0.85rem;">Ingin ngobrol langsung dengan pustakawan?</p>
                        <button onclick="window.startLiveChat()" style="background:#2563eb; color:white; border:none; padding:8px 16px; border-radius:20px; font-weight:600; cursor:pointer;">Masuk Live Chat</button>
                    </div>`);
                } else {
                    addMessage('bot', text);
                }
            }
        } catch (error) {
            console.error('Failed to send message:', error);
            addMessage('bot', 'Maaf, terjadi kesalahan. Silakan coba lagi.');
        } finally {
            state.isLoading = false;
        }
    }

    // Expose startLiveChat to window so the button can call it
    window.startLiveChat = async function() {
        addMessage('bot', 'Memulai sesi live chat... Mohon tunggu sebentar.');
        try {
            const res = await fetch('api/livechat.php?action=start_session', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: state.sessionId })
            });
            const data = await res.json();
            if (data.success) {
                state.isLiveChat = true;
                state.livechatSessionId = data.livechat_session_id;
                document.querySelector('.chatbot-header-status').innerHTML = '🟡 Live Chat';
                document.querySelector('.chatbot-header-title').innerHTML = 'Pustakawan RBC';
                addMessage('bot', '✅ Anda sekarang terhubung dengan Pustakawan RBC (Admin). Silakan ketik pertanyaan Anda.');
                
                // Start polling
                state.livechatInterval = setInterval(pollLiveChat, 3000);
            }
        } catch (e) {
            console.error(e);
            addMessage('bot', 'Gagal memulai live chat.');
        }
    };

    async function pollLiveChat() {
        if (!state.isLiveChat) return;
        try {
            const res = await fetch(`api/livechat.php?action=get_messages&session_id=${state.sessionId}&last_id=${state.lastMessageId}`);
            const data = await res.json();
            if (data.success) {
                if (!data.active) {
                    clearInterval(state.livechatInterval);
                    state.isLiveChat = false;
                    document.querySelector('.chatbot-header-status').innerHTML = '🟢 Online';
                    document.querySelector('.chatbot-header-title').innerHTML = 'Asisten Virtual';
                    addMessage('bot', 'Sesi live chat telah diakhiri oleh admin.');
                    return;
                }
                
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(m => {
                        state.lastMessageId = Math.max(state.lastMessageId, m.id);
                        if (m.sender === 'admin') {
                            addMessage('bot', m.message);
                        }
                    });
                }
            }
        } catch (e) { console.error(e); }
    }

    // Create the chatbot widget HTML
    function createWidget() {
        const widgetHTML = `
            <div id="chatbot-widget" class="chatbot-widget">
                <!-- Chat Toggle Button -->
                <button id="chatbot-toggle" class="chatbot-toggle" aria-label="Buka asisten virtual">
                    <span class="chatbot-toggle-icon">💬</span>
                    <span class="chatbot-toggle-badge">!</span>
                </button>

                <!-- Chat Window -->
                <div id="chatbot-window" class="chatbot-window" role="dialog" aria-label="Asisten Virtual Ruang Baca FISIP">
                    <!-- Header -->
                    <div class="chatbot-header">
                        <div class="chatbot-header-info">
                            <span class="chatbot-header-avatar">🤖</span>
                            <div>
                                <h3 class="chatbot-header-title">Asisten Virtual</h3>
                                <span class="chatbot-header-status">🟢 Online</span>
                            </div>
                        </div>
                        <div class="chatbot-header-actions">
                            <button id="chatbot-back" class="chatbot-action-btn hidden" aria-label="Kembali" title="Kembali">
                                ←
                            </button>
                            <button id="chatbot-reset" class="chatbot-action-btn" aria-label="Reset" title="Reset">
                                🔄
                            </button>
                            <button id="chatbot-close" class="chatbot-action-btn" aria-label="Tutup" title="Tutup">
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- Responses Screen -->
                    <div id="chatbot-responses" class="chatbot-content" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                        <div id="chatbot-scroll-area" style="flex: 1; overflow-y: auto; padding: 1.2rem; display: flex; flex-direction: column; scroll-behavior: smooth;">
                            <div class="chatbot-messages" id="chatbot-messages">
                                <!-- Messages will be rendered here -->
                            </div>
                        </div>
                        
                        <!-- Input Area -->
                        <div class="chatbot-input-area" style="padding: 12px 1rem; background: var(--bg-secondary); border-top: 1px solid var(--border); display: flex; gap: 8px; align-items: center; flex-shrink: 0;">
                            <input type="text" id="chatbot-text-input" placeholder="Tanya sesuatu di sini..." style="flex:1; padding: 12px 16px; border-radius: 24px; border: 1.5px solid var(--border); outline: none; background: var(--card-bg); color: var(--text-main); font-size: 0.88rem; transition: border-color 0.2s;" autocomplete="off" aria-label="Ketik pesan Anda">
                            <button id="chatbot-send-btn" style="width: 44px; height: 44px; border-radius: 50%; border: none; background: linear-gradient(135deg, var(--accent), var(--accent3)); color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 10px rgba(255, 77, 109, 0.25);" aria-label="Kirim pesan" title="Kirim">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="chatbot-footer">
                        <span class="chatbot-footer-text">Ruang Baca FISIP UNAIR</span>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', widgetHTML);
    }

    // Bind event listeners
    function bindEvents() {
        const toggle = document.getElementById('chatbot-toggle');
        const close = document.getElementById('chatbot-close');
        const back = document.getElementById('chatbot-back');
        const reset = document.getElementById('chatbot-reset');
        const textInput = document.getElementById('chatbot-text-input');
        const sendBtn = document.getElementById('chatbot-send-btn');

        toggle.addEventListener('click', toggleWidget);
        close.addEventListener('click', closeWidget);
        if(back) back.style.display = 'none';
        reset.addEventListener('click', resetChat);

        // Input events for custom typing
        if (sendBtn && textInput) {
            sendBtn.addEventListener('click', () => {
                const text = textInput.value;
                if (text) {
                    sendMessage(text);
                    textInput.value = '';
                }
            });

            textInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    sendBtn.click();
                }
            });
            
            textInput.addEventListener('focus', () => textInput.style.borderColor = 'var(--accent)');
            textInput.addEventListener('blur', () => textInput.style.borderColor = 'var(--border)');
            sendBtn.addEventListener('mousedown', () => sendBtn.style.transform = 'scale(0.9)');
            sendBtn.addEventListener('mouseup', () => sendBtn.style.transform = 'scale(1)');
            sendBtn.addEventListener('mouseleave', () => sendBtn.style.transform = 'scale(1)');
        }

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && state.isOpen) {
                closeWidget();
            }
        });
    }

    // Toggle widget open/closed
    function toggleWidget() {
        state.isOpen = !state.isOpen;
        const widget = document.getElementById('chatbot-widget');
        const toggle = document.getElementById('chatbot-toggle');
        const badge = document.querySelector('.chatbot-toggle-badge');

        if (state.isOpen) {
            widget.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
            badge.style.display = 'none';
            
            // Focus on text input
            setTimeout(() => {
                const textInput = document.getElementById('chatbot-text-input');
                if (textInput) textInput.focus();
            }, 300);
        } else {
            widget.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    }

    // Close widget
    function closeWidget() {
        state.isOpen = false;
        document.getElementById('chatbot-widget').classList.remove('open');
        document.getElementById('chatbot-toggle').setAttribute('aria-expanded', 'false');
    }

    // Reset chat
    function resetChat() {
        if (confirm('Reset percakapan?')) {
            state.messages = [];
            addMessage('bot', 'Halo! 👋 Saya LibBot, asisten AI dari Ruang Baca FISIP UNAIR. Ada yang bisa saya bantu hari ini?');
        }
    }

    // Add message to chat
    function addMessage(type, text) {
        state.messages.push({ type, text });
        renderMessages();
    }

    // Render messages
    function renderMessages() {
        const container = document.getElementById('chatbot-messages');
        const scrollArea = document.getElementById('chatbot-scroll-area') || container;
        
        container.innerHTML = state.messages.map(msg => `
            <div class="chatbot-message chatbot-message-${msg.type}">
                <div class="chatbot-message-avatar">
                    ${msg.type === 'user' ? '👤' : '🤖'}
                </div>
                <div class="chatbot-message-content">
                    <div class="chatbot-message-text">${formatMessage(msg.text)}</div>
                </div>
            </div>
        `).join('');

        // Hide quick replies when a user sends a custom message
        const quickReplies = document.getElementById('chatbot-quick-replies');
        if (state.messages.length > 1 && quickReplies) {
            quickReplies.style.opacity = '0.5';
            quickReplies.style.pointerEvents = 'none';
        } else if (quickReplies) {
            quickReplies.style.opacity = '1';
            quickReplies.style.pointerEvents = 'all';
        }

        // Scroll to bottom
        setTimeout(() => {
            scrollArea.scrollTop = scrollArea.scrollHeight;
        }, 10);
    }

    // Format message text (convert markdown-like formatting)
    function formatMessage(text) {
        if (!text) return '';
        
        // Convert **bold** to <strong>
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Convert newlines to <br>
        text = text.replace(/\n/g, '<br>');
        
        return text;
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();