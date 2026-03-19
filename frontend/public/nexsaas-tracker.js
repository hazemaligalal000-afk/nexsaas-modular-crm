/**
 * NexSaaS Attribution Tracker
 * 
 * Captures Click IDs + UTM parameters, stores in localStorage, sends to server.
 * 
 * Usage: <script src="https://yourcrm.com/nexsaas-tracker.js" data-tenant="tenant-uuid"></script>
 */

(function() {
  'use strict';

  const STORAGE_KEY = 'nexsaas_attribution';
  const SESSION_DURATION = 30 * 24 * 60 * 60 * 1000; // 30 days

  // Get tenant ID from script tag
  const script = document.currentScript || document.querySelector('script[data-tenant]');
  const tenantId = script ? script.getAttribute('data-tenant') : null;
  const apiEndpoint = script ? script.getAttribute('data-endpoint') || '/api/tracking/session' : '/api/tracking/session';

  if (!tenantId) {
    console.warn('NexSaaS Tracker: data-tenant attribute missing');
    return;
  }

  // Parse URL parameters
  function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
      // UTM parameters
      utm_source: params.get('utm_source'),
      utm_medium: params.get('utm_medium'),
      utm_campaign: params.get('utm_campaign'),
      utm_term: params.get('utm_term'),
      utm_content: params.get('utm_content'),
      
      // Click IDs
      fbclid: params.get('fbclid'),
      gclid: params.get('gclid'),
      ttclid: params.get('ttclid'),
      msclkid: params.get('msclkid'),
      
      // Meta-specific
      fbp: getCookie('_fbp'),
      fbc: getCookie('_fbc') || (params.get('fbclid') ? `fb.1.${Date.now()}.${params.get('fbclid')}` : null),
      
      // Page context
      landing_page: window.location.href,
      referrer: document.referrer || null,
      
      // Device info
      device_type: getDeviceType(),
      browser: getBrowser(),
      user_agent: navigator.userAgent,
    };
  }

  // Get or create session
  function getSession() {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored) {
      try {
        const session = JSON.parse(stored);
        // Check if session expired
        if (Date.now() - session.timestamp < SESSION_DURATION) {
          return session;
        }
      } catch (e) {
        console.error('NexSaaS Tracker: Failed to parse stored session', e);
      }
    }
    return null;
  }

  // Save session
  function saveSession(data) {
    const session = {
      ...data,
      timestamp: Date.now(),
      session_id: generateSessionId(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    return session;
  }

  // Send to server
  function sendToServer(data) {
    const payload = {
      tenant_id: tenantId,
      ...data,
    };

    // Use sendBeacon for reliability
    if (navigator.sendBeacon) {
      const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
      navigator.sendBeacon(apiEndpoint, blob);
    } else {
      // Fallback to fetch
      fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        keepalive: true,
      }).catch(err => console.error('NexSaaS Tracker: Send failed', err));
    }
  }

  // Helpers
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
  }

  function getDeviceType() {
    const ua = navigator.userAgent;
    if (/mobile/i.test(ua)) return 'mobile';
    if (/tablet|ipad/i.test(ua)) return 'tablet';
    return 'desktop';
  }

  function getBrowser() {
    const ua = navigator.userAgent;
    if (ua.indexOf('Firefox') > -1) return 'Firefox';
    if (ua.indexOf('Chrome') > -1) return 'Chrome';
    if (ua.indexOf('Safari') > -1) return 'Safari';
    if (ua.indexOf('Edge') > -1) return 'Edge';
    return 'Other';
  }

  function generateSessionId() {
    return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  // Main logic
  function init() {
    const urlParams = getUrlParams();
    const hasAttribution = Object.values(urlParams).some(v => v !== null && v !== undefined);

    if (hasAttribution) {
      // New attribution detected - save and send
      const session = saveSession(urlParams);
      sendToServer(session);
      console.log('NexSaaS Tracker: Attribution captured', session);
    } else {
      // Check existing session
      const session = getSession();
      if (session) {
        console.log('NexSaaS Tracker: Using existing session', session);
      }
    }
  }

  // Expose API for form submissions
  window.NexSaasTracker = {
    getAttribution: function() {
      return getSession();
    },
    
    trackConversion: function(eventName, value) {
      const session = getSession();
      if (session) {
        sendToServer({
          ...session,
          event_name: eventName,
          event_value: value,
          event_time: Date.now(),
        });
      }
    },
  };

  // Initialize on load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
