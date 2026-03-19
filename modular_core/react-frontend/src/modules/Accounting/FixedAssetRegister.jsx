import React, { useState, useEffect } from 'react';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';

/**
 * FixedAssetRegister: Fixed Asset Management UI
 * Task 35.6
 * Requirements: 51.1, 51.8, 51.9
 */
const FixedAssetRegister = () => {
  const [companyCode, setCompanyCode] = useState('01');
  const [category, setCategory] = useState('');
  const [assets, setAssets] = useState([]);
  const [totals, setTotals] = useState(null);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState(null);
  const [view, setView] = useState('register'); // 'register' or 'movements'

  const categories = [
    { value: '', label: 'All Categories' },
    { value: 'BUILDINGS', label: 'Buildings' },
    { value: 'FENCES', label: 'Fences' },
    { value: 'PORTA_CABINS', label: 'Porta Cabins' },
    { value: 'PLANT_EQUIPMENT', label: 'Plant Equipment' },
    { value: 'MARINE_EQUIPMENT', label: 'Marine Equipment' },
    { value: 'FURNITURE', label: 'Furniture' },
    { value: 'COMPUTER_HARDWARE', label: 'Computer Hardware' },
    { value: 'SOFTWARE', label: 'Software' },
    { value: 'VEHICLES', label: 'Vehicles' },
    { value: 'CRANES', label: 'Cranes' },
    { value: 'OTHER', label: 'Other' }
  ];

  const companies = [
    { code: '01', name: 'Company 01' },
    { code: '02', name: 'Company 02' },
    { code: '03', name: 'Company 03' },
    { code: '04', name: 'Company 04' },
    { code: '05', name: 'Company 05' },
    { code: '06', name: 'Company 06' }
  ];

  const COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8884D8', '#82CA9D'];

  useEffect(() => {
    if (view === 'register') {
      fetchAssetRegister();
    }
  }, [companyCode, category, view]);

  const fetchAssetRegister = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ company_code: companyCode });
      if (category) params.append('category', category);

      const response = await fetch(`/api/v1/accounting/fixed-assets/register?${params}`);
      const data = await response.json();

      if (data.success) {
        setAssets(data.data.assets || []);
        setTotals(data.data.totals || null);
      } else {
        setMessage({ type: 'error', text: data.error || 'Failed to fetch asset register' });
      }
    } catch (error) {
      setMessage({ type: 'error', text: 'Error fetching asset register' });
    } finally {
      setLoading(false);
    }
  };

  const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US', {
      style: 'decimal',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  };

  const getCategoryData = () => {
    const categoryMap = {};
    assets.forEach(asset => {
      if (!categoryMap[asset.asset_category]) {
        categoryMap[asset.asset_category] = {
          name: asset.asset_category,
          count: 0,
          value: 0
        };
      }
      categoryMap[asset.asset_category].count++;
      categoryMap[asset.asset_category].value += parseFloat(asset.net_book_value);
    });
    return Object.values(categoryMap);
  };

  return (
    <div className="fixed-asset-register" style={{ padding: '20px', maxWidth: '1400px', margin: '0 auto' }}>
      <h1>Fixed Asset Register</h1>

      {message && (
        <div className={`alert alert-${message.type}`} style={{
          padding: '10px',
          marginBottom: '20px',
          borderRadius: '4px',
          backgroundColor: message.type === 'success' ? '#d4edda' : '#f8d7da',
          color: message.type === 'success' ? '#155724' : '#721c24'
        }}>
          {message.text}
        </div>
      )}

      {/* Filters */}
      <div style={{
        display: 'grid',
        gridTemplateColumns: '1fr 1fr 1fr auto',
        gap: '15px',
        marginBottom: '30px',
        padding: '20px',
        backgroundColor: '#f8f9fa',
        borderRadius: '8px'
      }}>
        <div>
          <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Company</label>
          <select
            value={companyCode}
            onChange={(e) => setCompanyCode(e.target.value)}
            style={{
              width: '100%',
              padding: '8px',
              border: '1px solid #ddd',
              borderRadius: '4px'
            }}
          >
            {companies.map(company => (
              <option key={company.code} value={company.code}>
                {company.name}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Category</label>
          <select
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            style={{
              width: '100%',
              padding: '8px',
              border: '1px solid #ddd',
              borderRadius: '4px'
            }}
          >
            {categories.map(cat => (
              <option key={cat.value} value={cat.value}>
                {cat.label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>View</label>
          <select
            value={view}
            onChange={(e) => setView(e.target.value)}
            style={{
              width: '100%',
              padding: '8px',
              border: '1px solid #ddd',
              borderRadius: '4px'
            }}
          >
            <option value="register">Asset Register</option>
            <option value="movements">Asset Movements</option>
          </select>
        </div>

        <div style={{ display: 'flex', alignItems: 'flex-end' }}>
          <button
            onClick={fetchAssetRegister}
            disabled={loading}
            style={{
              padding: '8px 20px',
              backgroundColor: '#007bff',
              color: 'white',
              border: 'none',
              borderRadius: '4px',
              cursor: loading ? 'not-allowed' : 'pointer',
              fontWeight: 'bold'
            }}
          >
            {loading ? 'Loading...' : 'Refresh'}
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      {totals && (
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(4, 1fr)',
          gap: '15px',
          marginBottom: '30px'
        }}>
          <div style={{
            padding: '20px',
            backgroundColor: '#fff',
            border: '1px solid #ddd',
            borderRadius: '8px',
            textAlign: 'center'
          }}>
            <div style={{ fontSize: '14px', color: '#666', marginBottom: '5px' }}>Total Assets</div>
            <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#007bff' }}>
              {totals.asset_count}
            </div>
          </div>

          <div style={{
            padding: '20px',
            backgroundColor: '#fff',
            border: '1px solid #ddd',
            borderRadius: '8px',
            textAlign: 'center'
          }}>
            <div style={{ fontSize: '14px', color: '#666', marginBottom: '5px' }}>Total Cost</div>
            <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#28a745' }}>
              {formatCurrency(totals.total_cost)} EGP
            </div>
          </div>

          <div style={{
            padding: '20px',
            backgroundColor: '#fff',
            border: '1px solid #ddd',
            borderRadius: '8px',
            textAlign: 'center'
          }}>
            <div style={{ fontSize: '14px', color: '#666', marginBottom: '5px' }}>Accumulated Depreciation</div>
            <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#dc3545' }}>
              {formatCurrency(totals.total_accumulated_depreciation)} EGP
            </div>
          </div>

          <div style={{
            padding: '20px',
            backgroundColor: '#fff',
            border: '1px solid #ddd',
            borderRadius: '8px',
            textAlign: 'center'
          }}>
            <div style={{ fontSize: '14px', color: '#666', marginBottom: '5px' }}>Net Book Value</div>
            <div style={{ fontSize: '24px', fontWeight: 'bold', color: '#17a2b8' }}>
              {formatCurrency(totals.total_net_book_value)} EGP
            </div>
          </div>
        </div>
      )}

      {/* Charts */}
      {assets.length > 0 && (
        <div style={{
          display: 'grid',
          gridTemplateColumns: '2fr 1fr',
          gap: '20px',
          marginBottom: '30px'
        }}>
          {/* Bar Chart */}
          <div style={{
            padding: '20px',
            backgroundColor: '#fff',
            border: '1px solid #ddd',
            borderRadius: '8px'
          }}>
            <h3 style={{ marginTop: 0 }}>Assets by Category</h3>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={getCategoryData()}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="name" angle={-45} textAnchor="end" height={100} />
                <YAxis />
                <Tooltip formatter={(value) => formatCurrency(value)} />
                <Legend />
                <Bar dataKey="value" fill="#007bff" name="Net Book Value (EGP)" />
              </BarChart>
            </ResponsiveContainer>
          </div>

          {/* Pie Chart */}
          <div style={{
            padding: '20px',
            backgroundColor: '#fff',
            border: '1px solid #ddd',
            borderRadius: '8px'
          }}>
            <h3 style={{ marginTop: 0 }}>Asset Distribution</h3>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={getCategoryData()}
                  dataKey="count"
                  nameKey="name"
                  cx="50%"
                  cy="50%"
                  outerRadius={80}
                  label
                >
                  {getCategoryData().map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>
      )}

      {/* Asset Table */}
      <div style={{
        backgroundColor: '#fff',
        border: '1px solid #ddd',
        borderRadius: '8px',
        overflow: 'hidden'
      }}>
        <div style={{
          padding: '15px',
          backgroundColor: '#f8f9fa',
          borderBottom: '1px solid #ddd',
          fontWeight: 'bold'
        }}>
          Asset Register - Company {companyCode}
        </div>

        {loading ? (
          <div style={{ padding: '40px', textAlign: 'center' }}>Loading...</div>
        ) : assets.length > 0 ? (
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse' }}>
              <thead style={{ backgroundColor: '#f8f9fa' }}>
                <tr>
                  <th style={{ padding: '12px', textAlign: 'left', borderBottom: '2px solid #ddd' }}>Asset Code</th>
                  <th style={{ padding: '12px', textAlign: 'left', borderBottom: '2px solid #ddd' }}>Asset Name</th>
                  <th style={{ padding: '12px', textAlign: 'left', borderBottom: '2px solid #ddd' }}>Category</th>
                  <th style={{ padding: '12px', textAlign: 'center', borderBottom: '2px solid #ddd' }}>Purchase Date</th>
                  <th style={{ padding: '12px', textAlign: 'right', borderBottom: '2px solid #ddd' }}>Cost</th>
                  <th style={{ padding: '12px', textAlign: 'right', borderBottom: '2px solid #ddd' }}>Accumulated Depr.</th>
                  <th style={{ padding: '12px', textAlign: 'right', borderBottom: '2px solid #ddd' }}>Net Book Value</th>
                  <th style={{ padding: '12px', textAlign: 'center', borderBottom: '2px solid #ddd' }}>Status</th>
                </tr>
              </thead>
              <tbody>
                {assets.map((asset, index) => (
                  <tr key={index} style={{ borderBottom: '1px solid #eee' }}>
                    <td style={{ padding: '12px', fontFamily: 'monospace' }}>{asset.asset_code}</td>
                    <td style={{ padding: '12px' }}>{asset.asset_name_en}</td>
                    <td style={{ padding: '12px' }}>{asset.asset_category}</td>
                    <td style={{ padding: '12px', textAlign: 'center' }}>{asset.purchase_date}</td>
                    <td style={{ padding: '12px', textAlign: 'right', fontFamily: 'monospace' }}>
                      {formatCurrency(asset.purchase_cost)}
                    </td>
                    <td style={{ padding: '12px', textAlign: 'right', fontFamily: 'monospace', color: '#dc3545' }}>
                      {formatCurrency(asset.accumulated_depreciation)}
                    </td>
                    <td style={{ padding: '12px', textAlign: 'right', fontFamily: 'monospace', fontWeight: 'bold' }}>
                      {formatCurrency(asset.net_book_value)}
                    </td>
                    <td style={{ padding: '12px', textAlign: 'center' }}>
                      <span style={{
                        padding: '4px 8px',
                        borderRadius: '4px',
                        fontSize: '12px',
                        backgroundColor: asset.status === 'active' ? '#d4edda' : '#f8d7da',
                        color: asset.status === 'active' ? '#155724' : '#721c24'
                      }}>
                        {asset.status}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div style={{ padding: '40px', textAlign: 'center', color: '#666' }}>
            No assets found for the selected filters
          </div>
        )}
      </div>
    </div>
  );
};

export default FixedAssetRegister;
