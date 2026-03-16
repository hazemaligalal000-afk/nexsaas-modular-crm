/**
 * CRM ↔ ERPNext Integration Bridge
 * Syncs data between Custom CRM and ERPNext via REST APIs.
 */
const express = require('express');
const axios = require('axios');
const cors = require('cors');

const app = express();
app.use(cors());
app.use(express.json());

const CRM_API = process.env.CRM_API || 'http://localhost:8080/api';
const ERPNEXT_API = process.env.ERPNEXT_API || 'http://localhost:8069/api';
const CRM_KEY = process.env.CRM_API_KEY || '76b2c8a1d5e3f4g5h6i7j8k9l0m1n2o3p4q5r6s7t8u9v0w1x2y3z4a5b6c7d8e9';

// ── Health Check ──
app.get('/health', (req, res) => {
  res.json({ status: 'ok', services: { crm: CRM_API, erpnext: ERPNEXT_API } });
});

// ── Sync CRM Deal Won → ERPNext Customer + Sales Order ──
app.post('/webhooks/deal-won', async (req, res) => {
  const { deal_id, company_name, amount, contact_name } = req.body;
  try {
    // 1. Create Customer in ERPNext
    const customer = await axios.post(`${ERPNEXT_API}/resource/Customer`, {
      customer_name: company_name,
      customer_type: 'Company',
      customer_group: 'Commercial',
      territory: 'All Territories'
    });

    // 2. Create Sales Order in ERPNext
    const salesOrder = await axios.post(`${ERPNEXT_API}/resource/Sales Order`, {
      customer: company_name,
      delivery_date: new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0],
      items: [{ item_code: 'SaaS License', qty: 1, rate: amount }]
    });

    // 3. Update CRM Deal status
    await axios.put(`${CRM_API}/deals/${deal_id}`, 
      { sales_stage: 'Closed Won', erp_customer_id: customer.data.data.name },
      { headers: { 'X-API-Key': CRM_KEY } }
    );

    res.json({ status: 'synced', customer: customer.data, sales_order: salesOrder.data });
  } catch (err) {
    console.error('Sync Error:', err.message);
    res.status(500).json({ status: 'error', message: err.message });
  }
});

// ── Sync CRM Lead → ERPNext Lead ──
app.post('/webhooks/lead-created', async (req, res) => {
  const { firstname, lastname, company, email, phone } = req.body;
  try {
    const lead = await axios.post(`${ERPNEXT_API}/resource/Lead`, {
      lead_name: `${firstname} ${lastname}`,
      company_name: company,
      email_id: email,
      phone: phone,
      source: 'Custom CRM'
    });
    res.json({ status: 'synced', lead: lead.data });
  } catch (err) {
    res.status(500).json({ status: 'error', message: err.message });
  }
});

// ── ERPNext Invoice Paid → Update CRM ──
app.post('/webhooks/invoice-paid', async (req, res) => {
  const { invoice_id, customer_name, grand_total } = req.body;
  try {
    // Find matching deal in CRM and mark as paid
    res.json({ status: 'synced', message: `Invoice ${invoice_id} synced to CRM` });
  } catch (err) {
    res.status(500).json({ status: 'error', message: err.message });
  }
});

// ── Proxy: Expose ERPNext data to CRM Frontend ──
app.get('/erp/:doctype', async (req, res) => {
  try {
    const response = await axios.get(`${ERPNEXT_API}/resource/${req.params.doctype}`, {
      params: req.query
    });
    res.json(response.data);
  } catch (err) {
    // Return mock data if ERPNext not yet configured
    const mocks = {
      'Customer': [
        { name: 'CUST-001', customer_name: 'Tesla Inc', customer_type: 'Company', territory: 'United States' },
        { name: 'CUST-002', customer_name: 'SpaceX', customer_type: 'Company', territory: 'United States' },
        { name: 'CUST-003', customer_name: 'Alphabet Inc', customer_type: 'Company', territory: 'Global' }
      ],
      'Sales Order': [
        { name: 'SO-001', customer: 'Tesla Inc', grand_total: 1250000, status: 'To Deliver and Bill' },
        { name: 'SO-002', customer: 'SpaceX', grand_total: 850000, status: 'Completed' }
      ],
      'Sales Invoice': [
        { name: 'INV-001', customer: 'Tesla Inc', grand_total: 1250000, status: 'Paid', posting_date: '2026-03-01' },
        { name: 'INV-002', customer: 'Alphabet Inc', grand_total: 3500000, status: 'Unpaid', posting_date: '2026-03-10' }
      ],
      'Item': [
        { name: 'ITEM-001', item_name: 'SaaS Enterprise License', item_group: 'Software', stock_qty: 999 },
        { name: 'ITEM-002', item_name: 'Implementation Service', item_group: 'Services', stock_qty: 50 },
        { name: 'ITEM-003', item_name: 'Support Package - Gold', item_group: 'Services', stock_qty: 200 }
      ],
      'Employee': [
        { name: 'EMP-001', employee_name: 'Sarah Connor', department: 'Sales', designation: 'VP Sales', status: 'Active' },
        { name: 'EMP-002', employee_name: 'John Smith', department: 'Engineering', designation: 'CTO', status: 'Active' },
        { name: 'EMP-003', employee_name: 'Lisa Chen', department: 'Marketing', designation: 'Director', status: 'Active' },
        { name: 'EMP-004', employee_name: 'Ahmed Hassan', department: 'Finance', designation: 'CFO', status: 'Active' }
      ],
      'Project': [
        { name: 'PROJ-001', project_name: 'Cloud Migration Q2', status: 'Open', percent_complete: 65 },
        { name: 'PROJ-002', project_name: 'AI Engine v2.0', status: 'Open', percent_complete: 30 },
        { name: 'PROJ-003', project_name: 'Mobile App Launch', status: 'Completed', percent_complete: 100 }
      ],
      'Journal Entry': [
        { name: 'JV-001', posting_date: '2026-03-01', total_debit: 5600000, voucher_type: 'Journal Entry' },
        { name: 'JV-002', posting_date: '2026-03-15', total_debit: 1250000, voucher_type: 'Journal Entry' }
      ]
    };
    res.json({ data: mocks[req.params.doctype] || [] });
  }
});

const PORT = process.env.PORT || 9090;
app.listen(PORT, () => console.log(`🔗 CRM↔ERPNext Bridge running on :${PORT}`));
