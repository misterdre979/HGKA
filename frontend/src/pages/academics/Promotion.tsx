import { useEffect, useState } from 'react'
import { FkSelect } from '@/components/FkSelect'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Label, PageHeader, Select, Spinner, Table } from '@/components/ui'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { errMsg } from '@/lib/utils'
import { api } from '@/services/api'
import { ArmPicker } from './Academics'

export function PromotionPage() {
  const toast = useToast()
  const [classId, setClass] = useState('')
  const [armId, setArm] = useState('')
  const students = useGet<any>(classId ? '/students' : null, { per_page: 100, 'filter[school_class_id]': classId, 'filter[status]': 'active', ...(armId ? { 'filter[class_arm_id]': armId } : {}) })
  const [picked, setPicked] = useState<Set<number>>(new Set())
  const [action, setAction] = useState('promote')
  const [toClass, setToClass] = useState('')
  const [toArm, setToArm] = useState('')
  const [confirm, setConfirm] = useState(false)
  const [busy, setBusy] = useState(false)
  const rows: any[] = students.data?.data ?? []

  useEffect(() => { setPicked(new Set()) }, [classId, armId])
  const toggle = (id: number) => setPicked((s) => { const n = new Set(s); if (n.has(id)) n.delete(id); else n.add(id); return n })

  const run = async () => {
    setBusy(true)
    try {
      const r = await api.post('/students/promote', {
        student_ids: [...picked], action,
        ...(action === 'promote' && toClass ? { to_class_id: Number(toClass) } : {}), ...(action === 'promote' && toArm ? { to_arm_id: Number(toArm) } : {}),
      })
      const d = r.data
      toast.ok(typeof d === 'object' ? `Done: ${Object.entries(d).filter(([, v]) => typeof v === 'number').map(([k, v]) => `${v} ${k}`).join(', ') || 'completed'}.` : 'Done.')
      setConfirm(false); setPicked(new Set()); void students.reload()
    } catch (e) { toast.err(errMsg(e)); setConfirm(false) } finally { setBusy(false) }
  }

  return (
    <>
      <PageHeader title="Promotion" subtitle="Move students to the next class at the end of a session" />
      <Card>
        <div className="flex flex-wrap items-end gap-3"><ArmPicker classId={classId} armId={armId} setClass={setClass} setArm={setArm} /></div>
      </Card>
      {!classId ? <Card className="mt-4"><Empty text="Choose a class to list its active students." /></Card> : students.loading ? <Spinner /> : students.error ? <ErrorBox message={students.error} onRetry={students.reload} /> : (
        <Card className="mt-4" title={`${picked.size} of ${rows.length} selected`}>
          {!rows.length ? <Empty text="No active students here." /> : (
            <>
              <Table head={[<input key="all" type="checkbox" aria-label="Select all" checked={picked.size === rows.length} onChange={(e) => setPicked(e.target.checked ? new Set(rows.map((r) => r.id)) : new Set())} />, 'Admission no.', 'Name', 'Class', 'Status']}>
                {rows.map((r) => (
                  <tr key={r.id}><td className="px-3 py-2"><input type="checkbox" aria-label={`Select ${r.first_name}`} checked={picked.has(r.id)} onChange={() => toggle(r.id)} /></td>
                    <td className="px-3 py-2">{r.admission_no}</td><td className="px-3 py-2">{r.first_name} {r.last_name}</td><td className="px-3 py-2">{r.school_class?.name} {r.class_arm?.name}</td><td className="px-3 py-2"><Badge value={r.status} /></td></tr>
                ))}
              </Table>
              <div className="mt-4 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4">
                <div><Label>Action</Label><Select value={action} onChange={(e) => setAction(e.target.value)}><option value="promote">Promote</option><option value="repeat">Repeat class</option><option value="graduate">Graduate</option><option value="transfer">Transfer out</option></Select></div>
                {action === 'promote' && <>
                  <div className="min-w-[10rem]"><Label help="Leave empty for next class">Destination class</Label><FkSelect resource="classes" value={toClass} onChange={(v) => { setToClass(v); setToArm('') }} placeholder="Next class (automatic)" /></div>
                  {toClass && <div className="min-w-[10rem]"><Label>Destination arm</Label><FkSelect resource="class-arms" needsFilter filter={{ school_class_id: toClass }} value={toArm} onChange={setToArm} /></div>}
                </>}
                <Button disabled={!picked.size} onClick={() => setConfirm(true)}>Apply to {picked.size} student(s)</Button>
              </div>
            </>
          )}
        </Card>
      )}
      <Confirm open={confirm} busy={busy} title="Confirm promotion" confirmLabel="Apply" danger={action === 'graduate' || action === 'transfer'} onCancel={() => setConfirm(false)} onConfirm={run}
        message={`${action === 'promote' ? 'Promote' : action === 'repeat' ? 'Keep in the same class' : action === 'graduate' ? 'Graduate' : 'Transfer out'} ${picked.size} student(s)? This is recorded in the promotion history.`} />
    </>
  )
}
