/**
 * useAuth Hook
 * 
 * Authentication hook for accessing current user information
 */

import { useQuery } from '@tanstack/react-query';

interface User {
  id: number;
  email: string;
  name: string;
  tenant_id: string;
  company_code: string;
  crm_role?: string;
  accounting_role?: string;
}

export const useAuth = () => {
  const { data: user, isLoading } = useQuery<User>({
    queryKey: ['auth', 'user'],
    queryFn: async () => {
      const token = localStorage.getItem('token');
      if (!token) {
        throw new Error('Not authenticated');
      }

      const response = await fetch('/api/auth/me', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });

      if (!response.ok) {
        throw new Error('Failed to fetch user');
      }

      const data = await response.json();
      return data.data;
    },
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: false
  });

  const logout = () => {
    localStorage.removeItem('token');
    window.location.href = '/login';
  };

  return {
    user: user || null,
    isLoading,
    isAuthenticated: !!user,
    logout
  };
};
