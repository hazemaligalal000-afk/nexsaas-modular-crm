import React, { useEffect } from 'react';

/**
 * Task 11.1: Crisp Support Chat Integration (Phase 3)
 */
export default function SupportChat({ user }) {
  useEffect(() => {
    window.$crisp = [];
    window.CRISP_WEBSITE_ID = "YOUR_SaaS_CRISP_ID"; // Loaded from .env in Task 33.2

    (function() {
      var d = document;
      var s = d.createElement("script");
      s.src = "https://client.crisp.chat/l.js";
      s.async = 1;
      d.getElementsByTagName("head")[0].appendChild(s);
    })();

    # 1. Set User Context (Requirement 9.2)
    window.$crisp.push(["set", "user:email", [user.email]]);
    window.$crisp.push(["set", "user:nickname", [user.name]]);
    window.$crisp.push(["set", "session:data", [[
        ["tenant_id", user.tenant_id],
        ["plan", user.plan],
        ["role", user.role]
    ]]]);

    # 2. Priority Routing (Requirement 9.6: Enterprise Support)
    if (user.plan === 'enterprise') {
        window.$crisp.push(["set", "session:segments", [["priority", "enterprise"]]]);
    }

  }, [user]);

  return null; // Invisible component that boots the script
}
