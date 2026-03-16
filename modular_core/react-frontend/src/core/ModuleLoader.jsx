import React, { lazy } from 'react';
import DynamicModule from '../modules/DynamicModule/index';

// ── CRM Modules ──
const SalesOps     = lazy(() => import('../modules/SalesOps/index'));
const SalesPipeline = lazy(() => import('../modules/SalesPipeline/index'));
const Analytics    = lazy(() => import('../modules/Analytics/index'));

// ── AI Engine ──
const AIEngine     = lazy(() => import('../modules/AIEngine/index'));

// ── ERP / Operations Modules (ERPNext Integrated) ──
const Invoicing    = lazy(() => import('../modules/Invoicing/index'));
const Accounting   = lazy(() => import('../modules/Accounting/index'));
const InventoryMod = lazy(() => import('../modules/Inventory/index'));
const HRModule     = lazy(() => import('../modules/HRModule/index'));
const ProjectMgr   = lazy(() => import('../modules/ProjectManager/index'));

// ── Platform Modules ──
const Security     = lazy(() => import('../modules/Security/index'));
const Settings     = lazy(() => import('../modules/Settings/index'));
const Marketplace  = lazy(() => import('../modules/Marketplace/index'));
const Workflows    = lazy(() => import('../modules/Workflows/index'));

// ── Component Map: module ID → React component ──
export const componentMap = {
    // AI
    AIEngine,

    // CRM
    SalesOps,
    SalesPipeline,
    Analytics,

    // Operations / ERP
    Invoicing,
    Accounting,
    Inventory: InventoryMod,
    HRModule,
    ProjectManager: ProjectMgr,

    // CRM Vtiger (DynamicModule)
    Leads:      (p) => <DynamicModule {...p} moduleName="Leads"      icon="🎯" />,
    Contacts:   (p) => <DynamicModule {...p} moduleName="Contacts"   icon="👤" />,
    Accounts:   (p) => <DynamicModule {...p} moduleName="Accounts"   icon="🏢" />,
    Potentials: (p) => <DynamicModule {...p} moduleName="Potentials" icon="💰" />,
    HelpDesk:   (p) => <DynamicModule {...p} moduleName="HelpDesk"   icon="🎟️" />,

    // Platform
    Security,
    Settings,
    Marketplace,
    Workflows,
};

/**
 * Full unified module list — CRM + ERP in one sidebar
 */
export const useActiveModules = () => [
    // ── AI Engine ──
    { id: 'AIEngine',     path: '/ai',           section: 'AI Engine', icon: '🤖', label: 'Nexa Intelligence™' },

    // ── CRM ──
    { id: 'SalesOps',     path: '/sales-ops',    section: 'CRM',      icon: '⚡', label: 'Sales Operations' },
    { id: 'SalesPipeline', path: '/pipeline',     section: 'CRM',      icon: '🔄', label: 'Sales Pipeline' },
    { id: 'Leads',        path: '/leads',        section: 'CRM',      icon: '🎯', label: 'Leads' },
    { id: 'Contacts',     path: '/contacts',     section: 'CRM',      icon: '👤', label: 'Contacts' },
    { id: 'Accounts',     path: '/accounts',     section: 'CRM',      icon: '🏢', label: 'Accounts' },
    { id: 'Potentials',   path: '/opportunities', section: 'CRM',     icon: '💰', label: 'Opportunities' },
    { id: 'HelpDesk',     path: '/tickets',      section: 'CRM',      icon: '🎟️', label: 'Help Desk' },
    { id: 'Analytics',    path: '/analytics',    section: 'CRM',      icon: '📊', label: 'Analytics' },

    // ── Operations & ERP ──
    { id: 'Invoicing',     path: '/invoicing',    section: 'Operations', icon: '📄', label: 'Operations & Invoicing' },
    { id: 'Accounting',    path: '/accounting',   section: 'Operations', icon: '💳', label: 'Accounting' },
    { id: 'Inventory',     path: '/inventory',    section: 'Operations', icon: '📦', label: 'Inventory' },
    { id: 'HRModule',      path: '/hr',           section: 'Operations', icon: '👥', label: 'HR & Payroll' },
    { id: 'ProjectManager', path: '/projects',    section: 'Operations', icon: '🎯', label: 'Projects' },

    // ── Platform ──
    { id: 'Security',     path: '/security',     section: 'Platform',  icon: '🛡️', label: 'Security & Compliance' },
    { id: 'Workflows',    path: '/workflows',    section: 'Platform',  icon: '⚙️', label: 'Workflows' },
    { id: 'Marketplace',  path: '/marketplace',  section: 'Platform',  icon: '🛒', label: 'Marketplace' },
    { id: 'Settings',     path: '/settings',     section: 'Platform',  icon: '🛠️', label: 'Settings' },
];
