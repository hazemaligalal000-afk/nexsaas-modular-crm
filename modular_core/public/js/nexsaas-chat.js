/**
 * NexSaaS Elite Live Chat Widget (v2.1)
 * Embeddable script for tenant websites.
 * Requirements: Master Spec - Omnichannel 3.84
 */

(function() {
    // -------------------------------------------------------------------------
    // Configuration (Tenant-specific)
    // -------------------------------------------------------------------------
    const config = window.NexSaaSConfig || {
        tenantId: 'demo-tenant',
        primaryColor: '#3b82f6',
        greeting: 'How can we accelerate your revenue today?',
        apiEndpoint: '/api/webhooks/livechat'
    };

    // -------------------------------------------------------------------------
    // Styles (Injected for zero-bundle dependency)
    // -------------------------------------------------------------------------
    const styles = `
        #nexsaas-chat-wrapper { position: fixed; bottom: 20px; right: 20px; z-index: 99999; font-family: Inter, sans-serif; }
        #nexsaas-chat-bubble { width: 60px; height: 60px; border-radius: 50%; background: ${config.primaryColor}; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 25px rgba(0,0,0,0.15); transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        #nexsaas-chat-bubble:hover { transform: scale(1.1); }
        #nexsaas-chat-bubble svg { width: 30px; height: 30px; fill: currentColor; }
        
        #nexsaas-chat-window { width: 360px; height: 500px; background: #fff; border-radius: 16px; position: absolute; bottom: 80px; right: 0; box-shadow: 0 15px 50px rgba(0,0,0,0.2); overflow: hidden; display: none; flex-direction: column; opacity: 0; transform: translateY(20px); transition: all 0.3s ease-out; }
        #nexsaas-chat-window.open { display: flex; opacity: 1; transform: translateY(0); }
        
        #nexsaas-chat-header { background: ${config.primaryColor}; color: #fff; padding: 20px; display: flex; align-items: center; justify-content: space-between; }
        #nexsaas-chat-body { flex: 1; overflow-y: auto; padding: 15px; background: #f8fafc; display: flex; flex-direction: column; gap: 10px; }
        #nexsaas-chat-footer { padding: 15px; border-top: 1px solid #e2e8f0; display: flex; gap: 10px; }
        #nexsaas-chat-input { flex: 1; border: 1px solid #e2e8f0; border-radius: 20px; padding: 10px 15px; outline: none; transition: border 0.3s; font-size: 14px; }
        #nexsaas-chat-input:focus { border-color: ${config.primaryColor}; }
        #nexsaas-chat-send { background: ${config.primaryColor}; color: #fff; border: none; border-radius: 50%; width: 40px; height: 40px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 18px; }

        .msg { max-width: 80%; padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.5; }
        .msg-client { background: ${config.primaryColor}; color: #fff; align-self: flex-end; border-bottom-right-radius: 2px; }
        .msg-agent { background: #fff; border: 1px solid #e2e8f0; color: #1e293b; align-self: flex-start; border-bottom-left-radius: 2px; }
    `;

    // -------------------------------------------------------------------------
    // DOM Assembly
    // -------------------------------------------------------------------------
    const styleEl = document.createElement('style');
    styleEl.innerHTML = styles;
    document.head.appendChild(styleEl);

    const wrapper = document.createElement('div');
    wrapper.id = 'nexsaas-chat-wrapper';
    wrapper.innerHTML = `
        <div id="nexsaas-chat-window">
            <div id="nexsaas-chat-header">
                <div>
                    <h4 style="margin:0; font-size:15px; font-weight:700">NexSaaS Live Agent</h4>
                    <p style="margin:4px 0 0; font-size:11px; opacity:0.8">${config.greeting}</p>
                </div>
                <button id="nexsaas-chat-close" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px">&times;</button>
            </div>
            <div id="nexsaas-chat-body">
                <div class="msg msg-agent">Hi! 👋 How can we help you today?</div>
            </div>
            <div id="nexsaas-chat-footer">
                <input type="text" id="nexsaas-chat-input" placeholder="Type a message...">
                <button id="nexsaas-chat-send">↑</button>
            </div>
            <div style="background:#f8fafc; padding:4px 15px; text-align:center; font-size:9px; color:#94a3b8; border-top:1px solid #f1f5f9">
                Powered by 🔌 NexSaaS AI
            </div>
        </div>
        <div id="nexsaas-chat-bubble">
            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
        </div>
    `;
    document.body.appendChild(wrapper);

    // -------------------------------------------------------------------------
    // Interactive Logic
    // -------------------------------------------------------------------------
    const bubble = document.getElementById('nexsaas-chat-bubble');
    const windowEl = document.getElementById('nexsaas-chat-window');
    const closeBtn = document.getElementById('nexsaas-chat-close');
    const sendBtn = document.getElementById('nexsaas-chat-send');
    const input = document.getElementById('nexsaas-chat-input');
    const body = document.getElementById('nexsaas-chat-body');

    const toggle = () => windowEl.classList.toggle('open');
    bubble.onclick = toggle;
    closeBtn.onclick = toggle;

    const appendMessage = (text, type) => {
        const div = document.createElement('div');
        div.className = `msg msg-${type}`;
        div.innerText = text;
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    };

    const sendMessage = () => {
        const text = input.value.trim();
        if (!text) return;

        appendMessage(text, 'client');
        input.value = '';

        // Requirement 3.84 - Dispatch to Omnichannel Inbox
        fetch(config.apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                text: text,
                timestamp: new Date().toISOString(),
                clientId: getClientId(),
                tenantId: config.tenantId
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.reply) appendMessage(data.reply, 'agent');
        });
    };

    sendBtn.onclick = sendMessage;
    input.onkeydown = (e) => { if (e.key === 'Enter') sendMessage(); };

    function getClientId() {
        let id = localStorage.getItem('nexsaas_client_id');
        if (!id) {
            id = 'client_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('nexsaas_client_id', id);
        }
        return id;
    }
})();
