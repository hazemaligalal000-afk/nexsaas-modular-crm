import React, { useState } from 'react';
import { JournalEntryForm } from './JournalEntry/JournalEntryForm';
import { TrialBalance } from './Reports/TrialBalance';
import { ProfitLoss } from './Reports/ProfitLoss';
import { BalanceSheet } from './Reports/BalanceSheet';
import { COAManager } from './COA/COAManager';
import { PeriodManager } from './Period/PeriodManager';

export const AccountingDashboard: React.FC = () => {
  const [activeTab, setActiveTab] = useState<'vouchers' | 'reports' | 'coa' | 'periods' | 'payroll' | 'profit'>('vouchers');
  const [activeReport, setActiveReport] = useState<'trial-balance' | 'p-loss' | 'b-sheet'>('trial-balance');

  return (
    <div className="flex bg-gray-50 min-h-screen">
      {/* Sidebar */}
      <div className="w-64 bg-white shadow-md">
        <div className="p-6 border-b">
          <h1 className="text-xl font-bold text-gray-800">Accounting Module</h1>
          <p className="text-xs text-gray-500">NexSaaS ERP Foundation</p>
        </div>
        <nav className="p-4 space-y-2">
          <button 
            onClick={() => setActiveTab('vouchers')}
            className={`w-full text-left px-4 py-3 rounded-lg flex items-center gap-3 ${activeTab === 'vouchers' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            <span className="text-lg">📒</span> Vouchers
          </button>
          <button 
            onClick={() => setActiveTab('reports')}
            className={`w-full text-left px-4 py-3 rounded-lg flex items-center gap-3 ${activeTab === 'reports' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            <span className="text-lg">📊</span> Reports
          </button>
          <button 
            onClick={() => setActiveTab('payroll')}
            className={`w-full text-left px-4 py-3 rounded-lg flex items-center gap-3 ${activeTab === 'payroll' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            <span className="text-lg">👔</span> Payroll
          </button>
          <button 
            onClick={() => setActiveTab('profit')}
            className={`w-full text-left px-4 py-3 rounded-lg flex items-center gap-3 ${activeTab === 'profit' ? 'bg-emerald-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            <span className="text-lg">💎</span> Profit Share
          </button>
          <button 
            onClick={() => setActiveTab('coa')}
            className={`w-full text-left px-4 py-3 rounded-lg flex items-center gap-3 ${activeTab === 'coa' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            <span className="text-lg">🌳</span> Chart of Accounts
          </button>
          <button 
            onClick={() => setActiveTab('periods')}
            className={`w-full text-left px-4 py-3 rounded-lg flex items-center gap-3 ${activeTab === 'periods' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100'}`}
          >
            <span className="text-lg">📅</span> Financial Periods
          </button>
        </nav>
      </div>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col p-8 overflow-y-auto">
        <header className="flex justify-between items-center mb-8">
          <div>
            <h2 className="text-3xl font-bold text-gray-800 capitalize">{activeTab}</h2>
            {activeTab === 'reports' && (
              <div className="mt-4 flex gap-4">
                <button 
                  onClick={() => setActiveReport('trial-balance')}
                  className={`px-4 py-2 rounded-full text-sm font-bold ${activeReport === 'trial-balance' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}
                >
                  Trial Balance
                </button>
                <button 
                  onClick={() => setActiveReport('p-loss')}
                  className={`px-4 py-2 rounded-full text-sm font-bold ${activeReport === 'p-loss' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}
                >
                  Profit & Loss
                </button>
                <button 
                  onClick={() => setActiveReport('b-sheet')}
                  className={`px-4 py-2 rounded-full text-sm font-bold ${activeReport === 'b-sheet' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}
                >
                  Balance Sheet
                </button>
              </div>
            )}
          </div>
          <div className="flex gap-4">
            <div className="bg-white p-2 rounded shadow-sm flex items-center gap-2 border">
              <span className="text-sm font-medium">Company:</span>
              <select className="text-sm border-none focus:ring-0 bg-transparent font-bold">
                <option value="01">01 Globalize</option>
                <option value="02">02 Digitalize</option>
                <option value="05">05 Jusor</option>
              </select>
            </div>
          </div>
        </header>

        {/* Dynamic content based on tab */}
        <div className="bg-white shadow-xl rounded-2xl border border-gray-100 h-full">
          {activeTab === 'vouchers' && <JournalEntryForm />}
          {activeTab === 'reports' && activeReport === 'trial-balance' && <TrialBalance />}
          {activeTab === 'reports' && activeReport === 'p-loss' && <ProfitLoss />}
          {activeTab === 'reports' && activeReport === 'b-sheet' && <BalanceSheet />}
          {activeTab === 'coa' && <COAManager />}
          {activeTab === 'periods' && <PeriodManager />}
          {activeTab === 'payroll' && (
             <div className="p-12 text-center space-y-6">
               <div className="text-6xl">👔</div>
               <h3 className="text-2xl font-bold">Automated Payroll Run</h3>
               <p className="text-gray-500">Calculate salaries and generate accrual vouchers for 202507.</p>
               <button className="bg-indigo-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-indigo-200">
                 Execute Monthly Payroll
               </button>
             </div>
          )}
          {activeTab === 'profit' && (
             <div className="p-12 text-center space-y-6">
               <div className="text-6xl">💎</div>
               <h3 className="text-2xl font-bold">Profit Distribution</h3>
               <p className="text-gray-500">Distribute net profit to partners based on ownership shares.</p>
               <button className="bg-emerald-600 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-emerald-200">
                 Run Profit Distribution
               </button>
             </div>
          )}
        </div>
      </div>
    </div>
  );
};
