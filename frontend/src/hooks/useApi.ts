import { useCallback, useEffect, useRef, useState } from 'react'
import { api } from '@/services/api'
import { errMsg } from '@/lib/utils'

/** GET helper with loading/error state and manual reload. Skips the request while `url` is null. */
export function useGet<T = any>(url: string | null, params?: Record<string, unknown>) {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(!!url)
  const [error, setError] = useState<string | null>(null)
  const key = url ? url + JSON.stringify(params ?? {}) : ''
  const seq = useRef(0)

  const load = useCallback(async () => {
    if (!url) { setData(null); setLoading(false); return }
    const my = ++seq.current
    setLoading(true)
    setError(null)
    try {
      const r = await api.get(url, { params })
      if (my === seq.current) setData(r.data)
    } catch (e) {
      if (my === seq.current) setError(errMsg(e))
    } finally {
      if (my === seq.current) setLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key])

  useEffect(() => { void load() }, [load])
  return { data, loading, error, reload: load, setData }
}

export interface Page<T> { data: T[]; current_page: number; last_page: number; total: number; per_page: number }
