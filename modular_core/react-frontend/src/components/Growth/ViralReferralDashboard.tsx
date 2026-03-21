import React, { useState } from 'react';

/**
 * Viral Growth Dashboard Component (Phase 1 React Implementation)
 * Psychological Trigger: 'Invite 3 -> Get 1 Month Free'
 */
export const ViralReferralDashboard = ({ referralsCount = 1, refLink = "https://nexsaas.com/ref/NX_ABCD123" }) => {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(refLink);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="p-8 bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl relative overflow-hidden group">
            <div className="absolute top-0 right-0 p-8 w-48 h-48 bg-blue-500/5 rounded-full blur-3xl pointer-events-none group-hover:bg-blue-500/10 transition-all duration-500"></div>

            <div className="flex items-start justify-between mb-8">
                <div>
                    <h3 className="text-2xl font-black text-white leading-tight mb-2">
                        Get <span className="text-blue-400">NexSaaS Pro</span> <br /> 
                        For FREE 🍭
                    </h3>
                    <p className="text-slate-400 text-sm max-w-[200px]">
                        Invite 3 friends and unlock 1 Month of Pro Access automatically.
                    </p>
                </div>
                <div className="bg-blue-600/10 border border-blue-500/20 px-4 py-3 rounded-2xl text-center">
                    <div className="text-2xl font-black text-blue-400">{referralsCount}/3</div>
                    <div className="text-[9px] text-slate-500 font-bold uppercase tracking-widest">Referrals</div>
                </div>
            </div>

            <div className="mb-8 space-y-4">
                <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                    <div className="h-full bg-gradient-to-r from-blue-600 to-indigo-500 transition-all duration-1000" style={{ width: `${(referralsCount/3)*100}%` }}></div>
                </div>
                <div className="flex justify-between text-[10px] text-slate-500 font-bold uppercase tracking-widest">
                    <span>Started</span>
                    <span className="text-blue-400">Bonus Unlocked at 3</span>
                </div>
            </div>

            <div className="relative">
                <input 
                    readOnly 
                    value={refLink} 
                    className="w-full bg-slate-950 border border-slate-800 text-slate-400 text-xs py-4 px-6 rounded-xl pr-24"
                />
                <button 
                    onClick={handleCopy}
                    className="absolute right-2 top-2 bottom-2 bg-blue-600 hover:bg-blue-500 text-white text-[10px] px-6 rounded-lg font-black transition-all"
                >
                    {copied ? 'COPIED!' : 'COPY LINK'}
                </button>
            </div>
            
            <p className="mt-6 text-[10px] text-center text-slate-600 font-semibold italic">
                “NexSaaS is even better with friends (and for free).”
            </p>
        </div>
    );
};
