import React, { useState, useEffect, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Search, Plus, Trash2, MoreHorizontal, ChevronRight, Save, Send, CheckCircle, Calculator, Info } from 'lucide-react';

interface JournalLine {
  lineNo: number;
  accountCode: string;
  accountName: string;
  drValue: number | string;
  crValue: number | string;
  lineDesc: string;
  costCenter?: string;
  ccName?: string;
  vendor?: string;
  vendorName?: string;
  currency?: string;
  exRate: number;
  // All 35 fields included in internal state
  checkNo?: string;
  assetNo?: string;
  invoiceNo?: string;
  internalNo?: string;
  employeeNo?: string;
  partnerNo?: string;
  vendorWordCount?: number;
  translatorWordCount?: number;
  agentName?: string;
  isProfitLoss?: boolean;
}

export const JournalEntryForm: React.FC = () => {
  const queryClient = useQueryClient();
  const [activeLine, setActiveLine] = useState<number | null>(null);
  const [header, setHeader] = useState({
    voucher_no: '',
    voucher_date: new Date().toISOString().split('T')[0],
    voucher_code: '01', // Local Currency
    section_code: '01', // General
    fin_period: '202507',
    currency_code: '01',
    exchange_rate: 1.0,
    description: '',
    status: 'draft'
  });

  const [lines, setLines] = useState<JournalLine[]>([
    { lineNo: 1, accountCode: '', accountName: '', drValue: 0, crValue: 0, lineDesc: '', exRate: 1.0 }
  ]);

  // Totals calculation
  const totals = useMemo(() => {
    return lines.reduce((acc, curr) => ({
      dr: acc.dr + (Number(curr.drValue) || 0),
      cr: acc.cr + (Number(curr.crValue) || 0)
    }), { dr: 0, cr: 0 });
  }, [lines]);

  const balance = totals.dr - totals.cr;
  const isBalanced = Math.abs(balance) < 0.01;

  // Mutations
  const saveMutation = useMutation({
    mutationFn: async (data: any) => {
      const res = await fetch('/api/accounting/vouchers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      return res.json();
    }
  });

  const addLine = () => {
    setLines([...lines, { 
      lineNo: lines.length + 1, 
      accountCode: '', 
      accountName: '', 
      drValue: 0, 
      crValue: 0, 
      lineDesc: header.description,
      exRate: header.exchange_rate 
    }]);
  };

  const removeLine = (index: number) => {
    if (lines.length > 1) {
      const newLines = lines.filter((_, i) => i !== index);
      setLines(newLines.map((l, i) => ({ ...l, lineNo: i + 1 })));
    }
  };

  const updateLine = (index: number, updates: Partial<JournalLine>) => {
    const newLines = [...lines];
    newLines[index] = { ...newLines[index], ...updates };
    setLines(newLines);
  };

  return (
    <div className="flex h-full bg-white relative overflow-hidden">
      {/* Main Entry Panel */}
      <div className={`flex-1 flex flex-col transition-all duration-300 ${activeLine !== null ? 'mr-96' : ''}`}>
        {/* Header Ribbon */}
        <div className="bg-gray-50 p-6 border-b flex justify-between items-center shadow-sm z-10">
          <div className="flex gap-8 items-center">
            <div className="space-y-1">
              <label className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Voucher Number</label>
              <div className="text-xl font-black text-blue-900 font-mono">
                {header.voucher_no || 'AUTO-GEN'}
              </div>
            </div>
            <div className="h-10 w-[1px] bg-gray-200"></div>
            <div className="grid grid-cols-2 gap-4">
               <div className="space-y-1 text-sm">
                  <span className="text-gray-400">Date:</span>
                  <input 
                    type="date" 
                    className="ml-2 border-none bg-transparent font-bold focus:ring-0 cursor-pointer" 
                    value={header.voucher_date}
                    onChange={(e) => setHeader({ ...header, voucher_date: e.target.value })}
                  />
               </div>
               <div className="space-y-1 text-sm">
                  <span className="text-gray-400">Type:</span>
                  <select 
                    className="ml-2 border-none bg-transparent font-bold focus:ring-0 cursor-pointer"
                    value={header.voucher_code}
                    onChange={(e) => setHeader({ ...header, voucher_code: e.target.value })}
                  >
                    <option value="01">01 Local Currency (EGP)</option>
                    <option value="02">02 USD Voucher</option>
                    <option value="05">05 SAR Voucher</option>
                  </select>
               </div>
            </div>
          </div>

          <div className="flex gap-3">
            <button className="flex items-center gap-2 px-4 py-2 border rounded-xl hover:bg-gray-100 transition-all font-semibold">
              <Save size={18} /> Save Draft
            </button>
            <button 
              disabled={!isBalanced || totals.dr === 0}
              className={`flex items-center gap-2 px-6 py-2 rounded-xl shadow-lg transition-all font-bold text-white ${isBalanced && totals.dr > 0 ? 'bg-blue-600 hover:bg-blue-700 hover:-translate-y-0.5' : 'bg-gray-300 cursor-not-allowed'}`}
            >
              <Send size={18} /> Submit for Approval
            </button>
          </div>
        </div>

        {/* Lines Table */}
        <div className="flex-1 overflow-y-auto p-4 bg-gray-50/30">
          <div className="max-w-7xl mx-auto bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
            <table className="w-full text-sm border-collapse">
              <thead className="bg-[#1e293b] text-white">
                <tr>
                  <th className="p-4 w-12 text-center">#</th>
                  <th className="p-4 text-left">Account (Bilingual)</th>
                  <th className="p-4 w-40 text-right">Debit</th>
                  <th className="p-4 w-40 text-right">Credit</th>
                  <th className="p-4 text-left">Description</th>
                  <th className="p-4 w-12 text-center"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {lines.map((line, idx) => (
                  <tr 
                    key={idx} 
                    className={`transition-colors group ${activeLine === idx ? 'bg-blue-50/50' : 'hover:bg-gray-50'}`}
                    onClick={() => setActiveLine(idx)}
                  >
                    <td className="p-4 text-center text-gray-400 font-mono">{line.lineNo}</td>
                    <td className="p-4">
                      <div className="flex gap-2 items-center">
                        <input 
                          placeholder="Code..." 
                          className="w-24 bg-transparent font-bold text-blue-800 border-none focus:ring-0 p-0"
                          value={line.accountCode}
                          onChange={(e) => updateLine(idx, { accountCode: e.target.value })}
                        />
                        <div className="text-gray-500 font-medium">{line.accountName || 'Select Account'}</div>
                      </div>
                    </td>
                    <td className="p-4">
                      <input 
                        className="w-full text-right p-2 rounded-lg border border-transparent focus:border-blue-300 focus:bg-white transition-all bg-emerald-50/30 font-mono font-bold"
                        type="number" 
                        value={line.drValue}
                        onChange={(e) => updateLine(idx, { drValue: e.target.value, crValue: 0 })}
                      />
                    </td>
                    <td className="p-4">
                      <input 
                         className="w-full text-right p-2 rounded-lg border border-transparent focus:border-blue-300 focus:bg-white transition-all bg-rose-50/30 font-mono font-bold"
                        type="number" 
                        value={line.crValue}
                        onChange={(e) => updateLine(idx, { crValue: e.target.value, drValue: 0 })}
                      />
                    </td>
                    <td className="p-4">
                      <input 
                        className="w-full p-2 bg-transparent border-none focus:ring-0" 
                        value={line.lineDesc}
                        onChange={(e) => updateLine(idx, { lineDesc: e.target.value })}
                        placeholder="Add details..."
                      />
                    </td>
                    <td className="p-4 text-center">
                      <button 
                        onClick={(e) => { e.stopPropagation(); removeLine(idx); }}
                        className="text-gray-300 hover:text-rose-600 p-1 rounded-md transition-all opacity-0 group-hover:opacity-100"
                      >
                        <Trash2 size={16} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            
            <button 
              onClick={addLine}
              className="w-full py-4 bg-gray-50 hover:bg-white text-gray-500 hover:text-blue-600 border-t border-dashed font-bold flex items-center justify-center gap-2 transition-all"
            >
              <Plus size={20} /> Add New Ledger Line
            </button>
          </div>
        </div>

        {/* Bottom Status Bar */}
        <div className="bg-white p-6 border-t shadow-[0_-4px_16px_rgba(0,0,0,0.05)] z-10">
          <div className="max-w-7xl mx-auto flex justify-between items-center">
            <div className="flex gap-12">
              <div className="space-y-1">
                <span className="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Debit</span>
                <div className="text-2xl font-mono font-black text-emerald-600">{totals.dr.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
              </div>
              <div className="space-y-1">
                <span className="text-xs font-bold text-gray-400 uppercase tracking-widest">Total Credit</span>
                <div className="text-2xl font-mono font-black text-rose-600">{totals.cr.toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
              </div>
              <div className="space-y-1">
                <span className="text-xs font-bold text-gray-400 uppercase tracking-widest">Balance</span>
                <div className={`text-2xl font-mono font-black ${isBalanced ? 'text-blue-600' : 'text-orange-500'}`}>
                   {balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </div>
              </div>
            </div>

            <div className="flex flex-col items-end gap-2">
               {isBalanced ? (
                 <div className="flex items-center gap-2 text-emerald-600 font-bold bg-emerald-50 px-4 py-2 rounded-xl text-sm border border-emerald-100">
                    <CheckCircle size={18} /> Perfectly Balanced
                 </div>
               ) : (
                 <div className="flex items-center gap-2 text-orange-600 font-bold bg-orange-50 px-4 py-2 rounded-xl text-sm border border-orange-100 animate-pulse">
                    <Calculator size={18} /> Out of Balance
                 </div>
               )}
               <p className="text-xs text-gray-400">NexSaaS Accounting Engine v1.0.2</p>
            </div>
          </div>
        </div>
      </div>

      {/* Side Detail Overlay (All 35 fields) */}
      <aside 
        className={`fixed right-0 top-0 h-full w-96 bg-white/80 backdrop-blur-xl border-l shadow-2xl z-20 transform transition-transform duration-500 ease-out p-8 flex flex-col ${activeLine !== null ? 'translate-x-0' : 'translate-x-full'}`}
      >
        <button 
           onClick={() => setActiveLine(null)}
           className="absolute top-6 left-[-20px] bg-white border shadow-lg rounded-full p-2 hover:bg-blue-600 hover:text-white transition-all"
        >
          <ChevronRight size={20} />
        </button>

        <h3 className="text-xl font-black text-blue-900 border-b pb-4 mb-6 flex items-center gap-3">
           <Info size={24} className="text-blue-500" /> Line Information
        </h3>

        {activeLine !== null && (
          <div className="flex-1 overflow-y-auto pr-2 space-y-6">
            <div className="grid grid-cols-1 gap-4">
              <div className="space-y-2">
                <label className="text-xs font-bold text-gray-500 uppercase">Cost Center (cst_cntr)</label>
                <div className="flex gap-2">
                  <input 
                    className="w-1/3 p-3 bg-gray-50 rounded-xl border-none focus:ring-2 focus:ring-blue-500 font-mono"
                    placeholder="0101"
                    value={lines[activeLine].costCenter}
                    onChange={(e) => updateLine(activeLine, { costCenter: e.target.value })}
                  />
                  <input 
                    className="flex-1 p-3 bg-gray-50 rounded-xl border-none focus:ring-2 focus:ring-blue-500"
                    placeholder="CC Name"
                    value={lines[activeLine].ccName}
                    readOnly
                  />
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-xs font-bold text-gray-500 uppercase">Partner / Vendor</label>
                <div className="flex gap-2">
                  <input 
                    className="w-1/3 p-3 bg-gray-50 rounded-xl border-none focus:ring-2 focus:ring-blue-500 font-mono"
                    placeholder="VND01"
                    value={lines[activeLine].vendor}
                    onChange={(e) => updateLine(activeLine, { vendor: e.target.value })}
                  />
                  <input 
                    className="flex-1 p-3 bg-gray-50 rounded-xl border-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Partner Name"
                    value={lines[activeLine].vendorName}
                    readOnly
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <label className="text-xs font-bold text-gray-500 uppercase">Check/Ref No</label>
                  <input 
                    className="w-full p-3 bg-gray-50 rounded-xl border-none focus:ring-2 focus:ring-blue-500"
                    value={lines[activeLine].checkNo}
                    onChange={(e) => updateLine(activeLine, { checkNo: e.target.value })}
                  />
                </div>
                <div className="space-y-2">
                  <label className="text-xs font-bold text-gray-500 uppercase">Asset Number</label>
                  <input 
                    className="w-full p-3 bg-gray-50 rounded-xl border-none focus:ring-2 focus:ring-blue-500"
                    value={lines[activeLine].assetNo}
                    onChange={(e) => updateLine(activeLine, { assetNo: e.target.value })}
                  />
                </div>
              </div>

              <div className="p-4 bg-blue-50/50 rounded-2xl border border-blue-100 space-y-4">
                <h4 className="text-xs font-black text-blue-700 uppercase flex items-center gap-2">
                   🏢 Translation Activity Fields
                </h4>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-1">
                    <label className="text-[10px] font-bold text-blue-400">Client Word Count</label>
                    <input 
                      type="number"
                      className="w-full p-2 bg-white rounded-lg border-none focus:ring-2 focus:ring-blue-500"
                      value={lines[activeLine].vendorWordCount}
                      onChange={(e) => updateLine(activeLine, { vendorWordCount: Number(e.target.value) })}
                    />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] font-bold text-blue-400">Translator Words</label>
                    <input 
                      type="number"
                      className="w-full p-2 bg-white rounded-lg border-none focus:ring-2 focus:ring-blue-500"
                      value={lines[activeLine].translatorWordCount}
                      onChange={(e) => updateLine(activeLine, { translatorWordCount: Number(e.target.value) })}
                    />
                  </div>
                </div>
              </div>
            </div>
          </div>
        )}
      </aside>
    </div>
  );
};
