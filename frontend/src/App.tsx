import { lazy, Suspense, type ReactNode } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { Button, Spinner } from '@/components/ui'
import { AuthProvider, useAuth } from '@/context/AuthContext'
import { ToastProvider } from '@/context/ToastContext'
import { AppLayout } from '@/layouts/AppLayout'
import { AccountPage, ForcedChangePasswordPage, ForgotPasswordPage, homeFor, LoginPage, ResetPasswordPage } from '@/pages/auth/AuthPages'

const lz = <T extends Record<string, any>>(f: () => Promise<T>, name: keyof T) => lazy(() => f().then((m) => ({ default: m[name] as React.ComponentType })))

const Dashboard = lz(() => import('@/pages/Dashboard'), 'Dashboard')
const StudentsPage = lz(() => import('@/pages/people/Students'), 'StudentsPage')
const AdmissionPage = lz(() => import('@/pages/people/Students'), 'AdmissionPage')
const StudentProfilePage = lz(() => import('@/pages/people/Students'), 'StudentProfilePage')
const StaffPage = lz(() => import('@/pages/people/Staff'), 'StaffPage')
const StaffCreatePage = lz(() => import('@/pages/people/Staff'), 'StaffCreatePage')
const StaffProfilePage = lz(() => import('@/pages/people/Staff'), 'StaffProfilePage')
const LeavePage = lz(() => import('@/pages/people/Staff'), 'LeavePage')
const AcademicSetupPage = lz(() => import('@/pages/academics/Academics'), 'AcademicSetupPage')
const ExamsPage = lz(() => import('@/pages/academics/Academics'), 'ExamsPage')
const AttendancePage = lz(() => import('@/pages/academics/Academics'), 'AttendancePage')
const StaffAttendancePage = lz(() => import('@/pages/academics/Academics'), 'StaffAttendancePage')
const ScoreEntryPage = lz(() => import('@/pages/academics/Results'), 'ScoreEntryPage')
const ClassResultsPage = lz(() => import('@/pages/academics/Results'), 'ClassResultsPage')
const PromotionPage = lz(() => import('@/pages/academics/Promotion'), 'PromotionPage')
const InvoicesPage = lz(() => import('@/pages/finance/Finance'), 'InvoicesPage')
const PaymentsPage = lz(() => import('@/pages/finance/Finance'), 'PaymentsPage')
const FeeSetupPage = lz(() => import('@/pages/finance/Finance'), 'FeeSetupPage')
const ExpensesPage = lz(() => import('@/pages/finance/Finance'), 'ExpensesPage')
const PayrollPage = lz(() => import('@/pages/finance/Payroll'), 'PayrollPage')
const PortalHome = lz(() => import('@/pages/portal/Portal'), 'PortalHome')
const PortalChild = lz(() => import('@/pages/portal/Portal'), 'PortalChild')
const PaymentCallbackPage = lz(() => import('@/pages/portal/Portal'), 'PaymentCallbackPage')
const LibraryPage = lz(() => import('@/pages/operations/Operations'), 'LibraryPage')
const InventoryPage = lz(() => import('@/pages/operations/Operations'), 'InventoryPage')
const TransportPage = lz(() => import('@/pages/operations/Operations'), 'TransportPage')
const HostelPage = lz(() => import('@/pages/operations/Operations'), 'HostelPage')
const MedicalPage = lz(() => import('@/pages/operations/Operations'), 'MedicalPage')
const DisciplinePage = lz(() => import('@/pages/operations/Operations'), 'DisciplinePage')
const TeacherReportsPage = lz(() => import('@/pages/operations/Operations'), 'TeacherReportsPage')
const GuardiansPage = lz(() => import('@/pages/operations/Operations'), 'GuardiansPage')
const LessonNotesPage = lz(() => import('@/pages/operations/Operations'), 'LessonNotesPage')
const AnnouncementsPage = lz(() => import('@/pages/Communication'), 'AnnouncementsPage')
const MessagesPage = lz(() => import('@/pages/Communication'), 'MessagesPage')
const NotificationsPage = lz(() => import('@/pages/Communication'), 'NotificationsPage')
const ReportsPage = lz(() => import('@/pages/Reports'), 'ReportsPage')
const UsersPage = lz(() => import('@/pages/admin/Admin'), 'UsersPage')
const RolesPage = lz(() => import('@/pages/admin/Admin'), 'RolesPage')
const SettingsPage = lz(() => import('@/pages/admin/Admin'), 'SettingsPage')
const BackupsPage = lz(() => import('@/pages/admin/Admin'), 'BackupsPage')
const AuditLogsPage = lz(() => import('@/pages/admin/Admin'), 'AuditLogsPage')

function Forbidden() {
  return <div className="py-16 text-center"><h1 className="text-xl font-bold text-navy">Access denied</h1><p className="mt-1 text-sm text-slate-500">Your role does not include access to this page.</p></div>
}

/** Client-side gate. This is a convenience only: the API enforces the same permissions. */
function Guard({ perms, children }: { perms?: string[]; children: ReactNode }) {
  const { can } = useAuth()
  return perms && !can(...perms) ? <Forbidden /> : <>{children}</>
}

function Protected() {
  const { user, loading } = useAuth()
  if (loading) return <div className="grid min-h-screen place-items-center"><Spinner /></div>
  if (!user) return <Navigate to="/login" replace />
  if (user.must_change_password) return <Navigate to="/change-password" replace />
  return <AppLayout />
}

function Home() {
  const { can } = useAuth()
  const dest = homeFor(can)
  return dest === '/' ? <Dashboard /> : <Navigate to={dest} replace />
}

const g = (perms: string[] | undefined, el: ReactNode) => <Guard perms={perms}>{el}</Guard>

export default function App() {
  return (
    <BrowserRouter>
      <ToastProvider>
        <AuthProvider>
          <Suspense fallback={<Spinner />}>
            <Routes>
              <Route path="/login" element={<LoginPage />} />
              <Route path="/forgot-password" element={<ForgotPasswordPage />} />
              <Route path="/reset-password" element={<ResetPasswordPage />} />
              <Route path="/change-password" element={<ForcedChangePasswordPage />} />
              <Route element={<Protected />}>
                <Route index element={<Home />} />
                <Route path="account" element={<AccountPage />} />
                <Route path="portal" element={g(['own-children.view', 'own-record.view'], <PortalHome />)} />
                <Route path="portal/:id" element={g(['own-children.view', 'own-record.view'], <PortalChild />)} />
                <Route path="payment/callback" element={<PaymentCallbackPage />} />

                <Route path="students" element={g(['students.view'], <StudentsPage />)} />
                <Route path="students/new" element={g(['students.create'], <AdmissionPage />)} />
                <Route path="students/promote" element={g(['students.promote'], <PromotionPage />)} />
                <Route path="students/:id" element={g(['students.view'], <StudentProfilePage />)} />
                <Route path="guardians" element={g(['students.view'], <GuardiansPage />)} />
                <Route path="staff" element={g(['staff.view'], <StaffPage />)} />
                <Route path="staff/new" element={g(['staff.create'], <StaffCreatePage />)} />
                <Route path="staff/:id" element={g(['staff.view'], <StaffProfilePage />)} />
                <Route path="leave" element={g(['leave.view', 'leave.apply'], <LeavePage />)} />

                <Route path="academics" element={g(['academics.view'], <AcademicSetupPage />)} />
                <Route path="attendance" element={g(['attendance.take', 'attendance.view'], <AttendancePage />)} />
                <Route path="attendance/staff" element={g(['attendance.staff'], <StaffAttendancePage />)} />
                <Route path="results/entry" element={g(['exams.enter-scores', 'exams.manage'], <ScoreEntryPage />)} />
                <Route path="results/class" element={g(['exams.view'], <ClassResultsPage />)} />
                <Route path="exams" element={g(['exams.view'], <ExamsPage />)} />
                <Route path="lesson-notes" element={g(['lesson-notes.view'], <LessonNotesPage />)} />
                <Route path="teacher-reports" element={g(['teacher-reports.submit', 'teacher-reports.review'], <TeacherReportsPage />)} />

                <Route path="fees/invoices" element={g(['fees.view'], <InvoicesPage />)} />
                <Route path="fees/payments" element={g(['fees.view'], <PaymentsPage />)} />
                <Route path="fees/setup" element={g(['fees.view'], <FeeSetupPage />)} />
                <Route path="expenses" element={g(['finance.view', 'finance.expenses'], <ExpensesPage />)} />
                <Route path="payroll" element={g(['payroll.view'], <PayrollPage />)} />

                <Route path="library" element={g(['library.view'], <LibraryPage />)} />
                <Route path="inventory" element={g(['inventory.view'], <InventoryPage />)} />
                <Route path="transport" element={g(['transport.view'], <TransportPage />)} />
                <Route path="hostel" element={g(['hostel.view'], <HostelPage />)} />
                <Route path="medical" element={g(['medical.view'], <MedicalPage />)} />
                <Route path="discipline" element={g(['discipline.view'], <DisciplinePage />)} />

                <Route path="announcements" element={<AnnouncementsPage />} />
                <Route path="messages" element={g(['communication.view'], <MessagesPage />)} />
                <Route path="notifications" element={<NotificationsPage />} />
                <Route path="reports" element={g(['reports.view'], <ReportsPage />)} />

                <Route path="admin/users" element={g(['users.view'], <UsersPage />)} />
                <Route path="admin/roles" element={g(['roles.view'], <RolesPage />)} />
                <Route path="admin/settings" element={g(['settings.view'], <SettingsPage />)} />
                <Route path="admin/audit-logs" element={g(['audit-logs.view'], <AuditLogsPage />)} />
                <Route path="admin/backups" element={g(['backups.manage'], <BackupsPage />)} />
                <Route path="*" element={<div className="py-16 text-center"><h1 className="text-xl font-bold text-navy">Page not found</h1><a href="/"><Button className="mt-4">Go home</Button></a></div>} />
              </Route>
            </Routes>
          </Suspense>
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  )
}
