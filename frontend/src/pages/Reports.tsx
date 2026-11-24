import { FileSpreadsheet, FileText, Play } from 'lucide-react'
import { useState } from 'react'
import { FkSelect } from '@/components/FkSelect'
import { Button, Card, Empty, ErrorBox, Input, Label, PageHeader, Select, Spinner, Table } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { cn, errMsg, humanize } from '@/lib/utils'
import { api } from '@/services/api'

export function ReportsPage() {
  const { can } = useAuth()
  const toast = useToast()
  const catalog = useGet<any[]>('/reports')
  const [key, setKey] = useState<string | null>(null)
  const [vals, setVals] = useState<Record<string, string>>({})
  const [result, setResult] = useState<any>(null)
  const [busy, setBusy] = useState(false)
  const rep = catalog.data?.find((r) => r.key === key)
  const missing = rep?.filters.filter((f: any) => f.required && !vals[f.name]).map((f: any) => f.label) ?? []
  const params = () => Object.fromEntries(Object.entries(vals).filter(([, v]) => v !== ''))

  const run = async () => {
    setBusy(true)
    try { setResult((await api.get(`/reports/${key}`, { params: params() })).data) } catch (e) { toast.err(errMsg(e)); setResult(null) } finally { setBusy(false) }
  }
  const exportAs = (format: 'pdf' | 'xlsx') => download(`/reports/${key}`, { ...params(), format }, `${key}.${format}`).catch((e) => toast.err(errMsg(e)))

  return (
    <>
      <PageHeader title="Reports" subtitle="Run a report, then export it as PDF or Excel" />
      {catalog.loading ? <Spinner /> : catalog.error ? <ErrorBox message={catalog.error} onRetry={catalog.reload} /> : !catalog.data?.length ? <Card><Empty text="No reports are available for your role." /></Card> : (
        <div className="grid gap-5 lg:grid-cols-[18rem_1fr]">
          <Card className="h-fit" title="Available reports">
            <ul className="-mx-2 space-y-0.5">
              {catalog.data.map((r) => (
                <li key={r.key}><button onClick={() => { setKey(r.key); setVals({}); setResult(null) }} className={cn('w-full rounded-lg px-2 py-2 text-left text-sm', key === r.key ? 'bg-royal text-white' : 'hover:bg-slate-100')}>{r.title}</button></li>
              ))}
            </ul>
          </Card>
          <div className="min-w-0">
            {!rep ? <Card><Empty text="Choose a report on the left." /></Card> : (
              <>
                <Card title={rep.title}>
                  <div className="flex flex-wrap items-end gap-3">
                    {rep.filters.map((f: any) => (
                      <div key={f.name} className="min-w-[10rem]"><Label required={f.required}>{f.label}</Label>
                        {f.type === 'date' && <Input type="date" value={vals[f.name] ?? ''} onChange={(e) => setVals({ ...vals, [f.name]: e.target.value })} />}
                        {f.type === 'enum' && <Select value={vals[f.name] ?? ''} onChange={(e) => setVals({ ...vals, [f.name]: e.target.value })}><option value="">All</option>{f.vals.map((v: string) => <option key={v} value={v}>{humanize(v)}</option>)}</Select>}
                        {f.type === 'fk' && <FkSelect resource={f.ref} value={vals[f.name]} placeholder={f.required ? 'Select…' : 'All'} onChange={(v) => setVals({ ...vals, [f.name]: v })} />}
                        {f.type === 'string' && <Input value={vals[f.name] ?? ''} onChange={(e) => setVals({ ...vals, [f.name]: e.target.value })} />}
                      </div>
                    ))}
                    <Button loading={busy} disabled={missing.length > 0} onClick={run}><Play size={14} /> Run</Button>
                  </div>
                  {missing.length > 0 && <p className="mt-2 text-xs text-slate-500">Required: {missing.join(', ')}</p>}
                </Card>
                {result && (
                  <Card className="mt-4" title={`${result.title} · ${result.rows.length} row${result.rows.length === 1 ? '' : 's'}`} action={can('reports.export') && <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => exportAs('pdf')}><FileText size={14} /> PDF</Button><Button size="sm" variant="secondary" onClick={() => exportAs('xlsx')}><FileSpreadsheet size={14} /> Excel</Button></div>}>
                    {!result.rows.length ? <Empty text="No data for these filters." /> : (
                      <Table head={result.headings}>
                        {result.rows.map((row: any[], i: number) => <tr key={i} className="hover:bg-slate-50">{row.map((c, j) => <td key={j} className="whitespace-nowrap px-3 py-2">{c === null || c === '' ? '—' : String(c)}</td>)}</tr>)}
                      </Table>
                    )}
                  </Card>
                )}
              </>
            )}
          </div>
        </div>
      )}
    </>
  )
}
