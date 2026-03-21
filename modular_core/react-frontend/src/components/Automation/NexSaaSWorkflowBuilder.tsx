import React, { useState } from 'react';

/**
 * Phase 2 Core Scaling: Visual Workflow "No-Code" Interface
 * Provides the "Enterprise Flow Builder" experience to compete with HubSpot.
 */
export const NexSaaSWorkflowBuilder = () => {
    const [steps, setSteps] = useState([
        { id: 1, type: 'trigger', label: 'Lead Created', icon: '⚡' },
        { id: 2, type: 'action', label: 'Score with Claude AI', icon: '🤖' },
        { id: 3, type: 'condition', label: 'If Score > 80', icon: '⚖️' },
        { id: 4, type: 'action', label: 'Send WhatsApp Nudge', icon: '💬' }
    ]);

    return (
        <div className="flex flex-col items-center bg-slate-950 p-12 min-h-screen font-sans">
            <div className="max-w-4xl w-full">
                <div className="flex items-center justify-between mb-12">
                     <h1 className="text-3xl font-black text-slate-100 flex items-center gap-3">
                         🌊 Workflow <span className="text-blue-500">Builder</span>
                     </h1>
                     <button className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-2 rounded-lg shadow-2xl transition-all">
                         PUBLISH TO PROD
                     </button>
                </div>

                <div className="relative space-y-16">
                    {steps.map((step, index) => (
                        <div key={step.id} className="relative flex flex-col items-center group">
                            {/* Visual Flow Line */}
                            {index !== steps.length - 1 && (
                                <div className="absolute top-20 w-1 bg-gradient-to-b from-blue-500/50 to-indigo-500/20 h-16 group-hover:from-blue-400 group-hover:to-indigo-300 transition-all"></div>
                            )}

                            {/* Workflow Node */}
                            <div className={`w-72 p-6 rounded-2xl border ${step.type === 'trigger' ? 'bg-amber-500/10 border-amber-500/30' : 'bg-slate-900 border-slate-800'} 
                                            group-hover:translate-x-2 transition-all cursor-pointer shadow-2xl`}>
                                <div className="flex items-center gap-4">
                                    <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-xl 
                                                    ${step.type === 'trigger' ? 'bg-amber-500/20' : 'bg-blue-600/20 border border-blue-500/20'}`}>
                                        {step.icon}
                                    </div>
                                    <div>
                                        <div className="text-[10px] text-slate-500 uppercase tracking-widest font-black leading-none mb-1">
                                            {step.type}
                                        </div>
                                        <div className="text-sm font-extrabold text-slate-100">
                                            {step.label}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            {/* Node Connectors (HubSpot Inspired) */}
                            {index !== steps.length - 1 && (
                                <div className="z-10 bg-blue-600 w-8 h-8 rounded-full border-4 border-slate-950 flex items-center justify-center -mb-8 mt-8 cursor-pointer hover:scale-125 transition-all">
                                    <span className="text-white text-lg font-black">+</span>
                                </div>
                            )}
                        </div>
                    ))}
                    
                    {/* Add New Final Step Button */}
                    <div className="flex justify-center mt-12 opacity-50 hover:opacity-100 transition-all">
                        <div className="w-16 h-1 bg-slate-800 rounded-full"></div>
                    </div>
                </div>

                {/* Automation Log Feed (Authority Signal) */}
                <div className="mt-20 p-6 bg-slate-900/50 border border-slate-800 rounded-2xl">
                    <h3 className="text-xs text-slate-500 font-black uppercase tracking-widest mb-4">Live Execution Feed</h3>
                    <div className="space-y-2 font-mono text-[11px]">
                         <div className="text-emerald-500 flex items-center gap-2">
                             <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                             [01:49:12] Workflow "High Intent Sync" executed for Lead #8821
                         </div>
                         <div className="text-slate-500">[01:48:55] Delaying 120s for Lead #8819...</div>
                    </div>
                </div>
            </div>
        </div>
    );
};
