import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';

interface Account {
  account_code: string;
  account_name_en: string;
  account_name_ar: string;
  account_level: number;
  account_type: string;
  is_active: boolean;
  allow_posting: boolean;
}

export const COAManager: React.FC = () => {
  const [searchTerm, setSearchTerm] = useState('');

  const { data: accounts, isLoading } = useQuery({
    queryKey: ['coa', 'list'],
    queryFn: async () => {
       const response = await fetch('/api/accounting/coa?company_code=01');
       return response.json();
    }
  });

  const filteredAccounts = (accounts?.data || []).filter((acc: Account) => 
    acc.account_code.includes(searchTerm) || 
    acc.account_name_en.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div className="p-8 h-full flex flex-col">
      <div className="flex justify-between items-center mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Chart of Accounts Tree</h2>
        <div className="flex gap-4">
          <input 
            type="text" 
            placeholder="Search accounts..." 
            className="border rounded-lg px-4 py-2 w-64 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
          <button className="bg-white border text-gray-700 px-5 py-2 rounded-lg hover:bg-gray-50 flex items-center gap-2 font-medium transition-all shadow-sm">
            <span>📥</span> Export COA
          </button>
          <button className="bg-blue-600 text-white px-5 py-2 rounded-lg hover:bg-blue-700 shadow-md transition-all">
            + New Account
          </button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex-1 flex items-center justify-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
      ) : (
        <div className="flex-1 overflow-y-auto rounded-xl border bg-gray-50/50 p-4">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="text-gray-500 text-sm uppercase font-semibold border-b">
                <th className="p-4">Code</th>
                <th className="p-4">Name (English)</th>
                <th className="p-4">Name (Arabic)</th>
                <th className="p-4">Type</th>
                <th className="p-4 text-center">Posting</th>
                <th className="p-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody className="bg-white">
              {filteredAccounts.map((acc: Account) => (
                <tr key={acc.account_code} className="border-b last:border-0 hover:bg-blue-50/30 transition-colors">
                  <td className={`p-4 font-mono text-sm ${acc.account_level === 1 ? 'font-bold' : ''}`}>
                    <span style={{ marginLeft: `${(acc.account_level - 1) * 2}rem` }}>
                        {acc.account_level > 1 && <span className="text-gray-300 mr-2">└</span>}
                        {acc.account_code}
                    </span>
                  </td>
                  <td className={`p-4 ${acc.account_level === 1 ? 'font-bold text-blue-800' : 'text-gray-700'}`}>
                    {acc.account_name_en}
                  </td>
                  <td className="p-4 text-right font-arabic" dir="rtl">
                    {acc.account_name_ar}
                  </td>
                  <td className="p-4">
                    <span className={`px-2 py-1 rounded-full text-xs font-semibold ${
                      acc.account_type === 'Asset' ? 'bg-green-100 text-green-700' :
                      acc.account_type === 'Liability' ? 'bg-red-100 text-red-700' :
                      acc.account_type === 'Income' ? 'bg-blue-100 text-blue-700' :
                      'bg-gray-100 text-gray-700'
                    }`}>
                      {acc.account_type}
                    </span>
                  </td>
                  <td className="p-4 text-center">
                    {acc.allow_posting ? (
                      <span className="text-green-500">✅</span>
                    ) : (
                      <span className="text-gray-300">🚫</span>
                    )}
                  </td>
                  <td className="p-4 text-center">
                    <span className={`px-2 py-1 rounded text-xs ${acc.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500'}`}>
                      {acc.is_active ? 'Active' : 'Blocked'}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};
