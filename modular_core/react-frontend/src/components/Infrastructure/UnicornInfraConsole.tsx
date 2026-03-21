import React, { useState } from 'react';

/**
 * Phase 4 Scaling Dashboard: Unicorn Infrastructure Monitoring
 * Experience: Enterprise Authority & Zero-Downtime Reliability
 */
export const UnicornInfraConsole = ({ activePods = 12, cpuUsage = 42, queueDepth = 124 }) => {
    return (
        <div className="flex flex-col bg-slate-950 p-12 min-h-screen font-sans">
            <div className="max-w-5xl w-full mx-auto">
                <header className="flex items-center justify-between mb-12">
                    <div className="flex items-center gap-4">
                        <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-800 to-indigo-950 flex items-center justify-center text-2xl shadow-2xl border border-white/10">☸️</div>
                        <div>
                             <h1 className="text-3xl font-black text-white italic tracking-tighter uppercase">INFRA CONSOLE</h1>
                             <p className="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Status: High-Availability • Regional Clusters Active</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-4">
                        <span className="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span className="text-[11px] text-slate-400 font-black uppercase tracking-widest">Global Ingress OK</span>
                    </div>
                </header>

                <div className="grid grid-cols-3 gap-8 mb-12">
                    {[
                        { label: 'Active Pods', value: activePods, status: 'Scaling Up', icon: '🐳' },
                        { label: 'CPU Cluster Load', value: `${cpuUsage}%`, status: 'Healthy', icon: '⚡' },
                        { label: 'Job Queue Depth', value: queueDepth, status: 'Processing', icon: '🚥' }
                    ].map((metric, i) => (
                        <div key={i} className="p-8 bg-slate-900 border border-slate-800 rounded-3xl shadow-xl hover:border-blue-500/30 transition-all flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <span className="text-2xl">{metric.icon}</span>
                                <span className="text-[9px] text-slate-500 font-black uppercase tracking-widest px-3 py-1 bg-slate-950 border border-slate-800 rounded-full">{metric.status}</span>
                            </div>
                            <div>
                                <span className="text-[10px] text-slate-500 font-black uppercase tracking-widest block mb-1">{metric.label}</span>
                                <span className="text-4xl font-black text-white">{metric.value}</span>
                            </div>
                        </div>
                    ))}
                </div>

                <section className="p-10 bg-slate-900 border border-slate-800 rounded-[40px] shadow-2xl relative overflow-hidden group">
                     <div className="absolute top-0 right-0 p-10 w-96 h-96 bg-blue-600/5 rounded-full blur-3xl group-hover:bg-blue-600/10 transition-all duration-500"></div>
                     <h3 className="text-lg font-black text-white mb-10 flex items-center gap-3">
                         🚀 Deployment Lifecycle 
                         <span className="text-xs font-normal text-slate-500 uppercase tracking-widest">(Rolling Update v2.4.1 Active)</span>
                     </h3>

                     <div className="space-y-6">
                         {[
                            { step: 'Ingress Mapping', status: 'COMPLETED', time: '12m ago' },
                            { step: 'SSL Cert Generation', status: 'COMPLETED', time: '11m ago' },
                            { step: 'Pod Rebalancing', status: 'IN_PROGRESS', time: '2m ago' }
                         ].map((l, i) => (
                            <div key={i} className="flex items-center justify-between p-5 rounded-2xl bg-slate-950/50 border border-slate-800/50">
                                <span className="text-sm font-bold text-slate-300 italic">{l.step}</span>
                                <div className="flex items-center gap-4">
                                     <span className="text-[10px] text-slate-600 font-bold uppercase tracking-widest">{l.time}</span>
                                     <span className={`px-4 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest border ${l.status === 'COMPLETED' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-blue-500/10 text-blue-500 border-blue-500/20 animate-pulse'}`}>
                                         {l.status}
                                     </span>
                                </div>
                            </div>
                         ))}
                     </div>
                </section>
            </div>
        </div>
    );
};
