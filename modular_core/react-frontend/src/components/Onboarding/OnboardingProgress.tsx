import React from 'react';

/**
 * Phase 1 Quick Win: Frictionless Onboarding Dashboard Widget
 * Directs user to complete the "Critical 3" integration steps for initial value.
 */
export const OnboardingProgress = ({ percent = 33, steps = ['AI Engine', 'WhatsApp', 'Stripe'] }) => {
    return (
        <div className="p-6 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl relative overflow-hidden group hover:border-blue-500/50 transition-all duration-300">
            <div className="absolute top-0 right-0 p-4 opacity-5 group-hover:opacity-10 pointer-events-none">
                <div className="w-24 h-24 bg-blue-500 rounded-full blur-2xl"></div>
            </div>
            
            <h2 className="text-xl font-extrabold text-slate-100 flex items-center gap-2 mb-2">
                🚀 Unicorn Onboarding <span className="text-sm font-normal text-slate-400">({percent}%)</span>
            </h2>
            
            <p className="text-slate-400 text-sm mb-6">
                Complete these 3 tasks to unlock full AI lead scoring potential.
            </p>

            <div className="space-y-4">
                {steps.map((step, i) => (
                    <div key={i} className="flex items-center justify-between group/item">
                        <span className="text-slate-200 font-semibold">{step}</span>
                        <div className="flex items-center gap-3">
                           {i === 0 ? (
                                <span className="bg-emerald-500/10 text-emerald-500 text-[10px] px-2 py-1 rounded-full uppercase tracking-widest font-bold border border-emerald-500/20">Active</span>
                           ) : (
                                <button className="bg-blue-600 hover:bg-blue-500 text-white text-[10px] px-3 py-1 rounded-full font-extrabold transition-all group-hover/item:px-4">ACTIVATE</button>
                           )}
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-8">
                <div className="w-full bg-slate-800 h-2 rounded-full overflow-hidden">
                    <div className="h-full bg-gradient-to-r from-blue-600 to-indigo-500 transition-all duration-1000" style={{ width: `${percent}%` }}></div>
                </div>
            </div>
            
            <div className="mt-4 flex items-center justify-between">
                <span className="text-[11px] text-slate-500 uppercase tracking-widest font-bold">Time to First Value: 12m</span>
                <span className="text-[11px] text-blue-400 font-bold hover:underline cursor-pointer">View Guide →</span>
            </div>
        </div>
    );
};
