"""HGKA schema spec. Single source of truth for: migrations, Eloquent models, the validation
registry (App\\Support\\Resources), frontend resource configs and the role/permission seeder.
Run `python3 tools/generate.py` after editing."""

# ---------------------------------------------------------------- permissions
CATALOG = {
    'dashboard': ['view'],
    'users': ['view', 'create', 'update', 'delete'],
    'roles': ['view', 'manage'],
    'settings': ['view', 'update'],
    'audit-logs': ['view'],
    'backups': ['manage'],
    'students': ['view', 'create', 'update', 'delete', 'promote'],
    'staff': ['view', 'create', 'update', 'delete'],
    'leave': ['view', 'apply', 'approve'],
    'academics': ['view', 'manage'],
    'lesson-notes': ['view', 'manage'],
    'exams': ['view', 'manage', 'enter-scores', 'approve-results'],
    'attendance': ['view', 'take', 'report', 'staff'],
    'fees': ['view', 'manage', 'record-payment', 'approve-payment'],
    'finance': ['view', 'reports', 'expenses'],
    'library': ['view', 'manage'],
    'inventory': ['view', 'manage'],
    'transport': ['view', 'manage'],
    'hostel': ['view', 'manage'],
    'payroll': ['view', 'manage'],
    'communication': ['view', 'send'],
    'medical': ['view', 'manage'],
    'discipline': ['view', 'manage'],
    'reports': ['view', 'export'],
    'teacher-reports': ['submit', 'review'],
    'own-children': ['view'],
    'own-record': ['view'],
}

ROLES = {
    'super-admin': ('Super Administrator', 'Full system control', ['*']),
    'school-admin': ('School Administrator', 'Runs day-to-day administration', [
        'dashboard.*', 'users.*', 'roles.view', 'settings.*', 'audit-logs.view', 'students.*', 'staff.*', 'leave.*',
        'academics.*', 'lesson-notes.*', 'exams.view', 'attendance.*', 'communication.*', 'reports.*', 'library.*',
        'inventory.*', 'transport.*', 'hostel.*', 'fees.view', 'finance.view', 'payroll.view', 'medical.view',
        'teacher-reports.review']),
    'principal': ('Principal', 'Head of the school', [
        'dashboard.*', 'users.view', 'students.*', 'staff.view', 'leave.view', 'leave.approve', 'academics.*',
        'lesson-notes.view', 'exams.*', 'attendance.*', 'fees.view', 'finance.view', 'finance.reports',
        'communication.*', 'medical.view', 'discipline.*', 'reports.*', 'audit-logs.view', 'payroll.view',
        'teacher-reports.review', 'library.view', 'inventory.view', 'transport.view', 'hostel.view']),
    'vice-principal': ('Vice Principal', 'Deputy head', [
        'dashboard.*', 'students.view', 'students.update', 'students.promote', 'staff.view', 'leave.view',
        'academics.*', 'lesson-notes.view', 'exams.*', 'attendance.*', 'communication.*', 'discipline.*',
        'reports.view', 'teacher-reports.review']),
    'head-of-department': ('Head of Department', 'Leads an academic department', [
        'dashboard.view', 'students.view', 'staff.view', 'academics.view', 'lesson-notes.*', 'exams.view',
        'exams.enter-scores', 'attendance.view', 'attendance.take', 'attendance.report', 'communication.view',
        'reports.view', 'leave.apply', 'teacher-reports.submit']),
    'teacher': ('Teacher', 'Class and subject teacher', [
        'dashboard.view', 'students.view', 'academics.view', 'lesson-notes.*', 'exams.view', 'exams.enter-scores',
        'attendance.view', 'attendance.take', 'communication.view', 'leave.apply', 'teacher-reports.submit']),
    'accountant': ('Accountant', 'Manages school finances', [
        'dashboard.view', 'students.view', 'academics.view', 'fees.view', 'fees.manage', 'fees.record-payment',
        'finance.*', 'reports.*', 'payroll.view', 'communication.view', 'leave.apply']),
    'bursar': ('Bursar', 'Oversees fee collection and approvals', [
        'dashboard.view', 'students.view', 'academics.view', 'fees.*', 'finance.*', 'reports.*', 'payroll.*',
        'communication.view', 'leave.apply']),
    'librarian': ('Librarian', 'Runs the library', [
        'dashboard.view', 'students.view', 'staff.view', 'library.*', 'reports.view', 'communication.view',
        'leave.apply']),
    'school-nurse': ('School Nurse', 'Student health and medical records', [
        'dashboard.view', 'students.view', 'academics.view', 'medical.*', 'communication.view', 'leave.apply']),
    'transport-manager': ('Transport Manager', 'Vehicles, routes and drivers', [
        'dashboard.view', 'students.view', 'staff.view', 'academics.view', 'transport.*', 'reports.view',
        'communication.view', 'leave.apply']),
    'student': ('Student', 'Enrolled student', ['own-record.view', 'communication.view']),
    'parent': ('Parent/Guardian', 'Parent or guardian of enrolled students', ['own-children.view', 'communication.view']),
    'hr-officer': ('Human Resources Officer', 'Staff records, leave and payroll', [
        'dashboard.view', 'staff.*', 'leave.*', 'payroll.*', 'reports.view', 'communication.view', 'academics.view',
        'attendance.staff', 'attendance.view', 'attendance.report']),
}

# ---------------------------------------------------------------- field helpers
def f(name, type, **kw):
    d = {'name': name, 'type': type}
    d.update(kw)
    return d

S = lambda n, **k: f(n, 'string', **k)
X = lambda n, **k: f(n, 'text', **k)
I = lambda n, **k: f(n, 'int', **k)
D = lambda n, **k: f(n, 'decimal', **k)
DT = lambda n, **k: f(n, 'date', **k)
TM = lambda n, **k: f(n, 'time', **k)
TS = lambda n, **k: f(n, 'datetime', **k)
B = lambda n, **k: f(n, 'bool', **k)
E = lambda n, vals, **k: f(n, 'enum', vals=vals, **k)
J = lambda n, **k: f(n, 'json', **k)
K = lambda n, ref, **k: f(n, 'fk', ref=ref, **k)

HID = dict(frm=False)  # system-managed column: never accepted from generic forms


def R(key, model, table, module, label, fields, **kw):
    r = dict(key=key, model=model, table=table, module=module, label=label, fields=fields,
             group=kw.pop('group', 'misc'))
    r.update(kw)
    return r


GENDER = ['male', 'female']
LEVELS = ['nursery', 'primary', 'secondary']
DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']

RESOURCES = [
    # ------------------------------------------------------------ core (tables hand-written in core migration)
    R('users', 'User', 'users', 'users', 'Users', [], migrate=False, model_file=False, crud=False,
      lbl=['name'], group='core'),
    R('academic-sessions', 'AcademicSession', 'academic_sessions', 'academics', 'Academic sessions', [
        S('name', req=1, uniq=1, lst=1, max=20, help='e.g. 2026/2027'),
        DT('starts_on', req=1, lst=1), DT('ends_on', req=1, lst=1), B('is_current', lst=1, frm=False),
    ], migrate=False, lbl=['name'], order=('starts_on', 'desc'), hooks='AcademicHooks', group='core'),
    R('terms', 'Term', 'terms', 'academics', 'Terms', [
        K('academic_session_id', 'academic-sessions', req=1, lst=1, flt=1, cascade=1),
        S('name', req=1, lst=1, max=40, help='e.g. First Term'), I('sequence', req=1, lst=1, minv=1, maxv=3),
        DT('starts_on', req=1, lst=1), DT('ends_on', req=1, lst=1), B('is_current', lst=1, frm=False),
    ], migrate=False, lbl=['name'], order=('starts_on', 'desc'), hooks='AcademicHooks', group='core',
      uniq_together=[('academic_session_id', 'sequence')]),
    R('classes', 'SchoolClass', 'school_classes', 'academics', 'Classes', [
        S('name', req=1, uniq=1, lst=1, max=60), E('level', LEVELS, req=1, lst=1, flt=1),
        I('sequence', req=1, lst=1, help='Promotion order (1 = lowest)'), B('is_active', lst=1, default=True),
    ], migrate=False, lbl=['name'], order=('sequence', 'asc'), group='core'),
    R('class-arms', 'ClassArm', 'class_arms', 'academics', 'Class arms', [
        K('school_class_id', 'classes', req=1, lst=1, flt=1, cascade=1), S('name', req=1, lst=1, max=20),
        I('capacity'), K('form_teacher_id', 'staff', lst=1, late=1, help='Form teacher (can take attendance)'),
    ], migrate=False, lbl=['name'], order=('school_class_id', 'asc'), group='people',
      uniq_together=[('school_class_id', 'name')], lbl_fmt='{0}', lbl_rel='school_class'),
    R('settings', 'SchoolSetting', 'school_settings', 'settings', 'Settings', [], migrate=False, model_file=False,
      crud=False, group='core'),
    R('audit-logs', 'AuditLog', 'audit_logs', 'audit-logs', 'Audit logs', [
        K('user_id', 'users', lst=1, rel=1), S('action', lst=1, flt=0), S('auditable_type', lst=1),
        I('auditable_id', lst=1), J('old_values'), J('new_values'), S('ip_address', lst=1, max=45),
        S('user_agent'),
    ], migrate=False, readonly=True, ts=False, order=('id', 'desc'), search=['action', 'auditable_type'],
      group='core'),

    # ------------------------------------------------------------ people (phase 4)
    R('guardians', 'Guardian', 'guardians', 'students', 'Guardians', [
        K('user_id', 'users', **HID), S('first_name', req=1, lst=1), S('last_name', req=1, lst=1),
        S('relationship', req=1, lst=1, max=60), S('phone', req=1, lst=1, max=30), S('email'), S('occupation'),
        X('address'),
    ], group='people', lbl=['first_name', 'last_name'], search=['first_name', 'last_name', 'phone', 'email']),
    R('departments', 'Department', 'departments', 'staff', 'Departments', [
        S('name', req=1, uniq=1, lst=1), X('description', lst=1),
        K('head_staff_id', 'staff', late=1, lst=1, help='Head of department'),
    ], group='people', lbl=['name']),
    R('designations', 'Designation', 'designations', 'staff', 'Designations', [
        S('name', req=1, uniq=1, lst=1), K('department_id', 'departments', lst=1, flt=1),
    ], group='people', lbl=['name']),
    R('staff', 'Staff', 'staff', 'staff', 'Staff', [
        K('user_id', 'users', uniq=1, **HID), S('staff_no', uniq=1, lst=1, frm=False),
        S('first_name', req=1, lst=1), S('last_name', req=1, lst=1), S('other_name'),
        E('gender', GENDER, req=1, flt=1), DT('dob', label='Date of birth'), S('phone', lst=1, max=30),
        S('email', lst=1), X('address'),
        E('category', ['teaching', 'non_teaching'], req=1, lst=1, flt=1),
        K('department_id', 'departments', lst=1, flt=1), K('designation_id', 'designations', lst=1),
        S('qualification'), E('employment_type', ['permanent', 'contract', 'part_time'], default='permanent'),
        DT('employment_date'),
        E('status', ['active', 'on_leave', 'suspended', 'resigned', 'terminated'], default='active', lst=1, flt=1),
        S('bank_name'), S('account_number', max=20), S('account_name'),
        S('emergency_contact_name'), S('emergency_contact_phone', max=30), S('photo_path', **HID),
    ], group='people', lbl=['first_name', 'last_name'], lbl_fmt='{0} {1}',
      search=['first_name', 'last_name', 'staff_no', 'phone', 'email'], order=('first_name', 'asc'),
      pv=['staff.view'], custom_create=True),
    R('students', 'Student', 'students', 'students', 'Students', [
        S('admission_no', uniq=1, lst=1, frm=False), S('first_name', req=1, lst=1), S('last_name', req=1, lst=1),
        S('other_name'), E('gender', GENDER, req=1, lst=1, flt=1), DT('dob', label='Date of birth'),
        S('nationality', default='Nigerian'), S('state_of_origin'), S('religion', max=60), X('address'),
        S('phone', max=30), S('email'),
        K('school_class_id', 'classes', req=1, lst=1, flt=1),
        K('class_arm_id', 'class-arms', lst=1, dep='school_class_id', flt=1), DT('admission_date'),
        S('previous_school'),
        E('status', ['active', 'graduated', 'transferred', 'withdrawn', 'suspended'], default='active', lst=1,
          flt=1),
        S('photo_path', **HID), K('user_id', 'users', uniq=1, **HID),
    ], group='people', lbl=['first_name', 'last_name', 'admission_no'], lbl_fmt='{0} {1} ({2})',
      search=['first_name', 'last_name', 'admission_no', 'phone'], order=('first_name', 'asc'),
      custom_create=True),
    R('guardian-student', 'GuardianStudent', 'guardian_student', 'students', 'Guardian links', [
        K('guardian_id', 'guardians', req=1, cascade=1), K('student_id', 'students', req=1, cascade=1),
        B('is_primary', default=False),
    ], group='people', crud=False, ts=False, uniq_together=[('guardian_id', 'student_id')]),
    R('leave-requests', 'LeaveRequest', 'leave_requests', 'leave', 'Leave requests', [
        K('staff_id', 'staff', req=1, lst=1, cascade=1, flt=1),
        E('type', ['annual', 'sick', 'maternity', 'paternity', 'casual', 'study', 'other'], req=1, lst=1),
        DT('starts_on', req=1, lst=1), DT('ends_on', req=1, lst=1), I('days', lst=1), X('reason'),
        E('status', ['pending', 'approved', 'rejected', 'cancelled'], default='pending', lst=1, flt=1),
        K('decided_by', 'users'), TS('decided_at'), X('decision_note'),
    ], group='people', crud=False),
    R('staff-performance', 'StaffPerformanceRecord', 'staff_performance_records', 'staff', 'Staff performance', [
        K('staff_id', 'staff', req=1, lst=1, cascade=1, flt=1), K('term_id', 'terms', lst=1, flt=1),
        I('rating', req=1, lst=1, minv=1, maxv=5, help='1 (poor) to 5 (excellent)'),
        X('strengths'), X('areas_for_improvement'), X('comments'), K('reviewed_by', 'users', **HID),
    ], group='people', pv=['staff.view'], pw=['staff.update'], creator='reviewed_by',
      lbl=['id'], search=['comments']),
    R('attachments', 'Attachment', 'attachments', 'students', 'Attachments', [
        S('attachable_type', req=1, max=60, idx=1), I('attachable_id', req=1, idx=1), S('kind', max=60),
        S('title'), S('original_name'), S('path', max=500), S('mime', max=100), I('size'),
        K('uploaded_by', 'users'),
    ], group='people', crud=False),
    R('student-promotions', 'StudentPromotion', 'student_promotions', 'students', 'Promotions', [
        K('student_id', 'students', req=1, cascade=1),
        K('from_class_id', 'classes'), K('to_class_id', 'classes'),
        K('from_arm_id', 'class-arms'), K('to_arm_id', 'class-arms'),
        K('academic_session_id', 'academic-sessions'),
        E('type', ['promoted', 'repeated', 'graduated', 'transferred'], req=1), K('promoted_by', 'users'),
    ], group='people', crud=False),

    # ------------------------------------------------------------ academics (phase 5)
    R('subjects', 'Subject', 'subjects', 'academics', 'Subjects', [
        S('name', req=1, lst=1), S('code', uniq=1, lst=1, max=20), E('level', LEVELS + ['all'], default='all', lst=1,
                                                                       flt=1),
        B('is_core', lst=1), X('description'),
    ], group='academics', lbl=['name'], search=['name', 'code'], order=('name', 'asc')),
    R('class-subjects', 'ClassSubject', 'class_subjects', 'academics', 'Subject allocation', [
        K('school_class_id', 'classes', req=1, lst=1, flt=1, cascade=1),
        K('subject_id', 'subjects', req=1, lst=1, cascade=1),
    ], group='academics', uniq_together=[('school_class_id', 'subject_id')], lbl=['id'], ts=False,
      order=('school_class_id', 'asc')),
    R('teacher-assignments', 'TeacherAssignment', 'teacher_assignments', 'academics', 'Teacher allocation', [
        K('staff_id', 'staff', req=1, lst=1, flt=1, cascade=1), K('subject_id', 'subjects', req=1, lst=1, flt=1),
        K('class_arm_id', 'class-arms', req=1, lst=1, flt=1, cascade=1),
    ], group='academics', uniq_together=[('staff_id', 'subject_id', 'class_arm_id')], lbl=['id'],
      order=('staff_id', 'asc')),
    R('timetable', 'TimetableEntry', 'timetable_entries', 'academics', 'Timetable', [
        K('class_arm_id', 'class-arms', req=1, lst=1, flt=1, cascade=1), K('subject_id', 'subjects', req=1, lst=1),
        K('staff_id', 'staff', lst=1, flt=1), E('day_of_week', DAYS, req=1, lst=1, flt=1),
        TM('start_time', req=1, lst=1), TM('end_time', req=1, lst=1), S('room', max=60),
    ], group='academics', hooks='TimetableHooks', lbl=['id'], order=('day_of_week', 'asc')),
    R('lesson-notes', 'LessonNote', 'lesson_notes', 'lesson-notes', 'Lesson notes & plans', [
        S('title', req=1, lst=1), E('type', ['note', 'plan'], default='note', lst=1, flt=1),
        K('subject_id', 'subjects', req=1, lst=1, flt=1), K('school_class_id', 'classes', req=1, lst=1, flt=1),
        K('term_id', 'terms', lst=1, flt=1), K('staff_id', 'staff', **HID), I('week', minv=1, maxv=20),
        X('content'), K('created_by', 'users', **HID),
    ], group='academics', creator='created_by', own=dict(col='created_by', admin='academics.manage', list=False),
      lbl=['title'], search=['title'], order=('id', 'desc'), hooks='LessonNoteHooks'),
    R('calendar-events', 'CalendarEvent', 'calendar_events', 'academics', 'Academic calendar', [
        S('title', req=1, lst=1), E('type', ['holiday', 'exam', 'event', 'meeting', 'term_start', 'term_end'],
                                    default='event', lst=1, flt=1),
        DT('starts_on', req=1, lst=1), DT('ends_on', lst=1), K('term_id', 'terms', flt=1), X('description'),
    ], group='academics', pv=['academics.view', 'own-children.view', 'own-record.view'], lbl=['title'],
      order=('starts_on', 'asc')),

    # ------------------------------------------------------------ exams (phase 5)
    R('grading-scales', 'GradingScaleItem', 'grading_scale_items', 'exams', 'Grading scale', [
        E('level', LEVELS, req=1, lst=1, flt=1), S('grade', req=1, lst=1, max=5),
        I('min_score', req=1, lst=1, minv=0, maxv=100), I('max_score', req=1, lst=1, minv=0, maxv=100),
        S('remark', lst=1, max=60), D('points', prec=5),
    ], group='exams', uniq_together=[('level', 'grade')], lbl=['grade'], order=('min_score', 'desc'),
      pv=['exams.view']),
    R('exams', 'Exam', 'exams', 'exams', 'Examinations', [
        S('title', req=1, lst=1), E('type', ['ca', 'test', 'exam'], default='exam', lst=1, flt=1),
        K('term_id', 'terms', req=1, lst=1, flt=1), K('school_class_id', 'classes', lst=1, flt=1),
        K('subject_id', 'subjects', lst=1), I('max_score', default=100), DT('exam_date', req=1, lst=1),
        TM('start_time'), S('venue'),
        E('status', ['scheduled', 'ongoing', 'completed'], default='scheduled', lst=1, flt=1), X('instructions'),
    ], group='exams', lbl=['title'], search=['title'], order=('exam_date', 'asc'),
      pv=['exams.view', 'own-children.view', 'own-record.view']),
    R('results', 'Result', 'results', 'exams', 'Results', [
        K('student_id', 'students', req=1, cascade=1), K('term_id', 'terms', req=1), K('subject_id', 'subjects', req=1),
        K('school_class_id', 'classes', req=1), K('class_arm_id', 'class-arms'),
        D('ca_score', prec=6, default=0), D('exam_score', prec=6, default=0), D('total', prec=6, default=0),
        S('grade', max=5), S('remark', max=60), I('position'),
        E('status', ['draft', 'submitted', 'approved'], default='draft', idx=1),
        K('entered_by', 'users'), K('approved_by', 'users'), TS('approved_at'),
    ], group='exams', crud=False, uniq_together=[('student_id', 'term_id', 'subject_id')]),
    R('result-summaries', 'ResultSummary', 'result_summaries', 'exams', 'Result summaries', [
        K('student_id', 'students', req=1, cascade=1), K('term_id', 'terms', req=1),
        K('school_class_id', 'classes', req=1), K('class_arm_id', 'class-arms'),
        D('total_score', prec=10, default=0), D('average', prec=6, default=0), I('position'), I('class_size'),
        I('subjects_count', default=0), X('teacher_comment'), X('principal_comment'),
        E('status', ['draft', 'approved'], default='draft'),
    ], group='exams', crud=False, uniq_together=[('student_id', 'term_id')]),

    # ------------------------------------------------------------ attendance (phase 6)
    R('student-attendance', 'StudentAttendance', 'student_attendances', 'attendance', 'Student attendance', [
        K('student_id', 'students', req=1, cascade=1), K('class_arm_id', 'class-arms'),
        K('school_class_id', 'classes'), DT('date', req=1, idx=1),
        E('status', ['present', 'absent', 'late', 'excused'], req=1), S('remark'), K('marked_by', 'users'),
    ], group='attendance', crud=False, uniq_together=[('student_id', 'date')]),
    R('staff-attendance', 'StaffAttendance', 'staff_attendances', 'attendance', 'Staff attendance', [
        K('staff_id', 'staff', req=1, cascade=1), DT('date', req=1, idx=1),
        E('status', ['present', 'absent', 'late', 'excused'], req=1), TM('check_in'), TM('check_out'),
        S('remark'), K('marked_by', 'users'),
    ], group='attendance', crud=False, uniq_together=[('staff_id', 'date')]),

    # ------------------------------------------------------------ finance (phase 7)
    R('fee-structures', 'FeeStructure', 'fee_structures', 'fees', 'Fee structure', [
        S('name', req=1, lst=1), E('category', ['tuition', 'books', 'uniform', 'exam', 'pta', 'development', 'other'],
                                   default='tuition', lst=1, flt=1),
        K('term_id', 'terms', req=1, lst=1, flt=1),
        K('school_class_id', 'classes', lst=1, flt=1, help='Leave empty to charge every class'),
        D('amount', req=1, lst=1, money=1), B('is_optional', lst=1), X('description'),
    ], group='finance', lbl=['name'], search=['name'], order=('id', 'desc')),
    R('invoices', 'Invoice', 'invoices', 'fees', 'Invoices', [
        S('number', uniq=1, lst=1), K('student_id', 'students', req=1, lst=1, flt=1),
        K('term_id', 'terms', req=1, lst=1, flt=1), D('total', default=0, lst=1, money=1),
        D('discount', default=0, money=1), D('amount_paid', default=0, lst=1, money=1),
        D('balance', default=0, lst=1, money=1),
        E('status', ['unpaid', 'partial', 'paid', 'cancelled'], default='unpaid', lst=1, flt=1, idx=1),
        DT('due_date', lst=1), X('notes'), K('created_by', 'users'),
    ], group='finance', crud=False, uniq_together=[('student_id', 'term_id')], lbl=['number']),
    R('invoice-items', 'InvoiceItem', 'invoice_items', 'fees', 'Invoice items', [
        K('invoice_id', 'invoices', req=1, cascade=1), S('description', req=1), S('category', max=40),
        D('amount', req=1, money=1),
    ], group='finance', crud=False),
    R('payments', 'Payment', 'payments', 'fees', 'Payments', [
        S('reference', req=1, uniq=1, lst=1), S('receipt_no', uniq=1, lst=1),
        K('invoice_id', 'invoices', req=1, lst=1), K('student_id', 'students', req=1, lst=1),
        E('method', ['cash', 'bank_transfer', 'pos', 'cheque', 'paystack', 'flutterwave'], req=1, lst=1, flt=1),
        D('amount', req=1, lst=1, money=1),
        E('status', ['pending', 'awaiting_approval', 'successful', 'failed', 'rejected'], default='pending', lst=1,
          flt=1, idx=1),
        S('gateway_reference'), J('gateway_response'), S('payer_name'), S('payer_email'), S('bank_name'),
        S('cheque_no'), X('notes'), TS('paid_at'), K('recorded_by', 'users'), K('approved_by', 'users'),
        TS('approved_at'),
    ], group='finance', crud=False),
    R('scholarships', 'Scholarship', 'scholarships', 'fees', 'Scholarships & discounts', [
        K('student_id', 'students', req=1, lst=1, flt=1), S('name', req=1, lst=1),
        E('type', ['percent', 'fixed'], req=1, lst=1), D('value', req=1, lst=1),
        K('term_id', 'terms', lst=1, help='Leave empty to apply to every term'),
        B('is_active', default=True, lst=1), X('notes'),
    ], group='finance', lbl=['name'], search=['name'], order=('id', 'desc')),
    R('expenses', 'Expense', 'expenses', 'finance', 'Expenses', [
        S('title', req=1, lst=1), S('category', req=1, lst=1, max=80), D('amount', req=1, lst=1, money=1),
        DT('expense_date', req=1, lst=1, idx=1),
        E('payment_method', ['cash', 'bank_transfer', 'pos', 'cheque'], default='cash'),
        S('reference'), X('description'), K('recorded_by', 'users', **HID),
    ], group='finance', pv=['finance.view'], pw=['finance.expenses'], creator='recorded_by', lbl=['title'],
      search=['title', 'category'], order=('expense_date', 'desc')),
    R('payment-events', 'PaymentEvent', 'payment_events', 'fees', 'Payment events', [
        S('provider', req=1, max=30), S('event', max=80), S('reference'), J('payload'),
        B('signature_valid', default=False), B('processed', default=False),
    ], group='finance', crud=False),

    # ------------------------------------------------------------ library (phase 9)
    R('book-categories', 'BookCategory', 'book_categories', 'library', 'Book categories', [
        S('name', req=1, uniq=1, lst=1), X('description', lst=1),
    ], group='library', lbl=['name']),
    R('books', 'Book', 'books', 'library', 'Books', [
        S('isbn', max=30), S('title', req=1, lst=1), S('author', lst=1), S('publisher'),
        K('book_category_id', 'book-categories', lst=1, flt=1), S('edition', max=40), I('published_year'),
        S('shelf_location', max=60), I('copies_total', req=1, lst=1, minv=0, default=1),
        I('copies_available', lst=1, frm=False, default=1),
    ], group='library', lbl=['title'], search=['title', 'author', 'isbn'], order=('title', 'asc'),
      hooks='BookHooks'),
    R('book-loans', 'BookLoan', 'book_loans', 'library', 'Book loans', [
        K('book_id', 'books', req=1, cascade=1), E('borrower_type', ['student', 'staff'], req=1),
        K('student_id', 'students'), K('staff_id', 'staff'), DT('issued_on', req=1), DT('due_on', req=1, idx=1),
        DT('returned_on'), E('status', ['borrowed', 'returned', 'lost'], default='borrowed', idx=1),
        D('fine', default=0, money=1), K('issued_by', 'users'), X('notes'),
    ], group='library', crud=False),

    # ------------------------------------------------------------ inventory (phase 9)
    R('suppliers', 'Supplier', 'suppliers', 'inventory', 'Suppliers', [
        S('name', req=1, lst=1), S('contact_person', lst=1), S('phone', lst=1, max=30), S('email'), X('address'),
    ], group='inventory', lbl=['name'], search=['name', 'contact_person']),
    R('inventory-categories', 'InventoryCategory', 'inventory_categories', 'inventory', 'Inventory categories', [
        S('name', req=1, uniq=1, lst=1),
    ], group='inventory', lbl=['name']),
    R('inventory-items', 'InventoryItem', 'inventory_items', 'inventory', 'Stock items', [
        S('name', req=1, lst=1), S('sku', uniq=1, lst=1), K('inventory_category_id', 'inventory-categories', lst=1,
                                                              flt=1),
        S('unit', default='piece', max=30), I('quantity', lst=1, frm=False, default=0),
        I('reorder_level', lst=1, default=0, minv=0), D('unit_cost', money=1), S('location'),
    ], group='inventory', lbl=['name'], search=['name', 'sku'], order=('name', 'asc')),
    R('stock-movements', 'StockMovement', 'stock_movements', 'inventory', 'Stock movements', [
        K('inventory_item_id', 'inventory-items', req=1, lst=1, cascade=1),
        E('type', ['purchase', 'issue', 'return', 'adjustment'], req=1, lst=1, flt=1), I('quantity', req=1, lst=1),
        I('quantity_after', lst=1), S('reference'), S('issued_to', lst=1), X('notes'), K('user_id', 'users'),
    ], group='inventory', readonly=True, pv=['inventory.view'], order=('id', 'desc'), lbl=['id']),
    R('purchases', 'Purchase', 'purchases', 'inventory', 'Purchases', [
        K('supplier_id', 'suppliers', lst=1, flt=1), K('inventory_item_id', 'inventory-items', req=1, lst=1),
        I('quantity', req=1, lst=1, minv=1), D('unit_cost', req=1, money=1), D('total', lst=1, money=1),
        DT('purchase_date', req=1, lst=1), S('invoice_no'), X('notes'), K('recorded_by', 'users'),
    ], group='inventory', readonly=True, pv=['inventory.view'], order=('purchase_date', 'desc'), lbl=['id']),
    R('assets', 'Asset', 'assets', 'inventory', 'Assets', [
        S('tag', req=1, uniq=1, lst=1), S('name', req=1, lst=1),
        K('inventory_category_id', 'inventory-categories', lst=1, flt=1), X('description'), DT('purchase_date'),
        D('cost', money=1), S('location', lst=1),
        E('status', ['active', 'under_repair', 'disposed', 'lost'], default='active', lst=1, flt=1),
        S('assigned_to'),
    ], group='inventory', lbl=['name', 'tag'], lbl_fmt='{0} ({1})', search=['name', 'tag']),
    R('asset-maintenance', 'AssetMaintenance', 'asset_maintenances', 'inventory', 'Asset maintenance', [
        K('asset_id', 'assets', req=1, lst=1, cascade=1), DT('maintenance_date', req=1, lst=1),
        X('description', req=1), D('cost', lst=1, money=1), S('performed_by'), DT('next_due', lst=1),
    ], group='inventory', lbl=['id'], order=('maintenance_date', 'desc')),

    # ------------------------------------------------------------ transport & hostel (phase 9)
    R('drivers', 'Driver', 'drivers', 'transport', 'Drivers & attendants', [
        S('name', req=1, lst=1), E('role', ['driver', 'attendant'], default='driver', lst=1, flt=1),
        S('phone', lst=1, max=30), S('license_no'), DT('license_expiry', lst=1), X('address'),
        E('status', ['active', 'inactive'], default='active', lst=1),
    ], group='transport', lbl=['name'], search=['name', 'phone']),
    R('vehicles', 'Vehicle', 'vehicles', 'transport', 'Vehicles', [
        S('reg_no', req=1, uniq=1, lst=1), S('make', lst=1), S('model', lst=1), I('capacity', lst=1),
        E('status', ['active', 'maintenance', 'retired'], default='active', lst=1, flt=1),
        DT('insurance_expiry', lst=1), K('driver_id', 'drivers', lst=1), K('attendant_id', 'drivers'),
    ], group='transport', lbl=['reg_no'], search=['reg_no', 'make']),
    R('vehicle-maintenance', 'VehicleMaintenance', 'vehicle_maintenances', 'transport', 'Vehicle maintenance', [
        K('vehicle_id', 'vehicles', req=1, lst=1, cascade=1), DT('maintenance_date', req=1, lst=1),
        X('description', req=1), D('cost', lst=1, money=1), DT('next_due', lst=1), S('performed_by'),
    ], group='transport', lbl=['id'], order=('maintenance_date', 'desc')),
    R('transport-routes', 'TransportRoute', 'transport_routes', 'transport', 'Routes', [
        S('name', req=1, lst=1), X('pickup_points', help='One stop per line'), K('vehicle_id', 'vehicles', lst=1),
        D('fee', lst=1, money=1, default=0, help='Fee per term'), X('description'),
    ], group='transport', lbl=['name'], search=['name']),
    R('transport-allocations', 'TransportAllocation', 'transport_allocations', 'transport', 'Student routes', [
        K('student_id', 'students', req=1, lst=1, flt=1), K('transport_route_id', 'transport-routes', req=1, lst=1,
                                                              flt=1),
        K('term_id', 'terms', req=1, lst=1, flt=1), S('pickup_point', lst=1),
        E('status', ['active', 'stopped'], default='active', lst=1),
    ], group='transport', uniq_together=[('student_id', 'term_id')], lbl=['id']),
    R('hostels', 'Hostel', 'hostels', 'hostel', 'Hostels', [
        S('name', req=1, uniq=1, lst=1), E('gender', ['boys', 'girls', 'mixed'], default='mixed', lst=1),
        K('warden_id', 'staff', lst=1), D('fee_per_term', lst=1, money=1, default=0), X('description'),
    ], group='transport', lbl=['name']),
    R('hostel-rooms', 'HostelRoom', 'hostel_rooms', 'hostel', 'Rooms', [
        K('hostel_id', 'hostels', req=1, lst=1, flt=1, cascade=1), S('room_no', req=1, lst=1, max=30),
        I('capacity', req=1, lst=1, minv=1), S('type', max=40),
    ], group='transport', uniq_together=[('hostel_id', 'room_no')], lbl=['room_no']),
    R('hostel-allocations', 'HostelAllocation', 'hostel_allocations', 'hostel', 'Bed allocation', [
        K('student_id', 'students', req=1, lst=1, flt=1), K('hostel_room_id', 'hostel-rooms', req=1, lst=1, flt=1),
        I('bed_no', req=1, lst=1, minv=1), DT('allocated_on', req=1, lst=1), DT('vacated_on'),
        E('status', ['active', 'vacated'], default='active', lst=1, flt=1),
    ], group='transport', hooks='HostelAllocationHooks', lbl=['id']),

    # ------------------------------------------------------------ payroll (phase 10)
    R('salary-structures', 'SalaryStructure', 'salary_structures', 'payroll', 'Salary structures', [
        K('staff_id', 'staff', req=1, uniq=1, cascade=1, lst=1), D('basic_salary', req=1, lst=1, money=1),
        DT('effective_from'), X('notes'),
    ], group='hr', crud=False),
    R('salary-components', 'SalaryComponent', 'salary_components', 'payroll', 'Salary components', [
        K('staff_id', 'staff', req=1, cascade=1), E('type', ['allowance', 'deduction'], req=1), S('name', req=1),
        D('amount', req=1, money=1), B('is_percent', default=False),
    ], group='hr', crud=False),
    R('payroll-runs', 'PayrollRun', 'payroll_runs', 'payroll', 'Payroll runs', [
        I('period_year', req=1), I('period_month', req=1),
        E('status', ['draft', 'processed', 'paid'], default='draft'), D('total_gross', default=0),
        D('total_deductions', default=0), D('total_net', default=0), K('processed_by', 'users'),
        TS('processed_at'), TS('paid_at'),
    ], group='hr', crud=False, uniq_together=[('period_year', 'period_month')]),
    R('payslips', 'Payslip', 'payslips', 'payroll', 'Payslips', [
        K('payroll_run_id', 'payroll-runs', req=1, cascade=1), K('staff_id', 'staff', req=1),
        D('basic_salary', default=0), D('total_allowances', default=0), D('total_deductions', default=0),
        D('gross_pay', default=0), D('net_pay', default=0), J('components'),
        E('status', ['pending', 'paid'], default='pending'), TS('paid_at'), S('payment_reference'),
    ], group='hr', crud=False, uniq_together=[('payroll_run_id', 'staff_id')]),

    # ------------------------------------------------------------ communication (phase 10)
    R('announcements', 'Announcement', 'announcements', 'communication', 'Announcements', [
        S('title', req=1, lst=1), X('body', req=1), J('audience', help='Role slugs; empty means everyone'),
        E('priority', ['normal', 'important', 'urgent'], default='normal', lst=1, flt=1), B('is_pinned', lst=1),
        TS('published_at', lst=1), TS('expires_at'), K('created_by', 'users', **HID),
    ], group='communication', crud=False),
    R('app-notifications', 'AppNotification', 'app_notifications', 'communication', 'Notifications', [
        K('user_id', 'users', req=1, cascade=1), S('title', req=1), X('body'), S('type', max=40), J('data'),
        TS('read_at'),
    ], group='communication', crud=False),
    R('messages', 'Message', 'messages', 'communication', 'Messages', [
        K('sender_id', 'users', req=1, cascade=1), K('recipient_id', 'users', req=1, cascade=1), S('subject'),
        X('body', req=1), TS('read_at'), K('parent_id', 'messages', cascade=1),
    ], group='communication', crud=False),
    R('teacher-reports', 'TeacherReport', 'teacher_reports', 'teacher-reports', 'Reports to management', [
        S('title', req=1, lst=1), X('body', req=1), K('term_id', 'terms', lst=1),
        E('status', ['submitted', 'reviewed'], default='submitted', lst=1, flt=1), X('reviewer_note'),
        K('created_by', 'users', lst=1, rel=1, **HID), K('reviewed_by', 'users', **HID),
    ], group='communication', pv=['teacher-reports.review', 'teacher-reports.submit'],
      pw=['teacher-reports.review', 'teacher-reports.submit'], creator='created_by',
      own=dict(col='created_by', admin='teacher-reports.review', list=True), hooks='TeacherReportHooks',
      lbl=['title'], search=['title'], order=('id', 'desc')),
    R('message-logs', 'MessageLog', 'message_logs', 'communication', 'Delivery logs', [
        E('channel', ['sms', 'email'], req=1), S('recipient'), S('subject'), X('body'),
        E('status', ['sent', 'failed', 'skipped'], default='sent'), X('error'),
    ], group='communication', crud=False),

    # ------------------------------------------------------------ medical & discipline (phase 4/10)
    R('medical-profiles', 'MedicalProfile', 'medical_profiles', 'medical', 'Medical profiles', [
        K('student_id', 'students', req=1, uniq=1, lst=1, cascade=1), S('blood_group', max=5, lst=1),
        S('genotype', max=5, lst=1), X('allergies'), X('chronic_conditions'), X('medications'), S('doctor_name'),
        S('doctor_phone', max=30), S('emergency_contact_name', req=1), S('emergency_contact_phone', req=1, max=30),
        S('emergency_contact_relationship'), X('notes'),
    ], group='medical', lbl=['id'], order=('id', 'desc')),
    R('medical-visits', 'MedicalVisit', 'medical_visits', 'medical', 'Sick-bay visits', [
        K('student_id', 'students', req=1, lst=1, flt=1, cascade=1), DT('visit_date', req=1, lst=1),
        X('complaint', req=1), X('diagnosis'), X('treatment'), X('medication_given'), B('parent_notified', lst=1),
        K('recorded_by', 'users', **HID),
    ], group='medical', creator='recorded_by', lbl=['id'], order=('visit_date', 'desc')),
    R('disciplinary-records', 'DisciplinaryRecord', 'disciplinary_records', 'discipline', 'Disciplinary records', [
        K('student_id', 'students', req=1, lst=1, flt=1, cascade=1), DT('incident_date', req=1, lst=1),
        S('category', req=1, lst=1), E('severity', ['minor', 'moderate', 'serious'], default='minor', lst=1, flt=1),
        X('description', req=1), X('action_taken'),
        E('status', ['open', 'resolved', 'escalated'], default='open', lst=1, flt=1), B('parent_notified'),
        K('reported_by', 'users', **HID),
    ], group='medical', creator='reported_by', lbl=['id'], order=('incident_date', 'desc')),

    # ------------------------------------------------------------ system
    R('id-sequences', 'IdSequence', 'id_sequences', 'settings', 'ID sequences', [
        S('key', req=1, uniq=1, max=60), I('value', req=1, default=0),
    ], group='system', crud=False),
]

GROUP_ORDER = ['people', 'academics', 'exams', 'attendance', 'finance', 'library', 'inventory', 'transport', 'hr',
               'communication', 'medical', 'system']
