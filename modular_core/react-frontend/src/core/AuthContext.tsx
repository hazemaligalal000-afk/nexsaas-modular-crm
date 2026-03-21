import React, { createContext, useContext, useState, useEffect, ReactNode } from "react"

interface User {
  id: number
  email: string
  name: string
  role: string
  tenant_id: number
}

interface Permissions {
  [module: string]: {
    [action: string]: boolean
  }
}

interface AuthContextType {
  user: User | null
  permissions: Permissions
  loading: boolean
  login: (email: string, password: string) => Promise<any>
  logout: () => Promise<void>
  can: (module: string, action: string) => boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [permissions, setPermissions] = useState<Permissions>({})
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem("access_token")
    if (token) {
      fetchMe(token)
    } else {
      setLoading(false)
    }
  }, [])

  const fetchMe = async (token: string) => {
    try {
      const res = await fetch("/api/auth/me", {
        headers: { Authorization: `Bearer ${token}` },
      })
      const data = await res.json()
      if (data.success) {
        setUser(data.data.user)
        setPermissions(data.data.permissions)
      } else {
        localStorage.removeItem("access_token")
      }
    } catch (err) {
      console.error("Auth check failed:", err)
    } finally {
      setLoading(false)
    }
  }

  const login = async (email: string, password: string) => {
    const res = await fetch("/api/auth/login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email, password }),
    })
    const data = await res.json()
    if (data.success) {
      localStorage.setItem("access_token", data.data.access_token)
      setUser(data.data.user)
      await fetchMe(data.data.access_token)
    }
    return data
  }

  const logout = async () => {
    await fetch("/api/auth/logout", {
      method: "POST",
      headers: { Authorization: `Bearer ${localStorage.getItem("access_token")}` },
    })
    localStorage.removeItem("access_token")
    setUser(null)
    setPermissions({})
  }

  const can = (module: string, action: string) => {
    return permissions?.[module]?.[action] === true
  }

  return <AuthContext.Provider value={{ user, permissions, loading, login, logout, can }}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error("useAuth must be used within an AuthProvider")
  return context
}

interface CanProps {
  module: string
  action: string
  children: ReactNode
  fallback?: ReactNode
}

export function Can({ module, action, children, fallback = null }: CanProps) {
  const { can } = useAuth()
  return can(module, action) ? <>{children}</> : <>{fallback}</>
}
