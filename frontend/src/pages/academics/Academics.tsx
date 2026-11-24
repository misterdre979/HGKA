import { useState } from 'react'
import { FkSelect } from '@/components/FkSelect'
import { ResourceTable } from '@/components/ResourceTable'
import { Badge, Button, Card, Empty, ErrorBox, Input, Label, PageHeader, Select, Spinner, Table } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { errMsg, humanize, today } from '@/lib/utils'
import { api } from '@/services/api'
import { ResourceTabsPage } from '../ResourceTabsPage'

export function AcademicSetupPage() {
  const { can } = useAuth()
  const toast = useToast()
  const mk = (resource: string, endpoint: string) => ({
    resource,
    render: () => (
      <ResourceTable resource={resource} hideHeader extraRow={(r, reload) => can('academics.manage') && !r.is_current ? (
        <Button size="sm" variant="secondary" onClick={async () => { try { await api.post(`/${endpoint}/${r.id}/set-current`); toast.ok('Set as current.'); reload() } catch (e) { toast.err(errMsg(e)) } }}>Make current</Button>
      ) : null} />
    ),
  })
  return (
    <ResourceTabsPage title="Academic setup" subtitle="Sessions, classes, subjects, teaching assignments and timetable" tabs={[
      mk('academic-sessions', 'academic-sessions'), mk('terms', 'terms'),
      { resource: 'classes' }, { resource: 'class-arms' }, { resource: 'subjects' }, { resource: 'class-subjects' },
      { resource: 'teacher-assignments' }, { resource: 'timetable' }, { resource: 'calendar-events' },
      { resource: 'departments' }, { resource: 'designations' },
    ]} />
  )
}

export function ExamsPage() {
  return <ResourceTabsPage title="Examinations" subtitle="Exam schedule and grading scales" tabs={[{ resource: 'exams' }, { resource: 'grading-scales' }]} />
}

// ---------------------------------------------------------------- shared: class + arm picker
export function ArmPicker({ classId, armId, setClass, setArm }: { classId: string; armId: string; setClass: (v: string) => void; setArm: (v: string) => void }) {
  return (
    <>
      <div className="min-w-[10rem]"><Label>Class</Label><FkSelect resource="classes" value={classId} onChange={(v) => { setClass(v); setArm('') }} /></div>
      <div className="min-w-[10rem]"><Label>Arm</Label><FkSelect resource="class-arms" needsFilter filter={{ school_class_id: classId }} value={armId} onChange={setArm} /></div>
    </>
  )
}

// ---------------------------------------------------------------- student attendance
const STATUS = ['present', 'absent', 'late', 'excused'] as const
const STATUS_CLS: Record<string, string> = {
  present: 'bg-emerald-600 text-white', absent: 'bg-red-600 text-white', late: 'bg-amber-500 text-white', excused: 'bg-slate-600 text-white',
}

export function AttendancePage() {
  const { can } = useAuth()
  const toast = useToast()
  const [classId, setClass] = useState('')
  const [armId, setArm] = useState('')
  const [date, setDate] = useState(today())
  const sheet = useGet<{ students: any[]; already_marked: boolean }>(armId ? '/attendance/sheet' : null, { class_arm_id: armId, date })
  const [marks, setMarks] = useState<Record<number, { status: string; remark?: string }>>({})
  const [saving, setSaving] = useState(false)
  const [loadedKey, setLoadedKey] = useState('')

  const key = `${armId}|${date}`
  if (sheet.data && loadedKey !== key + sheet.data.students.length + sheet.data.already_marked) {
    setLoadedKey(key + sheet.data.students.length + sheet.data.already_marked)
    setMarks(Object.fromEntries(sheet.data.students.map((s) => [s.student_id, { status: s.status ?? 'present', remark: s.remark ?? '' }])))
  }
  const canTake = can('attendance.take')
  const counts = STATUS.map((st) => [st, Object.values(marks).filter((m) => m.status === st).length] as const)

  const save = async () => {
    setSaving(true)
    try {
      const r = await api.post('/attendance/save', { class_arm_id: Number(armId), date, records: Object.entries(marks).map(([id, m]) => ({ student_id: Number(id), status: m.status, remark: m.remark || null })) })
      toast.ok(`Saved ${r.data.saved} records${r.data.absence_alerts ? `. ${r.data.absence_alerts} parent alert(s) sent.` : '.'}`)
      void sheet.reload()
    } catch (e) { toast.err(errMsg(e)) } finally { setSaving(false) }
  }

  return (
    <>
      <PageHeader title="Class attendance" subtitle="Take or review the daily register" />
      <Card>
        <div className="flex flex-wrap items-end gap-3">
          <ArmPicker classId={classId} armId={armId} setClass={setClass} setArm={setArm} />
          <div><Label>Date</Label><Input type="date" max={today()} value={date} onChange={(e) => setDate(e.target.value)} /></div>
        </div>
      </Card>
      {!armId ? <Card className="mt-4"><Empty text="Choose a class and arm to open the register." /></Card> : sheet.loading ? <Spinner /> : sheet.error ? <ErrorBox message={sheet.error} onRetry={sheet.reload} /> : (
        <Card className="mt-4" title={`${sheet.data?.students.length ?? 0} students${sheet.data?.already_marked ? ' · register already saved' : ''}`}
          action={canTake && !!sheet.data?.students.length && <div className="flex gap-2"><Button size="sm" variant="secondary" onClick={() => setMarks(Object.fromEntries(Object.keys(marks).map((k) => [k, { ...marks[Number(k)], status: 'present' }])))}>All present</Button><Button size="sm" loading={saving} onClick={save}>Save register</Button></div>}>
          {!sheet.data?.students.length ? <Empty text="No active students in this arm." /> : (
            <>
              <div className="mb-3 flex flex-wrap gap-2 text-xs">{counts.map(([s, n]) => <Badge key={s} value={`${s}: ${n}`} tone={s === 'present' ? 'green' : s === 'absent' ? 'red' : s === 'late' ? 'amber' : 'gray'} />)}</div>
              <Table head={['#', 'Student', 'Status', 'Remark']}>
                {sheet.data.students.map((s, i) => (
                  <tr key={s.student_id}>
                    <td className="px-3 py-2 text-slate-400">{i + 1}</td>
                    <td className="px-3 py-2"><div className="font-medium">{s.name}</div><div className="text-xs text-slate-500">{s.admission_no}</div></td>
                    <td className="px-3 py-2"><div className="flex gap-1" role="group" aria-label={`Status for ${s.name}`}>
                      {STATUS.map((st) => (
                        <button key={st} disabled={!canTake} aria-pressed={marks[s.student_id]?.status === st} onClick={() => setMarks({ ...marks, [s.student_id]: { ...marks[s.student_id], status: st } })}
                          className={`rounded-md px-2 py-1 text-xs font-medium capitalize ${marks[s.student_id]?.status === st ? STATUS_CLS[st] : 'bg-slate-100 text-slate-600 hover:bg-slate-200'} disabled:opacity-60`}>{st}</button>
                      ))}
                    </div></td>
                    <td className="px-3 py-2"><Input disabled={!canTake} className="py-1" value={marks[s.student_id]?.remark ?? ''} onChange={(e) => setMarks({ ...marks, [s.student_id]: { ...marks[s.student_id], remark: e.target.value } })} /></td>
                  </tr>
                ))}
              </Table>
            </>
          )}
        </Card>
      )}
      {armId && can('attendance.view', 'attendance.report') && <AttendanceSummary armId={armId} />}
    </>
  )
}

function AttendanceSummary({ armId }: { armId: string }) {
  const [from, setFrom] = useState(() => new Date(Date.now() - 29 * 864e5).toISOString().slice(0, 10))
  const [to, setTo] = useState(today())
  const { data, loading, error } = useGet<any>('/attendance/summary', { class_arm_id: armId, from, to })
  return (
    <Card className="mt-4" title="Period summary" action={<div className="flex items-center gap-2"><Input type="date" className="py-1" value={from} onChange={(e) => setFrom(e.target.value)} aria-label="From" /><span className="text-slate-400">to</span><Input type="date" className="py-1" value={to} onChange={(e) => setTo(e.target.value)} aria-label="To" /></div>}>
      {loading ? <Spinner /> : error ? <ErrorBox message={error} /> : (
        <Table head={['Student', 'Present', 'Late', 'Excused', 'Absent', 'Rate']}>
          {(data?.students ?? data ?? []).map((r: any) => (
            <tr key={r.student_id ?? r.id}><td className="px-3 py-2">{r.name}</td><td className="px-3 py-2">{r.present}</td><td className="px-3 py-2">{r.late}</td><td className="px-3 py-2">{r.excused}</td><td className="px-3 py-2">{r.absent}</td><td className="px-3 py-2 font-medium">{r.rate === null || r.rate === undefined ? '—' : `${r.rate}%`}</td></tr>
          ))}
        </Table>
      )}
    </Card>
  )
}

// ---------------------------------------------------------------- staff attendance
export function StaffAttendancePage() {
  const toast = useToast()
  const [date, setDate] = useState(today())
  const sheet = useGet<any[]>('/attendance/staff/sheet', { date })
  const [marks, setMarks] = useState<Record<number, string>>({})
  const [loadedFor, setLoadedFor] = useState('')
  const [saving, setSaving] = useState(false)
  if (sheet.data && loadedFor !== date + sheet.data.length) {
    setLoadedFor(date + sheet.data.length)
    setMarks(Object.fromEntries(sheet.data.map((s) => [s.staff_id, s.status ?? 'present'])))
  }
  return (
    <>
      <PageHeader title="Staff attendance" actions={<Button loading={saving} onClick={async () => {
        setSaving(true)
        try { await api.post('/attendance/staff/save', { date, records: Object.entries(marks).map(([id, status]) => ({ staff_id: Number(id), status })) }); toast.ok('Staff register saved.') } catch (e) { toast.err(errMsg(e)) } finally { setSaving(false) }
      }}>Save register</Button>} />
      <Card>
        <div className="mb-3 max-w-xs"><Label>Date</Label><Input type="date" max={today()} value={date} onChange={(e) => setDate(e.target.value)} /></div>
        {sheet.loading ? <Spinner /> : sheet.error ? <ErrorBox message={sheet.error} onRetry={sheet.reload} /> : !sheet.data?.length ? <Empty text="No active staff." /> : (
          <Table head={['Staff', 'Category', 'Status']}>
            {sheet.data.map((s) => (
              <tr key={s.staff_id}><td className="px-3 py-2"><div className="font-medium">{s.name}</div><div className="text-xs text-slate-500">{s.staff_no}</div></td><td className="px-3 py-2">{humanize(s.category)}</td>
                <td className="px-3 py-2"><Select className="max-w-[10rem] py-1" value={marks[s.staff_id] ?? 'present'} onChange={(e) => setMarks({ ...marks, [s.staff_id]: e.target.value })}>{['present', 'absent', 'late', 'excused'].map((x) => <option key={x} value={x}>{humanize(x)}</option>)}</Select></td></tr>
            ))}
          </Table>
        )}
      </Card>
    </>
  )
}
