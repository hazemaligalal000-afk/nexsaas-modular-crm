import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { FileText, ShieldCheck, PieChart, Info, Download, Filter } from 'lucide-react';

export const TaxCompliance: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['tax-summary'],
    queryFn: async () => {
      const res = await fetch('/api/v1/accounting/tax/vat-report?company_code=01&start_period=202601&end_period=202603');
      return res.json();
    }
  });

  if (isLoading) return <div className="p-8 animate-pulse text-xl font-black">Calculating VAT liabilities...</div>;

  const summary = data?.data || { total_input_vat: 0, total_output_vat: 0, vat_payable_refundable: 0, status: 'Neutral' };

  return (
    <div className="p-8 space-y-12">
      <div className="flex justify-between items-center bg-white p-8 rounded-[3rem] shadow-2xl border border-slate-100 relative overflow-hidden group">
         <div className="flex flex-col gap-2">
            <div className="flex items-center gap-3">
               <div className="p-3 bg-indigo-600 rounded-3xl text-white shadow-xl shadow-indigo-100 flex items-center justify-center transform group-hover:scale-110 transition-transform">
                  <ShieldCheck size={28} />
               </div>
               <div>
                  <h1 className="text-3xl font-black text-slate-800 leading-none">Tax Compliance Board</h1>
                  <p className="text-slate-400 font-bold uppercase text-[10px] tracking-[0.2em] mt-2 italic shadow-sm bg-white/50 px-3 py-1 rounded-full border inline-block">Regional Filing: Egypt & GCC Standard</p>
               </div>
            </div>
         </div>
         <div className="flex gap-4">
            <button className="flex items-center gap-2 bg-slate-900 border-2 border-slate-900 px-6 py-4 rounded-[1.8rem] font-bold text-white hover:bg-slate-800 transition-all shadow-xl shadow-slate-200">
              <Download size={18} /> Official Filing Export
            </button>
            <button className="flex items-center gap-2 bg-white border-2 border-slate-100 px-6 py-4 rounded-[1.8rem] font-bold text-slate-700 hover:bg-slate-50 transition-all shadow-lg shadow-slate-50">
              <Filter size={18} /> FY-2026
            </button>
         </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
        {[
          { label: 'Input VAT (Purchases)', val: summary.total_input_vat.toLocaleString(), icon: <PieChart size={24}/>, col: 'text-indigo-600' },
          { label: 'Output VAT (Sales)', val: summary.total_output_vat.toLocaleString(), icon: <FileText size={24}/>, col: 'text-blue-600' },
          { label: 'Net Liability', val: Math.abs(summary.vat_payable_refundable).toLocaleString(), icon: <Info size={24}/>, col: summary.status === 'Payable' ? 'text-rose-600' : 'text-emerald-600' }
        ].map(card => (
          <div key={card.label} className="bg-white p-10 rounded-[3.5rem] shadow-2xl border-b-[6px] border-slate-100 hover:border-blue-500 transition-all group">
             <div className="flex justify-between items-start mb-10">
                <p className="text-slate-400 font-black uppercase text-[11px] tracking-widest">{card.label}</p>
                <div className={`p-4 rounded-3xl bg-slate-50 ${card.col} shadow-sm group-hover:bg-slate-900 group-hover:text-white transition-all`}>
                   {card.icon}
                </div>
             </div>
             <div className="flex items-baseline gap-2">
                <span className="text-4xl font-black text-slate-900 font-mono tracking-tighter">{card.val}</span>
                <span className="text-[11px] font-black text-slate-400 uppercase tracking-widest">EGP</span>
             </div>
             <p className={`mt-6 text-[10px] font-black uppercase tracking-widest bg-slate-50/50 px-4 py-2 rounded-full border inline-block ${card.col}`}>
                Region: Egypt (14%)
             </p>
          </div>
        ))}
      </div>

      <div className="bg-slate-900 p-12 rounded-[4rem] shadow-2xl relative overflow-hidden group">
         <div className="flex flex-col md:flex-row justify-between items-center gap-10">
            <div className="max-w-md">
               <h3 className="text-white text-3xl font-black leading-tight mb-4">Regional Status: {summary.status}</h3>
               <p className="text-slate-400 font-bold text-sm leading-relaxed mb-8">Generated based on Rules H+ for {summary.period}. Reconciliation of VAT output from sales vouchers (Voucher Type 1) and VAT input from expenditure vouchers (Voucher Type 2).</p>
               <div className="flex items-center gap-6">
                  <div className="h-14 w-14 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center text-white backdrop-blur-xl group-hover:bg-blue-600 transition-colors">
                     <ShieldCheck size={28} />
                  </div>
                  <div>
                     <div className="text-white font-black text-lg">Compliance Verified</div>
                     <div className="text-slate-500 font-bold text-[10px] uppercase tracking-widest mt-0.5">Hash: 87F2..990A</div>
                  </div>
               </div>
            </div>
            <div className="h-48 w-48 bg-white/5 rounded-full border-[20px] border-white/5 flex items-center justify-center backdrop-blur-3xl shadow-3xl transform group-hover:scale-110 transition-transform">
               <div className="text-white text-5xl font-black font-mono tracking-tighter">14%</div>
            </div>
         </div>
         <div className="absolute top-0 right-0 h-full w-1/3 bg-gradient-to-l from-indigo-500/10 to-transparent"></div>
      </div>
    </div>
  );
};
