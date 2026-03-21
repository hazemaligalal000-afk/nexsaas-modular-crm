import React from 'react';

/**
 * High-Converting Launch Component: Social Proof Notification Engine
 * Psychological Trigger: 'The Herd Effect' & 'Frictionless Growth'
 */
export const SocialProofNotification = ({ location = "Cairo", action = "just started a Free Trial" }) => {
    return (
        <div className="fixed bottom-8 left-8 p-6 bg-slate-950/80 backdrop-blur-xl border border-slate-800 rounded-2xl shadow-[0_24px_48px_rgba(0,0,0,0.5)] flex items-center gap-5 translate-y-0 opacity-100 transition-all duration-1000 group">
            <div className="w-12 h-12 rounded-xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center text-xl shadow-[0_0_12px_rgba(59,130,246,0.3)]">
                🚀
            </div>
            <div>
                <p className="text-[11px] text-slate-500 font-black uppercase tracking-widest leading-none mb-1">Live Social Proof</p>
                <div className="text-sm font-bold text-slate-200">
                    Someone in <span className="text-blue-400">{location}</span> {action}!
                </div>
            </div>
            <div className="absolute top-0 right-0 p-2 opacity-10 group-hover:opacity-100 italic text-[8px] text-slate-600 cursor-default">
                NexSaaS Verified
            </div>
        </div>
    );
};
