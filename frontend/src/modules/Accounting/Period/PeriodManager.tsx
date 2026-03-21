import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';

interface Period {
  period_code: string;
  period_name: string;
  status: 'draft' | 'open' | 'closing' | 'closed';
  start_date: string;
  end_date: string;
}

export const PeriodManager: React.FC = () => {
  const queryClient = useQueryClient();
  const [selectedCompany] = useState('01');

  const { data: periods, isLoading } = useQuery({
    queryKey: ['periods', selectedCompany],
    queryFn: async () => {
      const response = await fetch(`/api/accounting/periods?company_code=${selectedCompany}`);
      return response.json();
    }
  });

  const updateStatusMutation = useMutation({
    mutationFn: async ({ period, status }: { period: string, status: string }) => {
      const response = await fetch(`/api/accounting/periods/${period}/status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ status })
      });
      return response.json();
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['periods'] });
    }
  });

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'open': return 'bg-green-100 text-green-700 border-green-200';
      case 'closed': return 'bg-gray-100 text-gray-700 border-gray-200';
      case 'closing': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
      case 'draft': return 'bg-blue-100 text-blue-700 border-blue-200';
      default: return 'bg-gray-100 text-gray-700 border-gray-200';
    }
  };

  return (
    <div className="p-8 h-full flex flex-col bg-white">
      <div className="flex justify-between items-center mb-6">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">Financial Period Management</h2>
          <p className="text-sm text-gray-500">Only OPEN periods allow journal entry postings.</p>
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {(periods?.data || []).map((p: Period) => (
            <div key={p.period_code} className={`border rounded-2xl p-6 transition-all hover:shadow-lg ${p.status === 'open' ? 'ring-2 ring-blue-500 bg-blue-50/20' : 'bg-white'}`}>
              <div className="flex justify-between items-start mb-4">
                <div>
                  <h3 className="text-lg font-bold text-gray-800">{p.period_name}</h3>
                  <p className="text-xs text-gray-500 font-mono">{p.period_code}</p>
                </div>
                <span className={`px-3 py-1 rounded-full text-xs font-bold border uppercase ${getStatusColor(p.status)}`}>
                  {p.status}
                </span>
              </div>
              
              <div className="text-sm text-gray-600 mb-6 space-y-1">
                <div className="flex justify-between">
                  <span>Start Date:</span>
                  <span className="font-medium text-gray-800">{p.start_date}</span>
                </div>
                <div className="flex justify-between">
                  <span>End Date:</span>
                  <span className="font-medium text-gray-800">{p.end_date}</span>
                </div>
              </div>

              <div className="flex gap-2">
                {p.status === 'draft' && (
                  <button 
                    onClick={() => updateStatusMutation.mutate({ period: p.period_code, status: 'open' })}
                    className="flex-1 bg-green-600 text-white text-xs font-bold py-2 rounded-lg hover:bg-green-700"
                  >
                    Open Period
                  </button>
                )}
                {p.status === 'open' && (
                  <button 
                     onClick={() => updateStatusMutation.mutate({ period: p.period_code, status: 'closing' })}
                    className="flex-1 bg-yellow-500 text-white text-xs font-bold py-2 rounded-lg hover:bg-yellow-600"
                  >
                    Review / Closing
                  </button>
                )}
                {p.status === 'closing' && (
                  <button 
                     onClick={() => updateStatusMutation.mutate({ period: p.period_code, status: 'closed' })}
                    className="flex-1 bg-red-600 text-white text-xs font-bold py-2 rounded-lg hover:bg-red-700"
                  >
                    Close & Lock
                  </button>
                )}
                {p.status === 'closed' && (
                   <button 
                    onClick={() => updateStatusMutation.mutate({ period: p.period_code, status: 'open' })}
                    className="flex-1 border border-blue-600 text-blue-600 text-xs font-bold py-2 rounded-lg hover:bg-blue-50"
                  >
                    Re-open (Admin Only)
                   </button>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};
