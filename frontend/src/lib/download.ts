import { api } from '@/services/api'
import { errMsg } from './utils'

/** Downloads a binary (PDF/XLSX) from an authenticated endpoint. */
export async function download(url: string, params?: Record<string, unknown>, fallbackName = 'download') {
  try {
    const res = await api.get(url, { params, responseType: 'blob' })
    const cd = String(res.headers['content-disposition'] ?? '')
    const name = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd)?.[1] ?? fallbackName
    const href = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = href
    a.download = decodeURIComponent(name)
    document.body.appendChild(a)
    a.click()
    a.remove()
    setTimeout(() => URL.revokeObjectURL(href), 2000)
  } catch (e) {
    // Blob error bodies need decoding to surface the server's message
    const ax = e as { response?: { data?: Blob } }
    if (ax.response?.data instanceof Blob) {
      try {
        const j = JSON.parse(await ax.response.data.text())
        throw new Error(j.message ?? 'Download failed.')
      } catch (inner) {
        if (inner instanceof Error && inner.message !== 'Unexpected end of JSON input') throw inner
      }
    }
    throw new Error(errMsg(e))
  }
}
