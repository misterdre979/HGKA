import { AlertTriangle, BookOpen, CalendarCheck, GraduationCap, Package, Receipt, UserCog, Wallet } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Bar, BarChart, CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Badge, Card, ErrorBox, PageHeader, Spinner, Stat, Table } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useGet } from '@/hooks/useApi'
import { fmtDate, money } from '@/lib/utils'

const shortMoney = (n: number) => (n >= 1e6 ? `₦${(n / 1e6).toFixed(1)}M` : n >= 1e3 ? `₦${Math.round(n / 1e3)}k` : `₦${n}`)

export function Dashboard() {
  const { user } = useAuth()
  const { data: d, loading, error, reload } = useGet<any>('/dashboard')
  if (loading) return <Spinner />
  if (error || !d) return <ErrorBox message={error ?? 'Could not load the dashboard.'} onRetry={reload} />

  const fin = d.finance
  return (
    <>
      <PageHeader
        title={`Welcome, ${user?.name.split(' ')[0]}`}
        subtitle={d.period.session ? `${d.period.session.name} session${d.period.term ? ` · ${d.period.term.name}` : ''}` : 'No current academic session is set. Set one under Academic setup.'}
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {d.students && <Stat label="Active students" value={d.students.total} hint={`${d.students.male} boys · ${d.students.female} girls`} icon={<GraduationCap size={20} />} />}
        {d.staff && <Stat label="Staff" value={d.staff.teaching + d.staff.non_teaching} hint={`${d.staff.teaching} teaching · ${d.staff.non_teaching} non-teaching`} icon={<UserCog size={20} />} tone="gold" />}
        {d.attendance && <Stat label="Attendance today" value={d.attendance.today.rate === null ? 'Not marked' : `${d.attendance.today.rate}%`} hint={d.attendance.today.marked ? `${d.attendance.today.absent} absent · ${d.attendance.today.late} late` : 'No register submitted yet'} icon={<CalendarCheck size={20} />} tone="green" />}
        {d.classes && <Stat label="Classes" value={d.classes.active} hint={`${d.classes.arms} arms`} icon={<BookOpen size={20} />} />}
        {fin && <Stat label="Outstanding fees" value={money(fin.outstanding)} hint={`Collected this term: ${money(fin.collected_term)}`} icon={<Receipt size={20} />} tone="red" />}
        {fin && <Stat label="Revenue this month" value={money(fin.revenue_month)} hint={`Expenses: ${money(fin.expenses_month)}`} icon={<Wallet size={20} />} tone="green" />}
        {d.inventory && <Stat label="Low-stock items" value={d.inventory.low_stock} hint={<Link to="/inventory" className="text-royal hover:underline">View inventory</Link>} icon={<Package size={20} />} tone={d.inventory.low_stock ? 'red' : 'green'} />}
        {d.library && <Stat label="Overdue books" value={d.library.overdue} hint={`${d.library.on_loan} on loan`} icon={<AlertTriangle size={20} />} tone={d.library.overdue ? 'red' : 'green'} />}
      </div>

      {(fin?.awaiting_approval || d.pending_leave) ? (
        <div className="mt-4 flex flex-wrap gap-3 text-sm">
          {fin?.awaiting_approval ? <Link to="/fees/payments?status=awaiting_approval" className="rounded-lg bg-amber-50 px-3 py-2 text-amber-800 ring-1 ring-amber-200">{fin.awaiting_approval} payment(s) awaiting approval →</Link> : null}
          {d.pending_leave ? <Link to="/leave" className="rounded-lg bg-amber-50 px-3 py-2 text-amber-800 ring-1 ring-amber-200">{d.pending_leave} leave request(s) pending →</Link> : null}
        </div>
      ) : null}

      <div className="mt-6 grid gap-5 lg:grid-cols-2">
        {fin && (
          <Card title="Revenue vs expenses (6 months)">
            <div className="h-64">
              <ResponsiveContainer>
                <BarChart data={fin.monthly}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
                  <XAxis dataKey="month" tick={{ fontSize: 11 }} />
                  <YAxis tickFormatter={shortMoney} tick={{ fontSize: 11 }} width={56} />
                  <Tooltip formatter={(v) => money(Number(v))} />
                  <Legend />
                  <Bar dataKey="revenue" name="Revenue" fill="#246bce" radius={[3, 3, 0, 0]} />
                  <Bar dataKey="expenses" name="Expenses" fill="#e5a52b" radius={[3, 3, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>
        )}
        {d.attendance && (
          <Card title="Attendance rate (last 2 weeks)">
            {d.attendance.series.length === 0 ? <p className="py-10 text-center text-sm text-slate-500">No attendance has been recorded recently.</p> : (
              <div className="h-64">
                <ResponsiveContainer>
                  <LineChart data={d.attendance.series}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
                    <XAxis dataKey="date" tickFormatter={(v: string) => v.slice(5)} tick={{ fontSize: 11 }} />
                    <YAxis domain={[0, 100]} unit="%" tick={{ fontSize: 11 }} width={44} />
                    <Tooltip formatter={(v) => `${v}%`} labelFormatter={(l) => fmtDate(String(l))} />
                    <Line type="monotone" dataKey="rate" name="Attendance" stroke="#246bce" strokeWidth={2} dot={{ r: 3 }} />
                  </LineChart>
                </ResponsiveContainer>
              </div>
            )}
          </Card>
        )}
        {d.performance && d.performance.length > 0 && (
          <Card title="Average score by class (approved results)">
            <div className="h-64">
              <ResponsiveContainer>
                <BarChart data={d.performance}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
                  <XAxis dataKey="class" tick={{ fontSize: 11 }} />
                  <YAxis domain={[0, 100]} tick={{ fontSize: 11 }} width={36} />
                  <Tooltip />
                  <Bar dataKey="average" name="Average" fill="#102b50" radius={[3, 3, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>
        )}
        {d.upcoming_exams && (
          <Card title="Upcoming examinations">
            {d.upcoming_exams.length === 0 ? <p className="py-6 text-center text-sm text-slate-500">No upcoming exams are scheduled.</p> : (
              <Table head={['Date', 'Exam', 'Class']}>
                {d.upcoming_exams.map((e: any) => (
                  <tr key={e.id}><td className="px-3 py-2">{fmtDate(e.exam_date)}</td><td className="px-3 py-2">{e.title}</td><td className="px-3 py-2">{e.school_class?.name}</td></tr>
                ))}
              </Table>
            )}
          </Card>
        )}
        {fin && fin.recent_payments.length > 0 && (
          <Card title="Recent payments">
            <Table head={['Student', 'Amount', 'Method', 'Date']}>
              {fin.recent_payments.map((p: any) => (
                <tr key={p.id}>
                  <td className="px-3 py-2">{p.student?.first_name} {p.student?.last_name}</td>
                  <td className="px-3 py-2 font-medium">{money(p.amount)}</td>
                  <td className="px-3 py-2"><Badge value={p.method} /></td>
                  <td className="px-3 py-2">{fmtDate(p.paid_at)}</td>
                </tr>
              ))}
            </Table>
          </Card>
        )}
        <Card title="Announcements" action={<Link to="/announcements" className="text-xs text-royal hover:underline">All</Link>}>
          {d.announcements.length === 0 ? <p className="py-6 text-center text-sm text-slate-500">No announcements.</p> : (
            <ul className="divide-y divide-slate-100">
              {d.announcements.map((a: any) => (
                <li key={a.id} className="py-2.5">
                  <div className="flex items-center gap-2 text-sm font-medium text-slate-800">{a.title}{a.priority !== 'normal' && <Badge value={a.priority} />}</div>
                  <p className="line-clamp-2 text-xs text-slate-500">{a.body}</p>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </>
  )
}
