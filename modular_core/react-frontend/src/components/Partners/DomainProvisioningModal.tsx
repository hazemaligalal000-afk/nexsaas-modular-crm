import React, { useState } from 'react';

/**
 * High-Ticket Domain Provisioning Modal (Phase 3 Forward)
 * Experience: Zero-Friction Enterprise Ingress
 */
export const DomainProvisioningModal = ({ agencyName = "Nexus Marketing Group", currentDomain = "crm.nexus.com" }) => {
    const [status, setStatus] = useState('pending'); // 'pending', 'verified', 'error'

    return (
        <div className="fixed inset-0 bg-slate-950/90 backdrop-blur-3xl flex items-center justify-center p-6 z-[9999]">
            <div className="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-[32px] p-10 shadow-[0_32px_64px_rgba(0,0,0,0.8)] relative overflow-hidden group">
                <div className="absolute top-0 right-0 p-10 w-48 h-48 bg-blue-600/5 rounded-full blur-3xl group-hover:bg-blue-600/10 transition-all duration-500"></div>

                <div className="flex flex-col items-center text-center mb-10">
                    <div className="w-20 h-20 rounded-3xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center text-3xl mb-6 shadow-2xl">🌐</div>
                    <h3 className="text-2xl font-black text-white italic tracking-tighter uppercase mb-2">PROVISIONING DOMAIN</h3>
                    <p className="text-xs text-slate-500 font-bold leading-relaxed uppercase tracking-widest px-8">Point your CNAME to <span className="text-blue-400">cname.nexsaas.com</span> to activate white-labeling for {agencyName}.</p>
                </div>

                <div className="space-y-6 mb-10">
                    <div className="p-5 rounded-2xl bg-slate-950/50 border border-slate-800 flex items-center justify-between">
                        <div>
                             <span className="text-[10px] text-slate-600 font-black uppercase tracking-widest block mb-1">Target Hosting</span>
                             <span className="text-sm font-bold text-slate-300 italic">{currentDomain}</span>
                        </div>
                        <span className={`px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest border ${status === 'pending' ? 'bg-amber-500/10 text-amber-500 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'}`}>
                            {status === 'pending' ? 'Verifying DNS...' : 'DNS ACTIVE'}
                        </span>
                    </div>
                </div>

                <div className="flex flex-col gap-4">
                    <button className="w-full bg-blue-600 hover:bg-blue-500 text-white text-xs font-black py-5 rounded-2xl shadow-[0_8px_32px_rgba(59,130,246,0.3)] transition-all transform hover:-translate-y-1 active:translate-y-0 tracking-widest uppercase">
                        RE-CHECK DNS RECORD ➔
                    </button>
                    <p className="text-[9px] text-center text-slate-700 font-bold uppercase tracking-widest">SSL Certificate generation (Let's Encrypt) is handled automatically.</p>
                </div>
            </div>
        </div>
    );
};
