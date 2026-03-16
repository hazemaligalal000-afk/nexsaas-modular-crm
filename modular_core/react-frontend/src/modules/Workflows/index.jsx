import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';
import { eventBus } from '../../core/EventBus';
import WorkflowCanvas from './WorkflowCanvas';

export default function WorkflowsModule({ basePath }) {
    const [workflows, setWorkflows] = useState([]);
    const [view, setView] = useState('list'); // 'list' or 'visual'

    useEffect(() => {
        const fetchFlows = async () => {
            try {
                const res = await apiClient.get('/Workflows');
                setWorkflows(res.data);
            } catch (err) {
                setWorkflows([
                    { id: 1, name: 'Auto-Assign High Value Deals', trigger: 'deal.stage_changed', actions: 'Create Task, Notify Slack', active: true }
                ]);
            }
        };
        fetchFlows();

        const listenKb = eventBus.on('kb.article.published', payload => handleSystemEvent('kb.article.published', payload));
        const listenInv = eventBus.on('inventory.low_stock', payload => handleSystemEvent('inventory.low_stock', payload));
        
        return () => {
            listenKb();
            listenInv();
        };
    }, []);

    const handleSystemEvent = (event_name, payload) => {
        console.log(`[Workflow Engine Caught Event] ${event_name}`, payload);
    };

    return (
        <div style={{ padding: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
                <div>
                    <h2 style={{ margin: 0 }}>Workflow Automation Studio</h2>
                    <div style={{ marginTop: '8px' }}>
                        <button 
                            onClick={() => setView('list')} 
                            style={{ 
                                padding: '6px 16px', 
                                background: view === 'list' ? '#3b82f6' : 'white', 
                                color: view === 'list' ? 'white' : '#64748b',
                                border: '1px solid #e2e8f0',
                                borderRadius: '4px 0 0 4px',
                                cursor: 'pointer'
                            }}
                        >
                            List View
                        </button>
                        <button 
                            onClick={() => setView('visual')} 
                            style={{ 
                                padding: '6px 16px', 
                                background: view === 'visual' ? '#3b82f6' : 'white', 
                                color: view === 'visual' ? 'white' : '#64748b',
                                border: '1px solid #e2e8f0',
                                borderRadius: '0 4px 4px 0',
                                cursor: 'pointer'
                            }}
                        >
                            Visual Designer
                        </button>
                    </div>
                </div>
                <button style={{ background: '#10b981', color: '#fff', border: 'none', padding: '10px 20px', borderRadius: '8px', fontWeight: 'bold' }}>
                    + Create New Flow
                </button>
            </div>
            
            {view === 'list' ? (
                <div style={{ background: 'white', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                    <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
                                <th style={{ padding: '16px' }}>ID</th>
                                <th>Automation Name</th>
                                <th>Trigger Event</th>
                                <th>Actions</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {workflows.map(flow => (
                                <tr key={flow.id} style={{ borderBottom: '1px solid #f1f5f9' }}>
                                    <td style={{ padding: '16px' }}>{flow.id}</td>
                                    <td><strong>{flow.name}</strong></td>
                                    <td><code style={{ background: '#f1f5f9', padding: '2px 6px', color: '#3b82f6', borderRadius: '4px' }}>{flow.trigger}</code></td>
                                    <td>{flow.actions}</td>
                                    <td>
                                        <span style={{ color: flow.active ? '#10b981' : '#64748b', fontWeight: '500' }}>
                                            {flow.active ? '● Active' : '○ Inactive'}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                <WorkflowCanvas />
            )}
        </div>
    );
}
