import { Download, Plus, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Select, Spinner, Table, Tabs } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg, fmtDate, humanize, money } from '@/lib/utils'
import { api } from '@/services/api'

interface Comp { type: 'allowance' | 'deduction'; name: string; amount: string; is_percent: boolean }

function StructureModal({ staff, onClose, onSaved }: { staff: any; onClose: () => void; onSaved: () => void }) {
  const toast = useToast()
  const [basic, setBasic] = useState('')
  const [comps, setComps] = useState<Comp[]>([])
  const [busy, setBusy] = useState(false)
  useEffect(() => {
    if (!staff) return
    setBasic(String(staff.salary_structure?.basic_salary ?? ''))
    setComps((staff.salary_components ?? []).map((c: any) => ({ type: c.type, name: c.name, amount: String(c.amount), is_percent: !!c.is_percent })))
  }, [staff])
  const b = Number(basic) || 0
  const val = (c: Comp) => (c.is_percent ? (b * Number(c.amount || 0)) / 100 : Number(c.amount || 0))
  const gross = b + comps.filter((c) => c.type === 'allowance').reduce((s, c) => s + val(c), 0)
  const ded = comps.filter((c) => c.type === 'deduction').reduce((s, c) => s + val(c), 0)
  const valid = b > 0 && comps.every((c) => c.name.trim() && c.amount !== '' && Number(c.amount) >= 0)
  const upd = (i: number, p: Partial<Comp>) => setComps(comps.map((c, j) => (j === i ? { ...c, ...p } : c)))

  return (
    <Modal wide open={!!staff} onClose={onClose} title={`Salary structure · ${staff?.first_name ?? ''} ${staff?.last_name ?? ''}`} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={busy} disabled={!valid} onClick={async () => {
      setBusy(true)
      try {
        await api.put(`/payroll/structures/${staff.id}`, { basic_salary: b, components: comps.map((c) => ({ ...c, amount: Number(c.amount) })) })
        toast.ok('Salary structure saved.'); onSaved()
      } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
    }}>Save</Button></>}>
      <div className="space-y-4">
        <label className="block max-w-xs"><Label required>Basic salary (monthly)</Label><Input type="number" min="0" step="0.01" value={basic} onChange={(e) => setBasic(e.target.value)} /></label>
        <div>
          <div className="mb-2 flex items-center justify-between"><h3 className="text-sm font-semibold text-navy">Allowances & deductions</h3>
            <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => setComps([...comps, { type: 'allowance', name: '', amount: '', is_percent: false }])}><Plus size={13} /> Allowance</Button><Button size="sm" variant="secondary" onClick={() => setComps([...comps, { type: 'deduction', name: '', amount: '', is_percent: false }])}><Plus size={13} /> Deduction</Button></div></div>
          {!comps.length && <p className="text-sm text-slate-500">None added.</p>}
          {comps.map((c, i) => (
            <div key={i} className="mb-2 grid grid-cols-[5.5rem_1fr_7rem_6rem_2rem] items-center gap-2">
              <Badge value={c.type} tone={c.type === 'allowance' ? 'green' : 'red'} />
              <Input aria-label="Name" placeholder="Name" value={c.name} onChange={(e) => upd(i, { name: e.target.value })} />
              <Input aria-label="Amount" type="number" min="0" step="0.01" value={c.amount} onChange={(e) => upd(i, { amount: e.target.value })} />
              <label className="flex items-center gap-1 text-xs"><input type="checkbox" checked={c.is_percent} onChange={(e) => upd(i, { is_percent: e.target.checked })} /> % of basic</label>
              <button aria-label="Remove" className="text-red-600" onClick={() => setComps(comps.filter((_, j) => j !== i))}><Trash2 size={15} /></button>
            </div>
          ))}
        </div>
        <dl className="ml-auto grid max-w-xs grid-cols-2 gap-1 rounded-lg bg-slate-50 p-3 text-sm">
          <dt className="text-slate-500">Gross</dt><dd className="text-right">{money(gross)}</dd><dt className="text-slate-500">Deductions</dt><dd className="text-right">−{money(ded)}</dd><dt className="font-semibold">Net pay</dt><dd className="text-right font-bold">{money(gross - ded)}</dd>
        </dl>
      </div>
    </Modal>
  )
}

function RunDetail({ id, onClose }: { id: number | null; onClose: () => void }) {
  const toast = useToast()
  const { data, loading, error } = useGet<any>(id ? `/payroll/runs/${id}` : null)
  return (
    <Modal wide open={!!id} onClose={onClose} title={data ? `Payroll · ${new Date(data.period_year, data.period_month - 1).toLocaleString('en-GB', { month: 'long', year: 'numeric' })}` : 'Payroll'}>
      {loading ? <Spinner /> : error || !data ? <ErrorBox message={error ?? 'Not found'} /> : (
        <Table head={['Staff', 'Basic', 'Allowances', 'Deductions', 'Net pay', 'Bank', '']}>
          {data.payslips.map((p: any) => (
            <tr key={p.id}><td className="px-3 py-2">{p.staff?.first_name} {p.staff?.last_name}<div className="text-xs text-slate-400">{p.staff?.staff_no}</div></td><td className="px-3 py-2">{money(p.basic_salary)}</td><td className="px-3 py-2">{money(p.total_allowances)}</td><td className="px-3 py-2">{money(p.total_deductions)}</td><td className="px-3 py-2 font-semibold">{money(p.net_pay)}</td><td className="px-3 py-2 text-xs">{p.staff?.bank_name} {p.staff?.account_number}</td>
              <td className="px-3 py-2 text-right"><Button size="sm" variant="ghost" aria-label="Payslip PDF" onClick={() => download(`/payroll/payslips/${p.id}/pdf`).catch((e) => toast.err(errMsg(e)))}><Download size={14} /></Button></td></tr>
          ))}
        </Table>
      )}
    </Modal>
  )
}

export function PayrollPage() {
  const { can } = useAuth()
  const toast = useToast()
  const [tab, setTab] = useState('runs')
  const runs = useGet<any[]>('/payroll/runs')
  const structs = useGet<any[]>(tab === 'structures' ? '/payroll/structures' : null)
  const [edit, setEdit] = useState<any>(null)
  const [view, setView] = useState<number | null>(null)
  const [proc, setProc] = useState(false)
  const now = new Date()
  const [ym, setYm] = useState({ year: String(now.getFullYear()), month: String(now.getMonth() + 1) })
  const [confirm, setConfirm] = useState<{ run: any; kind: 'pay' | 'delete' } | null>(null)
  const [busy, setBusy] = useState(false)
  const manage = can('payroll.manage')

  return (
    <>
      <PageHeader title="Payroll" subtitle="Salary structures, monthly runs and payslips" actions={manage && <Button onClick={() => setProc(true)}><Plus size={16} /> New payroll run</Button>} />
      <Tabs value={tab} onChange={setTab} tabs={[{ id: 'runs', label: 'Payroll runs' }, { id: 'structures', label: 'Salary structures' }]} />
      {tab === 'runs' && (
        <Card>{runs.loading ? <Spinner /> : runs.error ? <ErrorBox message={runs.error} onRetry={runs.reload} /> : !runs.data?.length ? <Empty text="No payroll has been processed yet." /> : (
          <Table head={['Period', 'Staff', 'Gross', 'Deductions', 'Net', 'Status', '']}>
            {runs.data.map((r) => (
              <tr key={r.id}><td className="px-3 py-2 font-medium">{new Date(r.period_year, r.period_month - 1).toLocaleString('en-GB', { month: 'long', year: 'numeric' })}</td><td className="px-3 py-2">{r.payslips_count}</td><td className="px-3 py-2">{money(r.total_gross)}</td><td className="px-3 py-2">{money(r.total_deductions)}</td><td className="px-3 py-2 font-semibold">{money(r.total_net)}</td><td className="px-3 py-2"><Badge value={r.status} /></td>
                <td className="whitespace-nowrap px-3 py-2 text-right"><Button size="sm" variant="secondary" onClick={() => setView(r.id)}>Open</Button>{manage && r.status === 'processed' && <> <Button size="sm" onClick={() => setConfirm({ run: r, kind: 'pay' })}>Mark paid</Button> <Button size="sm" variant="ghost" className="text-red-600" onClick={() => setConfirm({ run: r, kind: 'delete' })}>Delete</Button></>}</td></tr>
            ))}
          </Table>
        )}</Card>
      )}
      {tab === 'structures' && (
        <Card>{structs.loading ? <Spinner /> : structs.error ? <ErrorBox message={structs.error} onRetry={structs.reload} /> : (
          <Table head={['Staff', 'Designation', 'Basic salary', 'Components', '']}>
            {structs.data?.map((s) => (
              <tr key={s.id}><td className="px-3 py-2">{s.first_name} {s.last_name}<div className="text-xs text-slate-400">{s.staff_no}</div></td><td className="px-3 py-2">{s.designation?.name ?? '—'}</td>
                <td className="px-3 py-2">{s.salary_structure ? money(s.salary_structure.basic_salary) : <span className="text-red-600">Not set</span>}</td><td className="px-3 py-2">{s.salary_components?.length ?? 0}</td>
                <td className="px-3 py-2 text-right">{manage && <Button size="sm" variant="secondary" onClick={() => setEdit(s)}>Edit</Button>}</td></tr>
            ))}
          </Table>
        )}</Card>
      )}

      <StructureModal staff={edit} onClose={() => setEdit(null)} onSaved={() => { setEdit(null); void structs.reload() }} />
      <RunDetail id={view} onClose={() => setView(null)} />
      <Modal open={proc} onClose={() => setProc(false)} title="Process payroll" footer={<><Button variant="secondary" onClick={() => setProc(false)}>Cancel</Button><Button loading={busy} onClick={async () => {
        setBusy(true)
        try { await api.post('/payroll/runs', { year: Number(ym.year), month: Number(ym.month) }); toast.ok('Payroll processed.'); setProc(false); void runs.reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
      }}>Process</Button></>}>
        <p className="mb-3 text-sm text-slate-500">Generates a payslip for every active staff member who has a salary structure.</p>
        <div className="grid grid-cols-2 gap-3">
          <label><Label required>Month</Label><Select value={ym.month} onChange={(e) => setYm({ ...ym, month: e.target.value })}>{Array.from({ length: 12 }, (_, i) => <option key={i} value={i + 1}>{new Date(2000, i).toLocaleString('en-GB', { month: 'long' })}</option>)}</Select></label>
          <label><Label required>Year</Label><Input type="number" min="2000" max="2100" value={ym.year} onChange={(e) => setYm({ ...ym, year: e.target.value })} /></label>
        </div>
      </Modal>
      <Confirm open={!!confirm} busy={busy} danger={confirm?.kind === 'delete'} title={confirm?.kind === 'pay' ? 'Mark as paid' : 'Delete payroll run'} confirmLabel={confirm?.kind === 'pay' ? 'Mark paid' : 'Delete'}
        message={confirm?.kind === 'pay' ? 'Staff will be notified and payslips become visible to them. This cannot be undone.' : 'The unpaid run and its payslips will be removed.'} onCancel={() => setConfirm(null)}
        onConfirm={async () => {
          setBusy(true)
          try { confirm!.kind === 'pay' ? await api.post(`/payroll/runs/${confirm!.run.id}/pay`) : await api.delete(`/payroll/runs/${confirm!.run.id}`); toast.ok('Done.'); setConfirm(null); void runs.reload() } catch (e) { toast.err(errMsg(e)); setConfirm(null) } finally { setBusy(false) }
        }} />
      <MyPayslips />
    </>
  )
}

function MyPayslips() {
  const toast = useToast()
  const { user } = useAuth()
  const { data } = useGet<any[]>(user?.staff_id ? '/payroll/my-payslips' : null)
  if (!data?.length) return null
  return (
    <Card className="mt-5" title="My payslips">
      <Table head={['Period', 'Net pay', 'Paid', '']}>{data.map((p) => <tr key={p.id}><td className="px-3 py-2">{new Date(p.payroll_run.period_year, p.payroll_run.period_month - 1).toLocaleString('en-GB', { month: 'long', year: 'numeric' })}</td><td className="px-3 py-2">{money(p.net_pay)}</td><td className="px-3 py-2">{fmtDate(p.paid_at)}</td><td className="px-3 py-2 text-right"><Button size="sm" variant="ghost" onClick={() => download(`/payroll/payslips/${p.id}/pdf`).catch((e) => toast.err(errMsg(e)))}><Download size={14} /></Button></td></tr>)}</Table>
    </Card>
  )
}
export { humanize }
