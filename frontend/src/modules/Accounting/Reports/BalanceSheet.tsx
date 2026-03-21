import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Scale, Briefcase, CreditCard, Landmark } from 'lucide-react';

export const BalanceSheet: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['bs-report'],
    queryFn: async () => {
      const res = await fetch('/api/v1/accounting/reports/balance-sheet?company_code=01&fin_period=202507');
      return res.json();
    }
  });

  if (isLoading) return <div className="p-8 animate-pulse text-xl font-bold">Balancing the books...</div>;

  const bs = data?.data || { assets: [], liabilities: [], equity: [], total_assets: 0, total_liabilities: 0, total_equity: 0 };

  return (
    <div className="p-8 max-w-6xl mx-auto space-y-12">
      <div className="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 bg-slate-900 p-8 rounded-[2rem] text-white overflow-hidden relative">
        <div className="space-y-4">
          <div className="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 rounded-full text-xs font-bold uppercase tracking-wider text-slate-400">
             <Landmark size={14} /> Financial Statement
          </div>
          <h1 className="text-4xl font-black">Statement of Financial Position</h1>
          <p className="text-slate-400 font-medium">Reporting Period Ending: July 31, 2025</p>
        </div>
        <div className="flex items-center gap-6">
           <div className="text-right">
             <div className="text-xs uppercase font-bold text-slate-500">Asset Base</div>
             <div className="text-3xl font-black font-mono text-emerald-400">
                {bs.total_assets.toLocaleString(undefined, { minimumFractionDigits: 2 })}
             </div>
           </div>
           <div className="h-10 w-[1px] bg-slate-700"></div>
           <div className="text-right">
             <div className="text-xs uppercase font-bold text-slate-500">Liabilities + Equity</div>
             <div className="text-3xl font-black font-mono text-blue-400">
                {(bs.total_liabilities + bs.total_equity).toLocaleString(undefined, { minimumFractionDigits: 2 })}
             </div>
           </div>
        </div>
        <Scale size={160} className="absolute right-[-40px] top-[-40px] text-white/5 pointer-events-none" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
        {/* Assets Section */}
        <div className="space-y-6">
          <h2 className="text-xl font-black flex items-center gap-3 text-emerald-600 border-b pb-4">
             <Briefcase size={24} /> Assets
          </h2>
          <div className="bg-white rounded-3xl p-6 shadow-sm border space-y-4">
            {bs.assets.map((a: any) => (
              <div key={a.account_code} className="flex justify-between items-center group cursor-default">
                 <div className="text-sm">
                   <div className="font-bold text-slate-700 group-hover:text-emerald-600 transition-colors">{a.account_name_en}</div>
                   <div className="text-xs text-slate-400 font-mono">{a.account_code}</div>
                 </div>
                 <div className="font-mono font-bold text-slate-900 bg-slate-50 px-3 py-1 rounded-lg">
                    {a.balance.toLocaleString()}
                 </div>
              </div>
            ))}
          </div>
        </div>

        {/* Liabilities & Equity Section */}
        <div className="space-y-12">
          {/* Liabilities */}
          <div className="space-y-6">
            <h2 className="text-xl font-black flex items-center gap-3 text-rose-600 border-b pb-4">
               <CreditCard size={24} /> Liabilities
            </h2>
            <div className="bg-white rounded-3xl p-6 shadow-sm border space-y-4">
              {bs.liabilities.map((l: any) => (
                <div key={l.account_code} className="flex justify-between items-center">
                   <div className="text-sm">
                     <div className="font-bold text-slate-700">{l.account_name_en}</div>
                     <div className="text-xs text-slate-400 font-mono">{l.account_code}</div>
                   </div>
                   <div className="font-mono font-bold text-slate-900 bg-slate-50 px-3 py-1 rounded-lg">
                      {Math.abs(l.balance).toLocaleString()}
                   </div>
                </div>
              ))}
            </div>
          </div>

          {/* Equity */}
          <div className="space-y-6 pt-6">
            <h2 className="text-xl font-black flex items-center gap-3 text-blue-600 border-b pb-4">
               <Scale size={24} /> Shareholders' Equity
            </h2>
            <div className="bg-white rounded-3xl p-6 shadow-sm border space-y-4">
              {bs.equity.map((e: any) => (
                <div key={e.account_code} className="flex justify-between items-center">
                   <div className="text-sm">
                     <div className="font-bold text-slate-700">{e.account_name_en}</div>
                     <div className="text-xs text-slate-400 font-mono">{e.account_code}</div>
                   </div>
                   <div className="font-mono font-bold text-slate-900 bg-slate-50 px-3 py-1 rounded-lg">
                      {Math.abs(e.balance).toLocaleString()}
                   </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
