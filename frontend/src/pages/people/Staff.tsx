import { zodResolver } from '@hookform/resolvers/zod'
import { Check, Eye, Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { z } from 'zod'
import { Attachments } from '@/components/Attachments'
import { FkSelect } from '@/components/FkSelect'
import { ResourceTable } from '@/components/ResourceTable'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Pager, Select, Spinner, Table, Tabs, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet, type Page } from '@/hooks/useApi'
import { errMsg, fieldErrors, fmtDate, humanize, money, today } from '@/lib/utils'
import { api } from '@/services/api'
import { CredentialsModal } from './Students'

export function StaffPage() {
  const { can } = useAuth()
  return (
    <ResourceTable
      resource="staff" noCreate title="Staff" subtitle="Teaching and non-teaching staff records"
      extraActions={can('staff.create') ? <Link to="/staff/new"><Button><Plus size={16} /> Add staff</Button></Link> : undefined}
      extraRow={(r) => <Link to={`/staff/${r.id}`} aria-label="View profile" className="rounded-lg p-1.5 text-royal hover:bg-slate-100"><Eye size={15} /></Link>}
    />
  )
}

const schema = z.object({
  first_name: z.string().trim().min(1, 'Required'), last_name: z.string().trim().min(1, 'Required'), other_name: z.string().optional(),
  gender: z.enum(['male', 'female'], { error: 'Select gender' }), dob: z.string().optional(), phone: z.string().optional(),
  email: z.string().email('Invalid email').or(z.literal('')).optional(), address: z.string().optional(),
  category: z.enum(['teaching', 'non_teaching'], { error: 'Select category' }),
  department_id: z.string().optional(), designation_id: z.string().optional(), qualification: z.string().optional(),
  employment_type: z.string().optional(), employment_date: z.string().optional(),
  bank_name: z.string().optional(), account_number: z.string().max(20).optional(), account_name: z.string().optional(),
  emergency_contact_name: z.string().optional(), emergency_contact_phone: z.string().optional(),
  create_account: z.boolean().optional(), role: z.string().optional(),
})
type V = z.infer<typeof schema>

export function StaffCreatePage() {
  const nav = useNavigate()
  const toast = useToast()
  const [creds, setCreds] = useState<any[] | null>(null)
  const [newId, setNewId] = useState<number | null>(null)
  const roles = useGet<any[]>('/roles')
  const { register, handleSubmit, watch, setValue, setError, formState: { errors, isSubmitting } } = useForm<V>({ resolver: zodResolver(schema), defaultValues: { employment_type: 'permanent', employment_date: today() } })
  const E = ({ m }: { m?: string }) => (m ? <p className="mt-1 text-xs text-red-600">{m}</p> : null)
  const wants = watch('create_account')

  return (
    <>
      <PageHeader title="Add staff member" subtitle="A staff number is generated automatically." />
      <form noValidate className="space-y-5" onSubmit={handleSubmit(async (v) => {
        try {
          const body = Object.fromEntries(Object.entries(v).filter(([, x]) => x !== '' && x !== undefined)) as Record<string, any>
          for (const k of ['department_id', 'designation_id']) if (body[k]) body[k] = Number(body[k])
          const r = await api.post('/staff', body)
          toast.ok(`Added: ${r.data.staff.staff_no}`)
          setNewId(r.data.staff.id)
          if (r.data.credentials) setCreds([r.data.credentials]); else nav(`/staff/${r.data.staff.id}`)
        } catch (e) {
          const fe = fieldErrors(e)
          if (Object.keys(fe).length) { Object.entries(fe).forEach(([k, m]) => setError(k as keyof V, { message: m })); toast.err('Please correct the highlighted fields.') } else toast.err(errMsg(e))
        }
      })}>
        <Card title="Personal details"><div className="grid gap-4 sm:grid-cols-3">
          <label><Label required>First name</Label><Input {...register('first_name')} /><E m={errors.first_name?.message} /></label>
          <label><Label>Other name</Label><Input {...register('other_name')} /></label>
          <label><Label required>Last name</Label><Input {...register('last_name')} /><E m={errors.last_name?.message} /></label>
          <label><Label required>Gender</Label><Select {...register('gender')}><option value="">Select…</option><option value="male">Male</option><option value="female">Female</option></Select><E m={errors.gender?.message} /></label>
          <label><Label>Date of birth</Label><Input type="date" {...register('dob')} /><E m={errors.dob?.message} /></label>
          <label><Label>Phone</Label><Input {...register('phone')} /></label>
          <label><Label>Email</Label><Input type="email" {...register('email')} /><E m={errors.email?.message} /></label>
          <label className="sm:col-span-2"><Label>Address</Label><Input {...register('address')} /></label>
        </div></Card>
        <Card title="Employment"><div className="grid gap-4 sm:grid-cols-3">
          <label><Label required>Category</Label><Select {...register('category')}><option value="">Select…</option><option value="teaching">Teaching</option><option value="non_teaching">Non-teaching</option></Select><E m={errors.category?.message} /></label>
          <label><Label>Department</Label><FkSelect resource="departments" value={watch('department_id')} onChange={(v) => setValue('department_id', v)} /></label>
          <label><Label>Designation</Label><FkSelect resource="designations" value={watch('designation_id')} onChange={(v) => setValue('designation_id', v)} /></label>
          <label><Label>Qualification</Label><Input {...register('qualification')} /></label>
          <label><Label>Employment type</Label><Select {...register('employment_type')}><option value="permanent">Permanent</option><option value="contract">Contract</option><option value="part_time">Part time</option></Select></label>
          <label><Label>Date employed</Label><Input type="date" {...register('employment_date')} /></label>
        </div></Card>
        <Card title="Bank & emergency contact"><div className="grid gap-4 sm:grid-cols-3">
          <label><Label>Bank</Label><Input {...register('bank_name')} /></label>
          <label><Label>Account number</Label><Input inputMode="numeric" {...register('account_number')} /><E m={errors.account_number?.message} /></label>
          <label><Label>Account name</Label><Input {...register('account_name')} /></label>
          <label><Label>Emergency contact</Label><Input {...register('emergency_contact_name')} /></label>
          <label><Label>Emergency phone</Label><Input {...register('emergency_contact_phone')} /></label>
        </div></Card>
        <Card title="System access">
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="h-4 w-4" {...register('create_account')} /> Create a login account (requires email)</label>
          {wants && (
            <label className="mt-3 block max-w-xs"><Label help="Teachers default to Teacher">Role</Label>
              <Select {...register('role')}><option value="">Default for category</option>{roles.data?.filter((r) => r.slug !== 'parent' && r.slug !== 'student').map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}</Select>
            </label>
          )}
        </Card>
        <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => nav('/staff')}>Cancel</Button><Button type="submit" loading={isSubmitting}>Save staff member</Button></div>
      </form>
      <CredentialsModal credentials={creds} onClose={() => { setCreds(null); nav(newId ? `/staff/${newId}` : '/staff') }} />
    </>
  )
}

export function StaffProfilePage() {
  const { id } = useParams()
  const { can } = useAuth()
  const toast = useToast()
  const { data, loading, error, reload } = useGet<any>(`/staff/${id}/profile`)
  const [tab, setTab] = useState('overview')
  if (loading) return <Spinner />
  if (error || !data) return <ErrorBox message={error ?? 'Not found'} onRetry={reload} />
  const s = data.staff
  const uploadPhoto = async (f: File) => {
    const fd = new FormData(); fd.append('photo', f)
    try { await api.post(`/staff/${id}/photo`, fd); toast.ok('Photo updated.'); void reload() } catch (e) { toast.err(errMsg(e)) }
  }
  return (
    <>
      <PageHeader title={`${s.first_name} ${s.last_name}`} subtitle={`${s.staff_no} · ${s.designation?.name ?? humanize(s.category)}`} actions={<Link to="/staff"><Button variant="secondary">Back to list</Button></Link>} />
      <div className="mb-5 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white p-4">
        {s.photo_url ? <img src={s.photo_url} alt="" className="h-20 w-20 rounded-xl object-cover" /> : <div className="grid h-20 w-20 place-items-center rounded-xl bg-gold/20 text-2xl font-bold text-amber-700">{s.first_name[0]}{s.last_name[0]}</div>}
        <div className="flex-1 text-sm"><Badge value={s.status} /> <span className="ml-2 text-slate-500">{s.department?.name}</span><div className="mt-1 text-slate-500">{s.email} · {s.phone}</div></div>
        {can('staff.update') && <label className="cursor-pointer text-sm text-royal hover:underline">Change photo<input type="file" accept="image/*" className="hidden" onChange={(e) => e.target.files?.[0] && void uploadPhoto(e.target.files[0])} /></label>}
      </div>
      <Tabs value={tab} onChange={setTab} tabs={[{ id: 'overview', label: 'Overview' }, { id: 'teaching', label: 'Teaching load' }, { id: 'leave', label: 'Leave' }, { id: 'perf', label: 'Performance' }, ...(data.salary !== undefined ? [{ id: 'pay', label: 'Salary' }] : []), { id: 'docs', label: 'Documents' }]} />
      {tab === 'overview' && (
        <Card><dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          {[['Qualification', s.qualification], ['Employment type', humanize(s.employment_type ?? '')], ['Employed', fmtDate(s.employment_date)], ['Date of birth', fmtDate(s.dob)], ['Bank', s.bank_name], ['Emergency contact', s.emergency_contact_name ? `${s.emergency_contact_name} (${s.emergency_contact_phone ?? ''})` : null]].map(([k, v]) => <div key={k as string}><dt className="text-xs text-slate-500">{k}</dt><dd>{v || '—'}</dd></div>)}
        </dl></Card>
      )}
      {tab === 'teaching' && <Card>{data.assignments?.length ? <Table head={['Subject', 'Class']}>{data.assignments.map((a: any) => <tr key={a.id}><td className="px-3 py-2">{a.subject?.name}</td><td className="px-3 py-2">{a.class_arm?.school_class?.name} {a.class_arm?.name}</td></tr>)}</Table> : <Empty text="No subjects assigned." />}</Card>}
      {tab === 'leave' && <Card>{data.leave_requests.length ? <Table head={['Type', 'From', 'To', 'Days', 'Status']}>{data.leave_requests.map((l: any) => <tr key={l.id}><td className="px-3 py-2">{humanize(l.type)}</td><td className="px-3 py-2">{fmtDate(l.starts_on)}</td><td className="px-3 py-2">{fmtDate(l.ends_on)}</td><td className="px-3 py-2">{l.days}</td><td className="px-3 py-2"><Badge value={l.status} /></td></tr>)}</Table> : <Empty text="No leave requests." />}</Card>}
      {tab === 'perf' && <Card>{data.performance.length ? <Table head={['Term', 'Rating', 'Comments']}>{data.performance.map((p: any) => <tr key={p.id}><td className="px-3 py-2">{p.term?.name}</td><td className="px-3 py-2">{p.rating}</td><td className="px-3 py-2">{p.comments}</td></tr>)}</Table> : <Empty text="No performance records." />}</Card>}
      {tab === 'pay' && (
        <Card>{data.salary ? <><div className="text-sm">Basic salary: <b>{money(data.salary.basic_salary)}</b></div><Table head={['Component', 'Type', 'Amount']}>{data.salary_components.map((c: any) => <tr key={c.id}><td className="px-3 py-2">{c.name}</td><td className="px-3 py-2"><Badge value={c.type} tone={c.type === 'allowance' ? 'green' : 'red'} /></td><td className="px-3 py-2">{c.is_percent ? `${c.amount}% of basic` : money(c.amount)}</td></tr>)}</Table></> : <Empty text="No salary structure yet. Set one under Payroll." />}</Card>
      )}
      {tab === 'docs' && <Card><Attachments type="staff" id={s.id} canWrite={can('staff.update')} /></Card>}
    </>
  )
}

// ---------------------------------------------------------------- leave
export function LeavePage() {
  const { can } = useAuth()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const { data, loading, error, reload } = useGet<Page<any>>('/leave', { page, status: status || undefined })
  const [open, setOpen] = useState(false)
  const [decide, setDecide] = useState<{ row: any; decision: 'approve' | 'reject' } | null>(null)
  const [note, setNote] = useState('')
  const [cancel, setCancel] = useState<any>(null)
  const { register, handleSubmit, reset, watch: watchL, setValue: setL, formState: { isSubmitting } } = useForm({ defaultValues: { staff_id: '', type: 'annual', starts_on: today(), ends_on: today(), reason: '' } })

  return (
    <>
      <PageHeader title="Leave" subtitle="Requests and approvals" actions={can('leave.apply', 'leave.approve') && <Button onClick={() => setOpen(true)}><Plus size={16} /> New request</Button>} />
      <Card>
        <div className="mb-3 max-w-xs"><Select aria-label="Status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All statuses</option>{['pending', 'approved', 'rejected', 'cancelled'].map((s) => <option key={s} value={s}>{humanize(s)}</option>)}</Select></div>
        {error && <ErrorBox message={error} onRetry={reload} />}
        {loading ? <Spinner /> : !data?.data.length ? <Empty text="No leave requests." /> : (
          <>
            <Table head={['Staff', 'Type', 'From', 'To', 'Days', 'Reason', 'Status', '']}>
              {data.data.map((l) => (
                <tr key={l.id}>
                  <td className="px-3 py-2">{l.staff?.first_name} {l.staff?.last_name}</td><td className="px-3 py-2">{humanize(l.type)}</td>
                  <td className="px-3 py-2">{fmtDate(l.starts_on)}</td><td className="px-3 py-2">{fmtDate(l.ends_on)}</td><td className="px-3 py-2">{l.days}</td>
                  <td className="max-w-xs truncate px-3 py-2">{l.reason}</td><td className="px-3 py-2"><Badge value={l.status} /></td>
                  <td className="whitespace-nowrap px-3 py-2 text-right">
                    {l.status === 'pending' && can('leave.approve') && <><Button size="sm" variant="ghost" className="text-emerald-700" aria-label="Approve" onClick={() => { setNote(''); setDecide({ row: l, decision: 'approve' }) }}><Check size={15} /></Button><Button size="sm" variant="ghost" className="text-red-600" aria-label="Reject" onClick={() => { setNote(''); setDecide({ row: l, decision: 'reject' }) }}><X size={15} /></Button></>}
                    {l.status === 'pending' && can('leave.apply') && <Button size="sm" variant="ghost" onClick={() => setCancel(l)}>Cancel</Button>}
                  </td>
                </tr>
              ))}
            </Table>
            <Pager page={data.current_page} last={data.last_page} total={data.total} onPage={setPage} />
          </>
        )}
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title="New leave request">
        <form className="space-y-4" onSubmit={handleSubmit(async (v) => {
          try {
            await api.post('/leave', { ...v, staff_id: v.staff_id ? Number(v.staff_id) : undefined })
            toast.ok('Leave request submitted.'); setOpen(false); reset(); void reload()
          } catch (e) { toast.err(errMsg(e)) }
        })}>
          {can('leave.approve') && <label className="block"><Label help="Leave empty for yourself">Staff member</Label><FkSelect resource="staff" value={watchL('staff_id')} onChange={(v) => setL('staff_id', v)} /></label>}
          <label className="block"><Label required>Type</Label><Select {...register('type')}>{['annual', 'sick', 'maternity', 'paternity', 'casual', 'study', 'other'].map((t) => <option key={t} value={t}>{humanize(t)}</option>)}</Select></label>
          <div className="grid grid-cols-2 gap-3"><label><Label required>From</Label><Input type="date" min={today()} {...register('starts_on', { required: true })} /></label><label><Label required>To</Label><Input type="date" min={today()} {...register('ends_on', { required: true })} /></label></div>
          <label className="block"><Label>Reason</Label><Textarea {...register('reason')} /></label>
          <div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setOpen(false)}>Close</Button><Button type="submit" loading={isSubmitting}>Submit</Button></div>
        </form>
      </Modal>

      <Modal open={!!decide} onClose={() => setDecide(null)} title={decide?.decision === 'approve' ? 'Approve leave' : 'Reject leave'}
        footer={<><Button variant="secondary" onClick={() => setDecide(null)}>Cancel</Button><Button variant={decide?.decision === 'approve' ? 'primary' : 'danger'} onClick={async () => {
          try { await api.post(`/leave/${decide!.row.id}/decide`, { decision: decide!.decision, note: note || undefined }); toast.ok('Decision recorded.'); setDecide(null); void reload() } catch (e) { toast.err(errMsg(e)) }
        }}>{decide?.decision === 'approve' ? 'Approve' : 'Reject'}</Button></>}>
        <label className="block"><Label>Note (optional)</Label><Textarea value={note} onChange={(e) => setNote(e.target.value)} /></label>
      </Modal>
      <Confirm open={!!cancel} title="Cancel request" message="Cancel this leave request?" confirmLabel="Cancel request" danger onCancel={() => setCancel(null)}
        onConfirm={async () => { try { await api.post(`/leave/${cancel.id}/cancel`); toast.ok('Cancelled.'); setCancel(null); void reload() } catch (e) { toast.err(errMsg(e)) } }} />
    </>
  )
}
