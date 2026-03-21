import React, { useState } from 'react';

/**
 * High-Ticket White-Label Management Console (Phase 3 Forward)
 * Experience: Agency Authority & SaaS-as-a-Service (SaaaS)
 */
export const AgencyWhiteLabelConsole = ({ agencyName = "Nexus Marketing Group", subTenantsCnt = 42 }) => {
    return (
        <div className="flex flex-col bg-slate-950 p-12 min-h-screen font-sans">
            <div className="max-w-5xl w-full mx-auto">
                <header className="flex items-center justify-between mb-12">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-600 to-blue-700 flex items-center justify-center text-2xl shadow-2xl">⚡</div>
                        <div>
                             <h1 className="text-3xl font-black text-white italic tracking-tighter uppercase">{agencyName}</h1>
                             <p className="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Agency Partner Portal • NexSaaS High-Ticket</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="bg-emerald-500/10 border border-emerald-500/20 px-6 py-3 rounded-2xl text-center">
                            <div className="text-2xl font-black text-emerald-500">${(subTenantsCnt * 29 * 0.2).toFixed(2)}</div>
                            <div className="text-[9px] text-slate-600 font-bold uppercase tracking-widest">Monthly Payout (20%)</div>
                        </div>
                    </div>
                </header>

                <div className="grid grid-cols-3 gap-8 mb-12">
                    {[
                        { label: 'Sub-Tenants', value: subTenantsCnt, icon: '🏢' },
                        { label: 'Active Leads', value: '1,240', icon: '🔥' },
                        { label: 'Custom Domain', value: 'crm.nexus.com', icon: '🌐' }
                    ].map((stat, i) => (
                        <div key={i} className="p-8 bg-slate-900 border border-slate-800 rounded-3xl shadow-xl flex flex-col gap-2">
                            <span className="text-2xl mb-2">{stat.icon}</span>
                            <span className="text-[10px] text-slate-500 font-black uppercase tracking-widest">{stat.label}</span>
                            <span className="text-3xl font-black text-white">{stat.value}</span>
                        </div>
                    ))}
                </div>

                <section className="p-10 bg-slate-900 border border-slate-800 rounded-[40px] shadow-2xl">
                     <h3 className="text-lg font-black text-white mb-8 flex items-center gap-3">
                         🎨 White-Label Branding 
                         <span className="text-xs font-normal text-slate-500 uppercase tracking-widest">(Agency Level)</span>
                     </h3>

                     <div className="grid grid-cols-2 gap-10">
                         <div className="space-y-6">
                             <div>
                                 <label className="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2 block">Agency Sub-Domain</label>
                                 <input value="nexus-crm" readOnly className="w-full bg-slate-950 border border-slate-800 text-slate-400 py-4 px-6 rounded-xl text-sm" />
                             </div>
                             <div>
                                 <label className="text-[10px] text-slate-500 font-black uppercase tracking-widest mb-2 block">Primary Identity Color</label>
                                 <div className="flex items-center gap-4">
                                     <div className="w-12 h-12 rounded-xl bg-indigo-600 border border-white/10 shadow-xl"></div>
                                     <span className="text-slate-400 font-mono text-sm uppercase">#4f46e5</span>
                                 </div>
                             </div>
                         </div>
                         <div className="bg-slate-950/50 border border-slate-800/50 rounded-3xl p-8 flex flex-col items-center justify-center text-center">
                             <div className="w-20 h-20 rounded-2xl bg-slate-800/30 flex items-center justify-center text-3xl mb-4 text-slate-600">🖼️</div>
                             <p className="text-xs text-slate-500 font-bold leading-relaxed mb-6">Logo must be 512x512 Transparent PNG <br /> for optimal sidebar display.</p>
                             <button className="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black px-8 py-3 rounded-full transition-all tracking-widest uppercase">Update Logo</button>
                         </div>
                     </div>
                </section>
            </div>
        </div>
    );
};
