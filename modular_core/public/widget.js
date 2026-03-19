/**
 * NexSaaS Live Chat Widget
 *
 * Embeddable script that renders a chat bubble on any external website and
 * connects to the NexSaaS WebSocket server for real-time messaging.
 *
 * Usage:
 *   <script src="https://your-nexsaas-host/widget.js"
 *           data-tenant="<tenant_id>"
 *           data-ws-host="your-nexsaas-host"
 *           data-ws-port="8080"
 *           async></script>
 *
 * Requirements: 12.7, 12.8
 */
(function (window, document) {
  'use strict';

  // -------------------------------------------------------------------------
  // Configuration — read from the <script> tag's data attributes
  // -------------------------------------------------------------------------
  var scriptTag = document.currentScript ||
    (function () {
      var scripts = document.getElementsByTagName('script');
      return scripts[scripts.length - 1];
    })();

  var tenantId  = scriptTag.getAttribute('data-tenant')  || '';
  var wsHost    = scriptTag.getAttribute('data-ws-host')  || window.location.hostname;
  var wsPort    = scriptTag.getAttribute('data-ws-port')  || '8080';
  var wsProto   = window.location.protocol === 'https:' ? 'wss' : 'ws';
  var wsUrl     = wsProto + '://' + wsHost + ':' + wsPort + '/chat?tenant=' + encodeURIComponent(tenantId);

  // -------------------------------------------------------------------------
  // State
  // -------------------------------------------------------------------------
  var sessionToken  = null;
  var socket        = null;
  var isOpen        = false;
  var reconnectDelay = 1000;
  var maxReconnect   = 30000;
  var messages      = [];       // { direction, body, ts }
  var cannedResponses = [];     // loaded from API on open

  // -------------------------------------------------------------------------
  // Styles
  // -------------------------------------------------------------------------
  var css = [
    '#nxs-widget-btn{position:fixed;bottom:24px;right:24px;width:56px;height:56px;border-radius:50%;',
    'background:#4f46e5;color:#fff;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.25);',
    'font-size:24px;display:flex;align-items:center;justify-content:center;z-index:99999;}',

    '#nxs-widget-box{position:fixed;bottom:92px;right:24px;width:340px;max-height:520px;',
    'background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);',
    'display:none;flex-direction:column;z-index:99999;font-family:sans-serif;overflow:hidden;}',
    '#nxs-widget-box.nxs-open{display:flex;}',

    '#nxs-widget-header{background:#4f46e5;color:#fff;padding:14px 16px;font-weight:600;font-size:15px;',
    'display:flex;align-items:center;justify-content:space-between;}',
    '#nxs-widget-header button{background:none;border:none;color:#fff;cursor:pointer;font-size:18px;line-height:1;}',

    '#nxs-widget-msgs{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;}',

    '.nxs-msg{max-width:80%;padding:8px 12px;border-radius:10px;font-size:13px;line-height:1.4;word-break:break-word;}',
    '.nxs-msg.nxs-in{background:#f3f4f6;align-self:flex-start;border-bottom-left-radius:2px;}',
    '.nxs-msg.nxs-out{background:#4f46e5;color:#fff;align-self:flex-end;border-bottom-right-radius:2px;}',

    '#nxs-widget-canned{padding:0 12px 6px;display:none;flex-wrap:wrap;gap:6px;}',
    '#nxs-widget-canned.nxs-visible{display:flex;}',
    '.nxs-canned-btn{background:#ede9fe;color:#4f46e5;border:none;border-radius:16px;',
    'padding:4px 10px;font-size:12px;cursor:pointer;white-space:nowrap;}',
    '.nxs-canned-btn:hover{background:#c4b5fd;}',

    '#nxs-widget-footer{padding:10px 12px;border-top:1px solid #e5e7eb;display:flex;gap:8px;align-items:flex-end;}',
    '#nxs-widget-input{flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;',
    'font-size:13px;resize:none;outline:none;max-height:80px;overflow-y:auto;}',
    '#nxs-widget-input:focus{border-color:#4f46e5;}',
    '#nxs-widget-send{background:#4f46e5;color:#fff;border:none;border-radius:8px;',
    'padding:8px 14px;cursor:pointer;font-size:13px;white-space:nowrap;}',
    '#nxs-widget-send:disabled{opacity:.5;cursor:default;}',
    '#nxs-widget-canned-toggle{background:none;border:none;cursor:pointer;font-size:18px;color:#6b7280;padding:4px;}',
  ].join('');

  // -------------------------------------------------------------------------
  // DOM construction
  // -------------------------------------------------------------------------
  function buildWidget() {
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);

    // Launcher button
    var btn = document.createElement('button');
    btn.id = 'nxs-widget-btn';
    btn.setAttribute('aria-label', 'Open chat');
    btn.innerHTML = '&#128172;';
    btn.addEventListener('click', toggleWidget);
    document.body.appendChild(btn);

    // Chat box
    var box = document.createElement('div');
    box.id = 'nxs-widget-box';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Live chat');
    box.innerHTML = [
      '<div id="nxs-widget-header">',
      '  <span>Chat with us</span>',
      '  <button id="nxs-widget-close" aria-label="Close chat">&#x2715;</button>',
      '</div>',
      '<div id="nxs-widget-msgs" aria-live="polite" aria-relevant="additions"></div>',
      '<div id="nxs-widget-canned" aria-label="Canned responses"></div>',
      '<div id="nxs-widget-footer">',
      '  <button id="nxs-widget-canned-toggle" title="Canned responses" aria-label="Toggle canned responses">&#9889;</button>',
      '  <textarea id="nxs-widget-input" rows="1" placeholder="Type a message…" aria-label="Message input"></textarea>',
      '  <button id="nxs-widget-send" aria-label="Send message">Send</button>',
      '</div>',
    ].join('');
    document.body.appendChild(box);

    document.getElementById('nxs-widget-close').addEventListener('click', closeWidget);
    document.getElementById('nxs-widget-send').addEventListener('click', sendMessage);
    document.getElementById('nxs-widget-canned-toggle').addEventListener('click', toggleCanned);

    var input = document.getElementById('nxs-widget-input');
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
    // Auto-grow textarea
    input.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });
  }

  // -------------------------------------------------------------------------
  // Widget open / close
  // -------------------------------------------------------------------------
  function toggleWidget() {
    isOpen ? closeWidget() : openWidget();
  }

  function openWidget() {
    isOpen = true;
    document.getElementById('nxs-widget-box').classList.add('nxs-open');
    document.getElementById('nxs-widget-btn').setAttribute('aria-expanded', 'true');
    if (!socket || socket.readyState > 1) {
      connect();
    }
    loadCannedResponses();
    document.getElementById('nxs-widget-input').focus();
  }

  function closeWidget() {
    isOpen = false;
    document.getElementById('nxs-widget-box').classList.remove('nxs-open');
    document.getElementById('nxs-widget-btn').setAttribute('aria-expanded', 'false');
  }

  // -------------------------------------------------------------------------
  // WebSocket connection — Requirement 12.7
  // -------------------------------------------------------------------------
  function connect() {
    if (!sessionToken) {
      sessionToken = generateToken();
    }

    var url = wsUrl + '&session=' + encodeURIComponent(sessionToken);
    socket = new WebSocket(url);

    socket.addEventListener('open', function () {
      reconnectDelay = 1000;
      appendSystemMessage('Connected. How can we help?');
    });

    socket.addEventListener('message', function (event) {
      try {
        var data = JSON.parse(event.data);
        if (data.type === 'message') {
          appendMessage('in', data.body);
        } else if (data.type === 'system') {
          appendSystemMessage(data.body);
        }
      } catch (e) {
        // ignore malformed frames
      }
    });

    socket.addEventListener('close', function () {
      if (isOpen) {
        appendSystemMessage('Reconnecting…');
        setTimeout(connect, reconnectDelay);
        reconnectDelay = Math.min(reconnectDelay * 2, maxReconnect);
      }
    });

    socket.addEventListener('error', function () {
      // error is always followed by close; handled above
    });
  }

  // -------------------------------------------------------------------------
  // Send message
  // -------------------------------------------------------------------------
  function sendMessage() {
    var input = document.getElementById('nxs-widget-input');
    var body  = input.value.trim();
    if (!body) return;

    var payload = JSON.stringify({
      type:          'message',
      session_token: sessionToken,
      body:          body,
      page_url:      window.location.href,
    });

    if (socket && socket.readyState === WebSocket.OPEN) {
      socket.send(payload);
      appendMessage('out', body);
      input.value = '';
      input.style.height = 'auto';
    } else {
      appendSystemMessage('Not connected. Trying to reconnect…');
      connect();
    }
  }

  // -------------------------------------------------------------------------
  // Canned responses — Requirement 12.8
  // -------------------------------------------------------------------------
  function loadCannedResponses() {
    // Fetch from the REST API; no auth required for read-only widget access
    // The server returns only the tenant's canned responses.
    var apiBase = wsProto === 'wss' ? 'https' : 'http';
    var apiUrl  = apiBase + '://' + wsHost + '/api/v1/crm/inbox/canned-responses/public?tenant=' + encodeURIComponent(tenantId);

    fetch(apiUrl)
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (data && data.data && Array.isArray(data.data.canned_responses)) {
          cannedResponses = data.data.canned_responses;
          renderCannedButtons();
        }
      })
      .catch(function () { /* silently ignore — canned responses are optional */ });
  }

  function renderCannedButtons() {
    var container = document.getElementById('nxs-widget-canned');
    container.innerHTML = '';
    cannedResponses.forEach(function (cr) {
      var btn = document.createElement('button');
      btn.className = 'nxs-canned-btn';
      btn.textContent = cr.title;
      btn.setAttribute('title', cr.body);
      btn.addEventListener('click', function () {
        document.getElementById('nxs-widget-input').value = cr.body;
        document.getElementById('nxs-widget-input').focus();
        hideCanned();
      });
      container.appendChild(btn);
    });
  }

  function toggleCanned() {
    var container = document.getElementById('nxs-widget-canned');
    container.classList.toggle('nxs-visible');
  }

  function hideCanned() {
    document.getElementById('nxs-widget-canned').classList.remove('nxs-visible');
  }

  // -------------------------------------------------------------------------
  // Message rendering
  // -------------------------------------------------------------------------
  function appendMessage(direction, body) {
    var msgs = document.getElementById('nxs-widget-msgs');
    var div  = document.createElement('div');
    div.className = 'nxs-msg ' + (direction === 'out' ? 'nxs-out' : 'nxs-in');
    div.textContent = body;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
    messages.push({ direction: direction, body: body, ts: Date.now() });
  }

  function appendSystemMessage(text) {
    var msgs = document.getElementById('nxs-widget-msgs');
    var div  = document.createElement('div');
    div.style.cssText = 'text-align:center;font-size:11px;color:#9ca3af;padding:4px 0;';
    div.textContent = text;
    msgs.appendChild(div);
    msgs.scrollTop = msgs.scrollHeight;
  }

  // -------------------------------------------------------------------------
  // Utilities
  // -------------------------------------------------------------------------
  function generateToken() {
    var arr = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(arr);
    } else {
      for (var i = 0; i < arr.length; i++) {
        arr[i] = Math.floor(Math.random() * 256);
      }
    }
    return Array.from(arr).map(function (b) {
      return b.toString(16).padStart(2, '0');
    }).join('');
  }

  // -------------------------------------------------------------------------
  // Boot
  // -------------------------------------------------------------------------
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', buildWidget);
  } else {
    buildWidget();
  }

}(window, document));
