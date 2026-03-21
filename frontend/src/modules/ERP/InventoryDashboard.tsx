import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Package, Truck, Activity, TrendingUp, AlertCircle } from 'lucide-react';

export const InventoryDashboard: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['inventory-summary'],
    queryFn: async () => {
      const res = await fetch('/api/v1/erp/inventory/summary?company_code=01');
      return res.json();
    }
  });

  if (isLoading) return <div className="p-8 animate-pulse text-xl font-black">Scanning Warehouses...</div>;

  const summary = data?.data || [];

  return (
    <div className="p-8 space-y-12">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        {[
          { label: 'Total SKU', val: summary.length, icon: <Package size={24}/>, col: 'bg-blue-600', shadow: 'shadow-blue-200' },
          { label: 'Stock Valuation', val: summary.reduce((a:any, b:any) => a + (b.current_qty * b.avg_unit_cost), 0).toLocaleString(), icon: <TrendingUp size={24}/>, col: 'bg-emerald-600', shadow: 'shadow-emerald-200' },
          { label: 'Low Stock Alerts', val: 0, icon: <AlertCircle size={24}/>, col: 'bg-amber-600', shadow: 'shadow-amber-200' },
          { label: 'Pending Shipments', val: 5, icon: <Truck size={24}/>, col: 'bg-slate-800', shadow: 'shadow-slate-200' }
        ].map(card => (
          <div key={card.label} className="bg-white p-6 rounded-[2.5rem] shadow-xl border border-slate-100/50 flex flex-col justify-between group overflow-hidden relative">
             <div className="flex justify-between items-start mb-6">
                <p className="text-slate-400 font-bold uppercase text-[10px] tracking-widest">{card.label}</p>
                <div className={`p-4 rounded-3xl ${card.col} text-white shadow-lg ${card.shadow} transform group-hover:scale-110 transition-transform`}>
                   {card.icon}
                </div>
             </div>
             <p className="text-3xl font-black text-slate-800 font-mono tracking-tight">{card.val}</p>
             <div className="h-12 w-full absolute bottom-[-20px] left-0 opacity-5 group-hover:opacity-10 transition-opacity">
                <svg viewBox="0 0 100 20" className="w-full h-full"><path d="M0 15 Q 10 5, 20 15 T 40 15 T 60 15 T 80 15 T 100 15" stroke="currentColor" fill="transparent" strokeWidth="2"/></svg>
             </div>
          </div>
        ))}
      </div>

      <div className="bg-white rounded-[3rem] shadow-2xl border border-slate-100 overflow-hidden">
        <div className="p-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
           <h3 className="text-xl font-black flex items-center gap-3 text-slate-800 tracking-tight">
              <Activity size={24} className="text-blue-600" /> Active Inventory Levels
           </h3>
           <div className="bg-white border text-[10px] font-black uppercase text-slate-400 px-4 py-2 rounded-full shadow-sm">
             Warehouse: MAIN-GIZ-01
           </div>
        </div>
        <div className="p-4 grid grid-cols-1 divide-y divide-slate-50">
           {summary.map((item: any) => (
              <div key={item.item_code} className="p-6 flex justify-between items-center group hover:bg-slate-50/50 transition-colors">
                 <div className="flex items-center gap-6">
                    <div className="h-16 w-16 rounded-3xl bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-blue-600 group-hover:text-white transition-all shadow-sm">
                       <Package size={24} />
                    </div>
                    <div>
                       <div className="font-black text-slate-800 text-lg">{item.item_name_en}</div>
                       <div className="text-sm text-slate-400 font-mono tracking-tighter">{item.item_code}</div>
                    </div>
                 </div>
                 <div className="h-10 w-[1px] bg-slate-100 mx-8"></div>
                 <div className="text-right">
                    <div className="text-[10px] uppercase font-bold text-slate-400 mb-1">Stock On Hand</div>
                    <div className="text-2xl font-black font-mono text-slate-900">{item.current_qty} Units</div>
                 </div>
                 <div className="w-48 text-right px-8">
                    <div className="text-[10px] uppercase font-bold text-slate-400 mb-1">Total Book Value</div>
                    <div className="text-lg font-black font-mono text-emerald-600">{(item.current_qty * item.avg_unit_cost).toLocaleString()}</div>
                 </div>
                 <button className="bg-slate-900 text-white p-4 rounded-3xl shadow-lg shadow-slate-200 opacity-0 group-hover:opacity-100 transition-all font-bold text-xs uppercase tracking-widest whitespace-nowrap">
                    View Ledger
                 </button>
              </div>
           ))}
        </div>
      </div>
    </div>
  );
};
