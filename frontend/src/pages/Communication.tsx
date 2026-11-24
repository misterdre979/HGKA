import { Mail, Megaphone, Pin, Plus, Reply, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Pager, Select, Spinner, Tabs, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet, type Page } from '@/hooks/useApi'
import { errMsg, fmtDateTime, humanize } from '@/lib/utils'
import { api } from '@/services/api'

// ---------------------------------------------------------------- announcements
export function AnnouncementsPage() {
  const { can } = useAuth()
  const toast = useToast()
  const { data, loading, error, reload } = useGet<any[]>('/announcements')
  const roles = useGet<any[]>(can('communication.send') ? '/roles' : null)
  const [open, setOpen] = useState(false)
  const [del, setDel] = useState<any>(null)
  const blank = { title: '', body: '', audience: [] as string[], priority: 'normal', is_pinned: false, notify: true, channels: ['app'] as string[] }
  const [f, setF] = useState(blank)
  const [busy, setBusy] = useState(false)
  const toggle = (k: 'audience' | 'channels', v: string) => setF({ ...f, [k]: f[k].includes(v) ? f[k].filter((x) => x !== v) : [...f[k], v] })

  return (
    <>
      <PageHeader title="Announcements" actions={can('communication.send') && <Button onClick={() => { setF(blank); setOpen(true) }}><Plus size={16} /> New announcement</Button>} />
      {loading ? <Spinner /> : error ? <ErrorBox message={error} onRetry={reload} /> : !data?.length ? <Card><Empty text="No announcements." /></Card> : (
        <div className="space-y-3">
          {data.map((a) => (
            <Card key={a.id}>
              <div className="flex items-start gap-3">
                <Megaphone className="mt-1 shrink-0 text-royal" size={18} />
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2"><h2 className="font-semibold text-navy">{a.title}</h2>{a.is_pinned && <Pin size={14} className="text-gold" aria-label="Pinned" />}{a.priority !== 'normal' && <Badge value={a.priority} />}</div>
                  <p className="mt-1 whitespace-pre-line text-sm text-slate-700">{a.body}</p>
                  <p className="mt-2 text-xs text-slate-400">{a.created_by?.name} · {fmtDateTime(a.published_at)}{a.audience?.length ? ` · for ${a.audience.map(humanize).join(', ')}` : ' · everyone'}</p>
                </div>
                {can('communication.send') && <Button size="sm" variant="ghost" className="text-red-600" aria-label="Delete" onClick={() => setDel(a)}><Trash2 size={15} /></Button>}
              </div>
            </Card>
          ))}
        </div>
      )}
      <Modal wide open={open} onClose={() => setOpen(false)} title="New announcement" footer={<><Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button loading={busy} disabled={!f.title.trim() || !f.body.trim()} onClick={async () => {
        setBusy(true)
        try { await api.post('/announcements', f); toast.ok('Announcement published.'); setOpen(false); void reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
      }}>Publish</Button></>}>
        <div className="space-y-3">
          <label className="block"><Label required>Title</Label><Input value={f.title} onChange={(e) => setF({ ...f, title: e.target.value })} /></label>
          <label className="block"><Label required>Message</Label><Textarea rows={5} value={f.body} onChange={(e) => setF({ ...f, body: e.target.value })} /></label>
          <div className="grid gap-3 sm:grid-cols-2">
            <label className="block"><Label>Priority</Label><Select value={f.priority} onChange={(e) => setF({ ...f, priority: e.target.value })}><option value="normal">Normal</option><option value="important">Important</option><option value="urgent">Urgent</option></Select></label>
            <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" className="h-4 w-4" checked={f.is_pinned} onChange={(e) => setF({ ...f, is_pinned: e.target.checked })} /> Pin to top</label>
          </div>
          <fieldset><legend className="mb-1 text-sm font-medium text-slate-700">Audience <span className="text-xs font-normal text-slate-400">(none selected = everyone)</span></legend>
            <div className="flex flex-wrap gap-x-4 gap-y-1">{roles.data?.map((r) => <label key={r.slug} className="flex items-center gap-1.5 text-sm"><input type="checkbox" checked={f.audience.includes(r.slug)} onChange={() => toggle('audience', r.slug)} /> {r.name}</label>)}</div></fieldset>
          <fieldset><legend className="mb-1 text-sm font-medium text-slate-700">Also send by</legend>
            <div className="flex flex-wrap gap-4 text-sm"><label className="flex items-center gap-1.5"><input type="checkbox" checked={f.notify} onChange={(e) => setF({ ...f, notify: e.target.checked })} /> Notify recipients</label>
              {f.notify && (['app', 'email', 'sms'] as const).map((c) => <label key={c} className="flex items-center gap-1.5"><input type="checkbox" checked={f.channels.includes(c)} onChange={() => toggle('channels', c)} /> {c === 'app' ? 'In-app' : c.toUpperCase()}</label>)}</div></fieldset>
        </div>
      </Modal>
      <Confirm open={!!del} danger title="Delete announcement" message={`Delete “${del?.title}”?`} confirmLabel="Delete" onCancel={() => setDel(null)}
        onConfirm={async () => { try { await api.delete(`/announcements/${del.id}`); toast.ok('Deleted.'); setDel(null); void reload() } catch (e) { toast.err(errMsg(e)); setDel(null) } }} />
    </>
  )
}

// ---------------------------------------------------------------- messages
export function MessagesPage() {
  const toast = useToast()
  const [box, setBox] = useState<'inbox' | 'sent'>('inbox')
  const [page, setPage] = useState(1)
  const list = useGet<Page<any>>(`/messages/${box}`, { page })
  const [read, setRead] = useState<any>(null)
  const [compose, setCompose] = useState<{ to?: any; parent_id?: number; subject?: string } | null>(null)
  const [q, setQ] = useState('')
  const [rcp, setRcp] = useState<any[]>([])
  const [to, setTo] = useState('')
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    if (!compose || compose.to) return
    const t = setTimeout(() => api.get('/messages/recipients', { params: { q } }).then((r) => setRcp(r.data)).catch(() => setRcp([])), 250)
    return () => clearTimeout(t)
  }, [q, compose])

  const open = async (m: any) => {
    try { const r = await api.get(`/messages/${m.id}`); setRead(r.data); if (box === 'inbox' && !m.read_at) void list.reload() } catch (e) { toast.err(errMsg(e)) }
  }
  const start = (c: NonNullable<typeof compose>) => { setCompose(c); setTo(c.to ? String(c.to.id) : ''); setSubject(c.subject ?? ''); setBody(''); setQ(''); setRcp([]) }

  return (
    <>
      <PageHeader title="Messages" actions={<Button onClick={() => start({})}><Plus size={16} /> New message</Button>} />
      <Tabs value={box} onChange={(v) => { setBox(v as 'inbox' | 'sent'); setPage(1) }} tabs={[{ id: 'inbox', label: 'Inbox' }, { id: 'sent', label: 'Sent' }]} />
      <Card>
        {list.loading && !list.data ? <Spinner /> : list.error ? <ErrorBox message={list.error} onRetry={list.reload} /> : !list.data?.data.length ? <Empty text="No messages." /> : (
          <>
            <ul className="divide-y divide-slate-100">
              {list.data.data.map((m) => (
                <li key={m.id}><button onClick={() => open(m)} className="flex w-full items-center gap-3 px-1 py-3 text-left hover:bg-slate-50">
                  <Mail size={16} className={box === 'inbox' && !m.read_at ? 'text-royal' : 'text-slate-300'} />
                  <div className="min-w-0 flex-1"><div className={`truncate text-sm ${box === 'inbox' && !m.read_at ? 'font-semibold' : ''}`}>{m.subject || '(no subject)'}</div><div className="truncate text-xs text-slate-500">{box === 'inbox' ? m.sender?.name : `To: ${m.recipient?.name}`} — {m.body}</div></div>
                  <span className="shrink-0 text-xs text-slate-400">{fmtDateTime(m.created_at)}</span></button></li>
              ))}
            </ul>
            <Pager page={list.data.current_page} last={list.data.last_page} total={list.data.total} onPage={setPage} />
          </>
        )}
      </Card>
      <Modal open={!!read} onClose={() => setRead(null)} title={read?.subject || '(no subject)'} footer={read && box === 'inbox' ? <Button onClick={() => { const m = read; setRead(null); start({ to: m.sender, parent_id: m.id, subject: m.subject?.startsWith('Re:') ? m.subject : `Re: ${m.subject ?? ''}` }) }}><Reply size={14} /> Reply</Button> : undefined}>
        <p className="mb-3 text-xs text-slate-500">{read?.sender?.name} → {read?.recipient?.name ?? 'you'} · {fmtDateTime(read?.created_at)}</p>
        <p className="whitespace-pre-line text-sm">{read?.body}</p>
      </Modal>
      <Modal open={!!compose} onClose={() => setCompose(null)} title="New message" footer={<><Button variant="secondary" onClick={() => setCompose(null)}>Cancel</Button><Button loading={busy} disabled={!to || !body.trim()} onClick={async () => {
        setBusy(true)
        try { await api.post('/messages', { recipient_id: Number(to), subject: subject || null, body, parent_id: compose?.parent_id ?? null }); toast.ok('Message sent.'); setCompose(null); if (box === 'sent') void list.reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
      }}>Send</Button></>}>
        <div className="space-y-3">
          {compose?.to ? <p className="text-sm">To: <b>{compose.to.name}</b></p> : (
            <div><Label required>To</Label><Input placeholder="Search by name or email…" value={q} onChange={(e) => setQ(e.target.value)} />
              <Select className="mt-2" size={4} value={to} onChange={(e) => setTo(e.target.value)} aria-label="Recipient">{rcp.map((u) => <option key={u.id} value={u.id}>{u.name} — {u.roles}</option>)}</Select></div>
          )}
          <label className="block"><Label>Subject</Label><Input value={subject} onChange={(e) => setSubject(e.target.value)} /></label>
          <label className="block"><Label required>Message</Label><Textarea rows={5} value={body} onChange={(e) => setBody(e.target.value)} /></label>
        </div>
      </Modal>
    </>
  )
}

// ---------------------------------------------------------------- notifications
export function NotificationsPage() {
  const toast = useToast()
  const [page, setPage] = useState(1)
  const { data, loading, error, reload } = useGet<Page<any>>('/notifications', { page })
  return (
    <>
      <PageHeader title="Notifications" actions={<Button variant="secondary" onClick={async () => { try { await api.post('/notifications/read-all'); toast.ok('All marked as read.'); void reload() } catch (e) { toast.err(errMsg(e)) } }}>Mark all read</Button>} />
      <Card>
        {loading && !data ? <Spinner /> : error ? <ErrorBox message={error} onRetry={reload} /> : !data?.data.length ? <Empty text="You're all caught up." /> : (
          <>
            <ul className="divide-y divide-slate-100">
              {data.data.map((n) => (
                <li key={n.id} className="flex items-start gap-3 py-3">
                  <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${n.read_at ? 'bg-slate-200' : 'bg-royal'}`} />
                  <div className="min-w-0 flex-1"><div className={`text-sm ${n.read_at ? '' : 'font-semibold'}`}>{n.title}</div><div className="text-sm text-slate-600">{n.body}</div><div className="mt-0.5 text-xs text-slate-400">{fmtDateTime(n.created_at)}</div></div>
                  {!n.read_at && <Button size="sm" variant="ghost" onClick={async () => { try { await api.post(`/notifications/${n.id}/read`); void reload() } catch (e) { toast.err(errMsg(e)) } }}>Mark read</Button>}
                </li>
              ))}
            </ul>
            <Pager page={data.current_page} last={data.last_page} total={data.total} onPage={setPage} />
          </>
        )}
      </Card>
    </>
  )
}
