import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';
import { eventBus } from '../../core/EventBus';

export default function SupportTicketsModule({ basePath }) {
    const [tickets, setTickets] = useState([]);

    useEffect(() => {
        const fetchTickets = async () => {
            try {
                const response = await apiClient.get('/SupportTickets');
                setTickets(response.data || []);
            } catch (err) {
                // Mock Response
                setTickets([
                    { id: 101, title: 'Server Configuration Issue', priority: 'High', status: 'Open', created_at: '2026-03-15 14:00' }
                ]);
            }
        };
        fetchTickets();
    }, []);

    const markResolved = (id) => {
        // Optimistic UI updates
        setTickets(tickets.map(t => t.id === id ? { ...t, status: 'Resolved' } : t));
        eventBus.emit('ticket.status_changed', { id, status: 'Resolved' });
        apiClient.put(`/SupportTickets/${id}`, { status: 'Resolved' });
    };

    return (
        <div style={{ padding: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h2>Support Inbox</h2>
                <div style={{ padding: '6px 12px', background: 'rgba(239, 68, 68, 0.2)', color: '#ef4444', borderRadius: '8px' }}>
                    SLA Breaches: 0
                </div>
            </div>

            <div style={{ marginTop: '20px' }}>
                <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ borderBottom: '1px solid #ccc' }}>
                            <th>ID</th>
                            <th>Issue Title</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {tickets.map(ticket => (
                            <tr key={ticket.id} style={{ borderBottom: '1px solid #eee' }}>
                                <td>#{ticket.id}</td>
                                <td>{ticket.title}</td>
                                <td>
                                    <span style={{ 
                                        padding: '4px 8px', 
                                        borderRadius: '12px', 
                                        fontSize: '12px',
                                        background: ticket.priority === 'High' ? 'rgba(245, 158, 11, 0.2)' : '#eee',
                                        color: ticket.priority === 'High' ? '#f59e0b' : '#333'
                                    }}>
                                        {ticket.priority}
                                    </span>
                                </td>
                                <td>{ticket.status}</td>
                                <td>
                                    {ticket.status !== 'Resolved' && (
                                        <button onClick={() => markResolved(ticket.id)} style={{ padding: '4px 8px', background: '#10b981', color: 'white', border: 'none', borderRadius: '4px', cursor: 'pointer' }}>
                                            Resolve
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
