/**
 * NexSaaS Unified Live Chat Widget v1.0
 * Embeddable on any site for direct-to-CRM communication.
 */

(function () {
    const CHAT_API = "https://your-nexsaas-domain.com/api/livechat";
    const PUSHER_KEY = "your-pusher-key";

    function generateSessionId() {
        return Math.random().toString(36).substring(2, 15);
    }

    let sessionId = sessionStorage.getItem("nexsaas_chat_id");
    if (!sessionId) {
        sessionId = generateSessionId();
        sessionStorage.setItem("nexsaas_chat_id", sessionId);
    }

    // Insert Widget UI
    const widget = document.createElement("div");
    widget.id = "nexsaas-chat-widget";
    widget.style = "position:fixed; bottom:20px; right:20px; width:350px; height:500px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.15); display:none; z-index:9999; flex-direction:column; font-family: 'Inter', sans-serif;";
    
    const header = document.createElement("div");
    header.style = "background:#3b82f6; color:#fff; padding:16px; border-radius:12px 12px 0 0; font-weight:800; display:flex; justify-content:space-between; align-items:center;";
    header.innerHTML = "<span>💬 Live Chat</span><button id='close-chat' style='background:none; border:none; color:#fff; cursor:pointer;'>✕</button>";

    const messageArea = document.createElement("div");
    messageArea.id = "chat-messages";
    messageArea.style = "flex:1; padding:16px; overflow-y:auto; background:#f8fafc; font-size:13px; color:#1e293b;";

    const inputArea = document.createElement("div");
    inputArea.style = "padding:12px; border-top:1px solid #e2e8f0; display:flex; gap:8px;";
    
    const input = document.createElement("input");
    input.id = "chat-input";
    input.placeholder = "Type your message...";
    input.style = "flex:1; padding:10px; border:1px solid #e2e8f0; border-radius:8px; outline:none; font-size:13px;";

    const sendBtn = document.createElement("button");
    sendBtn.innerText = "Send";
    sendBtn.style = "background:#3b82f6; color:#fff; padding:8px 16px; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:13px;";

    inputArea.appendChild(input);
    inputArea.appendChild(sendBtn);

    widget.appendChild(header);
    widget.appendChild(messageArea);
    widget.appendChild(inputArea);

    document.body.appendChild(widget);

    const toggle = document.createElement("button");
    toggle.id = "chat-toggle";
    toggle.innerHTML = "💬";
    toggle.style = "position:fixed; bottom:20px; right:20px; width:60px; height:60px; background:#3b82f6; color:#fff; border-radius:50%; border:none; font-size:24px; cursor:pointer; box-shadow:0 10px 20px rgba(59,130,246,0.3); z-index:9998;";

    document.body.appendChild(toggle);

    // Event Listeners
    toggle.onclick = () => {
        widget.style.display = widget.style.display === "flex" ? "none" : "flex";
        toggle.style.display = widget.style.display === "flex" ? "none" : "block";
    };

    document.getElementById("close-chat").onclick = () => {
        widget.style.display = "none";
        toggle.style.display = "block";
    };

    sendBtn.onclick = sendMessage;
    input.onkeydown = (e) => { if (e.key === "Enter") sendMessage(); };

    async function sendMessage() {
        const text = input.value.trim();
        if (!text) return;

        appendMessage("You", text, true);
        input.value = "";

        // API Send
        try {
            const formData = new FormData();
            formData.append("tenant_id", window.NEXSAAS_TENANT_ID);
            formData.append("session_id", sessionId);
            formData.append("message", text);

            await fetch(CHAT_API + "/send", { method: "POST", body: formData });
        } catch (e) {
            console.error("Chat Error:", e);
        }
    }

    function appendMessage(from, text, isSelf) {
        const msg = document.createElement("div");
        msg.style = `margin-bottom:12px; align-self: ${isSelf ? 'flex-end' : 'flex-start'}; max-width: 80%;`;
        msg.innerHTML = `
            <div style="font-size:10px; color:#64748b; margin-bottom:2px; text-align: ${isSelf ? 'right' : 'left'};">${from}</div>
            <div style="background: ${isSelf ? '#3b82f6' : '#fff'}; color: ${isSelf ? '#fff' : '#1e293b'}; padding:8px 12px; border-radius:12px; border: ${isSelf ? 'none' : '1px solid #e2e8f0'}; box-shadow:0 1px 2px rgba(0,0,0,0.05);">
                ${text}
            </div>
        `;
        messageArea.appendChild(msg);
        messageArea.scrollTop = messageArea.scrollHeight;
    }

    // Pusher Listen (Requirement Week 3)
    if (typeof Pusher !== "undefined") {
        const pusher = new Pusher(PUSHER_KEY, { cluster: 'mt1' });
        const channel = pusher.subscribe("client-chat-" + sessionId);
        channel.bind('agent-message', (data) => {
            appendMessage(data.agent_name, data.text, false);
        });
        channel.bind('agent-typing', (data) => {
            showTypingIndicator(data.agent);
        });
    }

    function showTypingIndicator(agent) {
        // Simple visual hint
        console.log(agent + " is typing...");
    }

})();
