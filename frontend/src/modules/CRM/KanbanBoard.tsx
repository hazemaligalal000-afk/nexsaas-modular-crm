import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { DollarSign, User, Calendar, MoreVertical } from 'lucide-react';

export const KanbanBoard: React.FC = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['deals-board'],
    queryFn: async () => {
      const res = await fetch('/api/v1/crm/deals/board?company_code=01');
      return res.json();
    }
  });

  if (isLoading) return <div className="p-8 animate-pulse text-lg font-bold">Loading Sales Pipeline...</div>;

  const board = data?.data || { prospecting: [], qualification: [], proposal: [], negotiation: [], closed_won: [] };

  const columns = [
    { id: 'prospecting', label: 'Prospecting', color: 'bg-slate-100' },
    { id: 'qualification', label: 'Qualification', color: 'bg-blue-50' },
    { id: 'proposal', label: 'Proposal', color: 'bg-indigo-50' },
    { id: 'negotiation', label: 'Negotiation', color: 'bg-amber-50' },
    { id: 'closed_won', label: 'Closed Won', color: 'bg-emerald-50' }
  ];

  return (
    <div className="p-8 h-full bg-slate-50 overflow-x-auto">
      <div className="flex gap-6 min-w-max h-full">
        {columns.map(col => (
          <div key={col.id} className={`w-80 rounded-3xl ${col.color} p-4 border border-slate-200/50 flex flex-col`}>
             <div className="flex justify-between items-center mb-6 px-2">
                <h3 className="font-black text-slate-800 uppercase text-xs tracking-widest">{col.label}</h3>
                <span className="bg-white/80 px-2 py-0.5 rounded-lg text-[10px] font-bold text-slate-500 shadow-sm">
                   {board[col.id]?.length || 0}
                </span>
             </div>
             
             <div className="space-y-4 flex-1 overflow-y-auto pr-1">
                {board[col.id]?.map((deal: any) => (
                   <div key={deal.id} className="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 group hover:shadow-md transition-all cursor-grab active:cursor-grabbing">
                      <div className="flex justify-between items-start mb-4">
                         <h4 className="font-bold text-slate-900 leading-tight group-hover:text-blue-600 transition-colors">{deal.title}</h4>
                         <MoreVertical size={16} className="text-slate-300" />
                      </div>
                      
                      <div className="space-y-3">
                         <div className="flex items-center gap-2 text-xs text-slate-500 font-medium">
                            <DollarSign size={14} className="text-emerald-500" />
                            <span className="font-mono font-bold text-slate-700">{deal.amount?.toLocaleString()}</span>
                         </div>
                         <div className="flex items-center gap-2 text-xs text-slate-500 font-medium">
                            <User size={14} />
                            <span>{deal.lead_name}</span>
                         </div>
                      </div>
                      
                      <div className="mt-5 pt-4 border-t border-slate-50 flex justify-between items-center">
                         <div className="flex items-center gap-1.5 text-[10px] text-slate-400 font-bold">
                            <Calendar size={12} />
                            {deal.expected_close_date}
                         </div>
                         <div className="h-2 w-12 bg-slate-100 rounded-full overflow-hidden">
                            <div className="h-full bg-blue-500 w-2/3"></div>
                         </div>
                      </div>
                   </div>
                ))}
                <button className="w-full py-3 rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm font-bold hover:border-blue-400 hover:text-blue-500 transition-all">
                   + Add Deal
                </button>
             </div>
          </div>
        ))}
      </div>
    </div>
  );
};
