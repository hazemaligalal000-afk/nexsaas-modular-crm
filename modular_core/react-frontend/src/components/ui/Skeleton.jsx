import React from 'react';

/**
 * Premium Skeleton Loader Component
 * Requirement: 6.146 - Modern UX Async States
 */
export const Skeleton = ({ width = '100%', height = '20px', borderRadius = '4px', className = '' }) => {
    return (
        <div 
            className={`skeleton-shimmer ${className}`}
            style={{
                width,
                height,
                borderRadius,
                background: '#1e293b',
                position: 'relative',
                overflow: 'hidden',
                marginBottom: '8px'
            }}
        >
            <style>{`
                .skeleton-shimmer::after {
                    content: "";
                    position: absolute;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    left: 0;
                    transform: translateX(-100%);
                    background-image: linear-gradient(
                        90deg,
                        rgba(255, 255, 255, 0) 0,
                        rgba(255, 255, 255, 0.05) 20%,
                        rgba(255, 255, 255, 0.1) 60%,
                        rgba(255, 255, 255, 0)
                    );
                    animation: shimmer 2s infinite;
                }
                @keyframes shimmer {
                    100% {
                        transform: translateX(100%);
                    }
                }
            `}</style>
        </div>
    );
};

export const TableSkeleton = ({ rows = 5 }) => (
    <div style={{ width: '100%' }}>
        {[...Array(rows)].map((_, i) => (
            <div key={i} style={{ display: 'flex', gap: '12px', padding: '12px 0', borderBottom: '1px solid #1e293b' }}>
                <Skeleton width="40px" height="40px" borderRadius="8px" />
                <div style={{ flex: 1 }}>
                    <Skeleton width="30%" height="16px" />
                    <Skeleton width="60%" height="12px" />
                </div>
                <Skeleton width="100px" height="32px" borderRadius="100px" />
            </div>
        ))}
    </div>
);
