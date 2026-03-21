import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { TrendingUp, TrendingDown, DollarSign, PieChart } from 'lucide-react';

export const ProfitLoss: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['pl-report'],
    queryFn: async () => {
      const res = await fetch('/api/v1/accounting/reports/profit-loss?company_code=01&start_period=202501&end_period=202507');
      return res.json();
    }
  });

  if (isLoading) return <div className="p-8 animate-pulse">Calculating performance...</div>;

  const pl = data?.data || { income: [], expenses: [], total_income: 0, total_expenses: 0, net_profit: 0 };

  return (
    <div className="p-8 max-w-5xl mx-auto space-y-8">
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-3xl font-black text-slate-900">Profit & Loss Statement</h1>
          <p className="text-slate-500 font-medium">Performance Analysis: Jan 2025 - Jul 2025</p>
        </div>
        <div className={`px-6 py-3 rounded-2xl shadow-lg flex items-center gap-3 ${pl.net_profit >= 0 ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white'}`}>
          {pl.net_profit >= 0 ? <TrendingUp size={24} /> : <TrendingDown size={24} />}
          <div className="text-right">
             <div className="text-[10px] uppercase font-bold opacity-80">Net Profit</div>
             <div className="text-2xl font-black font-mono">
               {pl.net_profit?.toLocaleString(undefined, { minimumFractionDigits: 2 })}
             </div>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        {/* Income Section */}
        <div className="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
          <div className="p-6 bg-emerald-50 border-b border-emerald-100 flex justify-between items-center text-emerald-900">
             <h3 className="font-black flex items-center gap-2"><DollarSign size={20}/> Operating Revenue</h3>
             <span className="font-mono font-bold">{pl.total_income?.toLocaleString()}</span>
          </div>
          <div className="p-4 divide-y">
            {pl.income.map((row: any) => (
              <div key={row.account_code} className="py-3 flex justify-between items-center">
                 <div className="text-sm">
                   <div className="font-bold text-slate-700">{row.account_name_en}</div>
                   <div className="text-xs text-slate-400 font-mono">{row.account_code}</div>
                 </div>
                 <div className="font-mono font-bold text-emerald-600">{(row.cr - row.dr).toLocaleString()}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Expenses Section */}
        <div className="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
           <div className="p-6 bg-rose-50 border-b border-rose-100 flex justify-between items-center text-rose-900">
             <h3 className="font-black flex items-center gap-2"><PieChart size={20}/> Expenditures</h3>
             <span className="font-mono font-bold">{pl.total_expenses?.toLocaleString()}</span>
          </div>
          <div className="p-4 divide-y">
            {pl.expenses.map((row: any) => (
              <div key={row.account_code} className="py-3 flex justify-between items-center">
                 <div className="text-sm">
                   <div className="font-bold text-slate-700">{row.account_name_en}</div>
                   <div className="text-xs text-slate-400 font-mono">{row.account_code}</div>
                 </div>
                 <div className="font-mono font-bold text-rose-600">{(row.dr - row.cr).toLocaleString()}</div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
