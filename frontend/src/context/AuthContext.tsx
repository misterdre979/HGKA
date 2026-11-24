import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, TOKEN_KEY } from '@/services/api'
import type { User } from '@/types'

interface Branding { school_name?: string; school_short_name?: string; motto?: string; logo_url?: string; primary_color?: string; currency_symbol?: string }

interface AuthState {
  user: User | null
  loading: boolean
  branding: Branding
  login: (email: string, password: string) => Promise<User>
  logout: () => Promise<void>
  refresh: () => Promise<void>
  /** True when the user holds ANY of the given permissions (no args = true). */
  can: (...perms: string[]) => boolean
  hasRole: (...roles: string[]) => boolean
}

const Ctx = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(!!localStorage.getItem(TOKEN_KEY))
  const [branding, setBranding] = useState<Branding>({})

  useEffect(() => {
    api.get('/settings/public').then((r) => setBranding(r.data)).catch(() => undefined)
  }, [])

  const refresh = useCallback(async () => {
    if (!localStorage.getItem(TOKEN_KEY)) {
      setUser(null)
      setLoading(false)
      return
    }
    try {
      const r = await api.get('/auth/me')
      setUser(r.data.user)
    } catch {
      setUser(null)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => { void refresh() }, [refresh])

  const login = async (email: string, password: string) => {
    const r = await api.post('/auth/login', { email, password })
    localStorage.setItem(TOKEN_KEY, r.data.token)
    setUser(r.data.user)
    return r.data.user as User
  }

  const logout = async () => {
    try { await api.post('/auth/logout') } catch { /* token may already be invalid */ }
    localStorage.removeItem(TOKEN_KEY)
    setUser(null)
  }

  const value = useMemo<AuthState>(() => ({
    user, loading, branding, login, logout, refresh,
    can: (...perms) => !perms.length || !!user?.permissions.some((p) => perms.includes(p)),
    hasRole: (...roles) => !!user?.roles.some((r) => roles.includes(r)),
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }), [user, loading, branding, refresh])

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>
}

export function useAuth() {
  const c = useContext(Ctx)
  if (!c) throw new Error('useAuth must be used inside AuthProvider')
  return c
}
