import React from 'react';

/**
 * Phase 3 Advanced Scaling: Mobile Unicorn Experience
 * A high-density, touch-optimized "Revenue Dashboard" for field sales teams.
 * Fulfills the "Unicorn Scaling - Mobile" requirement.
 */
export const NexSaaSMobileDashboard = ({ totalRevenue = '$152,000', leadsToday = 8, dealsWon = 3 }) => {
    return (
        <div className="flex flex-col min-h-screen bg-slate-950 p-6 font-sans select-none">
            {/* Mobile Sticky Header */}
            <div className="flex items-center justify-between mb-10 mt-2">
                <div className="flex items-center gap-3">
                     <div className="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-xl shadow-[0_4px_24px_rgba(59,130,246,0.3)]">🦄</div>
                     <span className="text-xl font-black text-white tracking-tight">NexSaaS</span>
                </div>
                <div className="flex items-center gap-4">
                     <div className="w-10 h-10 rounded-full border border-slate-800 bg-slate-900 flex items-center justify-center text-lg">🔔</div>
                     <div className="w-10 h-10 rounded-full border border-blue-500/30 bg-blue-600/10 flex items-center justify-center text-sm font-bold text-blue-400">HG</div>
                </div>
            </div>

            {/* Micro-Metrics Grid (High Density) */}
            <div className="grid grid-cols-2 gap-4 mb-8">
                <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex flex-col gap-1">
                    <span className="text-[10px] text-slate-500 font-extrabold uppercase tracking-widest">Leads Today</span>
                    <span className="text-2xl font-black text-white">{leadsToday}</span>
                    <span className="text-[10px] text-emerald-500 font-bold">+12% vs ytd</span>
                </div>
                <div className="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex flex-col gap-1">
                    <span className="text-[10px] text-slate-500 font-extrabold uppercase tracking-widest">Deals Won</span>
                    <span className="text-2xl font-black text-white">{dealsWon}</span>
                    <span className="text-[10px] text-blue-400 font-bold">New Record 🚀</span>
                </div>
            </div>

            {/* Priority AI Feed (Actionable Mobile Intelligence) */}
            <h3 className="text-xs text-slate-500 font-black uppercase tracking-widest mb-4">Priority AI Intel</h3>
            <div className="space-y-4 mb-12">
                 {[
                    { lead: 'Ahmed Galal', score: 92, intent: 'Buying', time: '12m ago' },
                    { lead: 'Sara Smith', score: 88, intent: 'Inquiry', time: '45m ago' }
                 ].map((intel, i) => (
                    <div key={i} className="p-5 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-900/50 border border-slate-800 flex items-center justify-between active:scale-95 transition-all">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 rounded-xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center text-lg">👤</div>
                            <div>
                                <div className="text-sm font-bold text-white leading-none mb-1">{intel.lead}</div>
                                <div className="text-[10px] text-slate-500 font-bold uppercase tracking-widest">{intel.intent} • {intel.time}</div>
                            </div>
                        </div>
                        <div className="text-right">
                            <div className="text-xs font-black text-blue-400">{intel.score}</div>
                            <div className="text-[9px] text-slate-600 font-bold uppercase">AI SCORE</div>
                        </div>
                    </div>
                 ))}
            </div>

            {/* Major Revenue Display */}
            <div className="p-10 rounded-[32px] bg-gradient-to-br from-blue-600 to-indigo-700 shadow-[0_24px_48px_rgba(59,130,246,0.3)] relative overflow-hidden">
                <div className="absolute -top-10 -right-10 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
                <span className="text-xs text-white/60 font-black uppercase tracking-widest block mb-2">Platform Revenue</span>
                <h2 className="text-4xl font-black text-white">{totalRevenue}</h2>
                <div className="mt-8 flex items-center gap-2">
                    <div className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></div>
                    <span className="text-[10px] text-white/50 font-bold uppercase tracking-widest leading-none">Global Growth Engine Active</span>
                </div>
            </div>

            {/* Mobile Bottom Navigation (Native Style) */}
            <div className="fixed bottom-0 left-0 right-0 p-6 bg-slate-950/80 backdrop-blur-xl border-t border-slate-900 flex items-center justify-around">
                <div className="text-blue-500 text-2xl cursor-pointer">🏠</div>
                <div className="text-slate-600 text-2xl cursor-pointer">💬</div>
                <div className="text-slate-600 text-2xl cursor-pointer">🤖</div>
                <div className="text-slate-600 text-2xl cursor-pointer">⚙️</div>
            </div>
        </div>
    );
};
