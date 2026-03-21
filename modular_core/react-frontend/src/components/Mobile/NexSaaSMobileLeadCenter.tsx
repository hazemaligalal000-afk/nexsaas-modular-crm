import React, { useState } from 'react';

/**
 * Phase 3 Forward Component: Mobile High-Conversion AI Dialer & Leads
 * Professional Mobile Experience for Global Teams (GCC/Egypt/USD markets)
 */
export const NexSaaSMobileLeadCenter = () => {
    const [leads, setLeads] = useState([
        { id: 1, name: "Hazem Ali", phone: "01002233445", score: 95, intent: "Buying", status: "Contacted" },
        { id: 2, name: "Sara Gamal", phone: "01223344556", score: 88, intent: "Asking", status: "Inbound" }
    ]);

    return (
        <div className="flex flex-col min-h-screen bg-slate-950 p-6 font-sans">
            <header className="flex items-center justify-between mb-8">
                <h2 className="text-2xl font-black text-white italic tracking-tighter">NX CENTER</h2>
                <div className="flex items-center gap-3">
                    <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span className="text-[10px] text-slate-500 font-bold uppercase tracking-widest leading-none">AI Active</span>
                    <div className="w-8 h-8 rounded-full border border-slate-800 bg-slate-900"></div>
                </div>
            </header>

            <div className="space-y-4 mb-20">
                {leads.map((lead) => (
                    <div key={lead.id} className="p-5 rounded-[24px] bg-slate-900 border border-slate-800 flex flex-col gap-4 active:scale-95 transition-all shadow-xl">
                        <div className="flex items-start justify-between">
                            <div className="flex gap-4">
                                <div className="w-12 h-12 rounded-2xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center text-xl">👤</div>
                                <div>
                                    <h4 className="font-bold text-white text-md leading-none mb-1">{lead.name}</h4>
                                    <p className="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-2">{lead.phone}</p>
                                    <span className={`text-[9px] px-2 py-0.5 rounded-full font-black uppercase tracking-widest border ${lead.intent === 'Buying' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-blue-500/10 text-blue-500 border-blue-500/20'}`}>
                                        {lead.intent} Intent
                                    </span>
                                </div>
                            </div>
                            <div className="text-right">
                                <div className="text-2xl font-black text-blue-400 leading-none">{lead.score}</div>
                                <div className="text-[8px] text-slate-700 font-black uppercase tracking-widest mt-1">AI Score</div>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-3 pt-2 border-t border-slate-800/50">
                            <button className="bg-slate-800 hover:bg-slate-700 text-white text-[11px] font-black py-3 rounded-xl tracking-widest uppercase">WhatsApp</button>
                            <button className="bg-blue-600 hover:bg-blue-500 text-white text-[11px] font-black py-3 rounded-xl tracking-widest uppercase shadow-[0_4px_16px_rgba(59,130,246,0.3)]">Dial Now</button>
                        </div>
                    </div>
                ))}
            </div>

            {/* Mobile Footer Tab Bar (Forward-Compatible) */}
            <nav className="fixed bottom-0 left-0 right-0 p-6 bg-slate-950/90 backdrop-blur-xl border-t border-slate-900 flex justify-around">
                <span className="text-2xl text-blue-500">🏠</span>
                <span className="text-2xl text-slate-700">💬</span>
                <span className="text-2xl text-slate-700">🤖</span>
                <span className="text-2xl text-slate-700">⚙️</span>
            </nav>
        </div>
    );
};
