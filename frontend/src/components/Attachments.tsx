import { Download, Paperclip, Trash2, Upload } from 'lucide-react'
import { useRef, useState } from 'react'
import { Button, Confirm, Empty, ErrorBox, Spinner } from '@/components/ui'
import { useToast } from '@/context/ToastContext'
import { useGet } from '@/hooks/useApi'
import { download } from '@/lib/download'
import { errMsg, fmtDate } from '@/lib/utils'
import { api } from '@/services/api'

/** Private document attachments (served only through authenticated downloads). */
export function Attachments({ type, id, canWrite }: { type: 'student' | 'staff' | 'lesson-note'; id: number; canWrite: boolean }) {
  const { data, loading, error, reload } = useGet<any[]>(`/attachments/${type}/${id}`)
  const toast = useToast()
  const input = useRef<HTMLInputElement>(null)
  const [busy, setBusy] = useState(false)
  const [del, setDel] = useState<any>(null)

  const upload = async (file: File) => {
    const fd = new FormData()
    fd.append('file', file)
    setBusy(true)
    try {
      await api.post(`/attachments/${type}/${id}`, fd)
      toast.ok('File uploaded.')
      void reload()
    } catch (e) { toast.err(errMsg(e)) } finally { setBusy(false); if (input.current) input.current.value = '' }
  }

  if (loading) return <Spinner />
  if (error) return <ErrorBox message={error} onRetry={reload} />
  return (
    <div>
      {canWrite && (
        <div className="mb-3">
          <input ref={input} type="file" className="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt" onChange={(e) => e.target.files?.[0] && void upload(e.target.files[0])} />
          <Button size="sm" variant="secondary" loading={busy} onClick={() => input.current?.click()}><Upload size={14} /> Upload document</Button>
          <span className="ml-2 text-xs text-slate-500">PDF, Office, JPG, PNG or TXT, up to 10 MB</span>
        </div>
      )}
      {!data?.length ? <Empty text="No documents uploaded." /> : (
        <ul className="divide-y divide-slate-100">
          {data.map((a) => (
            <li key={a.id} className="flex items-center gap-3 py-2 text-sm">
              <Paperclip size={15} className="text-slate-400" />
              <span className="flex-1 truncate">{a.title}<span className="ml-2 text-xs text-slate-400">{fmtDate(a.created_at)}</span></span>
              <Button size="sm" variant="ghost" aria-label="Download" onClick={() => download(`/attachment-files/${a.id}`, undefined, a.title).catch((e) => toast.err(errMsg(e)))}><Download size={14} /></Button>
              {canWrite && <Button size="sm" variant="ghost" className="text-red-600" aria-label="Delete" onClick={() => setDel(a)}><Trash2 size={14} /></Button>}
            </li>
          ))}
        </ul>
      )}
      <Confirm open={!!del} danger title="Remove document" message={`Remove “${del?.title}”?`} confirmLabel="Remove" onCancel={() => setDel(null)}
        onConfirm={async () => { try { await api.delete(`/attachment-files/${del.id}`); toast.ok('Removed.'); setDel(null); void reload() } catch (e) { toast.err(errMsg(e)) } }} />
    </div>
  )
}
