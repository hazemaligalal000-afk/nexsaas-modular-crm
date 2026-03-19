import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

/**
 * CurrencyMaster: Currency Master UI with Exchange Rate Management
 * Task 31.1, 31.2
 * Requirements: 47.1, 47.2, 47.3, 47.4, 47.5
 */
const CurrencyMaster = () => {
  const [currencies, setCurrencies] = useState([
    { code: '01', iso_code: 'EGP', name_en: 'Egyptian Pound', name_ar: 'جنيه مصري', is_base: true },
    { code: '02', iso_code: 'USD', name_en: 'US Dollar', name_ar: 'دولار أمريكي', is_base: false },
    { code: '03', iso_code: 'AED', name_en: 'UAE Dirham', name_ar: 'درهم إماراتي', is_base: false },
    { code: '04', iso_code: 'SAR', name_en: 'Saudi Riyal', name_ar: 'ريال سعودي', is_base: false },
    { code: '05', iso_code: 'EUR', name_en: 'Euro', name_ar: 'يورو', is_base: false },
    { code: '06', iso_code: 'GBP', name_en: 'British Pound', name_ar: 'جنيه إسترليني', is_base: false }
  ]);

  const [selectedCurrency, setSelectedCurrency] = useState(null);
  const [rateHistory, setRateHistory] = useState([]);
  const [currentRate, setCurrentRate] = useState('');
  const [rateDate, setRateDate] = useState(new Date().toISOString().split('T')[0]);
  const [dateRange, setDateRange] = useState({
    start: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    end: new Date().toISOString().split('T')[0]
  });
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState(null);

  // Fetch rate history when currency is selected
  useEffect(() => {
    if (selectedCurrency && selectedCurrency.code !== '01') {
      fetchRateHistory(selectedCurrency.code);
    }
  }, [selectedCurrency, dateRange]);

  const fetchRateHistory = async (currencyCode) => {
    setLoading(true);
    try {
      const response = await fetch(
        `/api/v1/accounting/fx/rates?currency_code=${currencyCode}&start_date=${dateRange.start}&end_date=${dateRange.end}`
      );
      const data = await response.json();
      
      if (data.success) {
        setRateHistory(data.data.rates || []);
      } else {
        setMessage({ type: 'error', text: data.error || 'Failed to fetch rate history' });
      }
    } catch (error) {
      setMessage({ type: 'error', text: 'Error fetching rate history' });
    } finally {
      setLoading(false);
    }
  };

  const handleSaveRate = async () => {
    if (!selectedCurrency || !currentRate || !rateDate) {
      setMessage({ type: 'error', text: 'Please fill all fields' });
      return;
    }

    if (parseFloat(currentRate) <= 0) {
      setMessage({ type: 'error', text: 'Rate must be a positive number' });
      return;
    }

    setLoading(true);
    try {
      const response = await fetch('/api/v1/accounting/fx/rates', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          currency_code: selectedCurrency.code,
          date: rateDate,
          rate: parseFloat(currentRate),
          source: 'manual'
        })
      });

      const data = await response.json();
      
      if (data.success) {
        setMessage({ type: 'success', text: 'Exchange rate saved successfully' });
        setCurrentRate('');
        fetchRateHistory(selectedCurrency.code);
      } else {
        setMessage({ type: 'error', text: data.error || 'Failed to save rate' });
      }
    } catch (error) {
      setMessage({ type: 'error', text: 'Error saving rate' });
    } finally {
      setLoading(false);
    }
  };

  const handleCurrencySelect = (currency) => {
    setSelectedCurrency(currency);
    setMessage(null);
    if (currency.code === '01') {
      setRateHistory([]);
    }
  };

  return (
    <div className="currency-master-container" style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}>
      <h1>Currency Master & Exchange Rate Management</h1>
      
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

      {/* Currency List */}
      <div className="currency-list" style={{ marginBottom: '30px' }}>
        <h2>Currencies</h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(250px, 1fr))', gap: '15px' }}>
          {currencies.map(currency => (
            <div
              key={currency.code}
              onClick={() => handleCurrencySelect(currency)}
              style={{
                padding: '15px',
                border: selectedCurrency?.code === currency.code ? '2px solid #007bff' : '1px solid #ddd',
                borderRadius: '8px',
                cursor: 'pointer',
                backgroundColor: selectedCurrency?.code === currency.code ? '#e7f3ff' : '#fff',
                transition: 'all 0.2s'
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div>
                  <div style={{ fontSize: '18px', fontWeight: 'bold' }}>{currency.iso_code}</div>
                  <div style={{ fontSize: '14px', color: '#666' }}>{currency.name_en}</div>
                  <div style={{ fontSize: '14px', color: '#666', direction: 'rtl' }}>{currency.name_ar}</div>
                </div>
                {currency.is_base && (
                  <span style={{
                    padding: '4px 8px',
                    backgroundColor: '#28a745',
                    color: 'white',
                    borderRadius: '4px',
                    fontSize: '12px'
                  }}>
                    Base
                  </span>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Rate Entry Form */}
      {selectedCurrency && selectedCurrency.code !== '01' && (
        <div className="rate-entry" style={{
          padding: '20px',
          border: '1px solid #ddd',
          borderRadius: '8px',
          marginBottom: '30px',
          backgroundColor: '#f8f9fa'
        }}>
          <h2>Enter Exchange Rate for {selectedCurrency.iso_code}</h2>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '15px', alignItems: 'end' }}>
            <div>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Date</label>
              <input
                type="date"
                value={rateDate}
                onChange={(e) => setRateDate(e.target.value)}
                style={{
                  width: '100%',
                  padding: '8px',
                  border: '1px solid #ddd',
                  borderRadius: '4px'
                }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>
                Rate to EGP
              </label>
              <input
                type="number"
                step="0.000001"
                value={currentRate}
                onChange={(e) => setCurrentRate(e.target.value)}
                placeholder="e.g., 50.00"
                style={{
                  width: '100%',
                  padding: '8px',
                  border: '1px solid #ddd',
                  borderRadius: '4px'
                }}
              />
            </div>
            <div>
              <label style={{ display: 'block', marginBottom: '5px', fontWeight: 'bold' }}>Source</label>
              <input
                type="text"
                value="Manual Entry"
                disabled
                style={{
                  width: '100%',
                  padding: '8px',
                  border: '1px solid #ddd',
                  borderRadius: '4px',
                  backgroundColor: '#e9ecef'
                }}
              />
            </div>
            <button
              onClick={handleSaveRate}
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
              {loading ? 'Saving...' : 'Save Rate'}
            </button>
          </div>
        </div>
      )}

      {/* Rate History Chart */}
      {selectedCurrency && selectedCurrency.code !== '01' && (
        <div className="rate-history" style={{
          padding: '20px',
          border: '1px solid #ddd',
          borderRadius: '8px',
          backgroundColor: '#fff'
        }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
            <h2>Exchange Rate History - {selectedCurrency.iso_code}</h2>
            <div style={{ display: 'flex', gap: '10px' }}>
              <div>
                <label style={{ marginRight: '5px', fontSize: '14px' }}>From:</label>
                <input
                  type="date"
                  value={dateRange.start}
                  onChange={(e) => setDateRange({ ...dateRange, start: e.target.value })}
                  style={{ padding: '5px', border: '1px solid #ddd', borderRadius: '4px' }}
                />
              </div>
              <div>
                <label style={{ marginRight: '5px', fontSize: '14px' }}>To:</label>
                <input
                  type="date"
                  value={dateRange.end}
                  onChange={(e) => setDateRange({ ...dateRange, end: e.target.value })}
                  style={{ padding: '5px', border: '1px solid #ddd', borderRadius: '4px' }}
                />
              </div>
            </div>
          </div>

          {loading ? (
            <div style={{ textAlign: 'center', padding: '40px' }}>Loading...</div>
          ) : rateHistory.length > 0 ? (
            <>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={rateHistory}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="date" />
                  <YAxis domain={['auto', 'auto']} />
                  <Tooltip />
                  <Legend />
                  <Line type="monotone" dataKey="rate" stroke="#007bff" strokeWidth={2} name="Rate to EGP" />
                </LineChart>
              </ResponsiveContainer>

              {/* Rate Table */}
              <div style={{ marginTop: '20px', maxHeight: '300px', overflowY: 'auto' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                  <thead style={{ position: 'sticky', top: 0, backgroundColor: '#f8f9fa' }}>
                    <tr>
                      <th style={{ padding: '10px', textAlign: 'left', borderBottom: '2px solid #ddd' }}>Date</th>
                      <th style={{ padding: '10px', textAlign: 'right', borderBottom: '2px solid #ddd' }}>Rate to EGP</th>
                    </tr>
                  </thead>
                  <tbody>
                    {rateHistory.map((rate, index) => (
                      <tr key={index} style={{ borderBottom: '1px solid #eee' }}>
                        <td style={{ padding: '10px' }}>{rate.date}</td>
                        <td style={{ padding: '10px', textAlign: 'right', fontFamily: 'monospace' }}>
                          {parseFloat(rate.rate).toFixed(6)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          ) : (
            <div style={{ textAlign: 'center', padding: '40px', color: '#666' }}>
              No rate history available for the selected date range
            </div>
          )}
        </div>
      )}

      {selectedCurrency && selectedCurrency.code === '01' && (
        <div style={{
          padding: '40px',
          textAlign: 'center',
          border: '1px solid #ddd',
          borderRadius: '8px',
          backgroundColor: '#f8f9fa'
        }}>
          <p style={{ fontSize: '16px', color: '#666' }}>
            EGP is the base currency. Exchange rates are not applicable.
          </p>
        </div>
      )}
    </div>
  );
};

export default CurrencyMaster;
