import { Eye, Plus } from 'lucide-react'
import { useState } from 'react'
import { FkSelect } from '@/components/FkSelect'
import { ResourceTable } from '@/components/ResourceTable'
import { Attachments } from '@/components/Attachments'
import { Badge, Button, Card, Confirm, Empty, ErrorBox, Input, Label, Modal, PageHeader, Pager, Select, Spinner, Stat, Table, Textarea } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { useGet, type Page } from '@/hooks/useApi'
import { errMsg, fmtDate, humanize, money, today } from '@/lib/utils'
import { api } from '@/services/api'
import { ResourceTabsPage } from '../ResourceTabsPage'

// ---------------------------------------------------------------- library
function LoansTab() {
  const { can } = useAuth()
  const toast = useToast()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const loans = useGet<Page<any>>('/library/loans', { page, status: status || undefined })
  const [issue, setIssue] = useState(false)
  const [act, setAct] = useState<{ loan: any; kind: 'return' | 'lost' } | null>(null)
  const [f, setF] = useState({ book_id: '', borrower_type: 'student', student_id: '', staff_id: '', due_on: '', notes: '' })
  const [busy, setBusy] = useState(false)

  const submit = async () => {
    setBusy(true)
    try {
      await api.post('/library/loans', { book_id: Number(f.book_id), borrower_type: f.borrower_type, student_id: f.borrower_type === 'student' ? Number(f.student_id) : null, staff_id: f.borrower_type === 'staff' ? Number(f.staff_id) : null, due_on: f.due_on || null, notes: f.notes || null })
      toast.ok('Book issued.'); setIssue(false); setF({ book_id: '', borrower_type: 'student', student_id: '', staff_id: '', due_on: '', notes: '' }); void loans.reload()
    } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
  }

  return (
    <Card>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <Select aria-label="Status" className="max-w-[11rem]" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All loans</option>{['borrowed', 'overdue', 'returned', 'lost'].map((s) => <option key={s} value={s}>{humanize(s)}</option>)}</Select>
        {can('library.manage') && <Button onClick={() => setIssue(true)}><Plus size={16} /> Issue book</Button>}
      </div>
      {loans.error && <ErrorBox message={loans.error} onRetry={loans.reload} />}
      {loans.loading && !loans.data ? <Spinner /> : !loans.data?.data.length ? <Empty text="No loans." /> : (
        <>
          <Table head={['Book', 'Borrower', 'Issued', 'Due', 'Status', 'Fine', '']}>
            {loans.data.data.map((l) => (
              <tr key={l.id}>
                <td className="px-3 py-2 font-medium">{l.book?.title}</td>
                <td className="px-3 py-2">{l.borrower_type === 'student' ? `${l.student?.first_name} ${l.student?.last_name}` : `${l.staff?.first_name} ${l.staff?.last_name}`} <span className="text-xs text-slate-400">({l.borrower_type})</span></td>
                <td className="px-3 py-2">{fmtDate(l.issued_on)}</td><td className="px-3 py-2">{fmtDate(l.due_on)}</td>
                <td className="px-3 py-2"><Badge value={l.status === 'borrowed' && l.overdue_days > 0 ? 'overdue' : l.status} />{l.overdue_days > 0 && l.status === 'borrowed' && <span className="ml-1 text-xs text-red-600">{l.overdue_days}d</span>}</td>
                <td className="px-3 py-2">{Number(l.fine_due) > 0 ? money(l.fine_due) : '—'}</td>
                <td className="whitespace-nowrap px-3 py-2 text-right">{l.status === 'borrowed' && can('library.manage') && <><Button size="sm" variant="secondary" onClick={() => setAct({ loan: l, kind: 'return' })}>Return</Button> <Button size="sm" variant="ghost" className="text-red-600" onClick={() => setAct({ loan: l, kind: 'lost' })}>Lost</Button></>}</td>
              </tr>
            ))}
          </Table>
          <Pager page={loans.data.current_page} last={loans.data.last_page} total={loans.data.total} onPage={setPage} />
        </>
      )}
      <Modal open={issue} onClose={() => setIssue(false)} title="Issue a book" footer={<><Button variant="secondary" onClick={() => setIssue(false)}>Cancel</Button><Button loading={busy} disabled={!f.book_id || !(f.borrower_type === 'student' ? f.student_id : f.staff_id)} onClick={submit}>Issue</Button></>}>
        <div className="space-y-3">
          <label className="block"><Label required>Book</Label><FkSelect resource="books" value={f.book_id} onChange={(v) => setF({ ...f, book_id: v })} /></label>
          <label className="block"><Label required>Borrower</Label><Select value={f.borrower_type} onChange={(e) => setF({ ...f, borrower_type: e.target.value })}><option value="student">Student</option><option value="staff">Staff</option></Select></label>
          {f.borrower_type === 'student' ? <label className="block"><Label required>Student</Label><FkSelect resource="students" value={f.student_id} onChange={(v) => setF({ ...f, student_id: v })} /></label>
            : <label className="block"><Label required>Staff member</Label><FkSelect resource="staff" value={f.staff_id} onChange={(v) => setF({ ...f, staff_id: v })} /></label>}
          <label className="block"><Label help="Defaults to the library loan period">Due date</Label><Input type="date" min={today()} value={f.due_on} onChange={(e) => setF({ ...f, due_on: e.target.value })} /></label>
        </div>
      </Modal>
      <Confirm open={!!act} danger={act?.kind === 'lost'} title={act?.kind === 'return' ? 'Return book' : 'Mark as lost'} confirmLabel={act?.kind === 'return' ? 'Return' : 'Mark lost'}
        message={act?.kind === 'return' ? 'Confirm the book has been returned. Any overdue fine is calculated automatically.' : 'The book will be marked lost and removed from available copies.'} onCancel={() => setAct(null)}
        onConfirm={async () => { try { await api.post(`/library/loans/${act!.loan.id}/${act!.kind}`); toast.ok('Updated.'); setAct(null); void loans.reload() } catch (e) { toast.err(errMsg(e)); setAct(null) } }} />
    </Card>
  )
}

export function LibraryPage() {
  const s = useGet<any>('/library/summary')
  return (
    <>
      {s.data && <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <Stat label="Titles" value={s.data.titles} /><Stat label="Copies" value={s.data.copies} /><Stat label="Available" value={s.data.available} tone="green" /><Stat label="On loan" value={s.data.on_loan} tone="gold" /><Stat label="Overdue" value={s.data.overdue} tone={s.data.overdue ? 'red' : 'green'} />
      </div>}
      <ResourceTabsPage title="Library" subtitle="Catalogue, loans and returns" tabs={[{ id: 'loans', label: 'Loans', perms: ['library.view'], render: () => <LoansTab /> }, { resource: 'books' }, { resource: 'book-categories' }]} />
    </>
  )
}

// ---------------------------------------------------------------- inventory
function StockModal({ item, onClose, onDone }: { item: any; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const [f, setF] = useState({ type: 'issue', quantity: '1', issued_to: '', reference: '', notes: '' })
  const [busy, setBusy] = useState(false)
  return (
    <Modal open={!!item} onClose={onClose} title={`Stock · ${item?.name ?? ''}`} footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={busy} disabled={!Number(f.quantity) || (f.type === 'issue' && !f.issued_to)} onClick={async () => {
      setBusy(true)
      try { await api.post(`/inventory/items/${item.id}/stock`, { ...f, quantity: Number(f.quantity), issued_to: f.issued_to || null, reference: f.reference || null, notes: f.notes || null }); toast.ok('Stock updated.'); onDone() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
    }}>Save</Button></>}>
      <div className="space-y-3">
        <p className="text-sm text-slate-500">In stock now: <b>{item?.quantity} {item?.unit}</b></p>
        <label className="block"><Label required>Action</Label><Select value={f.type} onChange={(e) => setF({ ...f, type: e.target.value })}><option value="issue">Issue out</option><option value="return">Return in</option><option value="adjustment">Adjustment (+/−)</option></Select></label>
        <label className="block"><Label required help={f.type === 'adjustment' ? 'negative to reduce' : undefined}>Quantity</Label><Input type="number" step="1" value={f.quantity} onChange={(e) => setF({ ...f, quantity: e.target.value })} /></label>
        {f.type === 'issue' && <label className="block"><Label required>Issued to</Label><Input value={f.issued_to} onChange={(e) => setF({ ...f, issued_to: e.target.value })} /></label>}
        <label className="block"><Label>Notes</Label><Textarea value={f.notes} onChange={(e) => setF({ ...f, notes: e.target.value })} /></label>
      </div>
    </Modal>
  )
}

function PurchaseModal({ open, onClose, onDone }: { open: boolean; onClose: () => void; onDone: () => void }) {
  const toast = useToast()
  const [f, setF] = useState({ supplier_id: '', inventory_item_id: '', quantity: '1', unit_cost: '', purchase_date: today(), invoice_no: '' })
  const [busy, setBusy] = useState(false)
  return (
    <Modal open={open} onClose={onClose} title="Record a purchase" footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={busy} disabled={!f.inventory_item_id || !f.unit_cost || !Number(f.quantity)} onClick={async () => {
      setBusy(true)
      try { await api.post('/inventory/purchases', { ...f, supplier_id: f.supplier_id ? Number(f.supplier_id) : null, inventory_item_id: Number(f.inventory_item_id), quantity: Number(f.quantity), unit_cost: Number(f.unit_cost), invoice_no: f.invoice_no || null }); toast.ok('Purchase recorded; stock increased.'); onDone() } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false) }
    }}>Record</Button></>}>
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="sm:col-span-2"><Label required>Item</Label><FkSelect resource="inventory-items" value={f.inventory_item_id} onChange={(v) => setF({ ...f, inventory_item_id: v })} /></label>
        <label><Label>Supplier</Label><FkSelect resource="suppliers" value={f.supplier_id} onChange={(v) => setF({ ...f, supplier_id: v })} /></label>
        <label><Label>Invoice no.</Label><Input value={f.invoice_no} onChange={(e) => setF({ ...f, invoice_no: e.target.value })} /></label>
        <label><Label required>Quantity</Label><Input type="number" min="1" step="1" value={f.quantity} onChange={(e) => setF({ ...f, quantity: e.target.value })} /></label>
        <label><Label required>Unit cost</Label><Input type="number" min="0" step="0.01" value={f.unit_cost} onChange={(e) => setF({ ...f, unit_cost: e.target.value })} /></label>
        <label><Label required>Date</Label><Input type="date" max={today()} value={f.purchase_date} onChange={(e) => setF({ ...f, purchase_date: e.target.value })} /></label>
      </div>
    </Modal>
  )
}

export function InventoryPage() {
  const { can } = useAuth()
  const low = useGet<any[]>('/inventory/low-stock')
  const [stock, setStock] = useState<any>(null)
  const [purchase, setPurchase] = useState(false)
  const [tick, setTick] = useState(0)
  return (
    <>
      {low.data && low.data.length > 0 && (
        <div role="status" className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><b>{low.data.length} item(s) at or below reorder level:</b> {low.data.slice(0, 6).map((i) => `${i.name} (${i.quantity})`).join(', ')}{low.data.length > 6 ? '…' : ''}</div>
      )}
      <ResourceTabsPage title="Inventory & assets" subtitle="Consumables, stock movements, purchases and fixed assets" tabs={[
        { id: 'items', resource: 'inventory-items', render: () => <ResourceTable key={tick} resource="inventory-items" hideHeader extraRow={(r) => can('inventory.manage') ? <Button size="sm" variant="secondary" onClick={() => setStock(r)}>Stock</Button> : null} /> },
        { id: 'purchases', resource: 'purchases', render: () => <div key={tick}>{can('inventory.manage') && <div className="mb-3 flex justify-end"><Button onClick={() => setPurchase(true)}><Plus size={16} /> Record purchase</Button></div>}<ResourceTable resource="purchases" hideHeader /></div> },
        { resource: 'stock-movements' }, { resource: 'inventory-categories' }, { resource: 'suppliers' }, { resource: 'assets' }, { resource: 'asset-maintenance' },
      ]} />
      <StockModal item={stock} onClose={() => setStock(null)} onDone={() => { setStock(null); setTick((t) => t + 1); void low.reload() }} />
      <PurchaseModal open={purchase} onClose={() => setPurchase(false)} onDone={() => { setPurchase(false); setTick((t) => t + 1); void low.reload() }} />
    </>
  )
}

// ---------------------------------------------------------------- transport
function RosterModal({ route, onClose }: { route: any; onClose: () => void }) {
  const { data, loading, error } = useGet<any>(route ? `/transport/routes/${route.id}/roster` : null)
  return (
    <Modal open={!!route} onClose={onClose} title={`Roster · ${route?.name ?? ''}`}>
      {loading ? <Spinner /> : error ? <ErrorBox message={error} /> : (
        <>
          <p className="mb-2 text-sm text-slate-500">Vehicle: {data?.route?.vehicle?.reg_no ?? '—'} · Driver: {data?.route?.vehicle?.driver?.name ?? '—'}</p>
          {!data?.students?.length ? <Empty text="No students allocated this term." /> : <Table head={['Student', 'Pickup point']}>{data.students.map((a: any) => <tr key={a.id}><td className="px-3 py-2">{a.student?.first_name} {a.student?.last_name}</td><td className="px-3 py-2">{a.pickup_point ?? '—'}</td></tr>)}</Table>}
        </>
      )}
    </Modal>
  )
}

export function TransportPage() {
  const [roster, setRoster] = useState<any>(null)
  return (
    <>
      <ResourceTabsPage title="Transport" subtitle="Routes, vehicles, drivers and student allocations" tabs={[
        { id: 'routes', resource: 'transport-routes', render: () => <ResourceTable resource="transport-routes" hideHeader extraRow={(r) => <Button size="sm" variant="ghost" aria-label="Roster" onClick={() => setRoster(r)}><Eye size={15} /></Button>} /> },
        { resource: 'vehicles' }, { resource: 'drivers' }, { resource: 'transport-allocations' }, { resource: 'vehicle-maintenance' },
      ]} />
      <RosterModal route={roster} onClose={() => setRoster(null)} />
    </>
  )
}

// ---------------------------------------------------------------- hostel
function Occupancy() {
  const { data, loading, error, reload } = useGet<any[]>('/hostel/occupancy')
  const [room, setRoom] = useState<any>(null)
  const occ = useGet<any[]>(room ? `/hostel/rooms/${room.id}/occupants` : null)
  if (loading) return <Spinner />
  if (error) return <ErrorBox message={error} onRetry={reload} />
  return (
    <div className="space-y-4">
      {!data?.length ? <Card><Empty text="No hostels set up yet." /></Card> : data.map((h) => (
        <Card key={h.id} title={`${h.name} · ${h.occupied}/${h.capacity} beds occupied`}>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            {h.rooms.map((r: any) => (
              <button key={r.id} onClick={() => setRoom(r)} className={`rounded-lg border p-3 text-left text-sm hover:border-royal ${r.available === 0 ? 'border-red-200 bg-red-50' : 'border-slate-200'}`}>
                <div className="font-semibold">Room {r.room_no}</div><div className="text-xs text-slate-500">{r.occupied}/{r.capacity} occupied</div>
              </button>
            ))}
          </div>
        </Card>
      ))}
      <Modal open={!!room} onClose={() => setRoom(null)} title={`Room ${room?.room_no ?? ''}`}>
        {occ.loading ? <Spinner /> : !occ.data?.length ? <Empty text="Room is empty." /> : <Table head={['Bed', 'Student']}>{occ.data.map((o: any) => <tr key={o.id}><td className="px-3 py-2">{o.bed_no ?? '—'}</td><td className="px-3 py-2">{o.student?.first_name} {o.student?.last_name} <span className="text-xs text-slate-400">{o.student?.admission_no}</span></td></tr>)}</Table>}
      </Modal>
    </div>
  )
}
export const HostelPage = () => <ResourceTabsPage title="Hostel" subtitle="Hostels, rooms and bed allocation" tabs={[{ id: 'occ', label: 'Occupancy', perms: ['hostel.view'], render: () => <Occupancy /> }, { resource: 'hostels' }, { resource: 'hostel-rooms' }, { resource: 'hostel-allocations' }]} />

// ---------------------------------------------------------------- simple resource pages
export const MedicalPage = () => <ResourceTabsPage title="Medical" subtitle="Health profiles and sick-bay visits" tabs={[{ resource: 'medical-visits' }, { resource: 'medical-profiles' }]} />
export const DisciplinePage = () => <ResourceTable resource="disciplinary-records" title="Discipline" subtitle="Incidents and actions taken" />
export const TeacherReportsPage = () => <ResourceTable resource="teacher-reports" title="Teacher reports" subtitle="Submitted by teachers and reviewed by school leadership" />
export const GuardiansPage = () => <ResourceTable resource="guardians" title="Parents / guardians" />

export function LessonNotesPage() {
  const { can } = useAuth()
  const [open, setOpen] = useState<any>(null)
  return (
    <>
      <ResourceTable resource="lesson-notes" title="Lesson notes" subtitle="Lesson plans and teaching notes" extraRow={(r) => <Button size="sm" variant="ghost" aria-label="Files" onClick={() => setOpen(r)}><Eye size={15} /></Button>} />
      <Modal open={!!open} onClose={() => setOpen(null)} title={`Attachments · ${open?.title ?? ''}`}>{open && <Attachments type="lesson-note" id={open.id} canWrite={can('lesson-notes.manage')} />}</Modal>
    </>
  )
}
export { PageHeader }
