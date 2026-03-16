import React, { useState } from 'react';

export default function WorkflowCanvas() {
    const [nodes, setNodes] = useState([
        { id: 1, type: 'trigger', label: 'Deal Won', color: '#10b981', x: 50, y: 50 },
        { id: 2, type: 'condition', label: 'Amount > $10k', color: '#f59e0b', x: 250, y: 50 },
        { id: 3, type: 'action', label: 'Create Invoice', color: '#3b82f6', x: 450, y: 50 },
        { id: 4, type: 'action', label: 'Notify Success Team', color: '#8b5cf6', x: 450, y: 150 }
    ]);

    const [edges, setEdges] = useState([
        { from: 1, to: 2 },
        { from: 2, to: 3 },
        { from: 2, to: 4 }
    ]);

    return (
        <div style={{ padding: '20px', background: '#f8fafc', borderRadius: '12px', border: '1px solid #e2e8f0', height: '400px', position: 'relative', overflow: 'hidden' }}>
            <h3 style={{ marginTop: 0 }}>Visual Automations Builder</h3>
            
            <div style={{ position: 'absolute', top: '60px', left: '20px', right: '20px', bottom: '20px', background: 'white', border: '1px solid #cbd5e1', borderRadius: '8px', backgroundImage: 'radial-gradient(#e2e8f0 1px, transparent 1px)', backgroundSize: '20px 20px' }}>
                
                {/* Render Edges (Simple SVG lines for mock) */}
                <svg style={{ position: 'absolute', width: '100%', height: '100%', pointerEvents: 'none' }}>
                    {edges.map((edge, idx) => {
                        const fromNode = nodes.find(n => n.id === edge.from);
                        const toNode = nodes.find(n => n.id === edge.to);
                        if (!fromNode || !toNode) return null;
                        return (
                            <line 
                                key={idx} 
                                x1={fromNode.x + 100} 
                                y1={fromNode.y + 25} 
                                x2={toNode.x} 
                                y2={toNode.y + 25} 
                                stroke="#94a3b8" 
                                strokeWidth="2" 
                                markerEnd="url(#arrow)" 
                            />
                        )
                    })}
                    <defs>
                        <marker id="arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                            <path d="M 0 0 L 10 5 L 0 10 z" fill="#94a3b8" />
                        </marker>
                    </defs>
                </svg>

                {/* Render Nodes */}
                {nodes.map(node => (
                    <div key={node.id} style={{
                        position: 'absolute',
                        left: node.x,
                        top: node.y,
                        width: '120px',
                        padding: '12px',
                        background: 'white',
                        border: `2px solid ${node.color}`,
                        borderRadius: '8px',
                        boxShadow: '0 4px 6px -1px rgba(0,0,0,0.1)',
                        textAlign: 'center',
                        fontSize: '12px',
                        fontWeight: 'bold',
                        color: '#334155',
                        cursor: 'grab'
                    }}>
                        <div style={{ fontSize: '10px', color: node.color, textTransform: 'uppercase', marginBottom: '4px' }}>{node.type}</div>
                        {node.label}
                    </div>
                ))}

            </div>
            
            <button style={{ position: 'absolute', bottom: '30px', right: '30px', background: '#3b82f6', color: 'white', border: 'none', padding: '8px 16px', borderRadius: '4px', cursor: 'pointer' }}>
                + Add Node
            </button>
        </div>
    );
}
