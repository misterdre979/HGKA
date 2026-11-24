import { zodResolver } from '@hookform/resolvers/zod'
import { BookOpenCheck, Eye, EyeOff, Lock, Mail, ReceiptText, ShieldCheck, Users } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useForm } from 'react-hook-form'
import { Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import { Logo } from '@/components/Logo'
import { Button, Card, Input, Label, PageHeader } from '@/components/ui'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errMsg, fieldErrors } from '@/lib/utils'
import { api } from '@/services/api'

export const homeFor = (can: (...p: string[]) => boolean) => (can('dashboard.view') ? '/' : can('own-children.view', 'own-record.view') ? '/portal' : '/notifications')

let blocksPlayed = false

const BLOCKS: { ch: string; cls: string; x: number; y: number; r: number; d: number }[] = [
  { ch: 'A', cls: 'bg-gold text-navy', x: 0, y: 2, r: -4, d: 0 },
  { ch: 'B', cls: 'bg-royal text-white', x: 1, y: 2, r: 3, d: 120 },
  { ch: 'C', cls: 'bg-white text-navy', x: 2, y: 2, r: -2, d: 240 },
  { ch: '1', cls: 'bg-white text-navy', x: 0.5, y: 1, r: 5, d: 380 },
  { ch: '2', cls: 'bg-gold text-navy', x: 1.5, y: 1, r: -5, d: 500 },
  { ch: '3', cls: 'bg-royal text-white', x: 1, y: 0, r: 2, d: 640 },
]

/** Alphabet blocks that drop into a pyramid the first time the sign-in area opens. */
function Blocks() {
  const [animate] = useState(() => !blocksPlayed)
  blocksPlayed = true
  return (
    <div aria-hidden className="relative hidden h-[210px] w-[232px] shrink-0 xl:block">
      {BLOCKS.map((b) => (
        <div
          key={b.ch}
          style={{ left: b.x * 76, top: b.y * 72, '--r0': `${b.r - 14}deg`, '--r1': `${b.r}deg`, '--d': `${b.d}ms`, transform: `rotate(${b.r}deg)` } as React.CSSProperties}
          className={`absolute grid h-16 w-16 place-items-center rounded-xl font-display text-3xl font-extrabold shadow-[0_6px_0_rgba(0,0,0,0.25)] transition-transform duration-300 hover:-translate-y-1 ${b.cls} ${animate ? 'block-drop' : ''}`}
        >
          {b.ch}
        </div>
      ))}
    </div>
  )
}

const WHO = [
  { icon: Users, who: 'Parents', what: 'Follow results, attendance and fees for each child.' },
  { icon: BookOpenCheck, who: 'Teachers', what: 'Take the register and enter scores for your classes.' },
  { icon: ReceiptText, who: 'School office', what: 'Admissions, payments, payroll and reports.' },
]

function Shell({ title, children, sub }: { title: string; sub?: string; children: ReactNode }) {
  const { branding } = useAuth()
  return (
    <main className="min-h-screen lg:grid lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
      <aside className="relative hidden overflow-hidden bg-navy text-white lg:flex lg:flex-col lg:justify-between lg:gap-8 lg:p-12 lg:py-10">
        <div aria-hidden className="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-royal/40 blur-3xl" />
        <div aria-hidden className="pointer-events-none absolute -bottom-48 -left-32 h-[28rem] w-[28rem] rounded-full bg-royal/25 blur-3xl" />
        <div className="relative"><Logo light /></div>
        <div className="relative max-w-md">
          <h2 className="font-display text-4xl font-extrabold leading-[1.1] tracking-tight xl:text-5xl">A calmer school day for every family.</h2>
          <p className="mt-3 text-base text-slate-300">
            {branding.motto ? `${branding.motto}. ` : ''}Results, fees, attendance and messages for His Grace Kiddies Academy, all in one place.
          </p>
          <ul className="mt-7 space-y-4">
            {WHO.map(({ icon: Icon, who, what }) => (
              <li key={who} className="flex items-start gap-3.5">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-white/10 text-gold"><Icon size={19} /></span>
                <span><span className="block font-semibold">{who}</span><span className="block text-sm text-slate-300">{what}</span></span>
              </li>
            ))}
          </ul>
        </div>
        <div className="relative flex items-end justify-between gap-6">
          <p className="max-w-[16rem] text-sm text-slate-400">Nursery, primary and secondary. Your data stays private to the school.</p>
          <Blocks />
        </div>
      </aside>

      <section className="flex min-h-screen flex-col justify-center bg-white px-6 py-10 sm:px-12">
        <div className="form-in mx-auto w-full max-w-sm">
          <div className="mb-10 lg:hidden"><Logo /></div>
          <h1 className="font-display text-3xl font-bold tracking-tight text-navy">{title}</h1>
          {sub && <p className="mt-2 text-sm text-slate-500">{sub}</p>}
          <div className="mt-8">{children}</div>
        </div>
        <p className="mx-auto mt-12 flex w-full max-w-sm items-center gap-2 text-xs text-slate-400"><ShieldCheck size={14} /> Sessions expire automatically. Sign out on shared computers.</p>
      </section>
    </main>
  )
}

/** Input with a leading icon and an optional trailing slot, used on the sign-in screens. */
function Field({ label, icon: Icon, error, trailing, children }: { label: string; icon: typeof Mail; error?: string; trailing?: ReactNode; children: ReactNode }) {
  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-slate-700">{label}</label>
      <div className={`group relative rounded-xl transition-shadow focus-within:shadow-[0_0_0_4px_rgba(36,107,206,0.15)] ${error ? 'shadow-[0_0_0_3px_rgba(220,38,38,0.15)]' : ''}`}>
        <Icon size={17} className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 transition-colors group-focus-within:text-royal" />
        {children}
        {trailing && <div className="absolute right-2 top-1/2 -translate-y-1/2">{trailing}</div>}
      </div>
      {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}
    </div>
  )
}

const fieldCls = 'w-full rounded-xl border border-slate-300 bg-slate-50 py-3 pl-10 pr-10 text-sm text-slate-800 outline-none transition-colors placeholder:text-slate-400 focus:border-royal focus:bg-white'

const strong = z.string().min(8, 'At least 8 characters').regex(/[a-z]/, 'Include a lowercase letter').regex(/[A-Z]/, 'Include an uppercase letter').regex(/\d/, 'Include a number')

export function LoginPage() {
  const { user, login, can } = useAuth()
  const nav = useNavigate()
  const [error, setError] = useState('')
  const [show, setShow] = useState(false)
  const [shakeKey, setShakeKey] = useState(0)
  const schema = z.object({ email: z.string().email('Enter a valid email'), password: z.string().min(1, 'Password is required') })
  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) })

  if (user) return <Navigate to={user.must_change_password ? '/change-password' : homeFor(can)} replace />

  return (
    <Shell title="Welcome back" sub="Sign in with the account the school created for you.">
      <form noValidate className="space-y-5" onSubmit={handleSubmit(async (v) => {
        setError('')
        try {
          const u = await login(v.email, v.password)
          nav(u.must_change_password ? '/change-password' : '/', { replace: true })
        } catch (e) { setError(errMsg(e)); setShakeKey((k) => k + 1) }
      })}>
        <Field label="Email" icon={Mail} error={errors.email?.message}>
          <input type="email" autoComplete="username" placeholder="you@school.ng" className={fieldCls} {...register('email')} />
        </Field>
        <Field
          label="Password" icon={Lock} error={errors.password?.message}
          trailing={<button type="button" onClick={() => setShow((v) => !v)} aria-label={show ? 'Hide password' : 'Show password'} className="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-700">{show ? <EyeOff size={17} /> : <Eye size={17} />}</button>}
        >
          <input type={show ? 'text' : 'password'} autoComplete="current-password" placeholder="Your password" className={fieldCls} {...register('password')} />
        </Field>
        {error && <p key={shakeKey} role="alert" className="shake rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{error}</p>}
        <Button type="submit" size="md" className="w-full rounded-xl py-3" loading={isSubmitting}>Sign in</Button>
        <p className="text-center text-sm"><Link to="/forgot-password" className="font-medium text-royal hover:underline">Forgot your password?</Link></p>
        <p className="border-t border-slate-100 pt-5 text-center text-xs text-slate-500">New here? The school office creates your account. Ask them for your login details.</p>
      </form>
    </Shell>
  )
}

export function ForgotPasswordPage() {
  const [sent, setSent] = useState('')
  const [error, setError] = useState('')
  const { register, handleSubmit, formState: { isSubmitting } } = useForm<{ email: string }>()
  return (
    <Shell title="Reset password" sub="We will email you a reset link.">
      {sent ? <p className="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{sent}</p> : (
        <form className="space-y-4" onSubmit={handleSubmit(async (v) => {
          setError('')
          try { setSent((await api.post('/auth/forgot-password', v)).data.message) } catch (e) { setError(errMsg(e)) }
        })}>
          <label className="block"><Label required>Email</Label><Input type="email" required {...register('email')} /></label>
          {error && <p role="alert" className="text-sm text-red-600">{error}</p>}
          <Button type="submit" className="w-full" loading={isSubmitting}>Send reset link</Button>
        </form>
      )}
      <p className="mt-4 text-center text-sm"><Link to="/login" className="text-royal hover:underline">Back to sign in</Link></p>
    </Shell>
  )
}

export function ResetPasswordPage() {
  const [sp] = useSearchParams()
  const nav = useNavigate()
  const toast = useToast()
  const schema = z.object({ password: strong, password_confirmation: z.string() }).refine((d) => d.password === d.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match' })
  const { register, handleSubmit, setError, formState: { errors, isSubmitting } } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) })
  return (
    <Shell title="Choose a new password">
      <form noValidate className="space-y-4" onSubmit={handleSubmit(async (v) => {
        try {
          await api.post('/auth/reset-password', { ...v, token: sp.get('token'), email: sp.get('email') })
          toast.ok('Password reset. Please sign in.')
          nav('/login')
        } catch (e) {
          const fe = fieldErrors(e)
          setError('password', { message: fe.password ?? fe.email ?? errMsg(e) })
        }
      })}>
        <label className="block"><Label required>New password</Label><Input type="password" autoComplete="new-password" {...register('password')} />{errors.password && <p className="mt-1 text-xs text-red-600">{errors.password.message}</p>}</label>
        <label className="block"><Label required>Confirm password</Label><Input type="password" autoComplete="new-password" {...register('password_confirmation')} />{errors.password_confirmation && <p className="mt-1 text-xs text-red-600">{errors.password_confirmation.message}</p>}</label>
        <Button type="submit" className="w-full" loading={isSubmitting}>Reset password</Button>
      </form>
    </Shell>
  )
}

function ChangePasswordForm({ onDone }: { onDone: () => void }) {
  const { refresh } = useAuth()
  const toast = useToast()
  const schema = z.object({ current_password: z.string().min(1, 'Required'), password: strong, password_confirmation: z.string() })
    .refine((d) => d.password === d.password_confirmation, { path: ['password_confirmation'], message: 'Passwords do not match' })
  const { register, handleSubmit, reset, setError, formState: { errors, isSubmitting } } = useForm<z.infer<typeof schema>>({ resolver: zodResolver(schema) })
  const Err = ({ n }: { n: keyof z.infer<typeof schema> }) => (errors[n] ? <p className="mt-1 text-xs text-red-600">{errors[n]?.message}</p> : null)
  return (
    <form noValidate className="space-y-4" onSubmit={handleSubmit(async (v) => {
      try {
        await api.post('/auth/change-password', v)
        toast.ok('Password updated.')
        reset()
        await refresh()
        onDone()
      } catch (e) {
        const fe = fieldErrors(e)
        if (Object.keys(fe).length) for (const [k, m] of Object.entries(fe)) setError(k as 'password', { message: m })
        else toast.err(errMsg(e))
      }
    })}>
      <label className="block"><Label required>Current password</Label><Input type="password" autoComplete="current-password" {...register('current_password')} /><Err n="current_password" /></label>
      <label className="block"><Label required help="8+ chars, upper & lower case, a number">New password</Label><Input type="password" autoComplete="new-password" {...register('password')} /><Err n="password" /></label>
      <label className="block"><Label required>Confirm new password</Label><Input type="password" autoComplete="new-password" {...register('password_confirmation')} /><Err n="password_confirmation" /></label>
      <Button type="submit" loading={isSubmitting}>Update password</Button>
    </form>
  )
}

/** Shown on first login (must_change_password) before anything else is reachable. */
export function ForcedChangePasswordPage() {
  const { user, logout, can } = useAuth()
  const nav = useNavigate()
  if (!user) return <Navigate to="/login" replace />
  if (!user.must_change_password) return <Navigate to={homeFor(can)} replace />
  return (
    <Shell title="Set a new password" sub="For your security you must replace the temporary password before continuing.">
      <ChangePasswordForm onDone={() => nav('/', { replace: true })} />
      <button className="mt-4 text-sm text-slate-500 hover:underline" onClick={async () => { await logout(); nav('/login') }}>Sign out</button>
    </Shell>
  )
}

export function AccountPage() {
  const { user, refresh } = useAuth()
  const toast = useToast()
  const { register, handleSubmit, formState: { isSubmitting } } = useForm({ defaultValues: { name: user?.name ?? '', phone: user?.phone ?? '' } })
  return (
    <>
      <PageHeader title="My account" />
      <div className="grid gap-5 lg:grid-cols-2">
        <Card title="Profile">
          <form className="space-y-4" onSubmit={handleSubmit(async (v) => {
            try { await api.post('/profile', v); await refresh(); toast.ok('Profile updated.') } catch (e) { toast.err(errMsg(e)) }
          })}>
            <label className="block"><Label>Email</Label><Input value={user?.email ?? ''} disabled readOnly /></label>
            <label className="block"><Label required>Name</Label><Input {...register('name', { required: true })} /></label>
            <label className="block"><Label>Phone</Label><Input {...register('phone')} /></label>
            <Button type="submit" loading={isSubmitting}>Save profile</Button>
          </form>
        </Card>
        <Card title="Change password"><ChangePasswordForm onDone={() => undefined} /></Card>
      </div>
    </>
  )
}