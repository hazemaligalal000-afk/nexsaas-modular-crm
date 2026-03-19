/**
 * NexSaaS CRM Mock API Server — Multi-Role Edition
 * Supports 13 demo accounts across all roles
 */
import http from 'http';

const PORT = 8081;

// ── Users ──────────────────────────────────────────────────────────────────
const USERS = {
  'owner@acme.com': {
    user: { id: 1, name: 'Hazem Al-Rashid', email: 'owner@acme.com', role: 'owner', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'H' },
    permissions: {
      leads: { read: true, create: true, update: true, delete: true, import: true },
      analytics: { view: true, revenue: true, agents: true, export: true, financials: true },
      billing: { view: true, manage: true },
      settings: { branding: true, users: true, rbac: true, billing: true },
      workflows: { read: true, create: true, update: true, delete: true },
      hr: { read: true, manage: true },
    },
  },
  'admin@acme.com': {
    user: { id: 2, name: 'Sara Ali', email: 'admin@acme.com', role: 'admin', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'S' },
    permissions: {
      leads: { read: true, create: true, update: true, delete: true, import: true },
      analytics: { view: true, revenue: true, agents: true, export: true },
      settings: { branding: true, users: true },
      workflows: { read: true, create: true, update: true },
    },
  },
  'rep@acme.com': {
    user: { id: 3, name: 'Ahmed Hassan', email: 'rep@acme.com', role: 'sales_rep', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'A' },
    permissions: { leads: { read: true, create: true, update: true }, deals: { read: true, create: true, update: true } },
  },
  'accountant@acme.com': {
    user: { id: 4, name: 'Nour Khalil', email: 'accountant@acme.com', role: 'accountant', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'N' },
    permissions: { finance: { read: true, vouchers: true, reconcile: true }, reports: { view: true, export: true } },
  },
  'finance@acme.com': {
    user: { id: 5, name: 'Rami Saad', email: 'finance@acme.com', role: 'finance_manager', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'R' },
    permissions: { finance: { read: true, manage: true, consolidate: true, budget: true }, reports: { view: true, export: true, schedule: true } },
  },
  'hr@acme.com': {
    user: { id: 6, name: 'Dina Mostafa', email: 'hr@acme.com', role: 'hr_manager', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'D' },
    permissions: { hr: { read: true, manage: true, payroll: true, hire: true }, reports: { view: true, export: true } },
  },
  'support@acme.com': {
    user: { id: 7, name: 'Karim Nabil', email: 'support@acme.com', role: 'support_agent', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'K' },
    permissions: { tickets: { read: true, update: true, close: true }, messaging: { read: true, send: true } },
  },
  'support.mgr@acme.com': {
    user: { id: 8, name: 'Layla Farid', email: 'support.mgr@acme.com', role: 'support_manager', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'L' },
    permissions: { tickets: { read: true, update: true, close: true, assign: true, delete: true }, reports: { view: true, export: true } },
  },
  'marketing@acme.com': {
    user: { id: 9, name: 'Yasmin Taha', email: 'marketing@acme.com', role: 'marketing', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'Y' },
    permissions: { campaigns: { read: true, create: true, update: true }, leads: { read: true, import: true }, analytics: { view: true } },
  },
  'pm@acme.com': {
    user: { id: 10, name: 'Tarek Mansour', email: 'pm@acme.com', role: 'project_manager', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'T' },
    permissions: { projects: { read: true, create: true, update: true, delete: true }, hr: { read: true } },
  },
  'inventory@acme.com': {
    user: { id: 11, name: 'Hana Zaki', email: 'inventory@acme.com', role: 'inventory_manager', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'H' },
    permissions: { inventory: { read: true, create: true, update: true, delete: true }, reports: { view: true } },
  },
  'it@acme.com': {
    user: { id: 12, name: 'Samer Adel', email: 'it@acme.com', role: 'it_admin', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'S' },
    permissions: { system: { read: true, manage: true, security: true }, settings: { users: true, rbac: true, integrations: true } },
  },
  'hrstaff@acme.com': {
    user: { id: 13, name: 'Mona Sherif', email: 'hrstaff@acme.com', role: 'hr_staff', tenant_id: 'tenant_acme_001', company_code: 'ACME', avatar: 'M' },
    permissions: { hr: { read: true }, attendance: { read: true, update: true } },
  },
};

// Token → email map (in-memory session)
const sessions = {};

// ── Mock Data ──────────────────────────────────────────────────────────────
const MOCK_LEADS = [
  { id: 1, first_name: 'Alice',  last_name: 'Johnson',  email: 'alice@techcorp.com',   source: 'Website',    ai_score: 87, lifecycle_stage: 'Qualified', assigned_to: 3, created_at: '2026-03-01T10:00:00Z' },
  { id: 2, first_name: 'Bob',    last_name: 'Smith',    email: 'bob@startup.io',        source: 'LinkedIn',   ai_score: 62, lifecycle_stage: 'New',       assigned_to: 3, created_at: '2026-03-05T14:30:00Z' },
  { id: 3, first_name: 'Carol',  last_name: 'Williams', email: 'carol@enterprise.com',  source: 'Referral',   ai_score: 94, lifecycle_stage: 'Demo',      assigned_to: 2, created_at: '2026-03-08T09:15:00Z' },
  { id: 4, first_name: 'David',  last_name: 'Brown',    email: 'david@agency.net',      source: 'Cold Email', ai_score: 41, lifecycle_stage: 'New',       assigned_to: 3, created_at: '2026-03-10T16:45:00Z' },
  { id: 5, first_name: 'Eva',    last_name: 'Martinez', email: 'eva@globalcorp.com',    source: 'Event',      ai_score: 78, lifecycle_stage: 'Proposal',  assigned_to: 2, created_at: '2026-03-12T11:20:00Z' },
];

const MOCK_DEALS = [
  { potentialid: '101', potentialname: 'Netflix Content Delivery AI',   amount: '95000',   sales_stage: 'New Lead',    company_name: 'Netflix',    assigned_to: 3, win_probability: 22, closingdate: '2026-04-30' },
  { potentialid: '102', potentialname: 'Alphabet Global Expansion',     amount: '1250000', sales_stage: 'Qualified',   company_name: 'Alphabet',   assigned_to: 3, win_probability: 45, closingdate: '2026-05-15' },
  { potentialid: '103', potentialname: 'Meta Ad Platform Integration',  amount: '850000',  sales_stage: 'Demo',        company_name: 'Meta',       assigned_to: 2, win_probability: 58, closingdate: '2026-04-20' },
  { potentialid: '104', potentialname: 'Amazon Web Services Migration', amount: '3500000', sales_stage: 'Proposal',    company_name: 'Amazon',     assigned_to: 2, win_probability: 71, closingdate: '2026-06-01' },
  { potentialid: '105', potentialname: 'Apple Supply Chain Opt.',       amount: '450000',  sales_stage: 'Negotiation', company_name: 'Apple',      assigned_to: 3, win_probability: 83, closingdate: '2026-04-10' },
  { potentialid: '106', potentialname: 'Microsoft Azure AI Deal',       amount: '2100000', sales_stage: 'Closed Won',  company_name: 'Microsoft',  assigned_to: 3, win_probability: 100, closingdate: '2026-03-28' },
];

const OWNER_ANALYTICS = {
  kpis: [
    { label: 'Total Revenue',    value: '$8.4M',  trend: '+23%', icon: '💰' },
    { label: 'Active Pipeline',  value: '$4.2M',  trend: '+18%', icon: '🏗️' },
    { label: 'Total Leads',      value: '1,284',  trend: '+12%', icon: '👤' },
    { label: 'Win Rate',         value: '34%',    trend: '+5%',  icon: '🏆' },
    { label: 'MRR',              value: '$142K',  trend: '+9%',  icon: '📈' },
    { label: 'Churn Rate',       value: '2.1%',   trend: '-0.4%',icon: '📉' },
  ],
  pipeline_breakdown: [
    { lifecycle_stage: 'New Lead',    cnt: 412 },
    { lifecycle_stage: 'Qualified',   cnt: 347 },
    { lifecycle_stage: 'Demo',        cnt: 198 },
    { lifecycle_stage: 'Proposal',    cnt: 124 },
    { lifecycle_stage: 'Negotiation', cnt: 87  },
    { lifecycle_stage: 'Closed Won',  cnt: 116 },
  ],
  revenue_by_month: [
    { month: 'Oct', value: 580000 }, { month: 'Nov', value: 720000 },
    { month: 'Dec', value: 650000 }, { month: 'Jan', value: 890000 },
    { month: 'Feb', value: 1100000 },{ month: 'Mar', value: 1420000 },
  ],
  team_performance: [
    { name: 'Sara Ali',      deals: 11, revenue: '$2.8M', quota: '80%', trend: '↑' },
    { name: 'Ahmed Hassan',  deals: 8,  revenue: '$1.9M', quota: '64%', trend: '↑' },
    { name: 'John Doe',      deals: 14, revenue: '$3.2M', quota: '91%', trend: '↑' },
    { name: 'Maria Garcia',  deals: 5,  revenue: '$950K', quota: '54%', trend: '↓' },
  ],
};

const ADMIN_ANALYTICS = {
  kpis: [
    { label: 'Total Leads',    value: '1,284', trend: '+12%', icon: '👤' },
    { label: 'Qualified',      value: '347',   trend: '+8%',  icon: '✅' },
    { label: 'Pipeline Value', value: '$4.2M', trend: '+23%', icon: '🏗️' },
    { label: 'Win Rate',       value: '34%',   trend: '+5%',  icon: '🏆' },
    { label: 'Avg Deal Size',  value: '$285K', trend: '+11%', icon: '💼' },
  ],
  pipeline_breakdown: [
    { lifecycle_stage: 'New Lead',    cnt: 412 },
    { lifecycle_stage: 'Qualified',   cnt: 347 },
    { lifecycle_stage: 'Demo',        cnt: 198 },
    { lifecycle_stage: 'Proposal',    cnt: 124 },
    { lifecycle_stage: 'Negotiation', cnt: 87  },
    { lifecycle_stage: 'Closed Won',  cnt: 116 },
  ],
};

const REP_ANALYTICS = {
  kpis: [
    { label: 'My Leads',       value: '23',    trend: '+3',   icon: '👤' },
    { label: 'My Open Deals',  value: '4',     trend: '+1',   icon: '💼' },
    { label: 'My Pipeline',    value: '$640K', trend: '+12%', icon: '🏗️' },
    { label: 'Tasks Due Today',value: '5',     trend: '0',    icon: '📋' },
  ],
  pipeline_breakdown: [
    { lifecycle_stage: 'New Lead',    cnt: 8  },
    { lifecycle_stage: 'Qualified',   cnt: 7  },
    { lifecycle_stage: 'Demo',        cnt: 4  },
    { lifecycle_stage: 'Proposal',    cnt: 3  },
    { lifecycle_stage: 'Negotiation', cnt: 1  },
  ],
  my_tasks: [
    { id: 1, title: 'Follow up with Alice Johnson',    due: 'Today',    priority: 'high'   },
    { id: 2, title: 'Send proposal to Bob Smith',      due: 'Today',    priority: 'high'   },
    { id: 3, title: 'Schedule demo with Eva Martinez', due: 'Tomorrow', priority: 'medium' },
    { id: 4, title: 'Update deal notes — Netflix',     due: 'Mar 21',   priority: 'low'    },
    { id: 5, title: 'Call David Brown re: pricing',    due: 'Mar 22',   priority: 'medium' },
  ],
};

const MOCK_WORKFLOWS = [
  { id: 1, name: 'Auto-Assign High Value Deals', trigger: 'deal.stage_changed',   actions: 'Create Task, Notify Slack', active: true  },
  { id: 2, name: 'Lead Score Alert',             trigger: 'lead.score_updated',   actions: 'Send Email, Push Notification', active: true  },
  { id: 3, name: 'Deal Rotting Alert',           trigger: 'deal.no_activity_7d',  actions: 'Notify Owner, Create Task', active: true  },
  { id: 4, name: 'New Lead Welcome',             trigger: 'lead.captured',         actions: 'Send Welcome Email', active: false },
];

// ── Helpers ────────────────────────────────────────────────────────────────
function respond(res, status, body) {
  res.writeHead(status, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-API-Key',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
  });
  res.end(JSON.stringify(body));
}

function ok(res, data, meta = {}) {
  respond(res, 200, { success: true, data, error: null, meta: { timestamp: new Date().toISOString(), ...meta } });
}

function getUserFromReq(req) {
  const auth = req.headers['authorization'] || '';
  const token = auth.replace('Bearer ', '');
  const email = sessions[token];
  return email ? USERS[email] : null;
}

function readBody(req) {
  return new Promise(resolve => {
    let body = '';
    req.on('data', c => body += c);
    req.on('end', () => { try { resolve(JSON.parse(body)); } catch { resolve({}); } });
  });
}

// ── Server ─────────────────────────────────────────────────────────────────
const server = http.createServer(async (req, res) => {
  const url = req.url.split('?')[0];
  const method = req.method;

  if (method === 'OPTIONS') { respond(res, 204, {}); return; }

  // POST /api/auth/login
  if (url === '/api/auth/login' && method === 'POST') {
    const body = await readBody(req);
    const account = USERS[body.email];
    if (!account) return respond(res, 401, { success: false, error: 'Invalid credentials', data: null });
    const token = `mock_token_${body.email}_${Date.now()}`;
    sessions[token] = body.email;
    return ok(res, { access_token: token, user: account.user });
  }

  // GET /api/auth/me
  if (url === '/api/auth/me') {
    const account = getUserFromReq(req);
    if (!account) return respond(res, 401, { success: false, error: 'Unauthorized', data: null });
    return ok(res, { user: account.user, permissions: account.permissions });
  }

  // POST /api/auth/logout
  if (url === '/api/auth/logout' && method === 'POST') {
    const auth = (req.headers['authorization'] || '').replace('Bearer ', '');
    delete sessions[auth];
    return ok(res, { message: 'Logged out' });
  }

  // GET /api/analytics/overview — role-aware
  if (url === '/api/analytics/overview') {
    const account = getUserFromReq(req);
    const role = account?.user?.role;
    const data = role === 'owner' ? OWNER_ANALYTICS : role === 'admin' ? ADMIN_ANALYTICS : REP_ANALYTICS;
    return ok(res, data);
  }

  // GET /api/leads — reps only see their own
  if (url === '/api/leads' && method === 'GET') {
    const account = getUserFromReq(req);
    const role = account?.user?.role;
    const userId = account?.user?.id;
    const leads = (role === 'sales_rep') ? MOCK_LEADS.filter(l => l.assigned_to === userId) : MOCK_LEADS;
    return ok(res, leads);
  }

  // GET /api/deals — reps only see their own
  if (url === '/api/deals' && method === 'GET') {
    const account = getUserFromReq(req);
    const role = account?.user?.role;
    const userId = account?.user?.id;
    const deals = (role === 'sales_rep') ? MOCK_DEALS.filter(d => d.assigned_to === userId) : MOCK_DEALS;
    return ok(res, deals);
  }

  if (url.startsWith('/api/deals/') && method === 'PUT') {
    return ok(res, { updated: true });
  }

  if (url === '/api/Workflows' || url === '/api/workflows') {
    return ok(res, MOCK_WORKFLOWS);
  }

  respond(res, 404, { success: false, error: `No mock for ${method} ${url}`, data: null });
});

server.listen(PORT, () => {
  console.log(`\n✅ NexSaaS Mock API  →  http://localhost:${PORT}`);
  console.log(`\n   Accounts:`);
  console.log(`   👑  owner@acme.com   / any password  (Owner)`);
  console.log(`   🛡️  admin@acme.com   / any password  (Admin)`);
  console.log(`   👤  rep@acme.com     / any password  (Sales Rep)\n`);
});
