import React from 'react';

/**
 * Requirement 19: Loading Skeletons for AI-enhanced UX (Phase 3)
 */
export const SkeletonCard = () => (
    <div style={{ background: '#0d1a30', border: '1px solid #1e3a5f', borderRadius: '24px', padding: '30px', flex: 1, minWidth: '240px', overflow: 'hidden', position: 'relative' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
            <div style={{ width: '32px', height: '32px', borderRadius: '8px', background: '#1e3a5f' }} />
            <div style={{ width: '40px', height: '18px', borderRadius: '6px', background: '#1e3a5f' }} />
        </div>
        <div style={{ width: '60%', height: '12px', borderRadius: '4px', background: '#1e3a5f', marginBottom: '12px' }} />
        <div style={{ width: '80%', height: '24px', borderRadius: '6px', background: '#1e3a5f' }} />
        <div style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, background: 'linear-gradient(to right, transparent, rgba(29, 78, 216, 0.1), transparent)', animation: 'skeleton-shimmer 1.5s infinite' }} />
        <style>{`
          @keyframes skeleton-shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
          }
        `}</style>
    </div>
);

export const SkeletonGraph = () => (
    <div style={{ height: '350px', background: '#0b1628', padding: '30px', borderRadius: '24px', border: '1px solid #1e3a5f', overflow: 'hidden', position: 'relative' }}>
        <div style={{ width: '200px', height: '18px', borderRadius: '6px', background: '#1e3a5f', marginBottom: '12px' }} />
        <div style={{ width: '400px', height: '12px', borderRadius: '4px', background: '#1e3a5f', marginBottom: '40px' }} />
        <div style={{ display: 'flex', alignItems: 'flex-end', gap: '8px', height: '200px' }}>
            {[1, 2, 3, 4, 5, 6, 7, 8].map(i => (
                <div key={i} style={{ flex: 1, background: '#1e3a5f', height: `${Math.random()*100}%`, borderRadius: '6px 6px 0 0' }} />
            ))}
        </div>
         <div style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, background: 'linear-gradient(to right, transparent, rgba(29, 78, 216, 0.05), transparent)', animation: 'skeleton-shimmer 1.5s infinite' }} />
    </div>
);
