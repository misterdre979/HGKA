import { useEffect, useMemo, useState } from 'react'
import { FkSelect } from '@/components/FkSelect'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Select, Spinner, Table, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg } from '@/lib/utils'
import { api } from '@/services/api'
import { ArmPicker } from './Academics'

function TermSelect({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const cur = useGet<any>('/academic/current')
  useEffect(() => { if (!value && cur.data?.term) onChange(String(cur.data.term.id)) }, [cur.data, value, onChange])
  return <div className="min-w-[10rem]"><Label>Term</Label><FkSelect resource="terms" value={value} onChange={onChange} /></div>
}

// ---------------------------------------------------------------- score entry (teachers)
interface Row { student_id: number; admission_no: string; name: string; ca_score: any; exam_score: any; total: any; grade: string | null; position: number | null; status: string }

export function ScoreEntryPage() {
  const toast = useToast()
  const [termId, setTerm] = useState('')
  const [classId, setClass] = useState('')
  const [armId, setArm] = useState('')
  const [subjectId, setSubject] = useState('')
  const subjects = useGet<any[]>(armId ? '/results/subjects' : null, { class_arm_id: armId })
  const ready = termId && armId && subjectId
  const sheet = useGet<{ max_ca: number; max_exam: number; students: Row[] }>(ready ? '/results/sheet' : null, { term_id: termId, class_arm_id: armId, subject_id: subjectId })
  const [scores, setScores] = useState<Record<number, { ca: string; exam: string }>>({})
  const [loaded, setLoaded] = useState('')
  const [busy, setBusy] = useState<'save' | 'submit' | null>(null)
  const [confirm, setConfirm] = useState(false)

  const sig = sheet.data ? `${termId}|${armId}|${subjectId}|${sheet.data.students.map((s) => `${s.ca_score}${s.exam_score}${s.status}`).join(',')}` : ''
  if (sheet.data && loaded !== sig) {
    setLoaded(sig)
    setScores(Object.fromEntries(sheet.data.students.map((s) => [s.student_id, { ca: s.ca_score ?? '', exam: s.exam_score ?? '' }])))
  }

  const maxCa = sheet.data?.max_ca ?? 40
  const maxExam = sheet.data?.max_exam ?? 60
  const rows = sheet.data?.students ?? []
  const locked = (r: Row) => r.status === 'approved' || r.status === 'submitted'
  const invalid = (r: Row) => {
    const s = scores[r.student_id]
    if (!s) return false
    return (s.ca !== '' && (Number(s.ca) < 0 || Number(s.ca) > maxCa || isNaN(Number(s.ca)))) || (s.exam !== '' && (Number(s.exam) < 0 || Number(s.exam) > maxExam || isNaN(Number(s.exam))))
  }
  const anyInvalid = rows.some(invalid)
  const anyEditable = rows.some((r) => !locked(r))

  const payload = () => rows.filter((r) => !locked(r)).map((r) => ({
    student_id: r.student_id, ca_score: scores[r.student_id]?.ca === '' ? null : Number(scores[r.student_id]?.ca), exam_score: scores[r.student_id]?.exam === '' ? null : Number(scores[r.student_id]?.exam),
  }))
  const base = { term_id: Number(termId), subject_id: Number(subjectId), class_arm_id: Number(armId) }

  const save = async () => {
    setBusy('save')
    try { const r = await api.post('/results/scores', { ...base, scores: payload() }); toast.ok(`Saved ${r.data.saved} score(s). Positions updated.`); void sheet.reload() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(null) }
  }
  const submit = async () => {
    setBusy('submit')
    try {
      await api.post('/results/scores', { ...base, scores: payload() })
      const r = await api.post('/results/submit', base)
      toast.ok(`${r.data.submitted} result(s) submitted for approval.`)
      setConfirm(false); void sheet.reload()
    } catch (e) { toast.err(errMsg(e)); setConfirm(false) } finally { setBusy(null) }
  }

  return (
    <>
      <PageHeader title="Score entry" subtitle="Enter continuous-assessment and exam scores. Totals, grades and positions are calculated by the server." />
      <Card>
        <div className="flex flex-wrap items-end gap-3">
          <TermSelect value={termId} onChange={setTerm} />
          <ArmPicker classId={classId} armId={armId} setClass={setClass} setArm={(v) => { setArm(v); setSubject('') }} />
          <div className="min-w-[12rem]"><Label>Subject</Label>
            <Select value={subjectId} onChange={(e) => setSubject(e.target.value)} disabled={!armId}>
              <option value="">{armId ? (subjects.data?.length ? 'Select…' : 'No subjects assigned to you') : 'Choose a class arm first'}</option>
              {subjects.data?.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select>
          </div>
        </div>
      </Card>
      {!ready ? <Card className="mt-4"><Empty text="Select term, class and subject to open the score sheet." /></Card> : sheet.loading ? <Spinner /> : sheet.error ? <ErrorBox message={sheet.error} onRetry={sheet.reload} /> : (
        <Card className="mt-4" title={`Score sheet · CA /${maxCa} · Exam /${maxExam}`}
          action={anyEditable && <div className="flex gap-2"><Button size="sm" variant="secondary" loading={busy === 'save'} disabled={anyInvalid} onClick={save}>Save draft</Button><Button size="sm" disabled={anyInvalid || !!busy} onClick={() => setConfirm(true)}>Submit for approval</Button></div>}>
          {!rows.length ? <Empty text="No active students in this class arm." /> : (
            <Table head={['#', 'Student', `CA (/${maxCa})`, `Exam (/${maxExam})`, 'Total', 'Grade', 'Pos.', 'Status']}>
              {rows.map((r, i) => {
                const s = scores[r.student_id] ?? { ca: '', exam: '' }
                const bad = invalid(r)
                return (
                  <tr key={r.student_id} className={bad ? 'bg-red-50' : ''}>
                    <td className="px-3 py-2 text-slate-400">{i + 1}</td>
                    <td className="px-3 py-2"><div className="font-medium">{r.name}</div><div className="text-xs text-slate-500">{r.admission_no}</div></td>
                    <td className="px-3 py-2"><Input aria-label={`CA score for ${r.name}`} type="number" min={0} max={maxCa} step="0.5" disabled={locked(r)} className="w-20 py-1" value={s.ca} onChange={(e) => setScores({ ...scores, [r.student_id]: { ...s, ca: e.target.value } })} /></td>
                    <td className="px-3 py-2"><Input aria-label={`Exam score for ${r.name}`} type="number" min={0} max={maxExam} step="0.5" disabled={locked(r)} className="w-20 py-1" value={s.exam} onChange={(e) => setScores({ ...scores, [r.student_id]: { ...s, exam: e.target.value } })} /></td>
                    <td className="px-3 py-2 font-medium">{r.total ?? '—'}</td><td className="px-3 py-2">{r.grade ?? '—'}</td><td className="px-3 py-2">{r.position ?? '—'}</td>
                    <td className="px-3 py-2"><Badge value={r.status === 'new' ? 'draft' : r.status} tone={r.status === 'new' ? 'gray' : undefined} /></td>
                  </tr>
                )
              })}
            </Table>
          )}
          {anyInvalid && <p role="alert" className="mt-3 text-sm text-red-600">Some scores are outside the allowed range. Fix the highlighted rows.</p>}
        </Card>
      )}
      <Confirm open={confirm} title="Submit for approval" message="Submitted scores are locked until the principal approves or reopens them. Continue?" confirmLabel="Submit" busy={busy === 'submit'} onCancel={() => setConfirm(false)} onConfirm={submit} />
    </>
  )
}

// ---------------------------------------------------------------- class results, approval, report cards
export function ClassResultsPage() {
  const { can } = useAuth()
  const toast = useToast()
  const [termId, setTerm] = useState('')
  const [classId, setClass] = useState('')
  const [armId, setArm] = useState('')
  const ready = termId && armId
  const sheet = useGet<any>(ready ? '/results/class-sheet' : null, { term_id: termId, class_arm_id: armId })
  const [confirm, setConfirm] = useState<'approve' | 'unapprove' | null>(null)
  const [comment, setComment] = useState<any>(null)
  const [busy, setBusy] = useState(false)

  const act = async () => {
    setBusy(true)
    try {
      const r = await api.post(`/results/${confirm}`, { term_id: Number(termId), class_arm_id: Number(armId) })
      toast.ok(confirm === 'approve' ? `${r.data.approved} result(s) approved.` : `${r.data.reopened} result(s) reopened.`)
      setConfirm(null); void sheet.reload()
    } catch (e) { toast.err(errMsg(e)); setConfirm(null) } finally { setBusy(false) }
  }
  const subjects: any[] = sheet.data?.subjects ?? []
  const status: string = sheet.data?.status ?? 'none'
  const canComment = can('exams.enter-scores', 'exams.manage', 'exams.approve-results')

  return (
    <>
      <PageHeader title="Results & report cards" subtitle="Class broadsheet, approval workflow and report cards" />
      <Card>
        <div className="flex flex-wrap items-end gap-3"><TermSelect value={termId} onChange={setTerm} /><ArmPicker classId={classId} armId={armId} setClass={setClass} setArm={setArm} /></div>
      </Card>
      {!ready ? <Card className="mt-4"><Empty text="Select a term and class arm." /></Card> : sheet.loading ? <Spinner /> : sheet.error ? <ErrorBox message={sheet.error} onRetry={sheet.reload} /> : (
        <Card className="mt-4" title={<span className="flex items-center gap-2">Broadsheet <Badge value={status} /></span>}
          action={<div className="flex gap-2">
            {can('exams.approve-results') && status === 'submitted' && <Button size="sm" onClick={() => setConfirm('approve')}>Approve results</Button>}
            {can('exams.approve-results') && status === 'approved' && <Button size="sm" variant="secondary" onClick={() => setConfirm('unapprove')}>Reopen results</Button>}
          </div>}>
          {!sheet.data.students.length ? <Empty text="No scores have been entered for this class and term." /> : (
            <>
              <Table head={['Pos.', 'Student', ...subjects.map((s) => s.code ?? s.name), 'Total', 'Avg', '']}>
                {sheet.data.students.map((s: any) => (
                  <tr key={s.student_id}>
                    <td className="px-3 py-2 font-semibold">{s.position ?? '—'}</td>
                    <td className="px-3 py-2"><div className="font-medium">{s.name}</div><div className="text-xs text-slate-500">{s.admission_no}</div></td>
                    {subjects.map((sub) => { const c = s.scores?.[sub.id]; return <td key={sub.id} className="px-3 py-2 whitespace-nowrap">{c ? <>{c.total} <span className="text-xs text-slate-400">{c.grade}</span></> : '—'}</td> })}
                    <td className="px-3 py-2 font-medium">{s.total ?? '—'}</td><td className="px-3 py-2">{s.average ?? '—'}</td>
                    <td className="whitespace-nowrap px-3 py-2 text-right">
                      {canComment && <Button size="sm" variant="ghost" onClick={() => setComment(s)}>Comments</Button>}
                      {status === 'approved' && <Button size="sm" variant="secondary" onClick={() => download(`/results/report-card/${s.student_id}/${termId}/pdf`).catch((e) => toast.err(errMsg(e)))}>PDF</Button>}
                    </td>
                  </tr>
                ))}
              </Table>
              <p className="mt-3 text-xs text-slate-500">Subject scores show total and grade. Report cards become available once results are approved.</p>
            </>
          )}
        </Card>
      )}
      <Confirm open={!!confirm} title={confirm === 'approve' ? 'Approve results' : 'Reopen results'} busy={busy} confirmLabel={confirm === 'approve' ? 'Approve' : 'Reopen'}
        message={confirm === 'approve' ? 'Approved results become visible to parents and students and are locked.' : 'Results return to draft so teachers can correct them. Parents will no longer see them.'} onCancel={() => setConfirm(null)} onConfirm={act} />
      <CommentModal row={comment} termId={termId} onClose={() => setComment(null)} onSaved={() => { setComment(null); void sheet.reload() }} />
    </>
  )
}

function CommentModal({ row, termId, onClose, onSaved }: { row: any; termId: string; onClose: () => void; onSaved: () => void }) {
  const { can } = useAuth()
  const toast = useToast()
  const [t, setT] = useState('')
  const [p, setP] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { if (row) { setT(row.teacher_comment ?? ''); setP(row.principal_comment ?? '') } }, [row])
  const isPrincipal = can('exams.approve-results')
  const body = useMemo(() => ({ term_id: Number(termId), teacher_comment: t, ...(isPrincipal ? { principal_comment: p } : {}) }), [termId, t, p, isPrincipal])
  return (
    <Modal open={!!row} onClose={onClose} title={`Comments · ${row?.name ?? ''}`} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={busy} onClick={async () => {
      setBusy(true)
      try { await api.put(`/results/comments/${row.student_id}`, body); toast.ok('Comments saved.'); onSaved() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
    }}>Save</Button></>}>
      <div className="space-y-3">
        <label className="block"><Label>Class teacher's comment</Label><Textarea value={t} maxLength={1000} onChange={(e) => setT(e.target.value)} /></label>
        {isPrincipal && <label className="block"><Label>Principal's comment</Label><Textarea value={p} maxLength={1000} onChange={(e) => setP(e.target.value)} /></label>}
      </div>
    </Modal>
  )
}
