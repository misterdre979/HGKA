import { useEffect, useState } from 'react'
import { api } from '@/services/api'
import { Select } from '@/components/ui'

export interface Option { id: number; label: string }

/** Select populated from /lookup/{resource}. `filter` narrows options (e.g. arms of the chosen class). */
export function useLookup(resource: string | undefined, filter?: Record<string, string | number | null | undefined>, enabled = true) {
  const [opts, setOpts] = useState<Option[]>([])
  const [loading, setLoading] = useState(false)
  const fk = JSON.stringify(filter ?? {})
  useEffect(() => {
    if (!resource || !enabled) { setOpts([]); return }
    let live = true
    setLoading(true)
    const params: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(filter ?? {})) if (v !== null && v !== undefined && v !== '') params[`filter[${k}]`] = v
    api.get(`/lookup/${resource}`, { params })
      .then((r) => live && setOpts(r.data))
      .catch(() => live && setOpts([]))
      .finally(() => live && setLoading(false))
    return () => { live = false }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resource, fk, enabled])
  return { opts, loading }
}

type Props = Omit<React.SelectHTMLAttributes<HTMLSelectElement>, 'onChange' | 'value'> & {
  resource: string
  value: string | number | null | undefined
  onChange: (v: string) => void
  filter?: Record<string, string | number | null | undefined>
  placeholder?: string
  /** When true the list stays empty until `filter` values are all set. */
  needsFilter?: boolean
}

export function FkSelect({ resource, value, onChange, filter, placeholder = 'Select…', needsFilter, ...rest }: Props) {
  const ready = !needsFilter || Object.values(filter ?? {}).every((v) => v !== null && v !== undefined && v !== '')
  const { opts, loading } = useLookup(resource, filter, ready)
  return (
    <Select {...rest} value={value ?? ''} onChange={(e) => onChange(e.target.value)} disabled={rest.disabled || loading}>
      <option value="">{loading ? 'Loading…' : ready ? placeholder : 'Choose the parent field first'}</option>
      {opts.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}
    </Select>
  )
}
