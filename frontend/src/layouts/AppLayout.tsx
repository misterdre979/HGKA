import { Bell, ChevronDown, KeyRound, LogOut, Menu, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { Logo } from '@/components/Logo'
import { NAV } from '@/config/nav'
import { useAuth } from '@/context/AuthContext'
import { cn } from '@/lib/utils'
import { api } from '@/services/api'

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { can, branding } = useAuth()
  const groups = NAV.map((g) => ({ ...g, items: g.items.filter((i) => !i.perms || can(...i.perms)) })).filter((g) => g.items.length)
  return (
    <div className="flex h-full flex-col bg-navy text-slate-200">
      <div className="border-b border-white/10 px-4 py-4">
        <Logo light size={38} />
        {branding.motto && <p className="mt-2 text-[11px] italic text-slate-400">{branding.motto}</p>}
      </div>
      <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4" aria-label="Main">
        {groups.map((g) => (
          <div key={g.title}>
            <div className="mb-1 px-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400">{g.title}</div>
            {g.items.map((i) => (
              <NavLink
                key={i.to} to={i.to} end={i.end ?? false} onClick={onNavigate}
                className={({ isActive }) => cn('flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition', isActive ? 'bg-royal text-white' : 'text-slate-300 hover:bg-white/10')}
              >
                <i.icon size={17} className="shrink-0" /> <span className="truncate">{i.label}</span>
              </NavLink>
            ))}
          </div>
        ))}
      </nav>
    </div>
  )
}

function NotificationBell() {
  const [n, setN] = useState(0)
  useEffect(() => {
    let live = true
    const tick = () => api.get('/notifications/counts').then((r) => live && setN(r.data.unread_notifications ?? r.data.notifications ?? 0)).catch(() => undefined)
    void tick()
    const t = setInterval(tick, 60000)
    return () => { live = false; clearInterval(t) }
  }, [])
  return (
    <Link to="/notifications" className="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label={`Notifications${n ? `, ${n} unread` : ''}`}>
      <Bell size={20} />
      {n > 0 && <span className="absolute -right-0.5 -top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{n > 99 ? '99+' : n}</span>}
    </Link>
  )
}

function UserMenu() {
  const { user, logout } = useAuth()
  const nav = useNavigate()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  useEffect(() => {
    const h = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false) }
    document.addEventListener('mousedown', h)
    return () => document.removeEventListener('mousedown', h)
  }, [])
  if (!user) return null
  return (
    <div className="relative" ref={ref}>
      <button onClick={() => setOpen(!open)} className="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100" aria-haspopup="menu" aria-expanded={open}>
        <span className="grid h-8 w-8 place-items-center rounded-full bg-royal text-sm font-semibold text-white">{user.name.slice(0, 1).toUpperCase()}</span>
        <span className="hidden text-left leading-tight sm:block">
          <span className="block text-sm font-medium text-slate-800">{user.name}</span>
          <span className="block text-[11px] text-slate-500">{user.role_names?.[0]}</span>
        </span>
        <ChevronDown size={14} className="text-slate-400" />
      </button>
      {open && (
        <div role="menu" className="absolute right-0 z-40 mt-1 w-52 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
          <Link role="menuitem" to="/account" onClick={() => setOpen(false)} className="flex items-center gap-2 px-3 py-2 text-sm hover:bg-slate-50"><KeyRound size={15} /> Account & password</Link>
          <button role="menuitem" onClick={async () => { await logout(); nav('/login') }} className="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-slate-50"><LogOut size={15} /> Sign out</button>
        </div>
      )}
    </div>
  )
}

export function AppLayout() {
  const [drawer, setDrawer] = useState(false)
  const loc = useLocation()
  useEffect(() => setDrawer(false), [loc.pathname])
  return (
    <div className="min-h-screen lg:pl-64">
      <aside className="fixed inset-y-0 left-0 hidden w-64 lg:block"><Sidebar /></aside>
      {drawer && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-navy/60" onClick={() => setDrawer(false)} />
          <aside className="absolute inset-y-0 left-0 w-72 max-w-[85vw]">
            <button onClick={() => setDrawer(false)} aria-label="Close menu" className="absolute right-2 top-3 z-10 rounded p-1 text-white"><X size={20} /></button>
            <Sidebar onNavigate={() => setDrawer(false)} />
          </aside>
        </div>
      )}
      <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur">
        <button className="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" onClick={() => setDrawer(true)} aria-label="Open menu"><Menu size={20} /></button>
        <div className="flex-1" />
        <div className="flex items-center gap-1"><NotificationBell /><UserMenu /></div>
      </header>
      <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6"><Outlet /></main>
    </div>
  )
}
