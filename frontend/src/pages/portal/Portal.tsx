import { CheckCircle2, Clock, XCircle } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Badge, Button, Card, Empty, ErrorBox, Input, Label, Modal, PageHeader, Select, Spinner, Stat, Table, Tabs } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg, fmtDate, humanize, money } from '@/lib/utils'
import { api } from '@/services/api'

export function PortalHome() {
  const { user } = useAuth()
  const { data, loading, error, reload } = useGet<any[]>('/portal/students')
  if (loading) return <Spinner />
  if (error) return <ErrorBox message={error} onRetry={reload} />
  return (
    <>
      <PageHeader title={`Hello, ${user?.name.split(' ')[0]}`} subtitle="Your children's progress, attendance and fees" />
      {!data?.length ? <Card><Empty text="No students are linked to your account yet. Please contact the school office." /></Card> : (
        <div className="grid gap-4 md:grid-cols-2">
          {data.map((s) => (
            <Link key={s.id} to={`/portal/${s.id}`} className="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-royal hover:shadow">
              <div className="flex items-center gap-3">
                {s.photo_url ? <img src={s.photo_url} alt="" className="h-14 w-14 rounded-xl object-cover" /> : <div className="grid h-14 w-14 place-items-center rounded-xl bg-royal/10 text-lg font-bold text-royal">{s.name[0]}</div>}
                <div><div className="font-semibold text-navy">{s.name}</div><div className="text-xs text-slate-500">{s.admission_no} · {s.class} {s.arm}</div></div>
              </div>
              <div className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                <div className="rounded-lg bg-slate-50 p-2"><div className="text-base font-bold text-navy">{s.attendance_rate === null ? '—' : `${s.attendance_rate}%`}</div>Attendance</div>
                <div className="rounded-lg bg-slate-50 p-2"><div className="text-base font-bold text-navy">{s.latest_result ? `${s.latest_result.average}%` : '—'}</div>Last average</div>
                <div className={`rounded-lg p-2 ${s.outstanding > 0 ? 'bg-red-50' : 'bg-emerald-50'}`}><div className={`text-base font-bold ${s.outstanding > 0 ? 'text-red-600' : 'text-emerald-700'}`}>{money(s.outstanding)}</div>Fees due</div>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  )
}

function PayModal({ invoice, onClose }: { invoice: any; onClose: () => void }) {
  const toast = useToast()
  const gw = useGet<Record<string, boolean>>(invoice ? '/payments/gateways' : null)
  const [gateway, setGateway] = useState('')
  const [amount, setAmount] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (invoice) setAmount(String(invoice.balance)) }, [invoice])
  const available = Object.entries(gw.data ?? {}).filter(([, on]) => on).map(([k]) => k)
  useEffect(() => { if (!gateway && available.length) setGateway(available[0]) }, [available, gateway])
  const over = Number(amount) > Number(invoice?.balance ?? 0)

  return (
    <Modal open={!!invoice} onClose={onClose} title={`Pay ${invoice?.number ?? ''}`} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button>
      <Button loading={busy} disabled={!gateway || !Number(amount) || over} onClick={async () => {
        setBusy(true)
        try {
          const r = await api.post('/payments/online/initialize', { invoice_id: invoice.id, amount: Number(amount), gateway })
          window.location.assign(r.data.authorization_url)
        } catch (e) { toast.err(errMsg(e)); setBusy(false) }
      }}>Continue to payment</Button></>}>
      {gw.loading ? <Spinner /> : !available.length ? (
        <p className="text-sm text-slate-600">Online payment is not available right now. Please pay at the school bursary or by bank transfer and ask the school to record it.</p>
      ) : (
        <div className="space-y-3">
          <p className="rounded-lg bg-slate-50 px-3 py-2 text-sm">Balance due: <b>{money(invoice?.balance)}</b></p>
          <label className="block"><Label required>Pay with</Label><Select value={gateway} onChange={(e) => setGateway(e.target.value)}>{available.map((g) => <option key={g} value={g}>{humanize(g)}</option>)}</Select></label>
          <label className="block"><Label required help="Part payments are allowed">Amount</Label><Input type="number" step="0.01" min="0.01" max={invoice?.balance} value={amount} onChange={(e) => setAmount(e.target.value)} />{over && <span className="text-xs text-red-600">Cannot exceed the balance</span>}</label>
          <p className="text-xs text-slate-500">You will be redirected to a secure checkout page. Your payment is confirmed by the provider, not by this page.</p>
        </div>
      )}
    </Modal>
  )
}

export function PortalChild() {
  const { id } = useParams()
  const toast = useToast()
  const [tab, setTab] = useState('overview')
  const stu = useGet<any>(`/portal/students/${id}`)
  const att = useGet<any>(tab === 'attendance' ? `/portal/students/${id}/attendance` : null)
  const res = useGet<any[]>(tab === 'results' ? `/portal/students/${id}/results` : null)
  const inv = useGet<any[]>(tab === 'fees' ? `/portal/students/${id}/invoices` : null)
  const pays = useGet<any[]>(tab === 'fees' ? `/portal/students/${id}/payments` : null)
  const [pay, setPay] = useState<any>(null)

  if (stu.loading) return <Spinner />
  if (stu.error || !stu.data) return <ErrorBox message={stu.error ?? 'Not found'} onRetry={stu.reload} />
  const s = stu.data

  return (
    <>
      <PageHeader title={`${s.first_name} ${s.last_name}`} subtitle={`${s.admission_no} · ${s.school_class?.name ?? ''} ${s.class_arm?.name ?? ''}`} actions={<Link to="/portal"><Button variant="secondary">Back</Button></Link>} />
      <Tabs value={tab} onChange={setTab} tabs={[{ id: 'overview', label: 'Overview' }, { id: 'attendance', label: 'Attendance' }, { id: 'results', label: 'Results' }, { id: 'fees', label: 'Fees & payments' }]} />

      {tab === 'overview' && (
        <div className="grid gap-5 lg:grid-cols-2">
          <Card title="Guardians on record">{s.guardians?.map((g: any) => <div key={g.id} className="py-1 text-sm">{g.first_name} {g.last_name} <span className="text-slate-400">({g.relationship})</span> · {g.phone}</div>)}</Card>
          <Card title="Details"><dl className="grid grid-cols-2 gap-2 text-sm">{[['Gender', humanize(s.gender)], ['Date of birth', fmtDate(s.dob)], ['Admitted', fmtDate(s.admission_date)], ['Status', humanize(s.status)]].map(([k, v]) => <div key={k}><dt className="text-xs text-slate-500">{k}</dt><dd>{v}</dd></div>)}</dl></Card>
        </div>
      )}

      {tab === 'attendance' && (att.loading ? <Spinner /> : att.error ? <ErrorBox message={att.error} /> : att.data && (
        <>
          <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <Stat label="Rate" value={att.data.rate === null ? '—' : `${att.data.rate}%`} tone="green" />
            {(['present', 'absent', 'late', 'excused'] as const).map((k) => <Stat key={k} label={humanize(k)} value={att.data.totals[k]} tone={k === 'absent' ? 'red' : 'royal'} />)}
          </div>
          <Card>{!att.data.records.length ? <Empty text="No attendance recorded." /> : <Table head={['Date', 'Status', 'Remark']}>{att.data.records.slice(0, 60).map((r: any) => <tr key={r.date}><td className="px-3 py-2">{fmtDate(r.date)}</td><td className="px-3 py-2"><Badge value={r.status} /></td><td className="px-3 py-2">{r.remark}</td></tr>)}</Table>}</Card>
        </>
      ))}

      {tab === 'results' && (res.loading ? <Spinner /> : res.error ? <ErrorBox message={res.error} /> : !res.data?.length ? <Card><Empty text="No approved results yet." /></Card> : (
        <div className="space-y-4">
          {res.data.map((t) => (
            <Card key={t.term.id} title={`${t.term.academic_session?.name ?? ''} · ${t.term.name}`} action={<Button size="sm" variant="secondary" onClick={() => download(`/results/report-card/${id}/${t.term.id}/pdf`).catch((e) => toast.err(errMsg(e)))}>Download report card</Button>}>
              <Table head={['Subject', 'CA', 'Exam', 'Total', 'Grade', 'Pos.', 'Remark']}>
                {t.results.map((r: any) => <tr key={r.id}><td className="px-3 py-2">{r.subject?.name}</td><td className="px-3 py-2">{r.ca_score}</td><td className="px-3 py-2">{r.exam_score}</td><td className="px-3 py-2 font-medium">{r.total}</td><td className="px-3 py-2">{r.grade}</td><td className="px-3 py-2">{r.position}</td><td className="px-3 py-2">{r.remark}</td></tr>)}
              </Table>
              {t.summary && <p className="mt-3 text-sm text-slate-600">Total <b>{t.summary.total_score}</b> · Average <b>{t.summary.average}%</b> · Position <b>{t.summary.position}/{t.summary.class_size}</b></p>}
            </Card>
          ))}
        </div>
      ))}

      {tab === 'fees' && (inv.loading ? <Spinner /> : inv.error ? <ErrorBox message={inv.error} /> : (
        <div className="space-y-4">
          {!inv.data?.length ? <Card><Empty text="No invoices yet." /></Card> : inv.data.map((i) => (
            <Card key={i.id} title={<span className="flex items-center gap-2">{i.term?.name} <Badge value={i.status} /></span>} action={<div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => download(`/invoices/${i.id}/pdf`).catch((e) => toast.err(errMsg(e)))}>Invoice PDF</Button>{Number(i.balance) > 0 && <Button size="sm" onClick={() => setPay(i)}>Pay now</Button>}</div>}>
              <Table head={['Item', 'Amount']}>{i.items.map((x: any) => <tr key={x.id}><td className="px-3 py-2">{x.description}</td><td className="px-3 py-2">{money(x.amount)}</td></tr>)}</Table>
              <dl className="ml-auto mt-3 grid max-w-xs grid-cols-2 gap-1 text-sm"><dt className="text-slate-500">Discount</dt><dd className="text-right">−{money(i.discount)}</dd><dt className="text-slate-500">Paid</dt><dd className="text-right text-emerald-700">{money(i.amount_paid)}</dd><dt className="font-semibold">Balance</dt><dd className="text-right font-bold text-red-600">{money(i.balance)}</dd></dl>
            </Card>
          ))}
          <Card title="Payment history">
            {!pays.data?.length ? <Empty text="No payments yet." /> : <Table head={['Date', 'Invoice', 'Method', 'Amount', 'Status', '']}>{pays.data.map((p) => <tr key={p.id}><td className="px-3 py-2">{fmtDate(p.paid_at ?? p.created_at)}</td><td className="px-3 py-2">{p.invoice?.number}</td><td className="px-3 py-2">{humanize(p.method)}</td><td className="px-3 py-2">{money(p.amount)}</td><td className="px-3 py-2"><Badge value={p.status} /></td><td className="px-3 py-2 text-right">{p.status === 'successful' && <Button size="sm" variant="ghost" onClick={() => download(`/payments/${p.id}/receipt`).catch((e) => toast.err(errMsg(e)))}>Receipt</Button>}</td></tr>)}</Table>}
          </Card>
        </div>
      ))}
      <PayModal invoice={pay} onClose={() => setPay(null)} />
    </>
  )
}

/** The provider redirects here. We never trust the URL: the server verifies the reference with the provider. */
export function PaymentCallbackPage() {
  const [sp] = useSearchParams()
  const reference = sp.get('reference') ?? sp.get('trxref') ?? sp.get('tx_ref')
  const [state, setState] = useState<{ phase: 'checking' | 'done' | 'error'; payment?: any; message?: string }>({ phase: 'checking' })
  const tries = useRef(0)

  useEffect(() => {
    if (!reference) { setState({ phase: 'error', message: 'No payment reference was found in the link.' }); return }
    let live = true
    const check = async () => {
      try {
        const r = await api.get(`/payments/verify/${encodeURIComponent(reference)}`)
        if (!live) return
        if (r.data.status === 'pending' && tries.current < 5) { tries.current++; setTimeout(check, 3000); return }
        setState({ phase: 'done', payment: r.data })
      } catch (e) { if (live) setState({ phase: 'error', message: errMsg(e) }) }
    }
    void check()
    return () => { live = false }
  }, [reference])

  const p = state.payment
  return (
    <div className="mx-auto max-w-md pt-10">
      <Card>
        <div className="py-6 text-center">
          {state.phase === 'checking' && <><Spinner label="Confirming your payment with the provider…" /></>}
          {state.phase === 'error' && <><XCircle className="mx-auto text-red-600" size={44} /><h1 className="mt-3 text-lg font-bold text-navy">We could not confirm this payment</h1><p className="mt-1 text-sm text-slate-500">{state.message}</p></>}
          {state.phase === 'done' && p.status === 'successful' && <><CheckCircle2 className="mx-auto text-emerald-600" size={48} /><h1 className="mt-3 text-lg font-bold text-navy">Payment successful</h1><p className="mt-1 text-sm text-slate-600">{money(p.amount)} received.</p><p className="text-xs text-slate-500">Receipt no: {p.receipt_no}</p></>}
          {state.phase === 'done' && p.status === 'pending' && <><Clock className="mx-auto text-amber-500" size={44} /><h1 className="mt-3 text-lg font-bold text-navy">Still processing</h1><p className="mt-1 text-sm text-slate-500">Your bank has not confirmed yet. You do not need to pay again; it will update automatically and you will be notified.</p></>}
          {state.phase === 'done' && !['successful', 'pending'].includes(p.status) && <><XCircle className="mx-auto text-red-600" size={44} /><h1 className="mt-3 text-lg font-bold text-navy">Payment not completed</h1><p className="mt-1 text-sm text-slate-500">No money was taken or the payment failed. You can try again.</p></>}
          <Link to="/portal"><Button className="mt-5">Back to my children</Button></Link>
        </div>
      </Card>
    </div>
  )
}
