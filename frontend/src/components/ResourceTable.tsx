import { Pencil, Plus, Search, Trash2 } from 'lucide-react'
import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { FkSelect } from '@/components/FkSelect'
import { ResourceForm } from '@/components/ResourceForm'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Modal, PageHeader, Pager, Select, Spinner, Table } from '@/components/ui'
import { LABELS, RESOURCES } from '@/config/resources.generated'
import type { ColumnConfig } from '@/config/types'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errMsg, fmtDate, humanize, money } from '@/lib/utils'
import { api } from '@/services/api'
import type { Page } from '@/hooks/useApi'

type Row = Record<string, any>

export function labelOf(ref: string, obj: Row | null | undefined): string {
  if (!obj) return '—'
  const spec = LABELS[ref]
  if (!spec) return String(obj.name ?? obj.title ?? obj.id)
  const vals = spec.cols.map((c) => obj[c] ?? '')
  if (spec.fmt) return spec.fmt.replace(/\{(\d+)\}/g, (_, i) => String(vals[Number(i)] ?? ''))
  return vals.filter((v) => v !== '').join(' ') || String(obj.id)
}

function Cell({ col, row }: { col: ColumnConfig; row: Row }) {
  const v = row[col.key]
  if (col.type === 'fk') return <>{labelOf(col.ref ?? '', row[col.rel ?? ''])}</>
  if (v === null || v === undefined || v === '') return <span className="text-slate-300">—</span>
  if (col.type === 'bool') return v ? <Badge value="yes" tone="green" /> : <Badge value="no" tone="gray" />
  if (col.type === 'date') return <>{fmtDate(v)}</>
  if (col.type === 'enum') return <Badge value={String(v)} />
  if (col.money) return <>{money(v)}</>
  if (col.type === 'text') return <span className="line-clamp-2 max-w-xs">{String(v)}</span>
  return <>{String(v)}</>
}

/**
 * Config-driven list + create/edit/delete screen for any generic resource.
 * Every button is gated by the permissions in the generated config; the API enforces them again.
 */
export function ResourceTable({ resource, title, subtitle, extraActions, extraRow, params, createDefaults, hideHeader, noCreate }: {
  resource: string
  title?: string
  subtitle?: string
  extraActions?: ReactNode
  extraRow?: (row: Row, reload: () => void) => ReactNode
  params?: Record<string, unknown>
  createDefaults?: Row
  hideHeader?: boolean
  noCreate?: boolean
}) {
  const cfg = RESOURCES[resource]
  const { can } = useAuth()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [filters, setFilters] = useState<Record<string, string>>({})
  const [data, setData] = useState<Page<Row> | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState<Row | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Row | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => { const t = setTimeout(() => { setQ(search); setPage(1) }, 350); return () => clearTimeout(t) }, [search])

  const pkey = JSON.stringify(params ?? {})
  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const query: Record<string, unknown> = { page, per_page: 15, ...(params ?? {}) }
      if (q) query.search = q
      for (const [k, v] of Object.entries(filters)) if (v) query[`filter[${k}]`] = v
      const r = await api.get(`/${resource}`, { params: query })
      setData(r.data)
    } catch (e) {
      setError(errMsg(e))
    } finally {
      setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resource, page, q, filters, pkey])

  useEffect(() => { void load() }, [load])

  if (!cfg) return <ErrorBox message={`Unknown resource “${resource}”.`} />

  const canCreate = !noCreate && !cfg.readonly && can(...cfg.perms.create)
  const canUpdate = !cfg.readonly && can(...cfg.perms.update)
  const canDelete = !cfg.readonly && can(...cfg.perms.delete)

  const save = async (values: Row) => {
    if (editing === 'new') {
      await api.post(`/${resource}`, { ...createDefaults, ...values })
      toast.ok(`${cfg.label.replace(/s$/, '')} created.`)
    } else if (editing) {
      await api.put(`/${resource}/${editing.id}`, values)
      toast.ok('Changes saved.')
    }
    setEditing(null)
    void load()
  }

  const remove = async () => {
    if (!deleting) return
    setBusy(true)
    try {
      await api.delete(`/${resource}/${deleting.id}`)
      toast.ok('Deleted.')
      setDeleting(null)
      if (data && data.data.length === 1 && page > 1) setPage(page - 1)
      else void load()
    } catch (e) {
      toast.err(errMsg(e))
      setDeleting(null)
    } finally {
      setBusy(false)
    }
  }

  const body = (
    <Card>
      <div className="mb-4 flex flex-wrap items-end gap-2">
        {cfg.search && (
          <div className="relative min-w-[12rem] flex-1 sm:max-w-xs">
            <Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <Input className="pl-9" placeholder={`Search ${cfg.label.toLowerCase()}…`} value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Search" />
          </div>
        )}
        {cfg.filters.map((f) => (
          <div key={f.name} className="min-w-[9rem]">
            {f.type === 'enum' && (
              <Select aria-label={f.label} value={filters[f.name] ?? ''} onChange={(e) => { setFilters({ ...filters, [f.name]: e.target.value }); setPage(1) }}>
                <option value="">{f.label}: all</option>
                {f.vals?.map((v) => <option key={v} value={v}>{humanize(v)}</option>)}
              </Select>
            )}
            {f.type === 'fk' && f.ref && (
              <FkSelect aria-label={f.label} resource={f.ref} value={filters[f.name]} placeholder={`${f.label}: all`} onChange={(v) => { setFilters({ ...filters, [f.name]: v }); setPage(1) }} />
            )}
            {f.type === 'bool' && (
              <Select aria-label={f.label} value={filters[f.name] ?? ''} onChange={(e) => { setFilters({ ...filters, [f.name]: e.target.value }); setPage(1) }}>
                <option value="">{f.label}: all</option><option value="1">Yes</option><option value="0">No</option>
              </Select>
            )}
          </div>
        ))}
      </div>

      {error && <ErrorBox message={error} onRetry={load} />}
      {loading && !data ? <Spinner /> : data && data.data.length === 0 ? (
        <Empty text={q || Object.values(filters).some(Boolean) ? 'No records match your search.' : `No ${cfg.label.toLowerCase()} yet.`}>
          {canCreate && !q && <Button size="sm" onClick={() => setEditing('new')}><Plus size={14} /> Add {cfg.label.replace(/s$/, '').toLowerCase()}</Button>}
        </Empty>
      ) : data && (
        <div className={loading ? 'opacity-60 transition' : ''}>
          <Table head={[...cfg.columns.map((c) => c.label), '']}>
            {data.data.map((row) => (
              <tr key={row.id} className="hover:bg-slate-50">
                {cfg.columns.map((c) => <td key={c.key} className="px-3 py-2.5 align-top"><Cell col={c} row={row} /></td>)}
                <td className="whitespace-nowrap px-3 py-2.5 text-right">
                  <div className="inline-flex gap-1">
                    {extraRow?.(row, load)}
                    {canUpdate && <Button size="sm" variant="ghost" aria-label="Edit" onClick={() => setEditing(row)}><Pencil size={14} /></Button>}
                    {canDelete && <Button size="sm" variant="ghost" aria-label="Delete" className="text-red-600" onClick={() => setDeleting(row)}><Trash2 size={14} /></Button>}
                  </div>
                </td>
              </tr>
            ))}
          </Table>
          <Pager page={data.current_page} last={data.last_page} total={data.total} onPage={setPage} />
        </div>
      )}
    </Card>
  )

  return (
    <>
      {!hideHeader && (
        <PageHeader
          title={title ?? cfg.label}
          subtitle={subtitle}
          actions={<>{extraActions}{canCreate && <Button onClick={() => setEditing('new')}><Plus size={16} /> Add</Button>}</>}
        />
      )}
      {hideHeader && canCreate && <div className="mb-3 flex justify-end"><Button onClick={() => setEditing('new')}><Plus size={16} /> Add</Button></div>}
      {body}

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? `Add ${cfg.label.replace(/s$/, '').toLowerCase()}` : `Edit ${cfg.label.replace(/s$/, '').toLowerCase()}`} wide={cfg.fields.length > 6}>
        {editing !== null && (
          <ResourceForm
            fields={cfg.fields}
            initial={editing === 'new' ? createDefaults : editing}
            onSubmit={save}
            onCancel={() => setEditing(null)}
          />
        )}
      </Modal>
      <Confirm open={!!deleting} title="Delete record" message="This cannot be undone. Records that are in use elsewhere cannot be deleted." confirmLabel="Delete" danger busy={busy} onCancel={() => setDeleting(null)} onConfirm={remove} />
    </>
  )
}
