import { Download, KeyRound, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { ResourceTable } from '@/components/ResourceTable'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Pager, Select, Spinner, Table, Tabs, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet, type Page } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg, fieldErrors, fmtDateTime, humanize } from '@/lib/utils'
import { api } from '@/services/api'

// ---------------------------------------------------------------- users
export function UsersPage() {
  const { can, user: me } = useAuth()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [role, setRole] = useState('')
  const [active, setActive] = useState('')
  const roles = useGet<any[]>('/roles')
  const users = useGet<Page<any>>('/users', { page, search: q || undefined, role: role || undefined, active: active || undefined })
  const [edit, setEdit] = useState<any | 'new' | null>(null)
  const [f, setF] = useState({ name: '', email: '', phone: '', roles: [] as string[], is_active: true })
  const [errs, setErrs] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  const [temp, setTemp] = useState<{ name: string; email: string; password: string } | null>(null)
  const [reset, setReset] = useState<any>(null)
  const [del, setDel] = useState<any>(null)
  useEffect(() => { const t = setTimeout(() => { setQ(search); setPage(1) }, 350); return () => clearTimeout(t) }, [search])

  const openEdit = (u: any | 'new') => {
    setErrs({}); setEdit(u)
    setF(u === 'new' ? { name: '', email: '', phone: '', roles: [], is_active: true } : { name: u.name, email: u.email, phone: u.phone ?? '', roles: u.roles.map((r: any) => r.slug), is_active: !!u.is_active })
  }
  const save = async () => {
    setBusy(true); setErrs({})
    try {
      if (edit === 'new') {
        const r = await api.post('/users', { ...f, phone: f.phone || null })
        setTemp({ name: r.data.user.name, email: r.data.user.email, password: r.data.temporary_password })
      } else { await api.put(`/users/${edit.id}`, { ...f, phone: f.phone || null }); toast.ok('User updated.') }
      setEdit(null); void users.reload()
    } catch (e) { const fe = fieldErrors(e); if (Object.keys(fe).length) setErrs(fe); else toast.err(errMsg(e)) } finally { setBusy(false) }
  }

  return (
    <>
      <PageHeader title="Users" subtitle="Accounts and their roles" actions={can('users.create') && <Button onClick={() => openEdit('new')}><Plus size={16} /> New user</Button>} />
      <Card>
        <div className="mb-4 flex flex-wrap gap-2">
          <Input className="max-w-xs" placeholder="Search name or email…" aria-label="Search" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Select aria-label="Role" className="max-w-[12rem]" value={role} onChange={(e) => { setRole(e.target.value); setPage(1) }}><option value="">All roles</option>{roles.data?.map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}</Select>
          <Select aria-label="Status" className="max-w-[9rem]" value={active} onChange={(e) => { setActive(e.target.value); setPage(1) }}><option value="">Any status</option><option value="1">Active</option><option value="0">Inactive</option></Select>
        </div>
        {users.error && <ErrorBox message={users.error} onRetry={users.reload} />}
        {users.loading && !users.data ? <Spinner /> : !users.data?.data.length ? <Empty text="No users found." /> : (
          <>
            <Table head={['Name', 'Email', 'Roles', 'Status', 'Last login', '']}>
              {users.data.data.map((u) => (
                <tr key={u.id}><td className="px-3 py-2 font-medium">{u.name}</td><td className="px-3 py-2">{u.email}</td><td className="px-3 py-2">{u.roles.map((r: any) => r.name).join(', ')}</td>
                  <td className="px-3 py-2"><Badge value={u.is_active ? 'active' : 'suspended'} tone={u.is_active ? 'green' : 'red'} /></td><td className="px-3 py-2 text-xs">{fmtDateTime(u.last_login_at)}</td>
                  <td className="whitespace-nowrap px-3 py-2 text-right">
                    {can('users.update') && <><Button size="sm" variant="secondary" onClick={() => openEdit(u)}>Edit</Button> <Button size="sm" variant="ghost" aria-label="Reset password" onClick={() => setReset(u)}><KeyRound size={14} /></Button></>}
                    {can('users.delete') && u.id !== me?.id && <Button size="sm" variant="ghost" className="text-red-600" aria-label="Delete" onClick={() => setDel(u)}><Trash2 size={14} /></Button>}
                  </td></tr>
              ))}
            </Table>
            <Pager page={users.data.current_page} last={users.data.last_page} total={users.data.total} onPage={setPage} />
          </>
        )}
      </Card>

      <Modal open={edit !== null} onClose={() => setEdit(null)} title={edit === 'new' ? 'New user' : 'Edit user'} footer={<><Button variant="secondary" onClick={() => setEdit(null)}>Cancel</Button><Button loading={busy} disabled={!f.name || !f.email || !f.roles.length} onClick={save}>Save</Button></>}>
        <div className="space-y-3">
          <label className="block"><Label required>Name</Label><Input value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} />{errs.name && <p className="text-xs text-red-600">{errs.name}</p>}</label>
          <label className="block"><Label required>Email</Label><Input type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />{errs.email && <p className="text-xs text-red-600">{errs.email}</p>}</label>
          <label className="block"><Label>Phone</Label><Input value={f.phone} onChange={(e) => setF({ ...f, phone: e.target.value })} /></label>
          <fieldset><legend className="mb-1 text-sm font-medium">Roles <span className="text-red-500">*</span></legend>
            <div className="grid grid-cols-2 gap-1">{roles.data?.map((r) => <label key={r.slug} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={f.roles.includes(r.slug)} onChange={() => setF({ ...f, roles: f.roles.includes(r.slug) ? f.roles.filter((x) => x !== r.slug) : [...f.roles, r.slug] })} /> {r.name}</label>)}</div>
            {(errs.roles || errs['roles.0']) && <p className="text-xs text-red-600">{errs.roles ?? errs['roles.0']}</p>}</fieldset>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={f.is_active} disabled={edit !== 'new' && edit?.id === me?.id} onChange={(e) => setF({ ...f, is_active: e.target.checked })} /> Account is active</label>
          {edit === 'new' && <p className="text-xs text-slate-500">A temporary password is generated and shown once. The user must change it at first sign-in.</p>}
        </div>
      </Modal>
      <Modal open={!!temp} onClose={() => setTemp(null)} title="User created" footer={<Button onClick={() => setTemp(null)}>Done</Button>}>
        <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Share this temporary password securely. It is shown only once.</p>
        <p className="text-sm">{temp?.name} · {temp?.email}</p><p className="mt-2 rounded-lg bg-slate-100 px-3 py-2 font-mono text-lg font-semibold">{temp?.password}</p>
      </Modal>
      <Confirm open={!!reset} title="Reset password" message={`Generate a new temporary password for ${reset?.name}? Their current password stops working immediately and all sessions are signed out.`} confirmLabel="Reset" onCancel={() => setReset(null)}
        onConfirm={async () => { try { const r = await api.post(`/users/${reset.id}/reset-password`); setTemp({ name: reset.name, email: reset.email, password: r.data.temporary_password }); setReset(null) } catch (e) { toast.err(errMsg(e)); setReset(null) } }} />
      <Confirm open={!!del} danger title="Remove user" message={`Remove ${del?.name}? They will no longer be able to sign in.`} confirmLabel="Remove" onCancel={() => setDel(null)}
        onConfirm={async () => { try { await api.delete(`/users/${del.id}`); toast.ok('User removed.'); setDel(null); void users.reload() } catch (e) { toast.err(errMsg(e)); setDel(null) } }} />
    </>
  )
}

// ---------------------------------------------------------------- roles & permissions
export function RolesPage() {
  const { can } = useAuth()
  const toast = useToast()
  const roles = useGet<any[]>('/roles')
  const perms = useGet<Record<string, { id: number; name: string }[]>>('/permissions')
  const [sel, setSel] = useState<any | 'new' | null>(null)
  const [name, setName] = useState('')
  const [desc, setDesc] = useState('')
  const [granted, setGranted] = useState<Set<string>>(new Set())
  const [busy, setBusy] = useState(false)
  const [del, setDel] = useState<any>(null)
  const manage = can('roles.manage')

  const pick = (r: any | 'new') => { setSel(r); setName(r === 'new' ? '' : r.name); setDesc(r === 'new' ? '' : r.description ?? ''); setGranted(new Set(r === 'new' ? [] : r.permissions)) }
  const toggle = (p: string) => setGranted((s) => { const n = new Set(s); if (n.has(p)) n.delete(p); else n.add(p); return n })
  const locked = sel !== 'new' && sel?.slug === 'super-admin'

  const save = async () => {
    setBusy(true)
    try {
      const body = { name, description: desc || null, permissions: [...granted] }
      if (sel === 'new') await api.post('/roles', body); else await api.put(`/roles/${sel.id}`, body)
      toast.ok('Role saved.'); setSel(null); void roles.reload()
    } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
  }

  return (
    <>
      <PageHeader title="Roles & permissions" subtitle="Control what each role can see and do" actions={manage && <Button onClick={() => pick('new')}><Plus size={16} /> New role</Button>} />
      <div className="grid gap-5 lg:grid-cols-[18rem_1fr]">
        <Card title="Roles">
          {roles.loading ? <Spinner /> : roles.error ? <ErrorBox message={roles.error} onRetry={roles.reload} /> : (
            <ul className="-mx-2 space-y-0.5">{roles.data?.map((r) => <li key={r.id}><button onClick={() => pick(r)} className={`flex w-full items-center justify-between rounded-lg px-2 py-2 text-left text-sm ${sel !== 'new' && sel?.id === r.id ? 'bg-royal text-white' : 'hover:bg-slate-100'}`}><span>{r.name}</span><span className="text-xs opacity-70">{r.users_count}</span></button></li>)}</ul>
          )}
        </Card>
        {sel === null ? <Card><Empty text="Select a role to view its permissions." /></Card> : (
          <Card title={sel === 'new' ? 'New role' : sel.name} action={manage && sel !== 'new' && !sel.is_system && <Button size="sm" variant="ghost" className="text-red-600" onClick={() => setDel(sel)}><Trash2 size={14} /> Delete</Button>}>
            <div className="mb-4 grid gap-3 sm:grid-cols-2">
              <label><Label required>Name</Label><Input value={name} disabled={!manage || (sel !== 'new' && sel.is_system)} onChange={(e) => setName(e.target.value)} /></label>
              <label><Label>Description</Label><Input value={desc} disabled={!manage} onChange={(e) => setDesc(e.target.value)} /></label>
            </div>
            {locked && <p className="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">Super Administrator always has every permission and cannot be edited.</p>}
            <div className="grid gap-4 sm:grid-cols-2">
              {perms.data && Object.entries(perms.data).map(([mod, list]) => (
                <fieldset key={mod} className="rounded-lg border border-slate-200 p-3">
                  <legend className="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{humanize(mod)}</legend>
                  {list.map((p) => <label key={p.id} className="flex items-center gap-2 py-0.5 text-sm"><input type="checkbox" disabled={!manage || locked} checked={locked || granted.has(p.name)} onChange={() => toggle(p.name)} /> {humanize(p.name.split('.')[1] ?? p.name)}</label>)}
                </fieldset>
              ))}
            </div>
            {manage && !locked && <div className="mt-4 flex justify-end"><Button loading={busy} disabled={!name.trim()} onClick={save}>Save role</Button></div>}
          </Card>
        )}
      </div>
      <Confirm open={!!del} danger title="Delete role" message={`Delete the role “${del?.name}”? Roles that still have users cannot be deleted.`} confirmLabel="Delete" onCancel={() => setDel(null)}
        onConfirm={async () => { try { await api.delete(`/roles/${del.id}`); toast.ok('Role deleted.'); setDel(null); setSel(null); void roles.reload() } catch (e) { toast.err(errMsg(e)); setDel(null) } }} />
    </>
  )
}

// ---------------------------------------------------------------- settings & branding
const FIELDS: { key: string; label: string; type?: 'text' | 'color' | 'number' | 'bool' | 'date'; help?: string; group: string }[] = [
  { group: 'School', key: 'school_name', label: 'School name' }, { group: 'School', key: 'school_short_name', label: 'Short name' }, { group: 'School', key: 'motto', label: 'Motto' },
  { group: 'School', key: 'address', label: 'Address' }, { group: 'School', key: 'phone', label: 'Phone' }, { group: 'School', key: 'email', label: 'Email' }, { group: 'School', key: 'principal_name', label: "Principal's name" },
  { group: 'Report cards', key: 'max_ca_score', label: 'Maximum CA score', type: 'number', help: 'CA + exam should total 100' }, { group: 'Report cards', key: 'max_exam_score', label: 'Maximum exam score', type: 'number' }, { group: 'Report cards', key: 'next_term_begins', label: 'Next term begins', type: 'date' },
  { group: 'Library', key: 'library_loan_days', label: 'Loan period (days)', type: 'number' }, { group: 'Library', key: 'library_fine_per_day', label: 'Fine per overdue day (₦)', type: 'number' },
  { group: 'Notifications', key: 'notify_parents_on_absence', label: 'Notify parents when a child is absent', type: 'bool' }, { group: 'Notifications', key: 'low_stock_notify', label: 'Notify store keepers on low stock', type: 'bool' },
  { group: 'Branding', key: 'primary_color', label: 'Primary colour', type: 'color' }, { group: 'Branding', key: 'secondary_color', label: 'Secondary colour', type: 'color' }, { group: 'Branding', key: 'accent_color', label: 'Accent colour', type: 'color' },
]

export function SettingsPage() {
  const { can, refresh } = useAuth()
  const toast = useToast()
  const { data, loading, error, reload } = useGet<Record<string, string>>('/settings')
  const [v, setV] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (data) setV(data) }, [data])
  const edit = can('settings.update')
  const groups = [...new Set(FIELDS.map((f) => f.group))]

  const logo = async (file: File) => {
    const fd = new FormData(); fd.append('logo', file)
    try { await api.post('/settings/logo', fd); toast.ok('Logo updated.'); void reload() } catch (e) { toast.err(errMsg(e)) }
  }
  if (loading) return <Spinner />
  if (error) return <ErrorBox message={error} onRetry={reload} />
  return (
    <>
      <PageHeader title="School settings" subtitle="Branding, report-card rules and notifications" actions={edit && <Button loading={busy} onClick={async () => {
        setBusy(true)
        try { await api.put('/settings', Object.fromEntries(FIELDS.map((f) => [f.key, v[f.key] ?? '']))); toast.ok('Settings saved.'); void reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
      }}>Save settings</Button>} />
      <div className="grid gap-5 lg:grid-cols-2">
        {groups.map((g) => (
          <Card key={g} title={g}>
            <div className="space-y-3">
              {FIELDS.filter((f) => f.group === g).map((f) => (
                f.type === 'bool'
                  ? <label key={f.key} className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={!edit} checked={v[f.key] === '1'} onChange={(e) => setV({ ...v, [f.key]: e.target.checked ? '1' : '0' })} /> {f.label}</label>
                  : <label key={f.key} className="block"><Label help={f.help}>{f.label}</Label>
                    {f.type === 'color' ? <div className="flex items-center gap-2"><input type="color" disabled={!edit} className="h-9 w-12 rounded border border-slate-300" value={v[f.key] || '#000000'} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} /><Input disabled={!edit} value={v[f.key] ?? ''} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} /></div>
                      : <Input disabled={!edit} type={f.type ?? 'text'} value={v[f.key] ?? ''} onChange={(e) => setV({ ...v, [f.key]: e.target.value })} />}</label>
              ))}
              {g === 'Branding' && (
                <div className="flex items-center gap-4 border-t border-slate-100 pt-3">
                  {data?.logo_url ? <img src={data.logo_url} alt="School logo" className="h-16 w-16 rounded-lg object-contain" /> : <div className="grid h-16 w-16 place-items-center rounded-lg bg-slate-100 text-xs text-slate-400">Default logo</div>}
                  {edit && <label className="cursor-pointer text-sm text-royal hover:underline">Upload logo (PNG/JPG/WEBP, max 2 MB)<input type="file" className="hidden" accept="image/png,image/jpeg,image/webp" onChange={(e) => { const f = e.target.files?.[0]; if (f) void logo(f).then(refresh) }} /></label>}
                </div>
              )}
            </div>
          </Card>
        ))}
      </div>
    </>
  )
}

// ---------------------------------------------------------------- backups & audit
export function BackupsPage() {
  const toast = useToast()
  const { data, loading, error, reload } = useGet<any[]>('/backups')
  const [busy, setBusy] = useState(false)
  return (
    <>
      <PageHeader title="Backups" subtitle="Database backups are also created automatically every night" actions={<Button loading={busy} onClick={async () => {
        setBusy(true)
        try { await api.post('/backups'); toast.ok('Backup created.'); void reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
      }}>Create backup now</Button>} />
      <Card>
        {loading ? <Spinner /> : error ? <ErrorBox message={error} onRetry={reload} /> : !data?.length ? <Empty text="No backups yet." /> : (
          <Table head={['File', 'Size', 'Created', '']}>
            {data.map((b) => <tr key={b.name}><td className="px-3 py-2 font-mono text-xs">{b.name}</td><td className="px-3 py-2">{(b.size / 1024).toFixed(1)} KB</td><td className="px-3 py-2">{fmtDateTime(b.created_at)}</td>
              <td className="px-3 py-2 text-right"><Button size="sm" variant="secondary" onClick={() => download(`/backups/${encodeURIComponent(b.name)}/download`, undefined, b.name).catch((e) => toast.err(errMsg(e)))}><Download size={14} /> Download</Button></td></tr>)}
          </Table>
        )}
      </Card>
    </>
  )
}

export const AuditLogsPage = () => <ResourceTable resource="audit-logs" title="Audit logs" subtitle="A record of sensitive actions across the system" />
export { Tabs, Textarea }
