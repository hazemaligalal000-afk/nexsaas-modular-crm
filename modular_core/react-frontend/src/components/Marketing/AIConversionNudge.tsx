import React from 'react';

/**
 * Phase 1 Quick Win: High-Intent Conversion Offer Widget
 * Psychological Trigger: Scarcity + Authority + Custom Value Proposition (CVP)
 */
export const AIConversionNudge = ({ trialDays = 4, leadsRemaining = 12 }) => {
    return (
        <div className="p-8 rounded-2xl bg-gradient-to-br from-blue-900/40 via-blue-900/10 to-transparent border border-blue-500/20 shadow-2xl relative overflow-hidden group">
            <div className="absolute top-0 right-0 p-8 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-500/10 transition-all duration-500"></div>
            
            <div className="flex items-start justify-between mb-8">
                <div>
                    <h3 className="text-2xl font-black text-white mb-2 leading-tight">
                        Don't Lose Your <br /> 
                        <span className="text-blue-400">AI Scoring Moat.</span>
                    </h3>
                    <p className="text-slate-400 text-sm max-w-[200px]">
                        Scoring lead intent gives you a 112% higher chance of winning deals. 
                    </p>
                </div>
                <div className="flex flex-col items-end gap-2 text-right">
                    <span className="bg-rose-500/20 text-rose-500 text-[10px] px-3 py-1 rounded-full font-black border border-rose-500/30 uppercase tracking-widest animate-pulse">Low SCARCITY</span>
                    <span className="text-[11px] text-slate-500 font-bold uppercase tracking-widest">{trialDays} Days Left</span>
                </div>
            </div>

            <div className="space-y-6 mb-10">
                <div className="flex items-center gap-4">
                    <div className="w-12 h-12 rounded-xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-xl">🤖</div>
                    <div>
                        <div className="text-sm font-bold text-slate-100">Intelligent Scoring</div>
                        <div className="text-[11px] text-slate-500">{leadsRemaining} Unprocessed Leads</div>
                    </div>
                </div>
                <div className="flex items-center gap-4">
                     <div className="w-12 h-12 rounded-xl bg-emerald-600/20 border border-emerald-500/30 flex items-center justify-center text-xl">🚀</div>
                    <div>
                        <div className="text-sm font-bold text-slate-100">Priority Support</div>
                        <div className="text-[11px] text-slate-500">Instant Whale-onboarding</div>
                    </div>
                </div>
            </div>

            <button className="w-full bg-blue-600 hover:bg-blue-500 text-white font-black py-4 rounded-xl shadow-[0_8px_32px_rgba(59,130,246,0.3)] transition-all transform hover:-translate-y-1 active:translate-y-0 text-sm tracking-widest uppercase mb-4">
                SECURE PRO ACCESS NOW →
            </button>
            
            <p className="text-[10px] text-center text-slate-500 font-semibold uppercase tracking-widest">
                Trusted by 5,000+ Revenue Teams Globally
            </p>
        </div>
    );
};
