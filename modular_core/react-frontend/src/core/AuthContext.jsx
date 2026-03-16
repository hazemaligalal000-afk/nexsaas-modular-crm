import React, { createContext, useContext, useState, useEffect } from 'react';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [permissions, setPermissions] = useState({});
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const token = localStorage.getItem('access_token');
        if (token) {
            fetchMe(token);
        } else {
            setLoading(false);
        }
    }, []);

    const fetchMe = async (token) => {
        try {
            const res = await fetch('/api/auth/me', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await res.json();
            if (data.success) {
                setUser(data.data.user);
                setPermissions(data.data.permissions);
            } else {
                localStorage.removeItem('access_token');
            }
        } catch (err) {
            console.error('Auth check failed:', err);
        } finally {
            setLoading(false);
        }
    };

    const login = async (email, password) => {
        const res = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (data.success) {
            localStorage.setItem('access_token', data.data.access_token);
            setUser(data.data.user);
            await fetchMe(data.data.access_token);
        }
        return data;
    };

    const logout = async () => {
        await fetch('/api/auth/logout', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${localStorage.getItem('access_token')}` }
        });
        localStorage.removeItem('access_token');
        setUser(null);
        setPermissions({});
    };

    /**
     * Check if current user has a specific permission.
     * Usage: can('leads', 'create')
     */
    const can = (module, action) => {
        return permissions?.[module]?.[action] === true;
    };

    return (
        <AuthContext.Provider value={{ user, permissions, loading, login, logout, can }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}

/**
 * Component wrapper for RBAC-based conditional rendering.
 * Usage: <Can module="leads" action="create"><CreateButton /></Can>
 */
export function Can({ module, action, children, fallback = null }) {
    const { can } = useAuth();
    return can(module, action) ? children : fallback;
}
