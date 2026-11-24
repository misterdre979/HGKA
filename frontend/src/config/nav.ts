import {
  BarChart3, BedDouble, BookOpen, Bus, CalendarCheck, CalendarDays, ClipboardList, CreditCard, FileText, GraduationCap, HeartPulse, LayoutDashboard,
  Library, Megaphone, MessageSquare, Package, Receipt, Settings, ShieldAlert, ShieldCheck, Users, UserCog, Wallet, Wrench, Baby, ScrollText, Database, Bell, Notebook, BadgeDollarSign,
  type LucideIcon,
} from 'lucide-react'

export interface NavItem { to: string; label: string; icon: LucideIcon; perms?: string[]; end?: boolean }
export interface NavGroup { title: string; items: NavItem[] }

/** Sidebar structure. An item shows when the user holds ANY listed permission. */
export const NAV: NavGroup[] = [
  { title: 'Overview', items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard, perms: ['dashboard.view'], end: true }] },
  {
    title: 'My family', items: [
      { to: '/portal', label: 'My children', icon: Baby, perms: ['own-children.view', 'own-record.view'] },
    ],
  },
  {
    title: 'People', items: [
      { to: '/students', label: 'Students', icon: GraduationCap, perms: ['students.view'] },
      { to: '/students/promote', label: 'Promotion', icon: ClipboardList, perms: ['students.promote'] },
      { to: '/guardians', label: 'Parents / guardians', icon: Users, perms: ['students.view'] },
      { to: '/staff', label: 'Staff', icon: UserCog, perms: ['staff.view'] },
      { to: '/leave', label: 'Leave', icon: CalendarDays, perms: ['leave.view', 'leave.apply'] },
    ],
  },
  {
    title: 'Academics', items: [
      { to: '/academics', label: 'Academic setup', icon: BookOpen, perms: ['academics.view'] },
      { to: '/attendance', label: 'Attendance', icon: CalendarCheck, perms: ['attendance.take', 'attendance.view'] },
      { to: '/results/entry', label: 'Score entry', icon: Notebook, perms: ['exams.enter-scores', 'exams.manage'] },
      { to: '/results/class', label: 'Results & report cards', icon: FileText, perms: ['exams.view'] },
      { to: '/exams', label: 'Exams & grading', icon: ClipboardList, perms: ['exams.view'] },
      { to: '/lesson-notes', label: 'Lesson notes', icon: Notebook, perms: ['lesson-notes.view'] },
      { to: '/teacher-reports', label: 'Teacher reports', icon: ScrollText, perms: ['teacher-reports.submit', 'teacher-reports.review'] },
    ],
  },
  {
    title: 'Finance', items: [
      { to: '/fees/invoices', label: 'Invoices', icon: Receipt, perms: ['fees.view'] },
      { to: '/fees/payments', label: 'Payments', icon: CreditCard, perms: ['fees.view'] },
      { to: '/fees/setup', label: 'Fee setup', icon: BadgeDollarSign, perms: ['fees.view'] },
      { to: '/expenses', label: 'Expenses', icon: Wallet, perms: ['finance.view', 'finance.expenses'] },
      { to: '/payroll', label: 'Payroll', icon: Wallet, perms: ['payroll.view'] },
    ],
  },
  {
    title: 'Operations', items: [
      { to: '/library', label: 'Library', icon: Library, perms: ['library.view'] },
      { to: '/inventory', label: 'Inventory & assets', icon: Package, perms: ['inventory.view'] },
      { to: '/transport', label: 'Transport', icon: Bus, perms: ['transport.view'] },
      { to: '/hostel', label: 'Hostel', icon: BedDouble, perms: ['hostel.view'] },
      { to: '/medical', label: 'Medical', icon: HeartPulse, perms: ['medical.view'] },
      { to: '/discipline', label: 'Discipline', icon: ShieldAlert, perms: ['discipline.view'] },
    ],
  },
  {
    title: 'Communication', items: [
      { to: '/announcements', label: 'Announcements', icon: Megaphone, perms: ['communication.view'] },
      { to: '/messages', label: 'Messages', icon: MessageSquare, perms: ['communication.view'] },
      { to: '/notifications', label: 'Notifications', icon: Bell },
    ],
  },
  {
    title: 'Insights', items: [{ to: '/reports', label: 'Reports', icon: BarChart3, perms: ['reports.view'] }],
  },
  {
    title: 'Administration', items: [
      { to: '/admin/users', label: 'Users', icon: Users, perms: ['users.view'] },
      { to: '/admin/roles', label: 'Roles & permissions', icon: ShieldCheck, perms: ['roles.view'] },
      { to: '/admin/settings', label: 'School settings', icon: Settings, perms: ['settings.view'] },
      { to: '/admin/audit-logs', label: 'Audit logs', icon: Wrench, perms: ['audit-logs.view'] },
      { to: '/admin/backups', label: 'Backups', icon: Database, perms: ['backups.manage'] },
    ],
  },
]
