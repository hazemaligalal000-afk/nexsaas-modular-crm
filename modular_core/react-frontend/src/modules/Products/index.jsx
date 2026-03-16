import React, { useState, useEffect } from 'react';
import apiClient from '../../api/client';
import { eventBus } from '../../core/EventBus';

export default function ProductsModule({ basePath }) {
    const [products, setProducts] = useState([]);

    useEffect(() => {
        const fetchCatalog = async () => {
            try {
                const res = await apiClient.get('/Products');
                setProducts(res.data);
            } catch (err) {
                setProducts([
                    { sku: 'PKG-ENTERPRISE-01', name: 'Enterprise CRM Seat', price: 150.00, currency: 'USD', stock_quantity: 5 }
                ]);
            }
        };
        fetchCatalog();

        // Listen for global hooks
        const unsub = eventBus.on('inventory.low_stock', (data) => {
            console.warn("Low Stock Alert UI Update", data);
        });
        return unsub;
    }, []);

    const simulateSale = (sku) => {
        const updated = products.map(p => {
            if (p.sku === sku) {
                const newStock = Math.max(0, p.stock_quantity - 1);
                if (newStock <= 5) {
                    eventBus.emit('inventory.low_stock', { sku, current: newStock });
                }
                return { ...p, stock_quantity: newStock };
            }
            return p;
        });
        setProducts(updated);
        apiClient.put(`/Products/${sku}`, { stock: 'decremented' }); // Mock backend sync
    };

    return (
        <div style={{ padding: '20px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                <h2>Product Catalog & Inventory</h2>
            </div>
            
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '20px', marginTop: '20px' }}>
                {products.map(prod => (
                    <div key={prod.sku} style={{ padding: '20px', border: '1px solid #ccc', borderRadius: '12px', background: 'rgba(255,255,255,0.05)' }}>
                        <div style={{ fontSize: '12px', color: '#888' }}>{prod.sku}</div>
                        <h3 style={{ margin: '8px 0' }}>{prod.name}</h3>
                        <div style={{ fontSize: '20px', fontWeight: 'bold', color: '#10b981' }}>
                            ${prod.price} {prod.currency}
                        </div>
                        <div style={{ marginTop: '16px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span style={{ 
                                color: prod.stock_quantity <= 5 ? '#ef4444' : '#64748b' 
                            }}>
                                {prod.stock_quantity} in stock
                            </span>
                            <button onClick={() => simulateSale(prod.sku)} style={{ background: '#3b82f6', color: '#fff', border: 'none', padding: '6px 12px', borderRadius: '4px' }}>
                                Deduct Item
                            </button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
