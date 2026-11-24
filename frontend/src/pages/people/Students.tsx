import { zodResolver } from '@hookform/resolvers/zod'
import { Eye, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { z } from 'zod'
import { Attachments } from '@/components/Attachments'
import { FkSelect } from '@/components/FkSelect'
import { ResourceTable } from '@/components/ResourceTable'
import { Badge, Button, Card, ErrorBox, Input, Label, Modal, PageHeader, Select, Spinner, Table, Tabs, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg, fieldErrors, fmtDate, money } from '@/lib/utils'
import { api } from '@/services/api'

export function StudentsPage() {
  const { can } = useAuth()
  return (
    <ResourceTable
      resource="students" noCreate title="Students" subtitle="Admissions, records and class lists"
      extraActions={can('students.create') ? <Link to="/students/new"><Button><Plus size={16} /> Admit student</Button></Link> : undefined}
      extraRow={(r) => <Link to={`/students/${r.id}`} aria-label="View profile" className="rounded-lg p-1.5 text-royal hover:bg-slate-100"><Eye size={15} /></Link>}
    />
  )
}

// ---------------------------------------------------------------- credentials dialog (shown once)
export function CredentialsModal({ credentials, onClose }: { credentials: any[] | null; onClose: () => void }) {
  return (
    <Modal open={!!credentials?.length} onClose={onClose} title="Login accounts created" footer={<Button onClick={onClose}>I have noted these</Button>}>
      <p className="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">These temporary passwords are shown only once. Each person must change theirs at first sign-in.</p>
      <Table head={['Role', 'Name', 'Email', 'Temporary password']}>
        {credentials?.map((c, i) => (
          <tr key={i}><td className="px-3 py-2">{c.role}</td><td className="px-3 py-2">{c.name}</td><td className="px-3 py-2">{c.email}</td><td className="px-3 py-2 font-mono font-semibold">{c.temporary_password}</td></tr>
        ))}
      </Table>
    </Modal>
  )
}

// ---------------------------------------------------------------- admission
const guardianSchema = z.object({
  first_name: z.string().trim().min(1, 'Required'), last_name: z.string().trim().min(1, 'Required'),
  relationship: z.string().trim().min(1, 'Required'), phone: z.string().trim().min(5, 'Phone is required'),
  email: z.string().email('Invalid email').or(z.literal('')).optional(), occupation: z.string().optional(), create_account: z.boolean().optional(),
})
const admissionSchema = z.object({
  first_name: z.string().trim().min(1, 'First name is required'), last_name: z.string().trim().min(1, 'Last name is required'), other_name: z.string().optional(),
  gender: z.enum(['male', 'female'], { error: 'Select gender' }), dob: z.string().optional(), nationality: z.string().optional(), state_of_origin: z.string().optional(),
  religion: z.string().optional(), address: z.string().optional(), phone: z.string().optional(), email: z.string().email('Invalid email').or(z.literal('')).optional(),
  school_class_id: z.string().min(1, 'Select a class'), class_arm_id: z.string().optional(), admission_date: z.string().optional(), previous_school: z.string().optional(),
  create_account: z.boolean().optional(), guardians: z.array(guardianSchema).max(4),
})
type AdmissionValues = z.infer<typeof admissionSchema>

const clean = (o: Record<string, any>) => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== '' && v !== undefined))

export function AdmissionPage() {
  const nav = useNavigate()
  const toast = useToast()
  const [creds, setCreds] = useState<any[] | null>(null)
  const [newId, setNewId] = useState<number | null>(null)
  const { register, control, handleSubmit, watch, setValue, setError, formState: { errors, isSubmitting } } = useForm<AdmissionValues>({
    resolver: zodResolver(admissionSchema),
    defaultValues: { gender: undefined, nationality: 'Nigerian', admission_date: new Date().toISOString().slice(0, 10), guardians: [{ first_name: '', last_name: '', relationship: 'Father', phone: '', email: '', create_account: true }] },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'guardians' })
  const classId = watch('school_class_id')
  const E = ({ m }: { m?: string }) => (m ? <p className="mt-1 text-xs text-red-600">{m}</p> : null)

  const onSubmit = handleSubmit(async (v) => {
    try {
      const body = {
        ...clean(v), school_class_id: Number(v.school_class_id), class_arm_id: v.class_arm_id ? Number(v.class_arm_id) : undefined,
        guardians: v.guardians.map((g) => clean(g)),
      }
      const r = await api.post('/students', body)
      toast.ok(`Admitted: ${r.data.student.admission_no}`)
      setNewId(r.data.student.id)
      if (r.data.credentials?.length) setCreds(r.data.credentials)
      else nav(`/students/${r.data.student.id}`)
    } catch (e) {
      const fe = fieldErrors(e)
      const keys = Object.keys(fe)
      if (keys.length) { keys.forEach((k) => setError(k as any, { message: fe[k] })); toast.err('Please correct the highlighted fields.') } else toast.err(errMsg(e))
    }
  })

  return (
    <>
      <PageHeader title="Admit a student" subtitle="An admission number is generated automatically." />
      <form onSubmit={onSubmit} noValidate className="space-y-5">
        <Card title="Student details">
          <div className="grid gap-4 sm:grid-cols-3">
            <label><Label required>First name</Label><Input {...register('first_name')} /><E m={errors.first_name?.message} /></label>
            <label><Label>Other name</Label><Input {...register('other_name')} /></label>
            <label><Label required>Last name</Label><Input {...register('last_name')} /><E m={errors.last_name?.message} /></label>
            <label><Label required>Gender</Label><Select {...register('gender')}><option value="">Select…</option><option value="male">Male</option><option value="female">Female</option></Select><E m={errors.gender?.message} /></label>
            <label><Label>Date of birth</Label><Input type="date" {...register('dob')} /><E m={errors.dob?.message} /></label>
            <label><Label>Religion</Label><Input {...register('religion')} /></label>
            <label><Label>Nationality</Label><Input {...register('nationality')} /></label>
            <label><Label>State of origin</Label><Input {...register('state_of_origin')} /></label>
            <label><Label>Previous school</Label><Input {...register('previous_school')} /></label>
            <label className="sm:col-span-3"><Label>Home address</Label><Textarea {...register('address')} /></label>
          </div>
        </Card>
        <Card title="Class">
          <div className="grid gap-4 sm:grid-cols-3">
            <label><Label required>Class</Label><FkSelect resource="classes" value={classId} onChange={(v) => { setValue('school_class_id', v, { shouldValidate: true }); setValue('class_arm_id', '') }} /><E m={errors.school_class_id?.message} /></label>
            <label><Label>Arm</Label><FkSelect resource="class-arms" needsFilter filter={{ school_class_id: classId }} value={watch('class_arm_id')} onChange={(v) => setValue('class_arm_id', v)} /></label>
            <label><Label>Admission date</Label><Input type="date" {...register('admission_date')} /></label>
          </div>
        </Card>
        <Card title="Parents / guardians" action={fields.length < 4 && <Button size="sm" variant="secondary" onClick={() => append({ first_name: '', last_name: '', relationship: 'Mother', phone: '', email: '', create_account: false })}><Plus size={14} /> Add guardian</Button>}>
          <div className="space-y-5">
            {fields.map((f, i) => (
              <div key={f.id} className="rounded-lg border border-slate-200 p-3">
                <div className="mb-2 flex items-center justify-between text-xs font-semibold uppercase text-slate-500">{i === 0 ? 'Primary guardian' : `Guardian ${i + 1}`}{fields.length > 1 && <button type="button" onClick={() => remove(i)} className="text-red-600" aria-label="Remove guardian"><Trash2 size={14} /></button>}</div>
                <div className="grid gap-3 sm:grid-cols-3">
                  <label><Label required>First name</Label><Input {...register(`guardians.${i}.first_name`)} /><E m={errors.guardians?.[i]?.first_name?.message} /></label>
                  <label><Label required>Last name</Label><Input {...register(`guardians.${i}.last_name`)} /><E m={errors.guardians?.[i]?.last_name?.message} /></label>
                  <label><Label required>Relationship</Label><Input {...register(`guardians.${i}.relationship`)} /><E m={errors.guardians?.[i]?.relationship?.message} /></label>
                  <label><Label required>Phone</Label><Input {...register(`guardians.${i}.phone`)} /><E m={errors.guardians?.[i]?.phone?.message} /></label>
                  <label><Label>Email</Label><Input type="email" {...register(`guardians.${i}.email`)} /><E m={errors.guardians?.[i]?.email?.message} /></label>
                  <label><Label>Occupation</Label><Input {...register(`guardians.${i}.occupation`)} /></label>
                </div>
                <label className="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" className="h-4 w-4" {...register(`guardians.${i}.create_account`)} /> Create a parent portal login (requires email)</label>
              </div>
            ))}
          </div>
        </Card>
        <div className="flex items-center justify-between">
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="h-4 w-4" {...register('create_account')} /> Create a student login (requires the student's email)</label>
          <div className="flex gap-2"><Button variant="secondary" onClick={() => nav('/students')}>Cancel</Button><Button type="submit" loading={isSubmitting}>Admit student</Button></div>
        </div>
      </form>
      <CredentialsModal credentials={creds} onClose={() => { setCreds(null); nav(newId ? `/students/${newId}` : '/students') }} />
    </>
  )
}

// ---------------------------------------------------------------- profile
export function StudentProfilePage() {
  const { id } = useParams()
  const { can } = useAuth()
  const toast = useToast()
  const { data, loading, error, reload } = useGet<any>(`/students/${id}/profile`)
  const [tab, setTab] = useState('overview')
  if (loading) return <Spinner />
  if (error || !data) return <ErrorBox message={error ?? 'Not found'} onRetry={reload} />
  const s = data.student
  const a = data.attendance_90_days
  const days = a.present + a.absent + a.late + a.excused

  const uploadPhoto = async (f: File) => {
    const fd = new FormData(); fd.append('photo', f)
    try { await api.post(`/students/${id}/photo`, fd); toast.ok('Photo updated.'); void reload() } catch (e) { toast.err(errMsg(e)) }
  }
  const tabs = [{ id: 'overview', label: 'Overview' }, { id: 'results', label: 'Results' }, { id: 'docs', label: 'Documents' },
    ...(data.medical_profile !== undefined ? [{ id: 'medical', label: 'Medical' }] : []), ...(data.disciplinary_records ? [{ id: 'discipline', label: 'Discipline' }] : [])]

  return (
    <>
      <PageHeader title={`${s.first_name} ${s.last_name}`} subtitle={`${s.admission_no} · ${s.school_class?.name ?? ''} ${s.class_arm?.name ?? ''}`} actions={<Link to="/students"><Button variant="secondary">Back to list</Button></Link>} />
      <div className="mb-5 flex flex-wrap items-center gap-4 rounded-xl border border-slate-200 bg-white p-4">
        {s.photo_url ? <img src={s.photo_url} alt="" className="h-20 w-20 rounded-xl object-cover" /> : <div className="grid h-20 w-20 place-items-center rounded-xl bg-royal/10 text-2xl font-bold text-royal">{s.first_name[0]}{s.last_name[0]}</div>}
        <div className="flex-1 text-sm">
          <div className="flex items-center gap-2"><Badge value={s.status} /><span className="text-slate-500 capitalize">{s.gender}</span><span className="text-slate-500">DOB: {fmtDate(s.dob)}</span></div>
          <div className="mt-1 text-slate-500">{s.address}</div>
        </div>
        {can('students.update') && (
          <label className="cursor-pointer text-sm text-royal hover:underline">Change photo
            <input type="file" accept="image/*" className="hidden" onChange={(e) => e.target.files?.[0] && void uploadPhoto(e.target.files[0])} />
          </label>
        )}
      </div>
      <Tabs tabs={tabs} value={tab} onChange={setTab} />

      {tab === 'overview' && (
        <div className="grid gap-5 lg:grid-cols-2">
          <Card title="Guardians">
            {s.guardians?.length ? <ul className="divide-y divide-slate-100 text-sm">{s.guardians.map((g: any) => <li key={g.id} className="py-2"><div className="font-medium">{g.first_name} {g.last_name} <span className="text-slate-400">({g.relationship})</span></div><div className="text-slate-500">{g.phone}{g.email ? ` · ${g.email}` : ''}</div></li>)}</ul> : <p className="text-sm text-slate-500">No guardians on record.</p>}
          </Card>
          <Card title="Attendance (last 90 days)">
            {days === 0 ? <p className="text-sm text-slate-500">No attendance recorded.</p> : (
              <div className="grid grid-cols-4 gap-2 text-center text-sm">
                {(['present', 'absent', 'late', 'excused'] as const).map((k) => <div key={k} className="rounded-lg bg-slate-50 p-2"><div className="text-lg font-bold text-navy">{a[k]}</div><div className="text-xs capitalize text-slate-500">{k}</div></div>)}
              </div>
            )}
          </Card>
          {data.fees && (
            <Card title="Fees">
              <dl className="grid grid-cols-3 gap-2 text-sm">
                <div><dt className="text-xs text-slate-500">Billed</dt><dd className="font-semibold">{money(data.fees.billed)}</dd></div>
                <div><dt className="text-xs text-slate-500">Paid</dt><dd className="font-semibold text-emerald-700">{money(data.fees.paid)}</dd></div>
                <div><dt className="text-xs text-slate-500">Balance</dt><dd className="font-semibold text-red-600">{money(data.fees.balance)}</dd></div>
              </dl>
              <Link className="mt-3 inline-block text-sm text-royal hover:underline" to={`/fees/invoices?student_id=${s.id}`}>View invoices →</Link>
            </Card>
          )}
          <Card title="Details">
            <dl className="grid grid-cols-2 gap-2 text-sm">
              {[['Nationality', s.nationality], ['State of origin', s.state_of_origin], ['Religion', s.religion], ['Admitted', fmtDate(s.admission_date)], ['Previous school', s.previous_school], ['Phone', s.phone]].map(([k, v]) => <div key={k}><dt className="text-xs text-slate-500">{k}</dt><dd>{v || '—'}</dd></div>)}
            </dl>
          </Card>
        </div>
      )}

      {tab === 'results' && (
        <Card>
          {!data.results?.length ? <p className="py-6 text-center text-sm text-slate-500">No results available.</p> : (
            <Table head={['Term', 'Total', 'Average', 'Position', 'Status', '']}>
              {data.results.map((r: any) => (
                <tr key={r.id}>
                  <td className="px-3 py-2">{r.term?.name}</td><td className="px-3 py-2">{r.total_score}</td><td className="px-3 py-2">{r.average}</td>
                  <td className="px-3 py-2">{r.position}/{r.class_size}</td><td className="px-3 py-2"><Badge value={r.status} /></td>
                  <td className="px-3 py-2 text-right">{r.status === 'approved' && <Button size="sm" variant="secondary" onClick={() => download(`/results/report-card/${s.id}/${r.term_id}/pdf`).catch((e) => toast.err(errMsg(e)))}>Report card PDF</Button>}</td>
                </tr>
              ))}
            </Table>
          )}
        </Card>
      )}
      {tab === 'docs' && <Card><Attachments type="student" id={s.id} canWrite={can('students.update')} /></Card>}
      {tab === 'medical' && (
        <div className="space-y-4">
          <Card title="Medical profile">{data.medical_profile ? <dl className="grid grid-cols-2 gap-2 text-sm">{[['Blood group', data.medical_profile.blood_group], ['Genotype', data.medical_profile.genotype], ['Allergies', data.medical_profile.allergies], ['Chronic conditions', data.medical_profile.chronic_conditions], ['Medications', data.medical_profile.medications]].map(([k, v]) => <div key={k}><dt className="text-xs text-slate-500">{k}</dt><dd>{v || '—'}</dd></div>)}</dl> : <p className="text-sm text-slate-500">No medical profile recorded. Add one under Medical.</p>}</Card>
          <Card title="Recent visits">{data.medical_visits?.length ? <Table head={['Date', 'Complaint', 'Treatment']}>{data.medical_visits.map((v: any) => <tr key={v.id}><td className="px-3 py-2">{fmtDate(v.visit_date)}</td><td className="px-3 py-2">{v.complaint}</td><td className="px-3 py-2">{v.treatment}</td></tr>)}</Table> : <p className="text-sm text-slate-500">No visits.</p>}</Card>
        </div>
      )}
      {tab === 'discipline' && (
        <Card>{data.disciplinary_records.length ? <Table head={['Date', 'Category', 'Description', 'Action']}>{data.disciplinary_records.map((v: any) => <tr key={v.id}><td className="px-3 py-2">{fmtDate(v.incident_date)}</td><td className="px-3 py-2">{v.category}</td><td className="px-3 py-2">{v.description}</td><td className="px-3 py-2">{v.action_taken}</td></tr>)}</Table> : <p className="text-sm text-slate-500">No records.</p>}</Card>
      )}
    </>
  )
}
