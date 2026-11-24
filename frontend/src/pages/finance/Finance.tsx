import { Download, Eye, FileText, Plus, RefreshCw, Search, Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { FkSelect } from '@/components/FkSelect'
import { ResourceTable } from '@/components/ResourceTable'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Pager, Select, Spinner, Table, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet, type Page } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg, fmtDate, fmtDateTime, humanize, money, today } from '@/lib/utils'
import { api } from '@/services/api'
import { ResourceTabsPage } from '../ResourceTabsPage'

const METHODS = ['cash', 'bank_transfer', 'pos', 'cheque']

// ---------------------------------------------------------------- record payment modal (staff)
export function RecordPaymentModal({ invoice, onClose, onDone }: { invoice: any; onClose: () => void; onDone: () => void }) {
  const { can } = useAuth()
  const toast = useToast()
  const [f, setF] = useState({ method: 'cash', amount: '', paid_on: today(), payer_name: '', bank_name: '', cheque_no: '', notes: '' })
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (invoice) setF((s) => ({ ...s, amount: String(invoice.balance) })) }, [invoice])
  const over = Number(f.amount) > Number(invoice?.balance ?? 0)
  return (
    <Modal open={!!invoice} onClose={onClose} title={`Record payment · ${invoice?.number ?? ''}`} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={busy} disabled={!Number(f.amount) || over || (f.method === 'cheque' && !f.cheque_no)} onClick={async () => {
      setBusy(true)
      try {
        const r = await api.post('/payments', { invoice_id: invoice.id, ...f, amount: Number(f.amount), cheque_no: f.cheque_no || null, bank_name: f.bank_name || null, payer_name: f.payer_name || null, notes: f.notes || null })
        toast.ok(r.data.status === 'awaiting_approval' ? 'Payment recorded and sent for approval.' : `Payment recorded. Receipt ${r.data.receipt_no}.`)
        onDone()
      } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
    }}>Record</Button></>}>
      <div className="space-y-3">
        <p className="rounded-lg bg-slate-50 px-3 py-2 text-sm">Balance due: <b>{money(invoice?.balance)}</b></p>
        {!can('fees.approve-payment') && <p className="text-xs text-amber-700">Manual payments you record are confirmed after approval by an authorised officer.</p>}
        <div className="grid grid-cols-2 gap-3">
          <label><Label required>Method</Label><Select value={f.method} onChange={(e) => setF({ ...f, method: e.target.value })}>{METHODS.map((m) => <option key={m} value={m}>{humanize(m)}</option>)}</Select></label>
          <label><Label required>Amount</Label><Input type="number" step="0.01" min="0.01" max={invoice?.balance} value={f.amount} onChange={(e) => setF({ ...f, amount: e.target.value })} />{over && <span className="text-xs text-red-600">Exceeds balance</span>}</label>
          <label><Label>Date paid</Label><Input type="date" max={today()} value={f.paid_on} onChange={(e) => setF({ ...f, paid_on: e.target.value })} /></label>
          <label><Label>Payer name</Label><Input value={f.payer_name} onChange={(e) => setF({ ...f, payer_name: e.target.value })} /></label>
          {(f.method === 'bank_transfer' || f.method === 'cheque') && <label><Label>Bank</Label><Input value={f.bank_name} onChange={(e) => setF({ ...f, bank_name: e.target.value })} /></label>}
          {f.method === 'cheque' && <label><Label required>Cheque number</Label><Input value={f.cheque_no} onChange={(e) => setF({ ...f, cheque_no: e.target.value })} /></label>}
        </div>
        <label className="block"><Label>Notes</Label><Textarea value={f.notes} onChange={(e) => setF({ ...f, notes: e.target.value })} /></label>
      </div>
    </Modal>
  )
}

// ---------------------------------------------------------------- invoice detail
function InvoiceDetail({ id, onClose, onChanged }: { id: number | null; onClose: () => void; onChanged: () => void }) {
  const { can } = useAuth()
  const toast = useToast()
  const { data: inv, loading, error, reload } = useGet<any>(id ? `/invoices/${id}` : null)
  const [pay, setPay] = useState(false)
  const [item, setItem] = useState({ description: '', amount: '', category: '' })
  const [disc, setDisc] = useState('')
  const [cancel, setCancel] = useState(false)
  useEffect(() => { if (inv) setDisc(String(inv.discount ?? 0)) }, [inv])
  const refresh = () => { void reload(); onChanged() }
  const run = async (fn: () => Promise<unknown>, ok: string) => { try { await fn(); toast.ok(ok); refresh() } catch (e) { toast.err(errMsg(e)) } }
  const editable = can('fees.manage') && inv && inv.status !== 'cancelled' && inv.status !== 'paid'

  return (
    <Modal wide open={!!id} onClose={onClose} title={inv ? `Invoice ${inv.number}` : 'Invoice'}>
      {loading ? <Spinner /> : error || !inv ? <ErrorBox message={error ?? 'Not found'} onRetry={reload} /> : (
        <div className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
            <div><div className="font-semibold">{inv.student?.first_name} {inv.student?.last_name} <span className="font-normal text-slate-500">({inv.student?.admission_no})</span></div><div className="text-slate-500">{inv.term?.name} · due {fmtDate(inv.due_date)}</div></div>
            <div className="flex items-center gap-2"><Badge value={inv.status} />
              <Button size="sm" variant="secondary" onClick={() => download(`/invoices/${id}/pdf`).catch((e) => toast.err(errMsg(e)))}><FileText size={14} /> PDF</Button></div>
          </div>
          <Table head={['Description', 'Category', 'Amount', '']}>
            {inv.items.map((i: any) => (
              <tr key={i.id}><td className="px-3 py-2">{i.description}</td><td className="px-3 py-2">{humanize(i.category ?? '')}</td><td className="px-3 py-2">{money(i.amount)}</td>
                <td className="px-3 py-2 text-right">{editable && <Button size="sm" variant="ghost" className="text-red-600" aria-label="Remove item" onClick={() => run(() => api.delete(`/invoices/${id}/items/${i.id}`), 'Item removed.')}><Trash2 size={14} /></Button>}</td></tr>
            ))}
          </Table>
          <dl className="ml-auto grid max-w-xs grid-cols-2 gap-1 text-sm">
            <dt className="text-slate-500">Total</dt><dd className="text-right">{money(inv.total)}</dd>
            <dt className="text-slate-500">Discount</dt><dd className="text-right">−{money(inv.discount)}</dd>
            <dt className="text-slate-500">Paid</dt><dd className="text-right text-emerald-700">{money(inv.amount_paid)}</dd>
            <dt className="font-semibold">Balance</dt><dd className="text-right font-bold text-red-600">{money(inv.balance)}</dd>
          </dl>
          {editable && (
            <div className="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-[1fr_8rem_auto]">
              <Input placeholder="Add a charge (description)" value={item.description} onChange={(e) => setItem({ ...item, description: e.target.value })} />
              <Input type="number" step="0.01" min="0.01" placeholder="Amount" value={item.amount} onChange={(e) => setItem({ ...item, amount: e.target.value })} />
              <Button disabled={!item.description || !Number(item.amount)} onClick={() => run(async () => { await api.post(`/invoices/${id}/items`, { description: item.description, amount: Number(item.amount) }); setItem({ description: '', amount: '', category: '' }) }, 'Charge added.')}><Plus size={14} /> Add</Button>
              <div className="flex items-center gap-2 sm:col-span-3"><Label>Discount</Label><Input className="max-w-[10rem]" type="number" step="0.01" min="0" value={disc} onChange={(e) => setDisc(e.target.value)} /><Button size="sm" variant="secondary" onClick={() => run(() => api.put(`/invoices/${id}`, { discount: Number(disc) }), 'Discount updated.')}>Apply</Button></div>
            </div>
          )}
          <div><h3 className="mb-1 text-sm font-semibold text-navy">Payments</h3>
            {!inv.payments.length ? <p className="text-sm text-slate-500">No payments yet.</p> : (
              <Table head={['Date', 'Reference', 'Method', 'Amount', 'Status', '']}>
                {inv.payments.map((p: any) => (
                  <tr key={p.id}><td className="px-3 py-2">{fmtDate(p.paid_at ?? p.created_at)}</td><td className="px-3 py-2 font-mono text-xs">{p.receipt_no ?? p.reference}</td><td className="px-3 py-2">{humanize(p.method)}</td><td className="px-3 py-2">{money(p.amount)}</td><td className="px-3 py-2"><Badge value={p.status} /></td>
                    <td className="px-3 py-2 text-right">{p.status === 'successful' && <Button size="sm" variant="ghost" aria-label="Receipt" onClick={() => download(`/payments/${p.id}/receipt`).catch((e) => toast.err(errMsg(e)))}><Download size={14} /></Button>}</td></tr>
                ))}
              </Table>
            )}
          </div>
          <div className="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-3">
            {can('fees.manage') && inv.status !== 'cancelled' && Number(inv.amount_paid) === 0 && <Button variant="danger" onClick={() => setCancel(true)}>Cancel invoice</Button>}
            {can('fees.record-payment') && inv.status !== 'cancelled' && Number(inv.balance) > 0 && <Button onClick={() => setPay(true)}>Record payment</Button>}
          </div>
          <RecordPaymentModal invoice={pay ? inv : null} onClose={() => setPay(false)} onDone={() => { setPay(false); refresh() }} />
          <Confirm open={cancel} danger title="Cancel invoice" message="The invoice will be voided. This is only possible while nothing has been paid." confirmLabel="Cancel invoice" onCancel={() => setCancel(false)}
            onConfirm={async () => { setCancel(false); await run(() => api.post(`/invoices/${id}/cancel`), 'Invoice cancelled.') }} />
        </div>
      )}
    </Modal>
  )
}

// ---------------------------------------------------------------- generate invoices
function GenerateModal({ open, onClose, onDone }: { open: boolean; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const cur = useGet<any>('/academic/current')
  const [termId, setTerm] = useState('')
  const [classId, setClass] = useState('')
  const [due, setDue] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (cur.data?.term && !termId) setTerm(String(cur.data.term.id)) }, [cur.data, termId])
  return (
    <Modal open={open} onClose={onClose} title="Generate invoices" footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={busy} disabled={!termId} onClick={async () => {
      setBusy(true)
      try {
        const r = await api.post('/invoices/generate', { term_id: Number(termId), school_class_id: classId ? Number(classId) : null, due_date: due || null })
        const d = r.data
        toast.ok(`Created ${d.created ?? 0} invoice(s)${d.skipped ? `, skipped ${d.skipped} that already exist` : ''}.`)
        onDone()
      } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
    }}>Generate</Button></>}>
      <div className="space-y-3">
        <p className="text-sm text-slate-500">Creates one invoice per active student from the fee structures, transport and hostel charges, minus scholarships. Students who already have an invoice for the term are skipped.</p>
        <label className="block"><Label required>Term</Label><FkSelect resource="terms" value={termId} onChange={setTerm} /></label>
        <label className="block"><Label>Class (empty = all)</Label><FkSelect resource="classes" value={classId} onChange={setClass} placeholder="All classes" /></label>
        <label className="block"><Label>Due date</Label><Input type="date" value={due} onChange={(e) => setDue(e.target.value)} /></label>
      </div>
    </Modal>
  )
}

export function InvoicesPage() {
  const { can } = useAuth()
  const [sp] = useSearchParams()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const [outstanding, setOutstanding] = useState(false)
  const [termId, setTerm] = useState('')
  const studentId = sp.get('student_id') ?? ''
  const { data, loading, error, reload } = useGet<Page<any>>('/invoices', { page, search: q || undefined, status: status || undefined, outstanding: outstanding || undefined, term_id: termId || undefined, student_id: studentId || undefined })
  const [open, setOpen] = useState<number | null>(null)
  const [gen, setGen] = useState(false)
  useEffect(() => { const t = setTimeout(() => { setQ(search); setPage(1) }, 350); return () => clearTimeout(t) }, [search])

  return (
    <>
      <PageHeader title="Invoices" subtitle={studentId ? 'Filtered to one student' : 'Student fee invoices'} actions={can('fees.manage') && <Button onClick={() => setGen(true)}><Plus size={16} /> Generate invoices</Button>} />
      <Card>
        <div className="mb-4 flex flex-wrap items-end gap-2">
          <div className="relative min-w-[12rem] flex-1 sm:max-w-xs"><Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><Input className="pl-9" placeholder="Invoice no. or student…" aria-label="Search" value={search} onChange={(e) => setSearch(e.target.value)} /></div>
          <Select aria-label="Status" className="max-w-[10rem]" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All statuses</option>{['unpaid', 'partial', 'paid', 'cancelled'].map((s) => <option key={s} value={s}>{humanize(s)}</option>)}</Select>
          <div className="min-w-[10rem]"><FkSelect aria-label="Term" resource="terms" value={termId} placeholder="All terms" onChange={(v) => { setTerm(v); setPage(1) }} /></div>
          <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" className="h-4 w-4" checked={outstanding} onChange={(e) => { setOutstanding(e.target.checked); setPage(1) }} /> Outstanding only</label>
        </div>
        {error && <ErrorBox message={error} onRetry={reload} />}
        {loading && !data ? <Spinner /> : !data?.data.length ? <Empty text="No invoices found." /> : (
          <>
            <Table head={['Invoice', 'Student', 'Class', 'Term', 'Total', 'Paid', 'Balance', 'Status', '']}>
              {data.data.map((i) => (
                <tr key={i.id} className="hover:bg-slate-50">
                  <td className="px-3 py-2 font-mono text-xs">{i.number}</td><td className="px-3 py-2">{i.student?.first_name} {i.student?.last_name}</td><td className="px-3 py-2">{i.student?.school_class?.name}</td><td className="px-3 py-2">{i.term?.name}</td>
                  <td className="px-3 py-2">{money(Number(i.total) - Number(i.discount))}</td><td className="px-3 py-2 text-emerald-700">{money(i.amount_paid)}</td><td className="px-3 py-2 font-medium">{money(i.balance)}</td><td className="px-3 py-2"><Badge value={i.status} /></td>
                  <td className="px-3 py-2 text-right"><Button size="sm" variant="ghost" aria-label="Open invoice" onClick={() => setOpen(i.id)}><Eye size={15} /></Button></td>
                </tr>
              ))}
            </Table>
            <Pager page={data.current_page} last={data.last_page} total={data.total} onPage={setPage} />
          </>
        )}
      </Card>
      <InvoiceDetail id={open} onClose={() => setOpen(null)} onChanged={reload} />
      <GenerateModal open={gen} onClose={() => setGen(false)} onDone={() => { setGen(false); void reload() }} />
    </>
  )
}

// ---------------------------------------------------------------- payments
export function PaymentsPage() {
  const { can } = useAuth()
  const toast = useToast()
  const [sp] = useSearchParams()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState(sp.get('status') ?? '')
  const [method, setMethod] = useState('')
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const { data, loading, error, reload } = useGet<Page<any>>('/payments', { page, status: status || undefined, method: method || undefined, search: q || undefined })
  const [reject, setReject] = useState<any>(null)
  const [reason, setReason] = useState('')
  const [busyId, setBusyId] = useState<number | null>(null)
  useEffect(() => { const t = setTimeout(() => { setQ(search); setPage(1) }, 350); return () => clearTimeout(t) }, [search])

  const act = async (id: number, path: string, ok: string, body?: object) => {
    setBusyId(id)
    try { await api.post(`/payments/${id}/${path}`, body); toast.ok(ok); void reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusyId(null) }
  }
  return (
    <>
      <PageHeader title="Payments" subtitle="Online and manual payments. Online payments are confirmed only by the payment provider." />
      <Card>
        <div className="mb-4 flex flex-wrap items-end gap-2">
          <div className="relative min-w-[12rem] flex-1 sm:max-w-xs"><Search size={15} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><Input className="pl-9" placeholder="Reference, receipt or payer…" aria-label="Search" value={search} onChange={(e) => setSearch(e.target.value)} /></div>
          <Select aria-label="Status" className="max-w-[11rem]" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All statuses</option>{['successful', 'awaiting_approval', 'pending', 'failed', 'rejected'].map((s) => <option key={s} value={s}>{humanize(s)}</option>)}</Select>
          <Select aria-label="Method" className="max-w-[10rem]" value={method} onChange={(e) => { setMethod(e.target.value); setPage(1) }}><option value="">All methods</option>{[...METHODS, 'paystack', 'flutterwave'].map((s) => <option key={s} value={s}>{humanize(s)}</option>)}</Select>
        </div>
        {error && <ErrorBox message={error} onRetry={reload} />}
        {loading && !data ? <Spinner /> : !data?.data.length ? <Empty text="No payments found." /> : (
          <>
            <Table head={['Date', 'Student', 'Invoice', 'Receipt / ref', 'Method', 'Amount', 'Status', '']}>
              {data.data.map((p) => (
                <tr key={p.id}>
                  <td className="px-3 py-2">{fmtDateTime(p.paid_at ?? p.created_at)}</td><td className="px-3 py-2">{p.student?.first_name} {p.student?.last_name}</td><td className="px-3 py-2 font-mono text-xs">{p.invoice?.number}</td>
                  <td className="px-3 py-2 font-mono text-xs">{p.receipt_no ?? p.reference}</td><td className="px-3 py-2">{humanize(p.method)}</td><td className="px-3 py-2 font-medium">{money(p.amount)}</td><td className="px-3 py-2"><Badge value={p.status} /></td>
                  <td className="whitespace-nowrap px-3 py-2 text-right">
                    {p.status === 'awaiting_approval' && can('fees.approve-payment') && <><Button size="sm" loading={busyId === p.id} onClick={() => act(p.id, 'approve', 'Payment approved.')}>Approve</Button> <Button size="sm" variant="secondary" onClick={() => { setReason(''); setReject(p) }}>Reject</Button></>}
                    {p.status === 'pending' && ['paystack', 'flutterwave'].includes(p.method) && can('fees.approve-payment', 'fees.record-payment') && <Button size="sm" variant="secondary" loading={busyId === p.id} onClick={() => act(p.id, 'requery', 'Checked with the provider.')}><RefreshCw size={13} /> Re-check</Button>}
                    {p.status === 'successful' && <Button size="sm" variant="ghost" aria-label="Receipt" onClick={() => download(`/payments/${p.id}/receipt`).catch((e) => toast.err(errMsg(e)))}><Download size={14} /></Button>}
                  </td>
                </tr>
              ))}
            </Table>
            <Pager page={data.current_page} last={data.last_page} total={data.total} onPage={setPage} />
          </>
        )}
      </Card>
      <Modal open={!!reject} onClose={() => setReject(null)} title="Reject payment" footer={<><Button variant="secondary" onClick={() => setReject(null)}>Cancel</Button><Button variant="danger" onClick={async () => { const id = reject.id; setReject(null); await act(id, 'reject', 'Payment rejected.', { reason: reason || null }) }}>Reject</Button></>}>
        <label className="block"><Label>Reason</Label><Textarea value={reason} onChange={(e) => setReason(e.target.value)} /></label>
      </Modal>
    </>
  )
}

export const FeeSetupPage = () => <ResourceTabsPage title="Fee setup" subtitle="Fee structures per term and class, and scholarships" tabs={[{ resource: 'fee-structures' }, { resource: 'scholarships' }]} />
export const ExpensesPage = () => <ResourceTable resource="expenses" title="Expenses" subtitle="School expenditure" />
