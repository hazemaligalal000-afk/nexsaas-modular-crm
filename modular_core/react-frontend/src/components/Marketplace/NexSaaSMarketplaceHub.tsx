import React, { useState } from 'react';

/**
 * Phase 5 Global Dominance: Developer Marketplace Portal
 * Experience: Platform-as-a-Service (PaaS) & Community Growth
 */
export const NexSaaSMarketplaceHub = () => {
    const [plugins, setPlugins] = useState([
        { name: "DocuSign Bridge", category: "Legal", author: "Verified", icon: "✍️", price: "Free" },
        { name: "Trello Sync Pro", category: "Project Mgmt", author: "Partner", icon: "📋", price: "$12/mo" },
        { name: "Zoom One-Tap", category: "Meetings", author: "Verified", icon: "📹", price: "Free" }
    ]);

    return (
        <div className="flex flex-col bg-slate-950 p-12 min-h-screen font-sans">
            <div className="max-w-6xl w-full mx-auto">
                <header className="flex items-center justify-between mb-16 px-4">
                    <div>
                         <h1 className="text-4xl font-black text-white italic tracking-tighter uppercase mb-2">NX MARKETPLACE</h1>
                         <p className="text-[11px] text-slate-500 font-bold uppercase tracking-widest letter-spacing-1">Extend NexSaaS with 250+ One-Click Plugins.</p>
                    </div>
                    <button className="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black px-10 py-4 rounded-2xl transition-all shadow-2xl tracking-widest uppercase">
                        BUILD A PLUGIN ➔
                    </button>
                </header>

                <div className="grid grid-cols-3 gap-10">
                    {plugins.map((p, i) => (
                        <div key={i} className="group p-8 bg-slate-900 border border-slate-800 rounded-[40px] hover:border-blue-500/50 transition-all shadow-2xl relative overflow-hidden flex flex-col items-center text-center">
                            <div className="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-all">
                                <span className="text-6xl">{p.icon}</span>
                            </div>

                            <div className="w-20 h-20 rounded-[28px] bg-slate-950 border border-slate-800 flex items-center justify-center text-3xl mb-6 shadow-xl group-hover:scale-110 transition-all duration-500">
                                {p.icon}
                            </div>

                            <div className="flex flex-col mb-8 gap-1">
                                <span className="text-[10px] text-slate-600 font-extrabold uppercase tracking-widest">{p.category}</span>
                                <h4 className="text-xl font-bold text-white tracking-tight">{p.name}</h4>
                                <span className="text-[11px] text-slate-400 font-medium">By {p.author}</span>
                            </div>

                            <div className="w-full flex items-center justify-between mt-auto">
                                <span className="text-sm font-black text-blue-400">{p.price}</span>
                                <button className="bg-slate-800 group-hover:bg-blue-600 text-white text-[10px] font-black px-6 py-2 rounded-xl transition-all tracking-widest uppercase">INSTALL</button>
                            </div>
                        </div>
                    ))}
                </div>

                <footer className="mt-20 p-12 bg-gradient-to-br from-blue-900/20 to-transparent border border-blue-500/20 rounded-[48px] flex flex-col items-center text-center">
                     <h2 className="text-3xl font-black text-white mb-4">The NexSaaS Ecosystem is Yours.</h2>
                     <p className="max-w-xl text-slate-400 text-sm leading-relaxed mb-10 italic">Leverage our Unified API to build the next generation of sales tools directly into the NexSaaS workflow engine. Reach 100k+ global users instantly.</p>
                     <div className="flex items-center gap-10 text-[10px] text-slate-600 font-black uppercase tracking-widest">
                         <span>OAuth 2.0 Ready</span>
                         <span>Webhook Hooks</span>
                         <span>Full Sandbox</span>
                     </div>
                </footer>
            </div>
        </div>
    );
};
