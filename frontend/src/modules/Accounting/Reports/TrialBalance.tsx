import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

interface TrialBalanceRow {
  account_code: string;
  account_name_en: string;
  account_name_ar: string;
  account_type: string;
  total_dr: number;
  total_cr: number;
  net_balance: number;
}

export const TrialBalance: React.FC = () => {
  const [period, setPeriod] = useState('202507');

  const { data: tbData, isLoading } = useQuery({
    queryKey: ['trial-balance', period],
    queryFn: async () => {
      const response = await fetch(`/api/accounting/reports/trial-balance?fin_period=${period}`);
      return response.json();
    }
  });

  const totals = (tbData?.data || []).reduce((acc: any, curr: TrialBalanceRow) => ({
    dr: acc.dr + parseFloat(curr.total_dr.toString()),
    cr: acc.cr + parseFloat(curr.total_cr.toString())
  }), { dr: 0, cr: 0 });

  return (
    <div className="p-8 h-full flex flex-col bg-white">
      <div className="flex justify-between items-center mb-8 border-b pb-6">
        <div>
          <h2 className="text-3xl font-extrabold text-gray-900">Trial Balance Report</h2>
          <p className="text-gray-500 mt-1">Summary of all ledger balances for the period.</p>
        </div>
        <div className="flex gap-4">
          <select 
            value={period} 
            onChange={(e) => setPeriod(e.target.value)}
            className="border rounded-xl px-4 py-2 font-mono text-sm bg-gray-50 hover:bg-white focus:ring-2 focus:ring-blue-500 transition-all outline-none"
          >
            <option value="202507">July 2025 (202507)</option>
            <option value="202506">June 2025 (202506)</option>
            <option value="202505">May 2025 (202505)</option>
          </select>
          <button className="bg-emerald-600 text-white px-6 py-2 rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition-all hover:-translate-y-0.5">
            Export PDF
          </button>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        <table className="w-full text-sm">
          <thead className="sticky top-0 bg-gray-50 shadow-sm">
            <tr className="text-gray-600 uppercase tracking-wider font-bold border-b">
              <th className="p-4 text-left">Account</th>
              <th className="p-4 text-left">Description</th>
              <th className="p-4 text-right">Debit (EGP)</th>
              <th className="p-4 text-right">Credit (EGP)</th>
              <th className="p-4 text-right">Net Balance</th>
            </tr>
          </thead>
          <tbody className="divide-y">
            {(tbData?.data || []).map((row: TrialBalanceRow) => (
              <tr key={row.account_code} className="hover:bg-blue-50/40 transition-colors">
                <td className="p-4 font-mono font-bold text-blue-700">{row.account_code}</td>
                <td className="p-4">
                  <div className="font-semibold text-gray-800">{row.account_name_en}</div>
                  <div className="text-xs text-gray-400 font-arabic" dir="rtl">{row.account_name_ar}</div>
                </td>
                <td className="p-4 text-right font-mono text-gray-700">{Math.abs(row.total_dr).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                <td className="p-4 text-right font-mono text-gray-700">{Math.abs(row.total_cr).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                <td className={`p-4 text-right font-mono font-bold ${row.net_balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                  {row.net_balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot className="sticky bottom-0 bg-gray-900 text-white shadow-2xl">
            <tr className="font-bold text-lg">
              <td colSpan={2} className="p-5 text-right uppercase tracking-widest text-xs opacity-70">Report Totals</td>
              <td className="p-5 text-right font-mono">{totals.dr.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
              <td className="p-5 text-right font-mono">{totals.cr.toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
              <td className="p-5 text-right font-mono text-emerald-400">
                {(totals.dr - totals.cr).toLocaleString(undefined, { minimumFractionDigits: 2 })}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  );
};
