import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';
import { eventBus } from '../../core/EventBus';

export default function KnowledgeBaseModule({ basePath }) {
    const [articles, setArticles] = useState([]);

    useEffect(() => {
        // Fetch specific data for the Tenant (Injected via apiClient X-API-Key)
        const fetchKb = async () => {
            try {
                // Mock endpoint mapping for the KB module wrapper
                const response = await apiClient.get('/KnowledgeBase/articles');
                setArticles(response.data || []);
            } catch (err) {
                console.warn('API Mock fallback active for KB');
                setArticles([{ id: 10, title: 'How to reset your API Key', category: 'Developer Docs', views: 1450, status: 'Published' }]);
            }
        };
        fetchKb();
    }, []);

    const publishArticle = () => {
        // Simulate Publishing Article and Dispatching globally to Zapier logic
        apiClient.post('/KnowledgeBase/articles', { title: 'New Document' });

        // Trigger EventBus generic hooks (Caught globally inside the Workspace Canvas)
        eventBus.emit('kb.article.published', { id: 11, title: 'New Document' });
    };

    return (
        <div className="module-container" style={{ padding: '20px' }}>
            <div className="module-header">
                <h2>Knowledge Base Categories</h2>
                <button onClick={publishArticle} className="btn-primary" style={{ padding: '8px 16px', background: '#3b82f6', color: '#fff', border: 'none', borderRadius: '4px' }}>
                    + Publish Article
                </button>
            </div>

            <div className="table-responsive" style={{ marginTop: '20px' }}>
                <table style={{ width: '100%', textAlign: 'left', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ borderBottom: '1px solid #ccc' }}>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Views</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {articles.map(article => (
                            <tr key={article.id} style={{ borderBottom: '1px solid #eee' }}>
                                <td>{article.id}</td>
                                <td>{article.title}</td>
                                <td>{article.category}</td>
                                <td>{article.views}</td>
                                <td><span style={{ padding: '4px 8px', background: '#10b981', color: 'white', borderRadius: '12px', fontSize: '12px' }}>{article.status}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
