import axios from 'axios'

export const cn = (...c: (string | false | null | undefined)[]) => c.filter(Boolean).join(' ')

export const money = (n: number | string | null | undefined, symbol = '₦') =>
  n === null || n === undefined || n === '' ? '—' : `${symbol}${Number(n).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

export const fmtDate = (d?: string | null) => {
  if (!d) return '—'
  const dt = new Date(d.length <= 10 ? d + 'T00:00:00' : d)
  return isNaN(dt.getTime()) ? d : dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

export const fmtDateTime = (d?: string | null) => {
  if (!d) return '—'
  const dt = new Date(d)
  return isNaN(dt.getTime()) ? d : dt.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export const humanize = (s: string) => s.replace(/[_-]/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
export const today = () => new Date().toISOString().slice(0, 10)

/** Pulls a readable message from an Axios/Laravel error. */
export function errMsg(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const d = e.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    if (d?.errors) return Object.values(d.errors).flat().join(' ')
    if (d?.message) return d.message
    if (!e.response) return 'Cannot reach the server. Check your connection.'
  }
  return e instanceof Error ? e.message : 'Something went wrong.'
}

/** Laravel field errors keyed by field name (for react-hook-form). */
export function fieldErrors(e: unknown): Record<string, string> {
  if (axios.isAxiosError(e) && e.response?.status === 422) {
    const errs = (e.response.data as { errors?: Record<string, string[]> })?.errors ?? {}
    return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]))
  }
  return {}
}
