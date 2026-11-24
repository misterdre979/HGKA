import { AlertTriangle, ChevronLeft, ChevronRight, Loader2, X } from 'lucide-react'
import { forwardRef, useEffect, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import { cn, humanize } from '@/lib/utils'

export const inputCls =
  'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-royal focus:outline-none focus:ring-2 focus:ring-royal/20 disabled:bg-slate-100 disabled:text-slate-500'

type BtnProps = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'secondary' | 'danger' | 'ghost' | 'gold'; size?: 'sm' | 'md'; loading?: boolean }

export function Button({ variant = 'primary', size = 'md', loading, className, children, disabled, ...rest }: BtnProps) {
  const v = {
    primary: 'bg-royal text-white hover:bg-royal/90',
    secondary: 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    ghost: 'text-slate-600 hover:bg-slate-100',
    gold: 'bg-gold text-navy hover:bg-gold/90',
  }[variant]
  return (
    <button
      type="button"
      {...rest}
      disabled={disabled || loading}
      className={cn('inline-flex items-center justify-center gap-2 rounded-lg font-medium transition disabled:cursor-not-allowed disabled:opacity-60', size === 'sm' ? 'px-2.5 py-1.5 text-xs' : 'px-4 py-2 text-sm', v, className)}
    >
      {loading && <Loader2 size={15} className="animate-spin" />}
      {children}
    </button>
  )
}

export function Card({ className, children, title, action }: { className?: string; children: ReactNode; title?: ReactNode; action?: ReactNode }) {
  return (
    <section className={cn('rounded-xl border border-slate-200 bg-white shadow-sm', className)}>
      {(title || action) && (
        <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
          <h2 className="text-sm font-semibold text-navy">{title}</h2>
          {action}
        </header>
      )}
      <div className="p-4">{children}</div>
    </section>
  )
}

export function PageHeader({ title, subtitle, actions }: { title: string; subtitle?: string; actions?: ReactNode }) {
  return (
    <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 className="text-xl font-bold text-navy">{title}</h1>
        {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
      </div>
      {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
    </div>
  )
}

export const Spinner = ({ label = 'Loading…' }: { label?: string }) => (
  <div className="flex items-center justify-center gap-2 py-10 text-sm text-slate-500"><Loader2 className="animate-spin" size={18} />{label}</div>
)

export const ErrorBox = ({ message, onRetry }: { message: string; onRetry?: () => void }) => (
  <div role="alert" className="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    <AlertTriangle size={18} className="shrink-0" />
    <span className="flex-1">{message}</span>
    {onRetry && <Button size="sm" variant="secondary" onClick={onRetry}>Retry</Button>}
  </div>
)

export const Empty = ({ text = 'Nothing to show yet.', children }: { text?: string; children?: ReactNode }) => (
  <div className="py-10 text-center text-sm text-slate-500">{text}{children && <div className="mt-3">{children}</div>}</div>
)

const tones: Record<string, string> = {
  green: 'bg-emerald-50 text-emerald-700 ring-emerald-200', red: 'bg-red-50 text-red-700 ring-red-200',
  amber: 'bg-amber-50 text-amber-700 ring-amber-200', blue: 'bg-blue-50 text-blue-700 ring-blue-200', gray: 'bg-slate-100 text-slate-600 ring-slate-200',
}
const statusTone: Record<string, string> = {
  active: 'green', paid: 'green', successful: 'green', approved: 'green', present: 'green', returned: 'green', completed: 'green', published: 'green', available: 'green',
  pending: 'amber', partial: 'amber', submitted: 'amber', awaiting_approval: 'amber', late: 'amber', draft: 'amber', borrowed: 'blue', scheduled: 'blue', unpaid: 'red',
  failed: 'red', rejected: 'red', absent: 'red', cancelled: 'red', lost: 'red', overdue: 'red', suspended: 'red', terminated: 'red', urgent: 'red', important: 'amber',
}
export const Badge = ({ value, tone }: { value: string; tone?: keyof typeof tones }) => (
  <span className={cn('inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset', tones[tone ?? statusTone[value] ?? 'gray'])}>{humanize(value)}</span>
)

export function Modal({ open, onClose, title, children, wide, footer }: { open: boolean; onClose: () => void; title: string; children: ReactNode; wide?: boolean; footer?: ReactNode }) {
  useEffect(() => {
    if (!open) return
    const h = (e: KeyboardEvent) => e.key === 'Escape' && onClose()
    document.addEventListener('keydown', h)
    return () => document.removeEventListener('keydown', h)
  }, [open, onClose])
  if (!open) return null
  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-navy/50 p-0 sm:items-center sm:p-4" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div role="dialog" aria-modal="true" aria-label={title} className={cn('flex max-h-[92vh] w-full flex-col rounded-t-2xl bg-white shadow-xl sm:rounded-2xl', wide ? 'sm:max-w-3xl' : 'sm:max-w-lg')}>
        <header className="flex items-center justify-between border-b border-slate-100 px-5 py-3">
          <h2 className="font-semibold text-navy">{title}</h2>
          <button onClick={onClose} aria-label="Close" className="rounded p-1 text-slate-500 hover:bg-slate-100"><X size={18} /></button>
        </header>
        <div className="overflow-y-auto p-5">{children}</div>
        {footer && <footer className="flex justify-end gap-2 border-t border-slate-100 px-5 py-3">{footer}</footer>}
      </div>
    </div>
  )
}

export function Confirm({ open, title, message, confirmLabel = 'Confirm', danger, onCancel, onConfirm, busy }: {
  open: boolean; title: string; message: ReactNode; confirmLabel?: string; danger?: boolean; busy?: boolean; onCancel: () => void; onConfirm: () => void
}) {
  return (
    <Modal open={open} onClose={onCancel} title={title} footer={<><Button variant="secondary" onClick={onCancel}>Cancel</Button><Button variant={danger ? 'danger' : 'primary'} loading={busy} onClick={onConfirm}>{confirmLabel}</Button></>}>
      <p className="text-sm text-slate-600">{message}</p>
    </Modal>
  )
}

export function Pager({ page, last, total, onPage }: { page: number; last: number; total: number; onPage: (p: number) => void }) {
  if (last <= 1) return <div className="px-1 pt-3 text-xs text-slate-500">{total} record{total === 1 ? '' : 's'}</div>
  return (
    <div className="flex items-center justify-between pt-3 text-sm">
      <span className="text-xs text-slate-500">{total} records · page {page} of {last}</span>
      <div className="flex gap-1">
        <Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => onPage(page - 1)} aria-label="Previous page"><ChevronLeft size={14} /></Button>
        <Button size="sm" variant="secondary" disabled={page >= last} onClick={() => onPage(page + 1)} aria-label="Next page"><ChevronRight size={14} /></Button>
      </div>
    </div>
  )
}

export const Stat = ({ label, value, hint, icon, tone = 'royal' }: { label: string; value: ReactNode; hint?: ReactNode; icon?: ReactNode; tone?: 'royal' | 'gold' | 'green' | 'red' }) => {
  const t = { royal: 'bg-royal/10 text-royal', gold: 'bg-gold/20 text-amber-700', green: 'bg-emerald-100 text-emerald-700', red: 'bg-red-100 text-red-700' }[tone]
  return (
    <div className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
      {icon && <div className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-lg', t)}>{icon}</div>}
      <div className="min-w-0">
        <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
        <div className="break-words text-xl font-bold leading-tight text-navy">{value}</div>
        {hint && <div className="mt-0.5 text-xs text-slate-500">{hint}</div>}
      </div>
    </div>
  )
}

export const Label = ({ children, required, help }: { children: ReactNode; required?: boolean; help?: string }) => (
  <span className="mb-1 block text-sm font-medium text-slate-700">
    {children}{required && <span className="text-red-500"> *</span>}
    {help && <span className="ml-1 text-xs font-normal text-slate-400">{help}</span>}
  </span>
)

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(({ className, ...p }, ref) => <input ref={ref} className={cn(inputCls, className)} {...p} />)
Input.displayName = 'Input'
export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(({ className, children, ...p }, ref) => <select ref={ref} className={cn(inputCls, className)} {...p}>{children}</select>)
Select.displayName = 'Select'
export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(({ className, ...p }, ref) => <textarea ref={ref} rows={3} className={cn(inputCls, className)} {...p} />)
Textarea.displayName = 'Textarea'

export const Tabs = ({ tabs, value, onChange }: { tabs: { id: string; label: string }[]; value: string; onChange: (id: string) => void }) => (
  <div className="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200">
    {tabs.map((t) => (
      <button key={t.id} onClick={() => onChange(t.id)} className={cn('whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium', value === t.id ? 'border-royal text-royal' : 'border-transparent text-slate-500 hover:text-slate-700')}>{t.label}</button>
    ))}
  </div>
)

export const Table = ({ head, children }: { head: ReactNode[]; children: ReactNode }) => (
  <div className="overflow-x-auto">
    <table className="w-full text-left text-sm">
      <thead className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
        <tr>{head.map((h, i) => <th key={i} className="whitespace-nowrap px-3 py-2 font-medium">{h}</th>)}</tr>
      </thead>
      <tbody className="divide-y divide-slate-100">{children}</tbody>
    </table>
  </div>
)
